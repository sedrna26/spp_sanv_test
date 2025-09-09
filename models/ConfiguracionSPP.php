<?php
// models/ConfiguracionSPP.php - Modelo para la gestión de configuraciones
class ConfiguracionSPP {
    private $db;

    public function __construct($database) {
        $this->db = $database->connect();
    }

    // Métodos para Especialidades
    public function getEspecialidades() {
        $sql = "SELECT * FROM especialidades ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function agregarEspecialidad($nombre) {
        $sql = "INSERT INTO especialidades (nombre) VALUES (?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nombre]);
    }
    
    public function eliminarEspecialidad($id) {
        $sql = "DELETE FROM especialidades WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    // Métodos para Juzgados
    public function getJuzgados() {
        $sql = "SELECT * FROM juzgado ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarJuzgado($nombre) {
        $sql = "INSERT INTO juzgado (nombre) VALUES (?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nombre]);
    }

    public function eliminarJuzgado($id) {
        $sql = "DELETE FROM juzgado WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    // Métodos para Centros de Salud
    public function getCentrosSalud() {
        $sql = "SELECT * FROM centros_salud ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarCentroSalud($nombre, $direccion) {
        $sql = "INSERT INTO centros_salud (nombre, direccion) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nombre, $direccion]);
    }

    public function eliminarCentroSalud($id) {
        $sql = "DELETE FROM centros_salud WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
}