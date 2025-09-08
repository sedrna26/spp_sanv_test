<?php
// models/Autorizacion.php
class Autorizacion {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    public function crear($datos) {
        $sql = "INSERT INTO autorizaciones (turno_id, juzgado_id, fecha_solicitud, observaciones) 
                VALUES (:turno_id, :juzgado_id, :fecha_solicitud, :observaciones)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($datos);
    }
    
    public function generarDocumentoAutorizacion($turnoId) {
        $sql = "SELECT t.*, i.nombre as interno_nombre, i.apellido as interno_apellido, 
                i.dni as interno_dni, i.numero_expediente,
                e.nombre as especialidad_nombre, c.nombre as centro_nombre,
                j.nombre as juzgado_nombre, j.tipo as juzgado_tipo, 
                j.formato_autorizacion, j.requiere_expediente
                FROM turnos t
                JOIN internos i ON t.interno_id = i.id
                JOIN especialidades e ON t.especialidad_id = e.id
                LEFT JOIN centros_salud c ON t.centro_salud_id = c.id
                LEFT JOIN juzgados j ON i.juzgado_id = j.id
                WHERE t.id = :turno_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['turno_id' => $turnoId]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$turno) return false;
        
        // Generar documento según formato del juzgado
        $contenido = $this->generarContenidoAutorizacion($turno);
        
        // Aquí iría la lógica para generar PDF
        // Por simplicidad, devolvemos el contenido como texto
        return $contenido;
    }
    
    private function generarContenidoAutorizacion($turno) {
        $fecha = date('d/m/Y');
        
        $contenido = "SOLICITUD DE AUTORIZACIÓN PARA ATENCIÓN MÉDICA\n\n";
        $contenido .= "Fecha: {$fecha}\n";
        $contenido .= "Dirigido a: {$turno['juzgado_nombre']}\n\n";
        
        $contenido .= "Por la presente se solicita autorización para:\n\n";
        $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
        $contenido .= "DNI: {$turno['interno_dni']}\n";
        
        if ($turno['requiere_expediente'] && $turno['numero_expediente']) {
            $contenido .= "Expediente N°: {$turno['numero_expediente']}\n";
        }
        
        $contenido .= "Especialidad: {$turno['especialidad_nombre']}\n";
        
        if ($turno['centro_nombre']) {
            $contenido .= "Centro de Salud: {$turno['centro_nombre']}\n";
        }
        
        if ($turno['fecha_turno']) {
            $contenido .= "Fecha del turno: " . date('d/m/Y H:i', strtotime($turno['fecha_turno'])) . "\n";
        }
        
        $contenido .= "Prioridad: " . strtoupper($turno['prioridad']) . "\n\n";
        
        if ($turno['observaciones']) {
            $contenido .= "Observaciones: {$turno['observaciones']}\n\n";
        }
        
        $contenido .= "Se adjunta la presente solicitud para su consideración y autorización.\n\n";
        $contenido .= "Saluda atentamente,\n";
        $contenido .= "Área de Sanidad - Establecimiento Penitenciario";
        
        return $contenido;
    }
    
    public function listarPendientes() {
        $sql = "SELECT a.*, t.fecha_turno, i.nombre as interno_nombre, 
                i.apellido as interno_apellido, i.dni as interno_dni,
                j.nombre as juzgado_nombre, e.nombre as especialidad_nombre
                FROM autorizaciones a
                JOIN turnos t ON a.turno_id = t.id
                JOIN internos i ON t.interno_id = i.id
                JOIN juzgados j ON a.juzgado_id = j.id
                JOIN especialidades e ON t.especialidad_id = e.id
                WHERE a.estado = 'pendiente'
                ORDER BY t.prioridad, a.fecha_solicitud";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
