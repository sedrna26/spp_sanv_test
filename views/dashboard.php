<?php
// dashboard.php
// Punto central de visualización para el sistema de sanidad del SPP.


// Para fines de demostración, establecemos un ID de usuario si no existe.
// En un sistema real, esto estaría protegido por un login.
$_SESSION['usuario_id'] = $_SESSION['usuario_id'] ?? 1;

// --- 1. Inclusión de Clases ---
// Incluimos todos los archivos necesarios para que el sistema funcione.
// En un proyecto más grande, se usaría un autoloader.
require_once 'config/database.php';
require_once 'models/TurnoMedicoSPP.php';
require_once 'models/AutorizacionMedicaSPP.php';
require_once 'models/InformeMedicoSPP.php';
require_once 'models/EspecialidadSPP.php';
require_once 'models/CentroSaludSPP.php';
// Asumimos que existe un modelo InternoSPP.php, aunque no fue provisto
// class InternoSPP { function __construct($db) {} } // Placeholder si no existe
require_once 'controllers/TurnoControllerSPP.php';
require_once 'controllers/AutorizacionControllerSPP.php';
require_once 'controllers/ReporteControllerSPP.php';


// --- 2. Obtención de Datos ---
// Instanciamos los controladores que nos proveerán la información.
$reporteController = new ReporteControllerSPP();
$turnoController = new TurnoControllerSPP();
$autorizacionController = new AutorizacionControllerSPP();

// Obtenemos el conjunto principal de datos del dashboard ejecutivo.
$dashboardData = $reporteController->dashboardEjecutivo();

// Obtenemos los 10 turnos más urgentes para un listado rápido.
$turnosUrgentesData = $turnoController->obtenerTurnosPorPrioridad(['limite' => 10]);
// Combinamos las prioridades más altas para mostrarlas juntas.
$turnosUrgentes = array_merge(
    $turnosUrgentesData['ingreso'] ?? [],
    $turnosUrgentesData['urgente'] ?? [],
    $turnosUrgentesData['prioritario'] ?? []
);

// Obtenemos las autorizaciones pendientes.
$autorizacionesPendientes = $autorizacionController->listarAutorizacionesPendientes();

// --- 3. Preparación de Datos para Gráficos ---
// Preparamos los datos de PHP para que puedan ser leídos por JavaScript (Chart.js).

// Gráfico de Turnos por Prioridad (Pastel)
$prioridadLabels = [];
$prioridadData = [];
if (!empty($dashboardData['turnos_por_prioridad'])) {
    foreach ($dashboardData['turnos_por_prioridad'] as $item) {
        $prioridadLabels[] = ucfirst($item['prioridad']);
        $prioridadData[] = $item['cantidad'];
    }
}

