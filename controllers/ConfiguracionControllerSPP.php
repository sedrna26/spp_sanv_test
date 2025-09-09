<?php
// controllers/ConfiguracionControllerSPP.php - Controlador para la gestión de configuración
require_once 'models/ConfiguracionSPP.php';
require_once 'config/database.php';

class ConfiguracionControllerSPP {
    private $configuracionModel;

    public function __construct() {
        $db = new Database();
        $this->configuracionModel = new ConfiguracionSPP($db);
    }

    public function getDatosConfiguracion() {
        try {
            $especialidades = $this->configuracionModel->getEspecialidades();
            $juzgados = $this->configuracionModel->getJuzgados();
            $centrosSalud = $this->configuracionModel->getCentrosSalud();

            return [
                'especialidades' => $especialidades,
                'juzgados' => $juzgados,
                'centros_salud' => $centrosSalud
            ];
        } catch (Exception $e) {
            error_log("Error al obtener datos de configuración: " . $e->getMessage());
            return [
                'especialidades' => [],
                'juzgados' => [],
                'centros_salud' => []
            ];
        }
    }

    public function manejarAccionesAjax($postData) {
        $accion = $postData['action'] ?? '';
        $resultado = ['success' => false, 'message' => 'Acción no válida'];

        try {
            switch ($accion) {
                case 'add_especialidad':
                    $nombre = $postData['nombre'] ?? '';
                    if ($this->configuracionModel->agregarEspecialidad($nombre)) {
                        $resultado = ['success' => true, 'message' => 'Especialidad agregada.'];
                    }
                    break;
                case 'delete_especialidad':
                    $id = $postData['id'] ?? 0;
                    if ($this->configuracionModel->eliminarEspecialidad($id)) {
                        $resultado = ['success' => true, 'message' => 'Especialidad eliminada.'];
                    }
                    break;
                case 'add_juzgado':
                    $nombre = $postData['nombre'] ?? '';
                    if ($this->configuracionModel->agregarJuzgado($nombre)) {
                        $resultado = ['success' => true, 'message' => 'Juzgado agregado.'];
                    }
                    break;
                case 'delete_juzgado':
                    $id = $postData['id'] ?? 0;
                    if ($this->configuracionModel->eliminarJuzgado($id)) {
                        $resultado = ['success' => true, 'message' => 'Juzgado eliminado.'];
                    }
                    break;
                case 'add_centro_salud':
                    $nombre = $postData['nombre'] ?? '';
                    $direccion = $postData['direccion'] ?? '';
                    if ($this->configuracionModel->agregarCentroSalud($nombre, $direccion)) {
                        $resultado = ['success' => true, 'message' => 'Centro de salud agregado.'];
                    }
                    break;
                case 'delete_centro_salud':
                    $id = $postData['id'] ?? 0;
                    if ($this->configuracionModel->eliminarCentroSalud($id)) {
                        $resultado = ['success' => true, 'message' => 'Centro de salud eliminado.'];
                    }
                    break;
            }
        } catch (Exception $e) {
            $resultado['message'] = 'Error: ' . $e->getMessage();
            error_log($resultado['message']);
        }
        return $resultado;
    }
}