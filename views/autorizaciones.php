<?php
// views/autorizaciones.php - Vista para la gestión de autorizaciones judiciales


// Incluir archivos necesarios
require_once 'config/database.php';
require_once 'controllers/AutorizacionControllerSPP.php';

// Verificar si es una petición AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Manejo de acciones AJAX
if ($isAjax) {
    header('Content-Type: application/json');

    try {
        $autorizacionController = new AutorizacionControllerSPP();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
            switch ($_POST['ajax_action']) {
                case 'actualizar_estado_autorizacion':
                    $resultado = $autorizacionController->actualizarEstado($_POST['autorizacion_id'], $_POST['estado'], $_POST['datos']);
                    echo json_encode($resultado);
                    break;
                
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Acción AJAX no válida.']);
                    break;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Petición incorrecta.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    }
    exit;
}

// Lógica para la vista
$autorizacionController = new AutorizacionControllerSPP();
$autorizaciones = $autorizacionController->listarAutorizacionesPendientes();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Autorizaciones Médicas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .container {
            margin-top: 20px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .table thead th {
            background-color: #e9ecef;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="fas fa-file-signature me-2"></i>
                Autorizaciones Médicas Pendientes
            </div>
            <div class="card-body">
                <?php if (empty($autorizaciones)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i> No hay autorizaciones pendientes en este momento.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>N° de Autorización</th>
                                    <th>N° de Turno</th>
                                    <th>PPL</th>
                                    <th>Juzgado</th>
                                    <th>Especialidad</th>
                                    <th>Observaciones</th>
                                    <th>Fecha Solicitud</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($autorizaciones as $auth): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($auth['autorizacion_id'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($auth['nro_turno'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($auth['nombre_completo'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($auth['juzgado'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($auth['especialidad'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($auth['observaciones'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($auth['fecha_solicitud'] ?? 'N/A') ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-success btn-sm m-1" onclick="autorizar(<?= $auth['autorizacion_id'] ?>)">
                                                <i class="fas fa-check me-1"></i> Autorizar
                                            </button>
                                            <button class="btn btn-danger btn-sm m-1" onclick="rechazar(<?= $auth['autorizacion_id'] ?>)">
                                                <i class="fas fa-times me-1"></i> Rechazar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAutorizar" tabindex="-1" aria-labelledby="modalAutorizarLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAutorizarLabel">Autorizar Solicitud Médica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formAutorizar">
                        <input type="hidden" id="authIdAutorizar">
                        <div class="mb-3">
                            <label for="numeroAutorizacion" class="form-label">Número de Autorización</label>
                            <input type="text" class="form-control" id="numeroAutorizacion" required>
                        </div>
                        <div class="mb-3">
                            <label for="fechaVencimiento" class="form-label">Fecha de Vencimiento (Opcional)</label>
                            <input type="date" class="form-control" id="fechaVencimiento">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="confirmarAutorizacion()">Confirmar Autorización</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Función para manejar peticiones AJAX de forma centralizada
        function ajaxRequest(data, successCallback, errorCallback) {
            $.ajax({
                url: 'autorizaciones.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof successCallback === 'function') {
                            successCallback(response);
                        }
                    } else {
                        alert(response.message || 'Ocurrió un error inesperado.');
                        if (typeof errorCallback === 'function') {
                            errorCallback(response);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error en la comunicación con el servidor.');
                    if (typeof errorCallback === 'function') {
                        errorCallback({success: false, message: 'Error de red.'});
                    }
                }
            });
        }

        // Llama a AutorizacionesModule.actualizarEstado en index.php
        // Esta función debe existir en tu archivo index.php
        function actualizarEstado(autorizacionId, estado, datos, callback) {
            ajaxRequest({
                ajax_action: 'actualizar_estado_autorizacion',
                autorizacion_id: autorizacionId,
                estado: estado,
                datos: datos
            }, callback);
        }

        let currentAuthId;

        // Abre el modal para autorizar y guarda el ID de la autorización
        function autorizar(authId) {
            currentAuthId = authId;
            $('#modalAutorizar').modal('show');
        }

        // Procesa la confirmación de la autorización desde el modal
        function confirmarAutorizacion() {
            const numeroAutorizacion = $('#numeroAutorizacion').val();
            const fechaVencimiento = $('#fechaVencimiento').val();

            if (!numeroAutorizacion) {
                alert('Por favor, ingrese el número de autorización.');
                return;
            }

            const datosAutorizacion = {
                numero_autorizacion: numeroAutorizacion,
                fecha_autorizacion: new Date().toISOString().slice(0, 10), // Fecha actual
                fecha_vencimiento: fechaVencimiento
            };

            actualizarEstado(currentAuthId, 'autorizado', datosAutorizacion, function(response) {
                alert('Autorización procesada exitosamente.');
                $('#modalAutorizar').modal('hide');
                location.reload(); // Recarga la página para mostrar el cambio
            });
        }

        // Procesa el rechazo de la autorización directamente
        function rechazar(authId) {
            if (confirm('¿Está seguro de que desea rechazar esta autorización?')) {
                actualizarEstado(authId, 'rechazado', {}, function(response) {
                    alert('Autorización rechazada exitosamente.');
                    location.reload(); // Recarga la página para mostrar el cambio
                });
            }
        }
    </script>
</body>
</html>