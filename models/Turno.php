<?php


// models/Turno.php
class Turno {
    private $db;
    private $pdo;
    
    public function __construct($database) {
        $this->db = $database;
        $this->pdo = $database->connect();
    }
    
    public function crear($datos) {
        $sql = "INSERT INTO turnos (interno_id, especialidad_id, centro_salud_id, fecha_solicitada, 
                fecha_turno, prioridad, observaciones, requiere_autorizacion) 
                VALUES (:interno_id, :especialidad_id, :centro_salud_id, :fecha_solicitada, 
                :fecha_turno, :prioridad, :observaciones, :requiere_autorizacion)";
        
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($datos);
        
        if ($success) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }
    
    public function obtenerPorId($id) {
        $sql = "SELECT t.*, i.nombre as interno_nombre, i.apellido as interno_apellido, 
                i.dni as interno_dni, e.nombre as especialidad_nombre, 
                c.nombre as centro_nombre, c.tipo as centro_tipo
                FROM turnos t
                JOIN internos i ON t.interno_id = i.id
                JOIN especialidades e ON t.especialidad_id = e.id
                LEFT JOIN centros_salud c ON t.centro_salud_id = c.id
                WHERE t.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function listarPorPrioridad($prioridad = null) {
        $where = $prioridad ? "WHERE t.prioridad = :prioridad" : "";
        
        $sql = "SELECT t.*, i.nombre as interno_nombre, i.apellido as interno_apellido, 
                i.dni as interno_dni, e.nombre as especialidad_nombre, 
                c.nombre as centro_nombre
                FROM turnos t
                JOIN internos i ON t.interno_id = i.id
                JOIN especialidades e ON t.especialidad_id = e.id
                LEFT JOIN centros_salud c ON t.centro_salud_id = c.id
                $where
                ORDER BY 
                    CASE t.prioridad 
                        WHEN 'ingreso' THEN 1
                        WHEN 'urgente' THEN 2
                        WHEN 'prioritario' THEN 3
                        WHEN 'normal' THEN 4
                    END,
                    t.fecha_solicitada";
        
        $stmt = $this->pdo->prepare($sql);
        if ($prioridad) {
            $stmt->execute(['prioridad' => $prioridad]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function actualizarEstado($id, $estado, $observaciones = null) {
        $sql = "UPDATE turnos SET estado = :estado";
        $params = ['id' => $id, 'estado' => $estado];
        
        if ($observaciones) {
            $sql .= ", observaciones = :observaciones";
            $params['observaciones'] = $observaciones;
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function obtenerTurnosPendientesAutorizacion() {
        $sql = "SELECT t.*, i.nombre as interno_nombre, i.apellido as interno_apellido, 
                i.dni as interno_dni, e.nombre as especialidad_nombre,
                j.nombre as juzgado_nombre, j.requiere_expediente
                FROM turnos t
                JOIN internos i ON t.interno_id = i.id
                JOIN especialidades e ON t.especialidad_id = e.id
                LEFT JOIN juzgados j ON i.juzgado_id = j.id
                WHERE t.requiere_autorizacion = 1 
                AND t.estado IN ('solicitado', 'confirmado')
                AND NOT EXISTS (
                    SELECT 1 FROM autorizaciones a 
                    WHERE a.turno_id = t.id AND a.estado = 'autorizado'
                )
                ORDER BY t.prioridad, t.fecha_solicitada";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

