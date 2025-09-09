<?php
// models/EspecialidadSPP.php - Modelo para especialidades médicas
class EspecialidadSPP {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    /**
     * Listar todas las especialidades activas
     */
    public function listarActivas() {
        $sql = "SELECT * FROM especialidades WHERE activo = 1 ORDER BY nombre";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener especialidad por ID
     */
    public function obtenerPorId($id) {
        $sql = "SELECT * FROM especialidades WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener especialidades más solicitadas
     */
    public function obtenerMasSolicitadas($limite = 10) {
        $sql = "SELECT 
                    e.*, 
                    COUNT(tm.id) as total_turnos
                FROM especialidades e
                LEFT JOIN turnos_medicos tm ON e.id = tm.especialidad_id
                WHERE e.activo = 1
                GROUP BY e.id
                ORDER BY total_turnos DESC
                LIMIT :limite";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