// Gráfico de Evolución Mensual (Líneas)
$evolucionLabels = [];
$evolucionTotales = [];
$evolucionRealizados = [];
if (!empty($dashboardData['evolucion_mensual'])) {
    foreach ($dashboardData['evolucion_mensual'] as $item) {
        $evolucionLabels[] = $item['mes'];
        $evolucionTotales[] = $item['total_turnos'];
        $evolucionRealizados[] = $item['realizados'];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Sanidad - SPP</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- Estilos Generales --- */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f7f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: auto;
        }
        h1, h2, h3 {
            color: #2c3e50;
            margin-top: 0;
        }

        /* --- Layout de Rejilla (Grid) --- */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .grid-col-span-2 {
            grid-column: span 2;
        }

        /* --- Tarjetas (Cards) --- */
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            overflow: auto; /* Para que las tablas no se desborden */
        }
        
        /* --- Indicadores Clave de Rendimiento (KPIs) --- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .kpi-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 5px solid #3498db;
        }
        .kpi-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2980b9;
        }
        .kpi-card .title {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .kpi-card.alert { border-color: #e74c3c; }
        .kpi-card.alert .number { color: #c0392b; }

        /* --- Alertas y Notificaciones --- */
        .alert-card {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left-width: 5px;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .alert-card.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .alert-card.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }

        /* --- Tablas --- */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
            font-size: 0.9rem;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: white;
            text-transform: uppercase;
        }
        .priority-urgente { background-color: #e74c3c; }
        .priority-ingreso { background-color: #d35400; }
        .priority-prioritario { background-color: #f39c12; }
        .priority-normal { background-color: #3498db; }
        
        /* --- Gráficos --- */
        .chart-container {
            height: 350px;
            position: relative;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard de Sanidad</h1>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="number"><?= htmlspecialchars($dashboardData['total_internos_activos'] ?? 0) ?></div>
                <div class="title">Internos Activos</div>
            </div>
            <div class="kpi-card">
                <div class="number"><?= htmlspecialchars($dashboardData['total_turnos_pendientes'] ?? 0) ?></div>
                <div class="title">Turnos Pendientes</div>
            </div>
            <div class="kpi-card alert">
                <div class="number"><?= htmlspecialchars($dashboardData['urgentes_sin_atender'] ?? 0) ?></div>
                <div class="title">Urgentes sin Atender (+2 días)</div>
            </div>
            <div class="kpi-card">
                <div class="number"><?= htmlspecialchars($dashboardData['total_autorizaciones_pendientes'] ?? 0) ?></div>
                <div class="title">Autorizaciones Pendientes</div>
            </div>
            <div class="kpi-card">
                <div class="number"><?= htmlspecialchars($dashboardData['traslados_hoy'] ?? 0) ?></div>
                <div class="title">Traslados Hoy</div>
            </div>
        </div>

        <div class="grid-container">
            
            <?php if (!empty($dashboardData['alertas'])): ?>
            <div class="card grid-col-span-2">
                <h2>Alertas y Notificaciones</h2>
                <?php foreach ($dashboardData['alertas'] as $alerta): ?>
                    <?php 
                        $class = 'info'; // Clase por defecto
                        if ($alerta['tipo'] == 'danger') $class = '';
                        if ($alerta['tipo'] == 'warning') $class = 'warning';
                    ?>
                    <div class="alert-card <?= $class ?>">
                        <strong>Alerta:</strong> <?= htmlspecialchars($alerta['mensaje']) ?>.
                        <a href="#" style="float: right; text-decoration: none;">Ver</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="card grid-col-span-2">
                <h2>Próximos Turnos Prioritarios</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Interno</th>
                            <th>Especialidad</th>
                            <th>Fecha Solicitud</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($turnosUrgentes)): ?>
                            <?php foreach ($turnosUrgentes as $turno): ?>
                            <tr>
                                <td><?= htmlspecialchars($turno['interno_apellido'] . ', ' . $turno['interno_nombre']) ?></td>
                                <td><?= htmlspecialchars($turno['especialidad_nombre']) ?></td>
                                <td><?= date('d/m/Y', strtotime($turno['fecha_solicitada'])) ?></td>
                                <td><span class="badge priority-<?= $turno['prioridad'] ?>"><?= htmlspecialchars($turno['prioridad']) ?></span></td>
                                <td><?= htmlspecialchars($turno['estado']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;">No hay turnos prioritarios pendientes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Turnos Pendientes por Prioridad</h2>
                <div class="chart-container">
                    <canvas id="turnosPrioridadChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h2>Evolución Mensual de Turnos</h2>
                <div class="chart-container">
                    <canvas id="evolucionTurnosChart"></canvas>
                </div>
            </div>

            <div class="card grid-col-span-2">
                 <h2>Autorizaciones Judiciales Pendientes</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Interno</th>
                            <th>Juzgado</th>
                            <th>Especialidad</th>
                            <th>Fecha Solicitud</th>
                            <th>Días Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($autorizacionesPendientes)): ?>
                            <?php foreach ($autorizacionesPendientes as $auth): ?>
                            <tr>
                                <td><?= htmlspecialchars($auth['interno_apellido'] . ', ' . $auth['interno_nombre']) ?></td>
                                <td><?= htmlspecialchars($auth['juzgado_nombre']) ?></td>
                                <td><?= htmlspecialchars($auth['especialidad_nombre']) ?></td>
                                <td><?= date('d/m/Y', strtotime($auth['fecha_solicitud'])) ?></td>
                                <td style="text-align: center; font-weight: bold; color: <?= $auth['dias_pendiente'] > 7 ? '#e74c3c' : '#333' ?>">
                                    <?= htmlspecialchars($auth['dias_pendiente']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;">No hay autorizaciones judiciales pendientes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

             <div class="card">
                <h3>Especialidades con Mayor Espera</h3>
                <ul style="padding-left: 20px;">
                    <?php if (!empty($dashboardData['especialidades_mayor_espera'])): ?>
                        <?php foreach($dashboardData['especialidades_mayor_espera'] as $item): ?>
                            <li>
                                <strong><?= htmlspecialchars($item['nombre']) ?>:</strong> 
                                <?= round($item['promedio_espera']) ?> días en promedio.
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No hay datos disponibles sobre tiempos de espera.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h3>Internos con Más Turnos (Últimos 6 meses)</h3>
                <ul style="padding-left: 20px;">
                    <?php if (!empty($dashboardData['internos_mas_turnos'])): ?>
                        <?php foreach($dashboardData['internos_mas_turnos'] as $item): ?>
                            <li>
                                <strong><?= htmlspecialchars($item['apellido'] . ', ' . $item['nombre']) ?>:</strong>
                                <?= htmlspecialchars($item['total_turnos']) ?> turnos.
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No hay datos disponibles sobre internos con turnos frecuentes.</li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>
    </div>

    <script>
        // Esperamos a que todo el contenido de la página se cargue
        document.addEventListener('DOMContentLoaded', function () {
            
            // --- GRÁFICO 1: Turnos por Prioridad (Doughnut) ---
            const ctxPrioridad = document.getElementById('turnosPrioridadChart').getContext('2d');
            new Chart(ctxPrioridad, {
                type: 'doughnut', // Tipo de gráfico: dona
                data: {
                    labels: <?= json_encode($prioridadLabels) ?>,
                    datasets: [{
                        label: 'Turnos Pendientes',
                        data: <?= json_encode($prioridadData) ?>,
                        backgroundColor: [
                            '#e74c3c', // Urgente
                            '#f39c12', // Prioritario
                            '#3498db', // Normal
                            '#d35400'  // Ingreso
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });

            // --- GRÁFICO 2: Evolución de Turnos (Line) ---
            const ctxEvolucion = document.getElementById('evolucionTurnosChart').getContext('2d');
            new Chart(ctxEvolucion, {
                type: 'line', // Tipo de gráfico: líneas
                data: {
                    labels: <?= json_encode($evolucionLabels) ?>,
                    datasets: [
                        {
                            label: 'Total de Turnos Solicitados',
                            data: <?= json_encode($evolucionTotales) ?>,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Turnos Realizados',
                            data: <?= json_encode($evolucionRealizados) ?>,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>