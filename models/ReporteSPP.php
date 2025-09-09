<?php
// models/ReporteSPP.php - Modelo para la gestión de reportes
class ReporteSPP {
    private $db;

    public function __construct($database) {
        $this->db = $database->connect();
    }

    /**
     * Obtiene estadísticas de turnos por estado en un rango de fechas.
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return array
     */
    public function getTurnosPorEstado($fechaInicio, $fechaFin) {
        $sql = "SELECT tm.estado, COUNT(*) as cantidad
                FROM turnos_medicos tm
                WHERE tm.created_at BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY tm.estado
                ORDER BY tm.estado";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'fecha_inicio' => $fechaInicio . ' 00:00:00',
            'fecha_fin' => $fechaFin . ' 23:59:59'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene estadísticas de turnos por especialidad en un rango de fechas.
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return array
     */
    public function getTurnosPorEspecialidad($fechaInicio, $fechaFin) {
        $sql = "SELECT e.nombre as especialidad, COUNT(tm.id) as cantidad
                FROM turnos_medicos tm
                JOIN especialidades e ON tm.especialidad_id = e.id
                WHERE tm.created_at BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY e.nombre
                ORDER BY cantidad DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'fecha_inicio' => $fechaInicio . ' 00:00:00',
            'fecha_fin' => $fechaFin . ' 23:59:59'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}