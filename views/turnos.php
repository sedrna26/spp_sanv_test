<?php
// views/turnos.php
class TurnosView {
    public static function render($turnos = [], $especialidades = [], $centros = []) {
        $html = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Gestión de Turnos</title>
            <link rel="stylesheet" href="styles.css">
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <h3>Gestión de Turnos Médicos</h3>
                    
                    <div style="margin-bottom: 2rem;">
                        <button onclick="abrirModal(\'modalNuevoTurno\')" class="btn btn-primary">
                            Nuevo Turno
                        </button>
                        <button onclick="filtrarPorPrioridad(\'urgente\')" class="btn btn-danger">
                            Solo Urgentes
                        </button>
                        <button onclick="filtrarPorPrioridad(\'\')" class="btn btn-secondary">
                            Ver Todos
                        </button>
                    </div>
                    
                    <table class="table" id="tablaTurnos">
                        <thead>
                            <tr>
                                <th>Fecha Solicitud</th>
                                <th>Interno</th>
                                <th>DNI</th>
                                <th>Especialidad</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Centro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($turnos as $turno) {
            $clasePrioridad = 'priority-' . $turno['prioridad'];
            $html .= '
                            <tr class="' . $clasePrioridad . '">
                                <td>' . date('d/m/Y', strtotime($turno['fecha_solicitada'])) . '</td>
                                <td>' . $turno['interno_apellido'] . ', ' . $turno['interno_nombre'] . '</td>
                                <td>' . $turno['interno_dni'] . '</td>
                                <td>' . $turno['especialidad_nombre'] . '</td>
                                <td><span class="badge priority-' . $turno['prioridad'] . '">' . strtoupper($turno['prioridad']) . '</span></td>
                                <td>' . $turno['estado'] . '</td>
                                <td>' . ($turno['centro_nombre'] ?? 'No asignado') . '</td>
                                <td>
                                    <button onclick="verDetalle(' . $turno['id'] . ')" class="btn btn-sm btn-primary">Ver</button>
                                    <button onclick="editarTurno(' . $turno['id'] . ')" class="btn btn-sm btn-warning">Editar</button>
                                    <button onclick="reprogramarTurno(' . $turno['id'] . ')" class="btn btn-sm btn-danger">Reprogramar</button>
                                </td>
                            </tr>';
        }
        
        $html .= '
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Modal Nuevo Turno -->
            <div id="modalNuevoTurno" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="cerrarModal(\'modalNuevoTurno\')">&times;</span>
                    <h3>Nuevo Turno Médico</h3>
                    
                    <form id="formNuevoTurno" method="POST" action="?page=turnos&action=crear">
                        <div class="form-group">
                            <label>Buscar Interno:</label>
                            <input type="text" id="buscarInterno" class="form-control" 
                                   placeholder="Nombre, apellido o DNI" onkeyup="buscarInternos()">
                            <div id="resultadosInterno" style="display:none;"></div>
                            <input type="hidden" id="interno_id" name="interno_id" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Especialidad:</label>
                            <select name="especialidad_id" class="form-control" required>';
        
        foreach ($especialidades as $esp) {
            $html .= '<option value="' . $esp['id'] . '">' . $esp['nombre'] . '</option>';
        }
        
        $html .= '
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Centro de Salud:</label>
                            <select name="centro_salud_id" class="form-control">';
        
        foreach ($centros as $centro) {
            $html .= '<option value="' . $centro['id'] . '">' . $centro['nombre'] . ' (' . $centro['tipo'] . ')</option>';
        }
        
        $html .= '
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Prioridad:</label>
                            <select name="prioridad" class="form-control" required>
                                <option value="normal">Normal</option>
                                <option value="prioritario">Prioritario</option>
                                <option value="urgente">Urgente</option>
                                <option value="ingreso">Ingreso</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Fecha Solicitada:</label>
                            <input type="date" name="fecha_solicitada" class="form-control" 
                                   value="' . date('Y-m-d') . '" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Fecha/Hora del Turno (si ya se tiene):</label>
                            <input type="datetime-local" name="fecha_turno" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="requiere_autorizacion" value="1" checked>
                                Requiere autorización judicial
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Observaciones:</label>
                            <textarea name="observaciones" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div style="text-align: right;">
                            <button type="button" onclick="cerrarModal(\'modalNuevoTurno\')" class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Crear Turno</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function abrirModal(modalId) {
                    document.getElementById(modalId).style.display = "block";
                }
                
                function cerrarModal(modalId) {
                    document.getElementById(modalId).style.display = "none";
                }
                
                function filtrarPorPrioridad(prioridad) {
                    const filas = document.querySelectorAll("#tablaTurnos tbody tr");
                    filas.forEach(fila => {
                        if (prioridad === "" || fila.classList.contains("priority-" + prioridad)) {
                            fila.style.display = "";
                        } else {
                            fila.style.display = "none";
                        }
                    });
                }
                
                function buscarInternos() {
                    const termino = document.getElementById("buscarInterno").value;
                    if (termino.length < 3) {
                        document.getElementById("resultadosInterno").style.display = "none";
                        return;
                    }
                    
                    fetch("?page=api&action=buscar_internos&q=" + encodeURIComponent(termino))
                        .then(response => response.json())
                        .then(data => {
                            let html = "";
                            data.forEach(interno => {
                                html += `<div onclick="seleccionarInterno(${interno.id}, \'${interno.nombre} ${interno.apellido}\')" 
                                           style="padding: 0.5rem; cursor: pointer; border-bottom: 1px solid #eee;">
                                           ${interno.apellido}, ${interno.nombre} - DNI: ${interno.dni}
                                         </div>`;
                            });
                            document.getElementById("resultadosInterno").innerHTML = html;
                            document.getElementById("resultadosInterno").style.display = "block";
                        });
                }
                
                function seleccionarInterno(id, nombre) {
                    document.getElementById("interno_id").value = id;
                    document.getElementById("buscarInterno").value = nombre;
                    document.getElementById("resultadosInterno").style.display = "none";
                }
                
                function verDetalle(turnoId) {
                    window.location.href = "?page=turnos&action=detalle&id=" + turnoId;
                }
                
                function editarTurno(turnoId) {
                    window.location.href = "?page=turnos&action=editar&id=" + turnoId;
                }
                
                function reprogramarTurno(turnoId) {
                    const motivo = prompt("Motivo de la reprogramación:");
                    if (motivo) {
                        const nuevaFecha = prompt("Nueva fecha (YYYY-MM-DD HH:MM):");
                        if (nuevaFecha) {
                            window.location.href = `?page=turnos&action=reprogramar&id=${turnoId}&motivo=${encodeURIComponent(motivo)}&fecha=${encodeURIComponent(nuevaFecha)}`;
                        }
                    }
                }
            </script>
        </body>
        </html>';
        
        return $html;
    }
}
