<?php
// models/CentroSaludSPP.php - Modelo para centros de salud
class CentroSaludSPP {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    /**
     * Listar centros de salud activos
     */
    public function listarActivos() {
        $sql = "SELECT * FROM centros_salud WHERE activo = 1 ORDER BY tipo, nombre";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener centro por ID
     */
    public function obtenerPorId($id) {
        $sql = "SELECT * FROM centros_salud WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener centros públicos y privados separadamente
     */
    public function listarPorTipo($tipo = null) {
        $where = $tipo ? "WHERE activo = 1 AND tipo = :tipo" : "WHERE activo = 1";
        $sql = "SELECT * FROM centros_salud $where ORDER BY nombre";
        
        $stmt = $this->db->prepare($sql);
        if ($tipo) {
            $stmt->execute(['tipo' => $tipo]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}