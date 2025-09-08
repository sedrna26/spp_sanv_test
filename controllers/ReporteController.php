<?php
// controllers/ReporteController.php
class ReporteController {
    private $db;
    
    public function __construct() {
        $this->db = (new Database())->connect();
    }
    
    public function estadisticasGenerales($fechaInicio = null, $fechaFin = null) {
        $whereClause = "";
        $params = [];
        
        if ($fechaInicio && $fechaFin) {
            $whereClause = "WHERE t.created_at BETWEEN :fecha_inicio AND :fecha_fin";
            $params = [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin
            ];
        }
        
        // Total de turnos por estado
        $sql = "SELECT estado, COUNT(*) as cantidad 
                FROM turnos t 
                {$whereClause}
                GROUP BY estado";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $turnosPorEstado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Turnos por prioridad
        $sql = "SELECT prioridad, COUNT(*) as cantidad 
                FROM turnos t 
                {$whereClause}
                GROUP BY prioridad";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $turnosPorPrioridad = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Especialidades más solicitadas
        $sql = "SELECT e.nombre, COUNT(t.id) as cantidad
                FROM especialidades e
                JOIN turnos t ON e.id = t.especialidad_id
                {$whereClause}
                GROUP BY e.id, e.nombre
                ORDER BY cantidad DESC
                LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $especialidadesMasSolicitadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Autorizaciones pendientes
        $sql = "SELECT COUNT(*) as cantidad
                FROM autorizaciones
                WHERE estado = 'pendiente'";
        $stmt = $this->db->query($sql);
        $autorizacionesPendientes = $stmt->fetchColumn();
        
        return [
            'turnos_por_estado' => $turnosPorEstado,
            'turnos_por_prioridad' => $turnosPorPrioridad,
            'especialidades_mas_solicitadas' => $especialidadesMasSolicitadas,
            'autorizaciones_pendientes' => $autorizacionesPendientes
        ];
    }
    
    public function reporteTrasladosPorPeriodo($fechaInicio, $fechaFin) {
        $sql = "SELECT 
                    DATE(tr.fecha_traslado) as fecha,
                    COUNT(*) as total_traslados,
                    SUM(CASE WHEN tr.estado = 'realizado' THEN 1 ELSE 0 END) as realizados,
                    SUM(CASE WHEN tr.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
                FROM traslados tr
                WHERE tr.fecha_traslado BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY DATE(tr.fecha_traslado)
                ORDER BY fecha";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function reporteInternosPorJuzgado() {
        $sql = "SELECT 
                    j.nombre as juzgado,
                    j.tipo,
                    COUNT(i.id) as total_internos,
                    COUNT(CASE WHEN i.tiene_obra_social = 1 THEN 1 END) as con_obra_social
                FROM juzgados j
                LEFT JOIN internos i ON j.id = i.juzgado_id AND i.estado = 'activo'
                GROUP BY j.id, j.nombre, j.tipo
                ORDER BY total_internos DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
