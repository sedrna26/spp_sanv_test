<?php
// views/configuracion.php - Vista para la gestión de configuración del sistema

// session_start();

// Manejo de peticiones AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    require_once '../controllers/ConfiguracionControllerSPP.php';
    $controller = new ConfiguracionControllerSPP();
    $response = $controller->manejarAccionesAjax($_POST);
    echo json_encode($response);
    exit;
}

// Lógica de la vista principal
require_once 'controllers/ConfiguracionControllerSPP.php';
$controller = new ConfiguracionControllerSPP();
$datos = $controller->getDatosConfiguracion();

$especialidades = $datos['especialidades'];
$juzgados = $datos['juzgados'];
$centrosSalud = $datos['centros_salud'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración del Sistema</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .container { margin-top: 20px; }
        .card-header { background-color: #007bff; color: white; font-weight: bold; }
        .nav-link.active { font-weight: bold; }
        .form-container { display: none; }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="fas fa-cogs me-2"></i>
                Configuración del Sistema
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="configTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="especialidades-tab" data-bs-toggle="tab" data-bs-target="#especialidades-pane" type="button" role="tab" aria-controls="especialidades-pane" aria-selected="true">Especialidades</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="juzgados-tab" data-bs-toggle="tab" data-bs-target="#juzgados-pane" type="button" role="tab" aria-controls="juzgados-pane" aria-selected="false">Juzgados</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="centros-tab" data-bs-toggle="tab" data-bs-target="#centros-pane" type="button" role="tab" aria-controls="centros-pane" aria-selected="false">Centros de Salud</button>
                    </li>
                </ul>
                <div class="tab-content pt-3" id="configTabsContent">
                    
                    <div class="tab-pane fade show active" id="especialidades-pane" role="tabpanel" aria-labelledby="especialidades-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5>Listado de Especialidades</h5>
                                <div class="list-group">
                                    <?php foreach ($especialidades as $e): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($e['nombre']) ?>
                                            <button class="btn btn-danger btn-sm" onclick="eliminar('especialidad', <?= $e['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (empty($especialidades)): ?>
                                    <p class="text-muted mt-2">No hay especialidades registradas.</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5>Agregar Nueva Especialidad</h5>
                                <form id="formEspecialidad">
                                    <div class="mb-3">
                                        <label for="nombreEspecialidad" class="form-label">Nombre de la Especialidad</label>
                                        <input type="text" class="form-control" id="nombreEspecialidad" name="nombre" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i> Agregar</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="juzgados-pane" role="tabpanel" aria-labelledby="juzgados-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5>Listado de Juzgados</h5>
                                <div class="list-group">
                                    <?php foreach ($juzgados as $j): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($j['nombre']) ?>
                                            <button class="btn btn-danger btn-sm" onclick="eliminar('juzgado', <?= $j['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (empty($juzgados)): ?>
                                    <p class="text-muted mt-2">No hay juzgados registrados.</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5>Agregar Nuevo Juzgado</h5>
                                <form id="formJuzgado">
                                    <div class="mb-3">
                                        <label for="nombreJuzgado" class="form-label">Nombre del Juzgado</label>
                                        <input type="text" class="form-control" id="nombreJuzgado" name="nombre" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i> Agregar</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="centros-pane" role="tabpanel" aria-labelledby="centros-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5>Listado de Centros de Salud</h5>
                                <div class="list-group">
                                    <?php foreach ($centrosSalud as $c): ?>
                                        <div class="list-group-item">
                                            <div><strong><?= htmlspecialchars($c['nombre']) ?></strong></div>
                                            <div class="text-muted"><small><?= htmlspecialchars($c['direccion']) ?></small></div>
                                            <button class="btn btn-danger btn-sm mt-2" onclick="eliminar('centro_salud', <?= $c['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (empty($centrosSalud)): ?>
                                    <p class="text-muted mt-2">No hay centros de salud registrados.</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5>Agregar Nuevo Centro de Salud</h5>
                                <form id="formCentroSalud">
                                    <div class="mb-3">
                                        <label for="nombreCentro" class="form-label">Nombre del Centro</label>
                                        <input type="text" class="form-control" id="nombreCentro" name="nombre" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="direccionCentro" class="form-label">Dirección</label>
                                        <input type="text" class="form-control" id="direccionCentro" name="direccion" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i> Agregar</button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Manejador para los formularios de agregar
            $('#formEspecialidad').on('submit', function(e) {
                e.preventDefault();
                enviarDatos('add_especialidad', $(this).serialize());
            });

            $('#formJuzgado').on('submit', function(e) {
                e.preventDefault();
                enviarDatos('add_juzgado', $(this).serialize());
            });

            $('#formCentroSalud').on('submit', function(e) {
                e.preventDefault();
                enviarDatos('add_centro_salud', $(this).serialize());
            });
        });

        function enviarDatos(action, data) {
            $.ajax({
                url: 'configuracion.php',
                type: 'POST',
                data: data + '&action=' + action,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload(); // Recargar la página para mostrar los cambios
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor.');
                }
            });
        }

        function eliminar(tipo, id) {
            if (confirm('¿Está seguro de que desea eliminar este registro?')) {
                const action = 'delete_' + (tipo === 'centro_salud' ? 'centro_salud' : tipo);
                enviarDatos(action, 'id=' + id);
            }
        }
    </script>
</body>
</html>