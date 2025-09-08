<?php
// router.php - Controlador principal
class Router {
    private $turnoController;
    private $reporteController;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->turnoController = new TurnoController();
        $this->reporteController = new ReporteController();
        
        // Crear tablas si no existen
        $this->db->createTables();
        $this->insertarDatosIniciales();
    }
    
    private function insertarDatosIniciales() {
        $pdo = $this->db->connect();
        
        // Verificar si ya hay datos
        $stmt = $pdo->query("SELECT COUNT(*) FROM juzgados");
        if ($stmt->fetchColumn() > 0) return;
        
        // Insertar juzgados de ejemplo
        $juzgados = [
            ['nombre' => 'Juzgado Federal N° 1', 'tipo' => 'federal', 'requiere_expediente' => 1],
            ['nombre' => 'Juzgado de Garantías N° 2', 'tipo' => 'penal', 'requiere_expediente' => 0],
            ['nombre' => 'Tribunal Oral Criminal N° 3', 'tipo' => 'penal', 'requiere_expediente' => 1]
        ];
        
        foreach ($juzgados as $juzgado) {
            $stmt = $pdo->prepare("INSERT INTO juzgados (nombre, tipo, requiere_expediente) VALUES (?, ?, ?)");
            $stmt->execute([$juzgado['nombre'], $juzgado['tipo'], $juzgado['requiere_expediente']]);
        }
        
        // Insertar especialidades
        $especialidades = [
            ['nombre' => 'Clínica Médica', 'requiere_estudios_previos' => 0],
            ['nombre' => 'Cardiología', 'requiere_estudios_previos' => 1, 'estudios_requeridos' => 'Electrocardiograma'],
            ['nombre' => 'Traumatología', 'requiere_estudios_previos' => 1, 'estudios_requeridos' => 'Radiografías'],
            ['nombre' => 'Oftalmología', 'requiere_estudios_previos' => 0],
            ['nombre' => 'Odontología', 'requiere_estudios_previos' => 0],
            ['nombre' => 'Psiquiatría', 'requiere_estudios_previos' => 0],
            ['nombre' => 'Cirugía General', 'requiere_estudios_previos' => 1, 'estudios_requeridos' => 'Análisis prequirúrgicos']
        ];
        
        foreach ($especialidades as $esp) {
            $stmt = $pdo->prepare("INSERT INTO especialidades (nombre, requiere_estudios_previos, estudios_requeridos) VALUES (?, ?, ?)");
            $stmt->execute([$esp['nombre'], $esp['requiere_estudios_previos'], $esp['estudios_requeridos'] ?? null]);
        }
        
        // Insertar centros de salud
        $centros = [
            ['nombre' => 'Hospital Provincial', 'tipo' => 'publico', 'telefono' => '0800-555-0001'],
            ['nombre' => 'Clínica Privada San Juan', 'tipo' => 'privado', 'telefono' => '0800-555-0002'],
            ['nombre' => 'Centro de Salud Zonal Norte', 'tipo' => 'publico', 'telefono' => '0800-555-0003']
        ];
        
        foreach ($centros as $centro) {
            $stmt = $pdo->prepare("INSERT INTO centros_salud (nombre, tipo, telefono) VALUES (?, ?, ?)");
            $stmt->execute([$centro['nombre'], $centro['tipo'], $centro['telefono']]);
        }
    }
    
    public function handleRequest() {
        $page = $_GET['page'] ?? 'dashboard';
        $action = $_GET['action'] ?? 'index';
        
        switch ($page) {
            case 'dashboard':
                $this->showDashboard();
                break;
            case 'turnos':
                $this->handleTurnos($action);
                break;
            case 'internos':
                $this->handleInternos($action);
                break;
            case 'autorizaciones':
                $this->handleAutorizaciones($action);
                break;
            case 'api':
                $this->handleApi($action);
                break;
            default:
                $this->showDashboard();
        }
    }
    
    private function showDashboard() {
        $reporteController = new ReporteController();
        $datos = $reporteController->estadisticasGenerales();
        
        // Agregar datos adicionales para el dashboard
        $pdo = $this->db->connect();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM turnos WHERE estado IN ('solicitado', 'confirmado')");
        $datos['total_turnos_pendientes'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM traslados WHERE DATE(fecha_traslado) = CURDATE()");
        $datos['traslados_hoy'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM internos WHERE estado = 'activo'");
        $datos['internos_activos'] = $stmt->fetchColumn();
        
        // Procesar turnos por prioridad para el formato esperado
        $turnosPorPrioridad = [];
        if (isset($datos['turnos_por_prioridad'])) {
            foreach ($datos['turnos_por_prioridad'] as $item) {
                $turnosPorPrioridad[$item['prioridad']] = $item['cantidad'];
            }
        }
        $datos['turnos_por_prioridad'] = $turnosPorPrioridad;
        
        echo DashboardView::render($datos);
    }
    
    private function handleTurnos($action) {
        switch ($action) {
            case 'crear':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $resultado = $this->turnoController->crearTurno($_POST);
                    if ($resultado['success']) {
                        header('Location: ?page=turnos&mensaje=Turno creado exitosamente');
                    } else {
                        header('Location: ?page=turnos&error=' . urlencode($resultado['message']));
                    }
                    exit;
                }
                break;
            case 'reprogramar':
                $turnoId = $_GET['id'] ?? 0;
                $motivo = $_GET['motivo'] ?? '';
                $nuevaFecha = $_GET['fecha'] ?? '';
                
                $resultado = $this->turnoController->procesarReprogramacion($turnoId, $nuevaFecha, $motivo);
                if ($resultado['success']) {
                    header('Location: ?page=turnos&mensaje=Turno reprogramado exitosamente');
                } else {
                    header('Location: ?page=turnos&error=' . urlencode($resultado['message']));
                }
                exit;
                break;
            default:
                $this->showTurnos();
        }
    }
    
    private function showTurnos() {
        $turnoModel = new Turno($this->db);
        $turnos = $turnoModel->listarPorPrioridad();
        
        // Obtener especialidades y centros para el formulario
        $pdo = $this->db->connect();
        $stmt = $pdo->query("SELECT * FROM especialidades ORDER BY nombre");
        $especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT * FROM centros_salud ORDER BY nombre");
        $centros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo TurnosView::render($turnos, $especialidades, $centros);
    }
    
    private function handleApi($action) {
        header('Content-Type: application/json');
        
        switch ($action) {
            case 'buscar_internos':
                $termino = $_GET['q'] ?? '';
                $internoModel = new Interno($this->db);
                $resultados = $internoModel->buscar($termino);
                echo json_encode($resultados);
                break;
        }
    }
    
    private function handleInternos($action) {
        // Implementar gestión de internos
        echo "<h1>Gestión de Internos - En desarrollo</h1>";
    }
    
    private function handleAutorizaciones($action) {
        // Implementar gestión de autorizaciones
        echo "<h1>Gestión de Autorizaciones - En desarrollo</h1>";
    }
}
