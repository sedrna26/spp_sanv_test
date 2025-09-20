<?php
// index.php - Punto de entrada principal del Sistema de Sanidad SPP
session_start();

// Para fines de demostración, establecemos un usuario si no existe
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario_nombre'] = 'Administrador Sanidad';
    $_SESSION['usuario_rol'] = 'admin';
}

// Incluir archivos necesarios
require_once 'config/database.php';

// Controladores
require_once 'controllers/TurnoControllerSPP.php';
require_once 'controllers/AutorizacionControllerSPP.php';
require_once 'controllers/ReporteControllerSPP.php';
require_once 'controllers/InstallControllerSPP.php';

// Modelos
require_once 'models/TurnoMedicoSPP.php';
require_once 'models/AutorizacionMedicaSPP.php';
require_once 'models/InformeMedicoSPP.php';
require_once 'models/EspecialidadSPP.php';
require_once 'models/CentroSaludSPP.php';
require_once 'models/InternoSPP.php';

// Verificar si es una petición AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Manejo de acciones AJAX
if ($isAjax) {
    header('Content-Type: application/json');
    // Obtener la acción del parámetro 'action'
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    try {
        if (isset($_POST['ajax_action'])) {
            echo handleAjaxRequest($_POST);
        } else {
            // Manejar peticiones AJAX desde las vistas
            switch ($_GET['action'] ?? '') {
                case 'internos':
                    // Asegúrate de que este 'case' esté dentro del bloque de manejo de AJAX
                    require_once 'controllers/InternoControllerSPP.php';
                    $controller = new InternoControllerSPP();
                    if (isset($_GET['id'])) {
                        $interno = $controller->obtenerInternoPorId($_GET['id']);
                        if ($interno) {
                            echo json_encode(['success' => true, 'interno' => $interno]);
                        } else {
                            http_response_code(404);
                            echo json_encode(['success' => false, 'message' => 'Interno no encontrado.']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
                    }
                    break;

                case 'turnos':
                    if (isset($_POST['action']) && $_POST['action'] === 'buscar_internos') {
                        $internoModel = new InternoSPP(new Database());
                        $termino = $_POST['termino'] ?? '';
                        $resultados = $internoModel->buscar($termino);
                        echo json_encode(['success' => true, 'internos' => $resultados]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                    }
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
                    break;
            }
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Si no es AJAX, determinar la acción a realizar
$action = $_GET['action'] ?? 'dashboard';
$subaction = $_GET['subaction'] ?? null;

// Función para manejar peticiones AJAX
function handleAjaxRequest($data)
{
    $ajaxAction = $data['ajax_action'];

    switch ($ajaxAction) {
        case 'crear_turno':
            $controller = new TurnoControllerSPP();
            return json_encode($controller->crearTurno($data));

        case 'buscar_interno':
            $internoModel = new InternoSPP(new Database());
            $termino = $data['termino'] ?? '';
            $resultados = $internoModel->buscar($termino);
            return json_encode(['success' => true, 'internos' => $resultados]);

        case 'actualizar_estado_autorizacion':
            $controller = new AutorizacionControllerSPP();
            return json_encode($controller->actualizarEstadoAutorizacion(
                $data['autorizacion_id'],
                $data['estado'],
                $data['datos'] ?? [],
                $_SESSION['usuario_id']
            ));

        case 'generar_documento':
            $controller = new AutorizacionControllerSPP();
            return json_encode($controller->generarDocumento($data['turno_id']));

        case 'marcar_turno_realizado':
            $controller = new TurnoControllerSPP();
            return json_encode($controller->marcarComoRealizado($data['turno_id'], $data));

        case 'procesar_ausencia':
            $controller = new TurnoControllerSPP();
            return json_encode($controller->procesarAusencia(
                $data['turno_id'],
                $data['motivo'] ?? 'No especificado',
                $_SESSION['usuario_id']
            ));

        default:
            return json_encode(['success' => false, 'message' => 'Acción no reconocida']);
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Sanidad - SPP</title>

    <!-- CDN para Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- CDN para icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            margin: 0;
            font-size: 1.8rem;
        }

        .header .user-info {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        /* Navigation */
        .nav-container {
            background-color: white;
            border-bottom: 1px solid #e9ecef;
            padding: 0;
        }

        .nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav li {
            border-right: 1px solid #e9ecef;
        }

        .nav li:last-child {
            border-right: none;
        }

        .nav a {
            display: block;
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav a:hover {
            background-color: #f8f9fa;
            color: #2c3e50;
        }

        .nav a.active {
            background-color: #3498db;
            color: white;
        }

        .nav a i {
            margin-right: 0.5rem;
        }

        /* Main content */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            color: #2c3e50;
            margin-bottom: 2rem;
            border-bottom: 3px solid #3498db;
            padding-bottom: 0.5rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #ecf0f1 0%, #bdc3c7 100%);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid #e9ecef;
            border-radius: 4px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: #27ae60;
            color: white;
        }

        .btn-warning {
            background-color: #f39c12;
            color: white;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.9rem;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Badges */
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
        }

        .badge-urgente {
            background-color: #e74c3c;
        }

        .badge-ingreso {
            background-color: #d35400;
        }

        .badge-prioritario {
            background-color: #f39c12;
        }

        .badge-normal {
            background-color: #3498db;
        }

        .badge-pendiente {
            background-color: #f39c12;
        }

        .badge-autorizado {
            background-color: #27ae60;
        }

        .badge-rechazado {
            background-color: #e74c3c;
        }

        /* Grid */
        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        }

        .grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        /* Loading */
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background-color: #f8f9fa;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            background-color: #f8f9fa;
            text-align: right;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav {
                flex-direction: column;
            }

            .nav li {
                border-right: none;
                border-bottom: 1px solid #e9ecef;
            }

            .main-container {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-heartbeat"></i> Sistema de Sanidad Penitenciaria</h1>
        <div class="user-info">
            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['usuario_nombre']) ?> |
            <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <!-- Navigation -->
    <div class="nav-container">
        <ul class="nav">
            <li><a href="?action=dashboard" class="<?= $action == 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a></li>
            <li><a href="?action=turnos" class="<?= $action == 'turnos' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Turnos Médicos
                </a></li>
            <li><a href="?action=autorizaciones" class="<?= $action == 'autorizaciones' ? 'active' : '' ?>">
                    <i class="fas fa-file-signature"></i> Autorizaciones
                </a></li>
            <li><a href="?action=internos" class="<?= $action == 'internos' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Internos
                </a></li>
            <li><a href="?action=reportes" class="<?= $action == 'reportes' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Reportes
                </a></li>
            <li><a href="?action=configuracion" class="<?= $action == 'configuracion' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> Configuración
                </a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <?php
        // Incluir el contenido según la acción
        switch ($action) {
            case 'dashboard':
                include 'views/dashboard.php';
                break;

            case 'turnos':
                include 'views/turnos.php';
                break;

            case 'autorizaciones':
                include 'views/autorizaciones.php';
                break;

            case 'internos':
                include 'views/ppl_perfiles.php';
                break;

            case 'reportes':
                require_once 'views/reportes.php';
                break;


            case 'configuracion':
                include 'views/configuracion.php';
                break;

            default:
                include 'views/dashboard.php';
                break;
        }
        ?>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="modal">
        <div class="modal-content" style="max-width: 300px; text-align: center;">
            <div class="modal-body">
                <div class="spinner"></div>
                <p style="margin-top: 1rem;">Procesando...</p>
            </div>
        </div>
    </div>

    <!-- JavaScript Principal -->
    <script>
        // Variables globales
        window.SistemaSSP = {
            currentAction: '<?= $action ?>',
            userId: <?= $_SESSION['usuario_id'] ?>
        };

        // Función para mostrar alertas
        function showAlert(message, type = 'info', duration = 5000) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <strong>${type === 'success' ? 'Éxito' : type === 'danger' ? 'Error' : 'Información'}:</strong> ${message}
                <button type="button" style="float: right; background: none; border: none; font-size: 1.2rem; cursor: pointer;" onclick="this.parentElement.remove()">&times;</button>
            `;

            const container = document.querySelector('.main-container');
            container.insertBefore(alertDiv, container.firstChild);

            if (duration > 0) {
                setTimeout(() => {
                    if (alertDiv.parentElement) {
                        alertDiv.remove();
                    }
                }, duration);
            }
        }

        // Función para mostrar loading
        function showLoading() {
            document.getElementById('loadingModal').style.display = 'block';
        }

        // Función para ocultar loading
        function hideLoading() {
            document.getElementById('loadingModal').style.display = 'none';
        }

        // Función para realizar peticiones AJAX
        function ajaxRequest(data, successCallback, errorCallback) {
            showLoading();

            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        if (successCallback) successCallback(data);
                        else showAlert(data.message, 'success');
                    } else {
                        if (errorCallback) errorCallback(data);
                        else showAlert(data.message, 'danger');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    if (errorCallback) errorCallback({
                        message: 'Error de conexión'
                    });
                    else showAlert('Error de conexión', 'danger');
                });
        }

        // Función para formatear fechas
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES');
        }

        // Función para formatear fecha y hora
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Inicialización cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Sistema de Sanidad SPP cargado correctamente');

            // Actualizar fecha y hora cada minuto
            setInterval(function() {
                const now = new Date();
                const dateTimeStr = now.toLocaleDateString('es-ES') + ' ' +
                    now.toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                const userInfo = document.querySelector('.user-info');
                if (userInfo) {
                    const parts = userInfo.innerHTML.split('|');
                    if (parts.length > 1) {
                        parts[1] = ` <i class="fas fa-calendar"></i> ${dateTimeStr}`;
                        userInfo.innerHTML = parts.join(' |');
                    }
                }
            }, 60000);
        });

        // Funciones específicas para cada módulo
        window.TurnosModule = {
            buscarInterno: function(termino, callback) {
                ajaxRequest({
                    ajax_action: 'buscar_interno',
                    termino: termino
                }, callback);
            },

            crearTurno: function(datos, callback) {
                ajaxRequest(Object.assign({
                    ajax_action: 'crear_turno'
                }, datos), callback);
            },

            marcarRealizado: function(turnoId, datos, callback) {
                ajaxRequest(Object.assign({
                    ajax_action: 'marcar_turno_realizado',
                    turno_id: turnoId
                }, datos), callback);
            },

            procesarAusencia: function(turnoId, motivo, callback) {
                ajaxRequest({
                    ajax_action: 'procesar_ausencia',
                    turno_id: turnoId,
                    motivo: motivo
                }, callback);
            }
        };

        window.AutorizacionesModule = {
            actualizarEstado: function(autorizacionId, estado, datos, callback) {
                ajaxRequest({
                    ajax_action: 'actualizar_estado_autorizacion',
                    autorizacion_id: autorizacionId,
                    estado: estado,
                    datos: datos
                }, callback);
            },

            generarDocumento: function(turnoId, callback) {
                ajaxRequest({
                    ajax_action: 'generar_documento',
                    turno_id: turnoId
                }, callback);
            }
        };
    </script>
</body>

</html>