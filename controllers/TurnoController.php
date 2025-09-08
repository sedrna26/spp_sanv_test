<?php

// controllers/TurnoController.php
class TurnoController {
    private $turnoModel;
    private $internoModel;
    private $autorizacionModel;
    private $informeModel;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->turnoModel = new Turno($this->db);
        $this->internoModel = new Interno($this->db);
        $this->autorizacionModel = new Autorizacion($this->db);
        $this->informeModel = new Informe($this->db);
    }
    
    public function crearTurno($datos) {
        try {
            // Validar datos requeridos
            $requeridos = ['interno_id', 'especialidad_id', 'fecha_solicitada', 'prioridad'];
            foreach ($requeridos as $campo) {
                if (empty($datos[$campo])) {
                    throw new Exception("El campo {$campo} es requerido");
                }
            }
            
            // Crear turno
            $turnoId = $this->turnoModel->crear($datos);
            
            if ($turnoId) {
                // Si requiere autorización, crear solicitud automáticamente
                if (!empty($datos['requiere_autorizacion'])) {
                    $interno = $this->internoModel->obtenerPorId($datos['interno_id']);
                    if ($interno && $interno['juzgado_id']) {
                        $autorizacion = [
                            'turno_id' => $turnoId,
                            'juzgado_id' => $interno['juzgado_id'],
                            'fecha_solicitud' => date('Y-m-d'),
                            'observaciones' => 'Generada automáticamente al crear el turno'
                        ];
                        $this->autorizacionModel->crear($autorizacion);
                    }
                }
                
                return ['success' => true, 'turno_id' => $turnoId, 'message' => 'Turno creado exitosamente'];
            }
            
            return ['success' => false, 'message' => 'Error al crear el turno'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function obtenerTurnosPorPrioridad() {
        $turnos = $this->turnoModel->listarPorPrioridad();
        
        $resultado = [
            'ingreso' => [],
            'urgente' => [],
            'prioritario' => [],
            'normal' => []
        ];
        
        foreach ($turnos as $turno) {
            $resultado[$turno['prioridad']][] = $turno;
        }
        
        return $resultado;
    }
    
    public function procesarReprogramacion($turnoId, $nuevaFecha, $motivo) {
        try {
            // Actualizar turno
            $this->turnoModel->actualizarEstado($turnoId, 'reprogramado', $motivo);
            
            // Generar informe de reprogramación
            $datos = [
                'motivo' => $motivo,
                'nueva_fecha' => $nuevaFecha
            ];
            
            $this->informeModel->generarInformeEstandar('reprogramacion', $turnoId, $datos);
            
            return ['success' => true, 'message' => 'Turno reprogramado exitosamente'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function marcarComoRealizado($turnoId, $observaciones = null) {
        try {
            $this->turnoModel->actualizarEstado($turnoId, 'realizado', $observaciones);
            
            // Generar informe de atención
            $datos = ['detalle' => $observaciones ?: 'Atención médica completada'];
            $this->informeModel->generarInformeEstandar('atencion', $turnoId, $datos);
            
            return ['success' => true, 'message' => 'Turno marcado como realizado'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}