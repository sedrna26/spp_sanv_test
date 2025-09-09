<?php
// controllers/AutorizacionControllerSPP.php - Controlador para autorizaciones judiciales

class AutorizacionControllerSPP {
    private $autorizacionModel;
    private $turnoModel;
    private $internoModel;
    private $db;

    /**
     * Constructor de la clase
     * Inicializa la conexión a la base de datos y los modelos necesarios.
     */
    public function __construct() {
        $this->db = new Database();
        $this->autorizacionModel = new AutorizacionMedicaSPP($this->db);
        $this->turnoModel = new TurnoMedicoSPP($this->db);
        $this->internoModel = new InternoSPP($this->db);
    }

    /**
     * Listar todas las autorizaciones pendientes
     * Obtiene una lista de autorizaciones que esperan respuesta del juzgado.
     * @return array Lista de autorizaciones pendientes.
     */
    public function listarAutorizacionesPendientes() {
        try {
            return $this->autorizacionModel->listarPendientes();
        } catch (Exception $e) {
            error_log("Error al listar autorizaciones pendientes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Actualizar el estado de una autorización
     * Cambia el estado (ej. a 'autorizado' o 'rechazado') y registra la acción.
     * @param int $autorizacionId ID de la autorización a actualizar.
     * @param string $nuevoEstado El nuevo estado ('autorizado', 'rechazado', etc.).
     * @param array $datosAdicionales Datos opcionales como número de autorización, fecha, etc.
     * @param int|null $usuarioId ID del usuario que realiza la acción.
     * @return array Resultado de la operación.
     */
    public function actualizarEstadoAutorizacion($autorizacionId, $nuevoEstado, $datosAdicionales = [], $usuarioId = null) {
        try {
            if (empty($autorizacionId) || empty($nuevoEstado)) {
                throw new Exception("ID de autorización y nuevo estado son requeridos.");
            }

            $resultado = $this->autorizacionModel->actualizarEstado($autorizacionId, $nuevoEstado, $datosAdicionales);

            if ($resultado) {
                // Registrar en auditoría
                $this->registrarAuditoria(
                    'Actualizar Estado Autorización',
                    'autorizaciones_medicas',
                    $autorizacionId,
                    "Estado de autorización actualizado a: {$nuevoEstado}. Datos: " . json_encode($datosAdicionales),
                    $usuarioId
                );

                return ['success' => true, 'message' => 'Estado de la autorización actualizado correctamente.'];
            }

            return ['success' => false, 'message' => 'No se pudo actualizar el estado de la autorización.'];

        } catch (Exception $e) {
            error_log("Error al actualizar estado de autorización: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generar el contenido del documento de solicitud de autorización
     * @param int $turnoId ID del turno para el cual se genera el documento.
     * @return array Contiene el resultado y el contenido del documento.
     */
    public function generarDocumento($turnoId) {
        try {
            if (empty($turnoId)) {
                throw new Exception("El ID del turno es requerido.");
            }

            $documento = $this->autorizacionModel->generarDocumentoAutorizacion($turnoId);

            if ($documento) {
                // Registrar en auditoría
                $this->registrarAuditoria(
                    'Generar Documento Autorización',
                    'turnos_medicos',
                    $turnoId,
                    "Se generó el documento de solicitud de autorización para el turno ID: {$turnoId}"
                );

                return ['success' => true, 'message' => 'Documento generado exitosamente.', 'documento' => $documento];
            }

            return ['success' => false, 'message' => 'No se pudo generar el documento. Verifique que el turno exista y requiera autorización.'];

        } catch (Exception $e) {
            error_log("Error al generar documento de autorización: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener los detalles de una autorización específica
     * @param int $autorizacionId El ID de la autorización.
     * @return array|false Los datos de la autorización o false si no se encuentra.
     */
    public function obtenerDetalleAutorizacion($autorizacionId) {
        try {
            $pdo = $this->db->connect();
            $sql = "SELECT 
                        am.*,
                        tm.fecha_turno, tm.prioridad, tm.motivo_consulta,
                        p.nombre as interno_nombre, p.apellido as interno_apellido, p.ci as interno_dni,
                        j.juzgado as juzgado_nombre,
                        e.nombre as especialidad_nombre
                    FROM autorizaciones_medicas am
                    JOIN turnos_medicos tm ON am.turno_id = tm.id
                    JOIN persona p ON am.id_ppl = p.id
                    JOIN juzgado j ON am.id_juzgado = j.id
                    JOIN especialidades e ON tm.especialidad_id = e.id
                    WHERE am.id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $autorizacionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo detalle de autorización: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar una acción en la tabla de auditoría
     * @param string $accion Descripción de la acción (ej. "Crear Turno").
     * @param string $tabla Nombre de la tabla principal afectada.
     * @param int $registroId ID del registro afectado.
     * @param string $detalles Información adicional sobre la operación.
     * @param int|null $usuarioId ID del usuario que realiza la acción.
     */
    private function registrarAuditoria($accion, $tabla, $registroId, $detalles, $usuarioId = null) {
        try {
            $pdo = $this->db->connect();
            $stmt = $pdo->prepare("
                INSERT INTO auditoria (id_usuario, accion, tabla_afectada, registro_id, detalles, nombre_archivo, correo)
                VALUES (:id_usuario, :accion, :tabla_afectada, :registro_id, :detalles, '', '')
            ");

            // Intenta obtener el ID de usuario de la sesión si no se proporciona
            $idUsuarioFinal = $usuarioId ?? $_SESSION['usuario_id'] ?? 1;

            $stmt->execute([
                'id_usuario' => $idUsuarioFinal,
                'accion' => $accion,
                'tabla_afectada' => $tabla,
                'registro_id' => $registroId,
                'detalles' => $detalles
            ]);

        } catch (Exception $e) {
            // Es importante que un fallo en la auditoría no detenga el flujo principal.
            // Por eso solo se registra el error.
            error_log("Error registrando auditoría: " . $e->getMessage());
        }
    }
     public function obtenerAutorizacionesConAlertas() {
        try {
            $pendientes = $this->autorizacionModel->listarPendientes();
            
            // Filtrar las que consideramos alertas (ej. más de 7 días pendientes)
            $alertas = array_filter($pendientes, function($auth) {
                return isset($auth['dias_pendiente']) && $auth['dias_pendiente'] > 7;
            });

            return ['success' => true, 'alertas' => array_values($alertas)];

        } catch (Exception $e) {
            error_log("Error obteniendo alertas de autorización: " . $e->getMessage());
            return ['success' => false, 'alertas' => []];
        }
    }
}