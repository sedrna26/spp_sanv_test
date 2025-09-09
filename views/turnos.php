<?php
// views/turnos.php - Vista para gestión de turnos médicos

// Verificar si es una petición AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        $turnoController = new TurnoControllerSPP();
        $internoModel = new InternoSPP(new Database());
        
        // Manejar acciones AJAX
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            switch ($_POST['action']) {
                case 'crear_turno':
                    $resultado = $turnoController->crearTurno($_POST);
                    echo json_encode($resultado);
                    break;
                    
                case 'marcar_realizado':
                    $resultado = $turnoController->marcarComoRealizado($_POST['turno_id'], $_POST);
                    echo json_encode($resultado);
                    break;
                    
                case 'procesar_ausencia':
                    $resultado = $turnoController->procesarAusencia($_POST['turno_id']);
                    echo json_encode($resultado);
                    break;
                    
                case 'buscar_internos':
                    $termino = $_POST['termino'] ?? '';
                    $internos = $internoModel->buscar($termino);
                    echo json_encode(['success' => true, 'internos' => $internos]);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Si no es AJAX, continuar con el renderizado normal de la página
$turnoController = new TurnoControllerSPP();
$especialidadModel = new EspecialidadSPP(new Database());
$centroSaludModel = new CentroSaludSPP(new Database());

// Obtener datos para mostrar
$filtros = ['limite' => 50];
$turnosPorPrioridad = $turnoController->obtenerTurnosPorPrioridad($filtros);

// Combinar todos los turnos en un solo array para mostrar en tabla
$todosTurnos = array_merge(
    $turnosPorPrioridad['ingreso'] ?? [],
    $turnosPorPrioridad['urgente'] ?? [],
    $turnosPorPrioridad['prioritario'] ?? [],
    $turnosPorPrioridad['normal'] ?? []
);

// Obtener especialidades y centros de salud para formularios
$especialidades = $especialidadModel->listarActivas();
$centrosSalud = $centroSaludModel->listarActivos();

// Manejo de acciones específicas de turnos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'crear_turno':
            $resultado = $turnoController->crearTurno($_POST);
            if ($resultado['success']) {
                echo "<script>showAlert('{$resultado['message']}', 'success');</script>";
                // Recargar datos
                $turnosPorPrioridad = $turnoController->obtenerTurnosPorPrioridad($filtros);
                $todosTurnos = array_merge(
                    $turnosPorPrioridad['ingreso'] ?? [],
                    $turnosPorPrioridad['urgente'] ?? [],
                    $turnosPorPrioridad['prioritario'] ?? [],
                    $turnosPorPrioridad['normal'] ?? []
                );
            } else {
                echo "<script>showAlert('{$resultado['message']}', 'danger');</script>";
            }
            break;
    }
}
?>

<h2 class="page-title">
    <i class="fas fa-calendar-check"></i> Gestión de Turnos Médicos
</h2>

