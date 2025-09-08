<?php
// models/Informe.php
class Informe {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    public function crear($datos) {
        $sql = "INSERT INTO informes (turno_id, interno_id, tipo_informe, contenido, 
                fecha_informe, archivo_adjunto) 
                VALUES (:turno_id, :interno_id, :tipo_informe, :contenido, 
                :fecha_informe, :archivo_adjunto)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($datos);
    }
    
    public function generarInformeEstandar($tipo, $turnoId, $datos = []) {
        $turno = new Turno(new Database());
        $turnoData = $turno->obtenerPorId($turnoId);
        
        if (!$turnoData) return false;
        
        $contenido = $this->generarContenidoPorTipo($tipo, $turnoData, $datos);
        
        $informe = [
            'turno_id' => $turnoId,
            'interno_id' => $turnoData['interno_id'],
            'tipo_informe' => $tipo,
            'contenido' => $contenido,
            'fecha_informe' => date('Y-m-d'),
            'archivo_adjunto' => null
        ];
        
        return $this->crear($informe);
    }
    
    private function generarContenidoPorTipo($tipo, $turno, $datos) {
        $fecha = date('d/m/Y H:i');
        $contenido = "";
        
        switch ($tipo) {
            case 'atencion':
                $contenido = "INFORME DE ATENCIÓN MÉDICA\n\n";
                $contenido .= "Fecha: {$fecha}\n";
                $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
                $contenido .= "DNI: {$turno['interno_dni']}\n";
                $contenido .= "Especialidad: {$turno['especialidad_nombre']}\n";
                $contenido .= "Centro: {$turno['centro_nombre']}\n\n";
                $contenido .= "Detalle de la atención:\n";
                $contenido .= isset($datos['detalle']) ? $datos['detalle'] : "Atención médica realizada según especialidad solicitada.";
                break;
                
            case 'reprogramacion':
                $contenido = "INFORME DE REPROGRAMACIÓN\n\n";
                $contenido .= "Fecha: {$fecha}\n";
                $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
                $contenido .= "DNI: {$turno['interno_dni']}\n";
                $contenido .= "Turno original: " . date('d/m/Y H:i', strtotime($turno['fecha_turno'])) . "\n";
                $contenido .= "Motivo de reprogramación: " . (isset($datos['motivo']) ? $datos['motivo'] : "Reprogramación solicitada por el centro de salud");
                $contenido .= "\nNueva fecha: " . (isset($datos['nueva_fecha']) ? $datos['nueva_fecha'] : "A confirmar");
                break;
                
            case 'acta_novedad':
                $contenido = "ACTA DE NOVEDAD\n\n";
                $contenido .= "Fecha: {$fecha}\n";
                $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
                $contenido .= "DNI: {$turno['interno_dni']}\n";
                $contenido .= "Turno programado: " . date('d/m/Y H:i', strtotime($turno['fecha_turno'])) . "\n\n";
                $contenido .= "NOVEDAD: ";
                $contenido .= isset($datos['novedad']) ? $datos['novedad'] : "El interno no asistió al turno programado.";
                $contenido .= "\n\nMotivo: " . (isset($datos['motivo']) ? $datos['motivo'] : "No especificado");
                break;
        }
        
        return $contenido;
    }
    
    public function listarPorInterno($internoId, $limite = 10) {
        $sql = "SELECT i.*, t.fecha_turno, e.nombre as especialidad_nombre
                FROM informes i
                LEFT JOIN turnos t ON i.turno_id = t.id
                LEFT JOIN especialidades e ON t.especialidad_id = e.id
                WHERE i.interno_id = :interno_id
                ORDER BY i.fecha_informe DESC, i.created_at DESC
                LIMIT :limite";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':interno_id', $internoId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}