<?php

// models/Interno.php
class Interno {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    public function crear($datos) {
        $sql = "INSERT INTO internos (dni, nombre, apellido, fecha_nacimiento, juzgado_id, numero_expediente, tiene_obra_social, obra_social) 
                VALUES (:dni, :nombre, :apellido, :fecha_nacimiento, :juzgado_id, :numero_expediente, :tiene_obra_social, :obra_social)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($datos);
    }
    
    public function obtenerPorId($id) {
        $sql = "SELECT i.*, j.nombre as juzgado_nombre, j.tipo as juzgado_tipo 
                FROM internos i 
                LEFT JOIN juzgados j ON i.juzgado_id = j.id 
                WHERE i.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function listarActivos() {
        $sql = "SELECT i.*, j.nombre as juzgado_nombre 
                FROM internos i 
                LEFT JOIN juzgados j ON i.juzgado_id = j.id 
                WHERE i.estado = 'activo' 
                ORDER BY i.apellido, i.nombre";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function buscar($termino) {
        $sql = "SELECT i.*, j.nombre as juzgado_nombre 
                FROM internos i 
                LEFT JOIN juzgados j ON i.juzgado_id = j.id 
                WHERE (i.nombre LIKE :termino OR i.apellido LIKE :termino OR i.dni LIKE :termino)
                AND i.estado = 'activo'
                ORDER BY i.apellido, i.nombre";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['termino' => "%$termino%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}