<div class="grid grid-2">
    <!-- Panel de Acciones -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Acciones Rápidas</h3>
        </div>
        <div class="card-body">
            <button onclick="mostrarModalNuevoTurno()" class="btn btn-primary" style="margin-bottom: 0.5rem;">
                <i class="fas fa-plus"></i> Nuevo Turno
            </button>
            
            <button onclick="filtrarPorPrioridad('urgente')" class="btn btn-danger" style="margin-bottom: 0.5rem;">
                <i class="fas fa-exclamation-triangle"></i> Solo Urgentes
            </button>
            
            <button onclick="filtrarPorPrioridad('ingreso')" class="btn btn-warning" style="margin-bottom: 0.5rem;">
                <i class="fas fa-sign-in-alt"></i> Solo Ingresos
            </button>
            
            <button onclick="filtrarPorPrioridad('')" class="btn btn-secondary" style="margin-bottom: 0.5rem;">
                <i class="fas fa-list"></i> Ver Todos
            </button>
            
            <hr>
            
            <div class="form-group">
                <label>Buscar por interno:</label>
                <input type="text" id="buscarEnTabla" class="form-control" 
                       placeholder="Nombre, apellido o DNI" onkeyup="buscarEnTablaTurnos()">
            </div>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Resumen de Turnos</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div style="text-align: center; padding: 1rem; background: #fee; border-radius: 4px;">
                    <div style="font-size: 2rem; color: #e74c3c; font-weight: bold;">
                        <?= count($turnosPorPrioridad['urgente'] ?? []) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Urgentes</div>
                </div>
                
                <div style="text-align: center; padding: 1rem; background: #fef5e7; border-radius: 4px;">
                    <div style="font-size: 2rem; color: #d35400; font-weight: bold;">
                        <?= count($turnosPorPrioridad['ingreso'] ?? []) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Ingresos</div>
                </div>
                
                <div style="text-align: center; padding: 1rem; background: #fff3cd; border-radius: 4px;">
                    <div style="font-size: 2rem; color: #f39c12; font-weight: bold;">
                        <?= count($turnosPorPrioridad['prioritario'] ?? []) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Prioritarios</div>
                </div>
                
                <div style="text-align: center; padding: 1rem; background: #e8f4fd; border-radius: 4px;">
                    <div style="font-size: 2rem; color: #3498db; font-weight: bold;">
                        <?= count($turnosPorPrioridad['normal'] ?? []) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Normales</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de Turnos -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Lista de Turnos Médicos</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tablaTurnos">
                <thead>
                    <tr>
                        <th>Fecha Solicitud</th>
                        <th>Interno</th>
                        <th>DNI</th>
                        <th>Especialidad</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Centro</th>
                        <th>Días Espera</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($todosTurnos)): ?>
                        <?php foreach ($todosTurnos as $turno): ?>
                        <tr class="fila-turno priority-<?= $turno['prioridad'] ?>" 
                            data-interno="<?= strtolower($turno['interno_apellido'] . ' ' . $turno['interno_nombre'] . ' ' . $turno['interno_dni']) ?>">
                            <td><?= date('d/m/Y', strtotime($turno['fecha_solicitada'])) ?></td>
                            <td><?= htmlspecialchars($turno['interno_apellido'] . ', ' . $turno['interno_nombre']) ?></td>
                            <td><?= htmlspecialchars($turno['interno_dni']) ?></td>
                            <td><?= htmlspecialchars($turno['especialidad_nombre']) ?></td>
                            <td>
                                <span class="badge badge-<?= $turno['prioridad'] ?>">
                                    <?= strtoupper($turno['prioridad']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $turno['estado'] == 'realizado' ? 'autorizado' : 'pendiente' ?>">
                                    <?= ucfirst($turno['estado']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($turno['centro_nombre'] ?? 'No asignado') ?></td>
                            <td style="text-align: center; font-weight: bold;">
                                <?= $turno['dias_desde_solicitud'] ?? 0 ?>
                            </td>
                            <td>
                                <button onclick="verDetalleTurno(<?= $turno['id'] ?>)" 
                                        class="btn btn-primary" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($turno['estado'] == 'solicitado' || $turno['estado'] == 'confirmado'): ?>
                                <button onclick="marcarRealizado(<?= $turno['id'] ?>)" 
                                        class="btn btn-success" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">
                                    <i class="fas fa-check"></i>
                                </button>
                                
                                <button onclick="procesarAusencia(<?= $turno['id'] ?>)" 
                                        class="btn btn-warning" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button onclick="reprogramarTurno(<?= $turno['id'] ?>)" 
                                        class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">
                                    <i class="fas fa-calendar-alt"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: #666;">
                                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                <br>No hay turnos médicos registrados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Turno -->
<div id="modalNuevoTurno" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Nuevo Turno Médico</h3>
            <span class="close" onclick="cerrarModal('modalNuevoTurno')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formNuevoTurno" onsubmit="crearTurnoMedico(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Buscar Interno: *</label>
                        <input type="text" id="buscarInterno" class="form-control" 
                               placeholder="Nombre, apellido o DNI" onkeyup="buscarInternos()" required>
                        <div id="resultadosInterno" style="display: none; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; background: white; position: absolute; z-index: 1000; width: 90%;"></div>
                        <input type="hidden" id="interno_id" name="id_ppl" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Especialidad: *</label>
                        <select name="especialidad_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= $esp['id'] ?>"><?= htmlspecialchars($esp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Centro de Salud:</label>
                        <select name="centro_salud_id" class="form-control">
                            <option value="">No asignado</option>
                            <?php foreach ($centrosSalud as $centro): ?>
                                <option value="<?= $centro['id'] ?>">
                                    <?= htmlspecialchars($centro['nombre'] . ' (' . $centro['tipo'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Prioridad: *</label>
                        <select name="prioridad" class="form-control" required>
                            <option value="normal">Normal</option>
                            <option value="prioritario">Prioritario</option>
                            <option value="urgente">Urgente</option>
                            <option value="ingreso">Ingreso</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Solicitada: *</label>
                        <input type="date" name="fecha_solicitada" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha/Hora del Turno:</label>
                        <input type="datetime-local" name="fecha_turno" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Motivo de Consulta:</label>
                    <input type="text" name="motivo_consulta" class="form-control" 
                           placeholder="Ej: Control médico general, dolor abdominal, etc.">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="requiere_autorizacion" value="1" checked>
                        Requiere autorización judicial
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Observaciones:</label>
                    <textarea name="observaciones" class="form-control" rows="3" 
                              placeholder="Información adicional sobre el turno..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="cerrarModal('modalNuevoTurno')" class="btn btn-secondary">
                Cancelar
            </button>
            <button type="submit" form="formNuevoTurno" class="btn btn-primary">
                <i class="fas fa-save"></i> Crear Turno
            </button>
        </div>
    </div>
</div>

<!-- Modal Detalle Turno -->
<div id="modalDetalleTurno" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalle del Turno</h3>
            <span class="close" onclick="cerrarModal('modalDetalleTurno')">&times;</span>
        </div>
        <div class="modal-body" id="contenidoDetalleTurno">
            <!-- Se llenará dinámicamente -->
        </div>
        <div class="modal-footer">
            <button type="button" onclick="cerrarModal('modalDetalleTurno')" class="btn btn-secondary">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
// Variables globales
let internosEncontrados = [];

// Función para mostrar modal
function mostrarModalNuevoTurno() {
    document.getElementById('modalNuevoTurno').style.display = 'block';
}

// Función para cerrar modal
function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'modalNuevoTurno') {
        document.getElementById('formNuevoTurno').reset();
        document.getElementById('resultadosInterno').style.display = 'none';
        document.getElementById('interno_id').value = '';
    }
}

// Función para filtrar por prioridad
function filtrarPorPrioridad(prioridad) {
    const filas = document.querySelectorAll('.fila-turno');
    filas.forEach(fila => {
        if (prioridad === '' || fila.classList.contains('priority-' + prioridad)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
}

// Función para buscar en tabla
function buscarEnTablaTurnos() {
    const termino = document.getElementById('buscarEnTabla').value.toLowerCase();
    const filas = document.querySelectorAll('.fila-turno');
    
    filas.forEach(fila => {
        const textoInterno = fila.getAttribute('data-interno');
        if (textoInterno.includes(termino)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
}

// Función para buscar internos
function buscarInternos() {
    const termino = document.getElementById('buscarInterno').value;
    const resultadosDiv = document.getElementById('resultadosInterno');
    
    if (termino.length < 3) {
        resultadosDiv.style.display = 'none';
        return;
    }

    // Mostrar loading
    showLoading();
    
    // Configurar la petición AJAX
    const formData = new FormData();
    formData.append('action', 'buscar_internos');
    formData.append('termino', termino);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.internos) {
            let resultadosHTML = '';
            data.internos.forEach(function(interno) {
                resultadosHTML += `
                    <div class="resultado-interno" onclick="seleccionarInterno(${interno.id}, '${interno.apellido}, ${interno.nombre}')">
                        ${interno.apellido}, ${interno.nombre} - DNI: ${interno.dni}
                    </div>`;
            });
            
            resultadosDiv.innerHTML = resultadosHTML;
            resultadosDiv.style.display = 'block';
        } else {
            resultadosDiv.innerHTML = '<div class="sin-resultados">No se encontraron internos</div>';
            resultadosDiv.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error al buscar internos', 'danger');
        resultadosDiv.style.display = 'none';
    })
    .finally(() => {
        hideLoading();
    });
}

// Función para mostrar resultados de búsqueda
function mostrarResultadosInterno(internos) {
    let html = '';
    internos.forEach(interno => {
        html += `<div onclick="seleccionarInterno(${interno.id}, '${interno.apellido}, ${interno.nombre}')" 
                   style="padding: 0.5rem; cursor: pointer; border-bottom: 1px solid #eee; hover: background-color: #f8f9fa;">
                   <strong>${interno.apellido}, ${interno.nombre}</strong><br>
                   <small>DNI: ${interno.dni || interno.ci} | ${interno.obra_social || 'Sin obra social'}</small>
                 </div>`;
    });
    
    document.getElementById('resultadosInterno').innerHTML = html;
    document.getElementById('resultadosInterno').style.display = 'block';
}

// Función para seleccionar interno
function seleccionarInterno(id, nombre) {
    document.getElementById('interno_id').value = id;
    document.getElementById('buscarInterno').value = nombre;
    document.getElementById('resultadosInterno').style.display = 'none';
}

// Función para crear turno médico
function crearTurnoMedico(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const datos = Object.fromEntries(formData);
    
    // Validar que se seleccionó un interno
    if (!datos.id_ppl) {
        showAlert('Debe seleccionar un interno válido', 'danger');
        return;
    }
    
    TurnosModule.crearTurno(datos, function(response) {
        if (response.success) {
            showAlert(response.message, 'success');
            cerrarModal('modalNuevoTurno');
            // Recargar página para mostrar el nuevo turno
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    });
}

// Función para ver detalle del turno
function verDetalleTurno(turnoId) {
    showLoading();
    
    fetch(`?action=turnos&subaction=detalle&id=${turnoId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenidoDetalleTurno').innerHTML = html;
            document.getElementById('modalDetalleTurno').style.display = 'block';
            hideLoading();
        })
        .catch(error => {
            hideLoading();
            showAlert('Error al cargar el detalle del turno', 'danger');
        });
}

// Función para marcar como realizado
function marcarRealizado(turnoId) {
    const medico = prompt('Nombre del médico que atendió:');
    const diagnostico = prompt('Diagnóstico o resultado de la consulta:');
    
    if (medico !== null) {
        const datos = {
            medico: medico || 'No especificado',
            diagnostico: diagnostico || '',
            observaciones: `Turno realizado. Médico: ${medico || 'No especificado'}`
        };
        
        TurnosModule.marcarRealizado(turnoId, datos, function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        });
    }
}

// Función para procesar ausencia
function procesarAusencia(turnoId) {
    const motivo = prompt('Motivo de la ausencia:', 'Interno no se presentó al turno');
    
    if (motivo !== null) {
        TurnosModule.procesarAusencia(turnoId, motivo, function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        });
    }
}

// Función para reprogramar turno
function reprogramarTurno(turnoId) {
    const motivo = prompt('Motivo de la reprogramación:');
    if (motivo) {
        const nuevaFecha = prompt('Nueva fecha y hora (YYYY-MM-DD HH:MM):');
        if (nuevaFecha) {
            // Esta función necesitaría ser implementada en el controlador
            showAlert('Funcionalidad de reprogramación en desarrollo', 'info');
        }
    }
}

// Ocultar resultados de búsqueda al hacer clic fuera
document.addEventListener('click', function(event) {
    if (!event.target.closest('#buscarInterno') && !event.target.closest('#resultadosInterno')) {
        document.getElementById('resultadosInterno').style.display = 'none';
    }
});
</script>

<style>
/* Estilos específicos para turnos */
.fila-turno:hover {
    background-color: #f8f9fa !important;
}

.priority-urgente {
    border-left: 4px solid #e74c3c !important;
}

.priority-ingreso {
    border-left: 4px solid #d35400 !important;
}

.priority-prioritario {
    border-left: 4px solid #f39c12 !important;
}

.priority-normal {
    border-left: 4px solid #3498db !important;
}

#resultadosInterno {
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

#resultadosInterno div:hover {
    background-color: #f8f9fa !important;
}
</style>