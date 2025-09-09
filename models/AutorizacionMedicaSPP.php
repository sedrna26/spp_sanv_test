<?php
// models/AutorizacionMedicaSPP.php - Modelo para autorizaciones médicas
class AutorizacionMedicaSPP {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    /**
     * Crear nueva autorización médica
     */
    public function crear($datos) {
        $sql = "INSERT INTO autorizaciones_medicas (
                    turno_id, id_ppl, id_juzgado, fecha_solicitud,
                    observaciones, usuario_solicita
                ) VALUES (
                    :turno_id, :id_ppl, :id_juzgado, :fecha_solicitud,
                    :observaciones, :usuario_solicita
                )";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'turno_id' => $datos['turno_id'],
            'id_ppl' => $datos['id_ppl'],
            'id_juzgado' => $datos['id_juzgado'],
            'fecha_solicitud' => $datos['fecha_solicitud'] ?? date('Y-m-d'),
            'observaciones' => $datos['observaciones'] ?? null,
            'usuario_solicita' => $datos['usuario_solicita'] ?? 1
        ]);
    }
    
    /**
     * Generar documento de autorización automáticamente
     */
    public function generarDocumentoAutorizacion($turnoId) {
        $sql = "SELECT 
                    tm.*, 
                    p.nombre as interno_nombre,
                    p.apellido as interno_apellido,
                    p.ci as interno_dni,
                    e.nombre as especialidad_nombre,
                    cs.nombre as centro_nombre,
                    cs.direccion as centro_direccion,
                    j.juzgado as juzgado_nombre,
                    j.requiere_expediente_medico,
                    sl.numero_expediente
                FROM turnos_medicos tm
                JOIN persona p ON tm.id_ppl = p.id
                JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN centros_salud cs ON tm.centro_salud_id = cs.id
                LEFT JOIN situacionlegal sl ON p.id = sl.id_ppl
                LEFT JOIN juzgado j ON sl.id_juzgado = j.id
                WHERE tm.id = :turno_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['turno_id' => $turnoId]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$turno) return false;
        
        return $this->generarContenidoAutorizacion($turno);
    }
    
    /**
     * Generar contenido del documento de autorización
     */
    private function generarContenidoAutorizacion($turno) {
        $fecha = date('d/m/Y');
        $hora = date('H:i');
        
        $contenido = "SOLICITUD DE AUTORIZACIÓN PARA ATENCIÓN MÉDICA\n\n";
        $contenido .= "Fecha: {$fecha} - Hora: {$hora}\n";
        $contenido .= "Dirigido a: {$turno['juzgado_nombre']}\n\n";
        
        $contenido .= "DATOS DEL INTERNO:\n";
        $contenido .= "Apellido y Nombre: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
        $contenido .= "DNI: {$turno['interno_dni']}\n";
        
        if ($turno['requiere_expediente_medico'] && $turno['numero_expediente']) {
            $contenido .= "Expediente N°: {$turno['numero_expediente']}\n";
        }
        
        $contenido .= "\nDETALLES DE LA ATENCIÓN SOLICITADA:\n";
        $contenido .= "Especialidad: {$turno['especialidad_nombre']}\n";
        
        if ($turno['centro_nombre']) {
            $contenido .= "Centro de Salud: {$turno['centro_nombre']}\n";
            if ($turno['centro_direccion']) {
                $contenido .= "Dirección: {$turno['centro_direccion']}\n";
            }
        }
        
        if ($turno['fecha_turno']) {
            $contenido .= "Fecha programada: " . date('d/m/Y', strtotime($turno['fecha_turno']));
            if (date('H:i', strtotime($turno['fecha_turno'])) != '00:00') {
                $contenido .= " - Hora: " . date('H:i', strtotime($turno['fecha_turno']));
            }
            $contenido .= "\n";
        }
        
        $contenido .= "Prioridad: " . strtoupper($turno['prioridad']) . "\n";
        
        if ($turno['motivo_consulta']) {
            $contenido .= "Motivo de consulta: {$turno['motivo_consulta']}\n";
        }
        
        if ($turno['observaciones']) {
            $contenido .= "Observaciones: {$turno['observaciones']}\n";
        }
        
        $contenido .= "\nSe solicita autorización para el traslado y atención médica del interno mencionado.\n";
        $contenido .= "La atención es necesaria por motivos de salud y bienestar del interno.\n\n";
        
        $contenido .= "Sin otro particular, saluda atentamente.\n\n";
        $contenido .= "ÁREA DE SANIDAD\n";
        $contenido .= "ESTABLECIMIENTO PENITENCIARIO\n";
        $contenido .= "San Juan, " . date('d/m/Y');
        
        return $contenido;
    }
    
    /**
     * Listar autorizaciones pendientes
     */
    public function listarPendientes() {
        $sql = "SELECT 
                    am.*,
                    tm.fecha_turno,
                    tm.prioridad,
                    p.nombre as interno_nombre,
                    p.apellido as interno_apellido,
                    p.ci as interno_dni,
                    j.juzgado as juzgado_nombre,
                    e.nombre as especialidad_nombre,
                    DATEDIFF(CURDATE(), am.fecha_solicitud) as dias_pendiente
                FROM autorizaciones_medicas am
                JOIN turnos_medicos tm ON am.turno_id = tm.id
                JOIN persona p ON am.id_ppl = p.id
                JOIN juzgado j ON am.id_juzgado = j.id
                JOIN especialidades e ON tm.especialidad_id = e.id
                WHERE am.estado = 'pendiente'
                ORDER BY 
                    CASE tm.prioridad 
                        WHEN 'ingreso' THEN 1
                        WHEN 'urgente' THEN 2
                        WHEN 'prioritario' THEN 3
                        WHEN 'normal' THEN 4
                    END,
                    am.fecha_solicitud";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Actualizar estado de autorización
     */
    public function actualizarEstado($id, $estado, $datos = []) {
        $campos = ["estado = :estado"];
        $params = ['id' => $id, 'estado' => $estado];
        
        if ($estado == 'autorizado' && !isset($datos['fecha_autorizacion'])) {
            $datos['fecha_autorizacion'] = date('Y-m-d');
        }
        
        $camposPermitidos = ['numero_autorizacion', 'fecha_autorizacion', 'fecha_vencimiento', 'observaciones'];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($datos[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[$campo] = $datos[$campo];
            }
        }
        
        $sql = "UPDATE autorizaciones_medicas SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
