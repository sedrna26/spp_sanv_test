<?php
// controllers/InternoControllerSPP.php
require_once 'models/InternoSPP.php';
require_once 'config/database.php';

class InternoControllerSPP {
    private $internoModel;

    public function __construct() {
        $db = new Database();
        $this->internoModel = new InternoSPP($db);
    }

    /**
     * @return array
     */
    public function listarInternos() {
        try {
            return $this->internoModel->listarActivos();
        } catch (Exception $e) {
            error_log("Error al listar internos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function obtenerInternoPorId($id) {
        try {
            return $this->internoModel->obtenerPorId($id);
        } catch (Exception $e) {
            error_log("Error al obtener interno por ID: " . $e->getMessage());
            return null;
        }
    }
}