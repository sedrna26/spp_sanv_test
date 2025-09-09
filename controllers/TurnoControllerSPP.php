<?php
// controllers/TurnoControllerSPP.php - Controlador adaptado para SPP
class TurnoControllerSPP {
    private $turnoModel;
    private $internoModel;
    private $autorizacionModel;
    private $informeModel;
    private $especialidadModel;
    private $centroSaludModel;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->turnoModel = new TurnoMedicoSPP($this->db);
        $this->internoModel = new InternoSPP($this->db);
        $this->autorizacionModel = new AutorizacionMedicaSPP($this->db);
        $this->informeModel = new InformeMedicoSPP($this->db);
        $this->especialidadModel = new EspecialidadSPP($this->db);
        $this->centroSaludModel = new CentroSaludSPP($this->db);
    }
    
    /**
     * Crear nuevo turno médico con validaciones SPP
     */
    public function crearTurno($datos) {
        try {
            // Validar datos requeridos
            $requeridos = ['id_ppl', 'especialidad_id', 'fecha_solicitada', 'prioridad'];
            foreach ($requeridos as $campo) {
                if (empty($datos[$campo])) {
                    throw new Exception("El campo {$campo} es requerido");
                }
            }
            
            // Verificar que el interno existe y está activo
            $interno = $this->internoModel->obtenerPorId($datos['id_ppl']);
            if (!$interno || $interno['estado_interno'] !== 'activo') {
                throw new Exception("El interno no existe o no está activo en el sistema");
            }
            
            // Verificar que la especialidad existe
            $especialidad = $this->especialidadModel->obtenerPorId($datos['especialidad_id']);
            if (!$especialidad) {
                throw new Exception("La especialidad seleccionada no existe");
            }
            
            // Agregar usuario que carga (desde sesión o parámetro)
            $datos['usuario_carga'] = $_SESSION['usuario_id'] ?? $datos['usuario_carga'] ?? 1;
            
            // Crear turno
            $turnoId = $this->turnoModel->crear($datos);
            
            if ($turnoId) {
                // Si requiere autorización, crear solicitud automáticamente
                if (!empty($datos['requiere_autorizacion']) && $interno['id_juzgado']) {
                    $autorizacionDatos = [
                        'turno_id' => $turnoId,
                        'id_ppl' => $datos['id_ppl'],
                        'id_juzgado' => $interno['id_juzgado'],
                        'fecha_solicitud' => date('Y-m-d'),
                        'observaciones' => 'Generada automáticamente al crear el turno',
                        'usuario_solicita' => $datos['usuario_carga']
                    ];
                    $this->autorizacionModel->crear($autorizacionDatos);
                }
                
                // Registrar en auditoría SPP
                $this->registrarAuditoria(
                    'Crear Turno Médico',
                    'turnos_medicos',
                    $turnoId,
                    "Turno creado para PPL {$interno['apellido']}, {$interno['nombre']} - Especialidad: {$especialidad['nombre']} - Prioridad: {$datos['prioridad']}"
                );
                
                              return [
                    'success' => true, 
                    'turno_id' => $turnoId, 
                    'message' => 'Turno médico creado exitosamente',
                    'datos' => [
                        'interno' => $interno['apellido'] . ', ' . $interno['nombre'],
                        'especialidad' => $especialidad['nombre'],
                        'requiere_autorizacion' => !empty($datos['requiere_autorizacion'])
                    ]
                ];
            }
            
            return ['success' => false, 'message' => 'Error al crear el turno médico'];
            
        } catch (Exception $e) {
            error_log("Error creando turno médico: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener turnos organizados por prioridad
     */
    public function obtenerTurnosPorPrioridad($filtros = []) {
        try {
            $limite = $filtros['limite'] ?? 100;
            $prioridad = $filtros['prioridad'] ?? null;
            
            $turnos = $this->turnoModel->listarPorPrioridad($prioridad, $limite);
            
            $resultado = [
                'ingreso' => [],
                'urgente' => [],
                'prioritario' => [],
                'normal' => []
            ];
            
            foreach ($turnos as $turno) {
                // Agregar información adicional
                $turno['dias_desde_solicitud'] = $this->calcularDiasDesdeSolicitud($turno['fecha_solicitada']);
                $turno['requiere_estudios'] = $this->verificarEstudiosRequeridos($turno['especialidad_id']);
                $turno['estado_autorizacion'] = $this->obtenerEstadoAutorizacion($turno['id']);
                
                $resultado[$turno['prioridad']][] = $turno;
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Error obteniendo turnos por prioridad: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Procesar reprogramación de turno con auditoría
     */
    public function procesarReprogramacion($turnoId, $nuevaFecha, $motivo, $usuarioId = null) {
        try {
            $turno = $this->turnoModel->obtenerPorId($turnoId);
            if (!$turno) {
                throw new Exception("El turno no existe");
            }
            
            // Actualizar turno
            $this->turnoModel->actualizarEstado($turnoId, 'reprogramado', $motivo, $usuarioId);
            
            // Crear nuevo turno con la nueva fecha
            $nuevoTurno = [
                'id_ppl' => $turno['id_ppl'],
                'especialidad_id' => $turno['especialidad_id'],
                'centro_salud_id' => $turno['centro_salud_id'],
                'fecha_solicitada' => date('Y-m-d'),
                'fecha_turno' => $nuevaFecha,
                'prioridad' => $turno['prioridad'],
                'observaciones' => 'Reprogramado desde turno ID: ' . $turnoId . '. Motivo: ' . $motivo,
                'requiere_autorizacion' => $turno['requiere_autorizacion'],
                'motivo_consulta' => $turno['motivo_consulta'],
                'usuario_carga' => $usuarioId
            ];
            
            $nuevoTurnoId = $this->turnoModel->crear($nuevoTurno);
            
            // Generar informe de reprogramación
            $datosInforme = [
                'motivo' => $motivo,
                'nueva_fecha' => $nuevaFecha,
                'turno_original' => $turnoId,
                'turno_nuevo' => $nuevoTurnoId
            ];
            
            $this->informeModel->generarInformeEstandar('reprogramacion', $turnoId, $datosInforme);
            
            // Registrar auditoría
            $this->registrarAuditoria(
                'Reprogramar Turno',
                'turnos_medicos',
                $turnoId,
                "Turno reprogramado. Motivo: {$motivo}. Nueva fecha: {$nuevaFecha}"
            );
            
            return [
                'success' => true, 
                'message' => 'Turno reprogramado exitosamente',
                'nuevo_turno_id' => $nuevoTurnoId
            ];
            
        } catch (Exception $e) {
            error_log("Error reprogramando turno: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Marcar turno como realizado con generación de informe
     */
    public function marcarComoRealizado($turnoId, $datosAtencion = []) {
        try {
            $turno = $this->turnoModel->obtenerPorId($turnoId);
            if (!$turno) {
                throw new Exception("El turno no existe");
            }
            
            // Actualizar estado del turno
            $this->turnoModel->actualizarEstado($turnoId, 'realizado', $datosAtencion['observaciones'] ?? null);
            
            // Generar informe de atención
            $datosInforme = array_merge([
                'detalle' => 'Atención médica completada',
                'medico' => $datosAtencion['medico'] ?? 'No especificado'
            ], $datosAtencion);
            
            $this->informeModel->generarInformeEstandar('atencion', $turnoId, $datosInforme);
            
            // Registrar auditoría
            $this->registrarAuditoria(
                'Turno Realizado',
                'turnos_medicos',
                $turnoId,
                "Atención médica completada - {$turno['especialidad_nombre']}"
            );
            
            return ['success' => true, 'message' => 'Turno marcado como realizado e informe generado'];
            
        } catch (Exception $e) {
            error_log("Error marcando turno como realizado: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Procesar ausencia de interno (No Asistió)
     */
    public function procesarAusencia($turnoId, $motivo = 'No especificado', $usuarioId = null) {
        try {
            $turno = $this->turnoModel->obtenerPorId($turnoId);
            if (!$turno) {
                throw new Exception("El turno no existe");
            }
            
            // Actualizar estado
            $this->turnoModel->actualizarEstado($turnoId, 'no_asistio', "No asistió. Motivo: {$motivo}", $usuarioId);
            
            // Generar acta de novedad
            $datosActa = [
                'novedad' => 'El interno no asistió al turno médico programado',
                'motivo' => $motivo,
                'fecha_turno_original' => $turno['fecha_turno']
            ];
            
            $this->informeModel->generarInformeEstandar('acta_novedad', $turnoId, $datosActa);
            
            // Registrar auditoría
            $this->registrarAuditoria(
                'Ausencia a Turno',
                'turnos_medicos',
                $turnoId,
                "Interno no asistió. Motivo: {$motivo}"
            );
            
            return [
                'success' => true, 
                'message' => 'Ausencia registrada y acta de novedad generada'
            ];
            
        } catch (Exception $e) {
            error_log("Error procesando ausencia: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener dashboard de turnos con estadísticas
     */
    public function obtenerDashboardTurnos() {
        try {
            $pdo = $this->db->connect();
            $dashboard = [];
            
            // Turnos por estado
            $stmt = $pdo->query("
                SELECT estado, COUNT(*) as cantidad 
                FROM turnos_medicos 
                WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY estado
            ");
            $dashboard['turnos_por_estado'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Turnos por prioridad
            $stmt = $pdo->query("
                SELECT prioridad, COUNT(*) as cantidad 
                FROM turnos_medicos 
                WHERE estado IN ('solicitado', 'confirmado', 'pendiente_estudios')
                GROUP BY prioridad
            ");
            $dashboard['turnos_por_prioridad'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Turnos urgentes pendientes
            $stmt = $pdo->query("
                SELECT COUNT(*) as cantidad
                FROM turnos_medicos 
                WHERE prioridad IN ('urgente', 'ingreso') 
                AND estado IN ('solicitado', 'confirmado')
            ");
            $dashboard['urgentes_pendientes'] = $stmt->fetchColumn();
            
            // Autorizaciones pendientes
            $stmt = $pdo->query("
                SELECT COUNT(*) as cantidad
                FROM autorizaciones_medicas 
                WHERE estado = 'pendiente'
            ");
            $dashboard['autorizaciones_pendientes'] = $stmt->fetchColumn();
            
            // Turnos de hoy
            $stmt = $pdo->query("
                SELECT COUNT(*) as cantidad
                FROM turnos_medicos 
                WHERE DATE(fecha_turno) = CURDATE()
            ");
            $dashboard['turnos_hoy'] = $stmt->fetchColumn();
            
            // Próximos turnos (próximos 7 días)
            $stmt = $pdo->query("
                SELECT 
                    DATE(fecha_turno) as fecha,
                    COUNT(*) as cantidad
                FROM turnos_medicos 
                WHERE fecha_turno BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND estado IN ('confirmado')
                GROUP BY DATE(fecha_turno)
                ORDER BY fecha
            ");
            $dashboard['proximos_turnos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $dashboard;
            
        } catch (Exception $e) {
            error_log("Error obteniendo dashboard de turnos: " . $e->getMessage());
            return [];
        }
    }
    
    // Métodos auxiliares privados
    private function calcularDiasDesdeSolicitud($fechaSolicitud) {
        $fecha1 = new DateTime($fechaSolicitud);
        $fecha2 = new DateTime();
        return $fecha1->diff($fecha2)->days;
    }
    
    private function verificarEstudiosRequeridos($especialidadId) {
        $especialidad = $this->especialidadModel->obtenerPorId($especialidadId);
        return $especialidad ? $especialidad['requiere_estudios_previos'] : false;
    }
    
    private function obtenerEstadoAutorizacion($turnoId) {
        $pdo = $this->db->connect();
        $stmt = $pdo->prepare("
            SELECT estado 
            FROM autorizaciones_medicas 
            WHERE turno_id = :turno_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute(['turno_id' => $turnoId]);
        $result = $stmt->fetchColumn();
        return $result ?: 'no_requerida';
    }
    
    private function registrarAuditoria($accion, $tabla, $registroId, $detalles, $usuarioId = null) {
        try {
            $pdo = $this->db->connect();
            $stmt = $pdo->prepare("
                INSERT INTO auditoria (id_usuario, accion, tabla_afectada, registro_id, detalles, nombre_archivo, correo)
                VALUES (:id_usuario, :accion, :tabla_afectada, :registro_id, :detalles, '', '')
            ");
            
            $stmt->execute([
                'id_usuario' => $usuarioId ?? $_SESSION['usuario_id'] ?? 1,
                'accion' => $accion,
                'tabla_afectada' => $tabla,
                'registro_id' => $registroId,
                'detalles' => $detalles
            ]);
            
        } catch (Exception $e) {
            error_log("Error registrando auditoría: " . $e->getMessage());
        }
    }
}