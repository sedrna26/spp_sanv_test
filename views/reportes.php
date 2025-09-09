<?php
// reportes.php - Vista para la generación de reportes


// Incluir archivos necesarios
require_once 'config/database.php';
require_once 'controllers/ReporteControllerSPP.php';

$controller = new ReporteControllerSPP();
$reporteDatos = [];
$reporteTitulo = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
    $fechaFin = $_POST['fecha_fin'] ?? date('Y-m-d');
    $tipoReporte = $_POST['tipo_reporte'] ?? 'estado';

    if (isset($_POST['exportar_excel'])) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="reporte_turnos_' . $tipoReporte . '.xls"');
        $csvData = $controller->exportarDatos($tipoReporte, 'excel', $fechaInicio, $fechaFin);
        echo $csvData;
        exit;
    }
    
    // Obtener datos para la vista previa
    $reporteDatos = $controller->exportarDatos($tipoReporte, 'array', $fechaInicio, $fechaFin);
    $reporteTitulo = "Reporte de Turnos por " . ($tipoReporte == 'estado' ? 'Estado' : 'Especialidad');
} else {
    // Valores por defecto al cargar la página por primera vez
    $fechaInicio = date('Y-m-01');
    $fechaFin = date('Y-m-d');
    $tipoReporte = 'estado';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generador de Reportes</title>
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
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="fas fa-chart-bar me-2"></i>
                Generador de Reportes de Sanidad
            </div>
            <div class="card-body">
                <form action="reportes.php" method="POST" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="tipo_reporte" class="form-label">Tipo de Reporte</label>
                            <select class="form-select" id="tipo_reporte" name="tipo_reporte">
                                <option value="estado" <?= $tipoReporte == 'estado' ? 'selected' : '' ?>>Por Estado del Turno</option>
                                <option value="especialidad" <?= $tipoReporte == 'especialidad' ? 'selected' : '' ?>>Por Especialidad</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Ver Reporte</button>
                        </div>
                    </div>
                </form>
                
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><?= htmlspecialchars($reporteTitulo) ?></h5>
                        <form action="reportes.php" method="POST">
                            <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                            <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                            <input type="hidden" name="tipo_reporte" value="<?= htmlspecialchars($tipoReporte) ?>">
                            <button type="submit" name="exportar_excel" class="btn btn-success"><i class="fas fa-file-excel me-1"></i> Exportar a Excel</button>
                        </form>
                    </div>

                    <?php if (empty($reporteDatos)): ?>
                        <div class="alert alert-warning text-center">
                            No se encontraron datos para el rango de fechas y tipo de reporte seleccionados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <?php if ($tipoReporte == 'estado'): ?>
                                            <th>Estado del Turno</th>
                                        <?php else: ?>
                                            <th>Especialidad</th>
                                        <?php endif; ?>
                                        <th>Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reporteDatos as $fila): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($tipoReporte == 'estado' ? $fila['estado'] : $fila['especialidad']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($fila['cantidad']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>