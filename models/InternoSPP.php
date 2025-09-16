<?php
// models/InternoSPP.php - Modelo adaptado para la base SPP
class InternoSPP {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    /**
     * Obtener interno por ID usando la estructura SPP
     */
    public function obtenerPorId($id) {
        $sql = "SELECT * FROM vista_internos_sanidad WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Listar internos activos con datos médicos
     */
    public function listarActivos() {
    $sql = "SELECT
                id, apellidos, nombres, dni, fechanac,
                obra_social, tiene_pami, apodo,
                alergias, medicamentos_habituales, enfermedades_cronicas
            FROM vista_internos_sanidad
            WHERE estado = 'Activo'
            ORDER BY apellidos, nombres";

    $stmt = $this->db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    
    /**
     * Buscar internos por término
     */
    public function buscar($termino) {
        $sql = "SELECT 
                    id, nombre, apellido, dni, 
                    obra_social, juzgado_nombre, estado_interno
                FROM vista_internos_sanidad 
                WHERE (nombre LIKE :termino 
                   OR apellido LIKE :termino 
                   OR dni LIKE :termino)
                AND estado_interno = 'activo'
                ORDER BY apellido, nombre
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['termino' => "%$termino%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Actualizar datos médicos de un interno
     */
    public function actualizarDatosMedicos($id, $datos) {
        $campos = [];
        $params = ['id' => $id];
        
        $camposPermitidos = [
            'tipo_sangre', 'obra_social', 'numero_afiliado', 'tiene_pami',
            'contacto_emergencia', 'telefono_emergencia', 'alergias',
            'medicamentos_habituales', 'enfermedades_cronicas', 'ultima_revision_medica'
        ];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($datos[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[$campo] = $datos[$campo];
            }
        }
        
        if (empty($campos)) return false;
        
        $sql = "UPDATE persona SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Obtener internos con turnos médicos pendientes
     */
    public function obtenerConTurnosPendientes() {
        $sql = "SELECT 
                    i.id, i.nombre, i.apellido, i.dni,
                    COUNT(tm.id) as turnos_pendientes,
                    MIN(tm.fecha_turno) as proximo_turno
                FROM vista_internos_sanidad i
                INNER JOIN turnos_medicos tm ON i.id = tm.id_ppl
                WHERE tm.estado IN ('solicitado', 'confirmado', 'pendiente_estudios')
                AND i.estado_interno = 'activo'
                GROUP BY i.id, i.nombre, i.apellido, i.dni
                ORDER BY turnos_pendientes DESC, proximo_turno ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
