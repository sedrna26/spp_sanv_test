<?php
// models/InformeMedicoSPP.php - Modelo para informes médicos
class InformeMedicoSPP {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->connect();
    }
    
    /**
     * Crear nuevo informe médico
     */
    public function crear($datos) {
        $sql = "INSERT INTO informes_medicos (
                    turno_id, id_ppl, tipo_informe, contenido,
                    diagnostico, tratamiento_indicado, medicamentos,
                    proxima_cita, fecha_informe, medico_responsable,
                    usuario_carga
                ) VALUES (
                    :turno_id, :id_ppl, :tipo_informe, :contenido,
                    :diagnostico, :tratamiento_indicado, :medicamentos,
                    :proxima_cita, :fecha_informe, :medico_responsable,
                    :usuario_carga
                )";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'turno_id' => $datos['turno_id'] ?? null,
            'id_ppl' => $datos['id_ppl'],
            'tipo_informe' => $datos['tipo_informe'],
            'contenido' => $datos['contenido'],
            'diagnostico' => $datos['diagnostico'] ?? null,
            'tratamiento_indicado' => $datos['tratamiento_indicado'] ?? null,
            'medicamentos' => $datos['medicamentos'] ?? null,
            'proxima_cita' => $datos['proxima_cita'] ?? null,
            'fecha_informe' => $datos['fecha_informe'] ?? date('Y-m-d'),
            'medico_responsable' => $datos['medico_responsable'] ?? null,
            'usuario_carga' => $datos['usuario_carga'] ?? 1
        ]);
    }
    
    /**
     * Generar informe estándar automáticamente
     */
    public function generarInformeEstandar($tipo, $turnoId, $datosAdicionales = []) {
        // Obtener datos del turno
        $turnoModel = new TurnoMedicoSPP(new Database());
        $turno = $turnoModel->obtenerPorId($turnoId);
        
        if (!$turno) return false;
        
        $contenido = $this->generarContenidoPorTipo($tipo, $turno, $datosAdicionales);
        
        $informe = [
            'turno_id' => $turnoId,
            'id_ppl' => $turno['id_ppl'],
            'tipo_informe' => $tipo,
            'contenido' => $contenido,
            'fecha_informe' => date('Y-m-d'),
            'medico_responsable' => $datosAdicionales['medico'] ?? null,
            'diagnostico' => $datosAdicionales['diagnostico'] ?? null,
            'tratamiento_indicado' => $datosAdicionales['tratamiento'] ?? null,
            'medicamentos' => $datosAdicionales['medicamentos'] ?? null,
            'proxima_cita' => $datosAdicionales['proxima_cita'] ?? null
        ];
        
        return $this->crear($informe);
    }
    
    /**
     * Generar contenido según tipo de informe
     */
    private function generarContenidoPorTipo($tipo, $turno, $datos) {
        $fecha = date('d/m/Y H:i');
        $contenido = "";
        
        switch ($tipo) {
            case 'atencion':
                $contenido = "INFORME DE ATENCIÓN MÉDICA\n\n";
                $contenido .= "Fecha de atención: {$fecha}\n";
                $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
                $contenido .= "DNI: {$turno['interno_dni']}\n";
                $contenido .= "Especialidad: {$turno['especialidad_nombre']}\n";
                
                if ($turno['centro_nombre']) {
                    $contenido .= "Centro de Salud: {$turno['centro_nombre']}\n";
                }
                
                $contenido .= "\nDETALLE DE LA ATENCIÓN:\n";
                $contenido .= isset($datos['detalle']) ? $datos['detalle'] : "Atención médica realizada según especialidad solicitada.";
                
                if (isset($datos['diagnostico'])) {
                    $contenido .= "\n\nDIAGNÓSTICO:\n" . $datos['diagnostico'];
                }
                
                if (isset($datos['tratamiento'])) {
                    $contenido .= "\n\nTRATAMIENTO INDICADO:\n" . $datos['tratamiento'];
                }
                
                if (isset($datos['medicamentos'])) {
                    $contenido .= "\n\nMEDICAMENTOS:\n" . $datos['medicamentos'];
                }
                
                break;
                
            case 'reprogramacion':
                $contenido = "INFORME DE REPROGRAMACIÓN DE TURNO\n\n";
                $contenido .= "Fecha: {$fecha}\n";
                $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
                $contenido .= "DNI: {$turno['interno_dni']}\n";
                $contenido .= "Especialidad: {$turno['especialidad_nombre']}\n";
                
                if ($turno['fecha_turno']) {
                    $contenido .= "Turno original: " . date('d/m/Y H:i', strtotime($turno['fecha_turno'])) . "\n";
                }
                
                $contenido .= "\nMOTIVO DE REPROGRAMACIÓN:\n";
                $contenido .= isset($datos['motivo']) ? $datos['motivo'] : "Reprogramación solicitada por el centro de salud";
                
                if (isset($datos['nueva_fecha'])) {
                    $contenido .= "\n\nNUEVA FECHA PROGRAMADA:\n" . $datos['nueva_fecha'];
                }
                
                break;
                
            case 'acta_novedad':
                $contenido = "ACTA DE NOVEDAD - TURNO MÉDICO\n\n";
                $contenido .= "Fecha: {$fecha}\n";
                $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
                $contenido .= "DNI: {$turno['interno_dni']}\n";
                $contenido .= "Especialidad: {$turno['especialidad_nombre']}\n";
                
                if ($turno['fecha_turno']) {
                    $contenido .= "Turno programado: " . date('d/m/Y H:i', strtotime($turno['fecha_turno'])) . "\n";
                }
                
                $contenido .= "\nNOVEDAD REGISTRADA:\n";
                $contenido .= isset($datos['novedad']) ? $datos['novedad'] : "El interno no asistió al turno programado sin justificación.";
                
                if (isset($datos['motivo'])) {
                    $contenido .= "\n\nMOTIVO:\n" . $datos['motivo'];
                }
                
                $contenido .= "\n\nOBSERVACIONES:\n";
                $contenido .= "Se registra la presente novedad para conocimiento del juzgado correspondiente.";
                
                break;
                
            case 'emergencia':
                $contenido = "INFORME DE ATENCIÓN DE EMERGENCIA\n\n";
                $contenido .= "Fecha y hora: {$fecha}\n";
                $contenido .= "Interno: {$turno['interno_apellido']}, {$turno['interno_nombre']}\n";
                $contenido .= "DNI: {$turno['interno_dni']}\n";
                
                $contenido .= "\nMOTIVO DE EMERGENCIA:\n";
                $contenido .= isset($datos['motivo_emergencia']) ? $datos['motivo_emergencia'] : "Situación de emergencia médica";
                
                $contenido .= "\n\nACCIONES REALIZADAS:\n";
                $contenido .= isset($datos['acciones']) ? $datos['acciones'] : "Atención médica de emergencia";
                
                break;
        }
        
        $contenido .= "\n\n" . str_repeat("-", 50);
        $contenido .= "\nÁREA DE SANIDAD - ESTABLECIMIENTO PENITENCIARIO";
        $contenido .= "\nSan Juan, " . date('d/m/Y');
        
        return $contenido;
    }
    
    /**
     * Listar informes por interno
     */
    public function listarPorInterno($idPpl, $limite = 20) {
        $sql = "SELECT 
                    im.*,
                    tm.fecha_turno,
                    e.nombre as especialidad_nombre,
                    cs.nombre as centro_nombre
                FROM informes_medicos im
                LEFT JOIN turnos_medicos tm ON im.turno_id = tm.id
                LEFT JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN centros_salud cs ON tm.centro_salud_id = cs.id
                WHERE im.id_ppl = :id_ppl
                ORDER BY im.fecha_informe DESC, im.created_at DESC
                LIMIT :limite";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_ppl', $idPpl, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Marcar informe como enviado al juzgado
     */
    public function marcarComoEnviado($id, $fechaEnvio = null) {
        $sql = "UPDATE informes_medicos 
                SET enviado_juzgado = 1, 
                    fecha_envio_juzgado = :fecha_envio,
                    estado = 'enviado'
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'fecha_envio' => $fechaEnvio ?? date('Y-m-d H:i:s')
        ]);
    }
}
