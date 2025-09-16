<?php
// ppl_perfiles.php - Vista para listar y ver perfiles de PPL


// Incluir archivos necesarios
require_once 'config/database.php';
require_once 'controllers/InternoControllerSPP.php';

// Manejo de peticiones AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $controller = new InternoControllerSPP();
    
    if (isset($_GET['id'])) {
        $interno = $controller->obtenerInternoPorId($_GET['id']);
        if ($interno) {
            echo json_encode(['success' => true, 'interno' => $interno]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'PPL no encontrado.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
    }
    exit;
}

// Lógica de la vista principal
$controller = new InternoControllerSPP();
$internos = $controller->listarInternos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfiles de PPL</title>
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
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }
        .table thead th {
            background-color: #e9ecef;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }
        .profile-btn {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="fas fa-users me-2"></i>
                Listado de Personas Privadas de Libertad (PPL)
            </div>
            <div class="card-body">
                <?php if (empty($internos)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i> No se encontraron PPL activos.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>DNI</th>
                                    <th>Nombre Completo</th>
                                    <th>Juzgado</th>
                                    <th>Ubicación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($internos as $interno): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($interno['dni'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(($interno['nombre'] ?? '') . ' ' . ($interno['apellido'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($interno['juzgado_nombre'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($interno['sector'] . ' / ' . $interno['pabellon'] . ' / ' . $interno['num_celda']) ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="verPerfil(<?= $interno['id'] ?>)">
                                                <i class="fas fa-eye me-1"></i> Ver Perfil
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

    <div class="modal fade" id="modalPerfilPPL" tabindex="-1" aria-labelledby="modalPerfilPPLLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalPerfilPPLLabel">Perfil de PPL</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function verPerfil(id) {
            $.ajax({
                url: 'ppl_perfiles.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const interno = response.interno;
                        let perfilHtml = `
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nombre Completo:</strong> ${interno.nombre} ${interno.apellido}</p>
                                    <p><strong>DNI:</strong> ${interno.dni}</p>
                                    <p><strong>Fecha de Nacimiento:</strong> ${interno.fecha_nac}</p>
                                    <p><strong>Juzgado:</strong> ${interno.juzgado_nombre}</p>
                                    <p><strong>Ubicación:</strong> ${interno.sector} / ${interno.pabellon} / ${interno.num_celda}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Obra Social:</strong> ${interno.obra_social || 'N/A'}</p>
                                    <p><strong>N° Afiliado:</strong> ${interno.numero_afiliado || 'N/A'}</p>
                                    <p><strong>Tiene PAMI:</strong> ${interno.tiene_pami == 1 ? 'Sí' : 'No'}</p>
                                    <p><strong>Contacto de Emergencia:</strong> ${interno.contacto_emergencia || 'N/A'}</p>
                                    <p><strong>Teléfono de Emergencia:</strong> ${interno.telefono_emergencia || 'N/A'}</p>
                                </div>
                            </div>
                            <hr>
                            <h5>Información Médica</h5>
                            <p><strong>Alergias:</strong> ${interno.alergias || 'Ninguna'}</p>
                            <p><strong>Medicamentos Habituales:</strong> ${interno.medicamentos_habituales || 'Ninguno'}</p>
                            <p><strong>Enfermedades Crónicas:</strong> ${interno.enfermedades_cronicas || 'Ninguna'}</p>
                            <p><strong>Última Revisión Médica:</strong> ${interno.ultima_revision_medica || 'N/A'}</p>
                        `;
                        $('#modalPerfilPPL .modal-body').html(perfilHtml);
                        $('#modalPerfilPPL').modal('show');
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Error al cargar el perfil.');
                }
            });
        }
    </script>
</body>
</html>