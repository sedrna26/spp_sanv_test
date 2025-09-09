<?php
// models/TurnoMedicoSPP.php - Modelo adaptado para turnos médicos
class TurnoMedicoSPP {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    /**
     * Crear un nuevo turno médico
     */
    public function crear($datos) {
        $sql = "INSERT INTO turnos_medicos (
                    id_ppl, especialidad_id, centro_salud_id, 
                    fecha_solicitada, fecha_turno, prioridad, 
                    observaciones, requiere_autorizacion, 
                    motivo_consulta, usuario_carga
                ) VALUES (
                    :id_ppl, :especialidad_id, :centro_salud_id,
                    :fecha_solicitada, :fecha_turno, :prioridad,
                    :observaciones, :requiere_autorizacion,
                    :motivo_consulta, :usuario_carga
                )";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'id_ppl' => $datos['id_ppl'],
            'especialidad_id' => $datos['especialidad_id'],
            'centro_salud_id' => $datos['centro_salud_id'] ?? null,
            'fecha_solicitada' => $datos['fecha_solicitada'],
            'fecha_turno' => $datos['fecha_turno'] ?? null,
            'prioridad' => $datos['prioridad'] ?? 'normal',
            'observaciones' => $datos['observaciones'] ?? null,
            'requiere_autorizacion' => $datos['requiere_autorizacion'] ?? 1,
            'motivo_consulta' => $datos['motivo_consulta'] ?? null,
            'usuario_carga' => $datos['usuario_carga'] ?? 1
        ]);
        
        return $success ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Obtener turno por ID con datos completos
     */
    public function obtenerPorId($id) {
        $sql = "SELECT 
                    tm.*,
                    p.nombre as interno_nombre,
                    p.apellido as interno_apellido,
                    p.ci as interno_dni,
                    e.nombre as especialidad_nombre,
                    e.requiere_estudios_previos,
                    e.estudios_requeridos,
                    cs.nombre as centro_nombre,
                    cs.tipo as centro_tipo,
                    cs.telefono as centro_telefono
                FROM turnos_medicos tm
                JOIN persona p ON tm.id_ppl = p.id
                JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN centros_salud cs ON tm.centro_salud_id = cs.id
                WHERE tm.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Listar turnos por prioridad
     */
    public function listarPorPrioridad($prioridad = null, $limite = 100) {
        $where = $prioridad ? "WHERE tm.prioridad = :prioridad" : "WHERE 1=1";
        
        $sql = "SELECT 
                    tm.*,
                    p.nombre as interno_nombre,
                    p.apellido as interno_apellido,
                    p.ci as interno_dni,
                    e.nombre as especialidad_nombre,
                    cs.nombre as centro_nombre,
                    j.juzgado as juzgado_nombre
                FROM turnos_medicos tm
                JOIN persona p ON tm.id_ppl = p.id
                JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN centros_salud cs ON tm.centro_salud_id = cs.id
                LEFT JOIN situacionlegal sl ON p.id = sl.id_ppl
                LEFT JOIN juzgado j ON sl.id_juzgado = j.id
                $where
                ORDER BY 
                    CASE tm.prioridad 
                        WHEN 'ingreso' THEN 1
                        WHEN 'urgente' THEN 2
                        WHEN 'prioritario' THEN 3
                        WHEN 'normal' THEN 4
                    END,
                    tm.fecha_solicitada, tm.created_at
                LIMIT :limite";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        
        if ($prioridad) {
            $stmt->bindValue(':prioridad', $prioridad, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Actualizar estado del turno
     */
    public function actualizarEstado($id, $estado, $observaciones = null, $usuarioId = null) {
        $campos = ["estado = :estado"];
        $params = ['id' => $id, 'estado' => $estado];
        
        if ($observaciones !== null) {
            $campos[] = "observaciones = :observaciones";
            $params['observaciones'] = $observaciones;
        }
        
        if ($usuarioId !== null) {
            $campos[] = "usuario_carga = :usuario_carga";
            $params['usuario_carga'] = $usuarioId;
        }
        
        $sql = "UPDATE turnos_medicos SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Obtener turnos que requieren autorización judicial
     */
    public function obtenerTurnosPendientesAutorizacion() {
        $sql = "SELECT 
                    tm.*,
                    p.nombre as interno_nombre,
                    p.apellido as interno_apellido,
                    p.ci as interno_dni,
                    e.nombre as especialidad_nombre,
                    j.juzgado as juzgado_nombre,
                    j.requiere_expediente_medico,
                    sl.numero_expediente
                FROM turnos_medicos tm
                JOIN persona p ON tm.id_ppl = p.id
                JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN situacionlegal sl ON p.id = sl.id_ppl
                LEFT JOIN juzgado j ON sl.id_juzgado = j.id
                WHERE tm.requiere_autorizacion = 1 
                AND tm.estado IN ('solicitado', 'confirmado')
                AND NOT EXISTS (
                    SELECT 1 FROM autorizaciones_medicas am 
                    WHERE am.turno_id = tm.id AND am.estado = 'autorizado'
                )
                ORDER BY 
                    CASE tm.prioridad 
                        WHEN 'ingreso' THEN 1
                        WHEN 'urgente' THEN 2
                        WHEN 'prioritario' THEN 3
                        WHEN 'normal' THEN 4
                    END,
                    tm.fecha_solicitada";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener turnos por rango de fechas
     */
    public function obtenerPorFechas($fechaInicio, $fechaFin, $estado = null) {
        $where = "WHERE tm.fecha_turno BETWEEN :fecha_inicio AND :fecha_fin";
        $params = [
            'fecha_inicio' => $fechaInicio . ' 00:00:00',
            'fecha_fin' => $fechaFin . ' 23:59:59'
        ];
        
        if ($estado) {
            $where .= " AND tm.estado = :estado";
            $params['estado'] = $estado;
        }
        
        $sql = "SELECT 
                    tm.*,
                    p.nombre as interno_nombre,
                    p.apellido as interno_apellido,
                    p.ci as interno_dni,
                    e.nombre as especialidad_nombre,
                    cs.nombre as centro_nombre
                FROM turnos_medicos tm
                JOIN persona p ON tm.id_ppl = p.id
                JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN centros_salud cs ON tm.centro_salud_id = cs.id
                $where
                ORDER BY tm.fecha_turno";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
