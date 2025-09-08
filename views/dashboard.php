<?php
// views/dashboard.php
class DashboardView {
    public static function render($datos) {
        $html = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sistema de Gestión de Sanidad</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
                .header { background: #2c3e50; color: white; padding: 1rem; }
                .nav { background: #34495e; padding: 0.5rem; }
                .nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; margin-right: 1rem; border-radius: 3px; }
                .nav a:hover { background: #555; }
                .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
                .card { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .card h3 { color: #2c3e50; margin-bottom: 1rem; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
                .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; text-align: center; }
                .stat-number { font-size: 2rem; font-weight: bold; }
                .priority-urgente { background: #e74c3c; color: white; }
                .priority-prioritario { background: #f39c12; color: white; }
                .priority-normal { background: #27ae60; color: white; }
                .priority-ingreso { background: #8e44ad; color: white; }
                .table { width: 100%; border-collapse: collapse; }
                .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
                .table th { background: #f8f9fa; font-weight: bold; }
                .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
                .btn-primary { background: #3498db; color: white; }
                .btn-success { background: #27ae60; color: white; }
                .btn-warning { background: #f39c12; color: white; }
                .btn-danger { background: #e74c3c; color: white; }
                .form-group { margin-bottom: 1rem; }
                .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
                .form-control { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
                .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
                .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
                .modal-content { background: white; margin: 5% auto; padding: 2rem; width: 80%; max-width: 600px; border-radius: 8px; }
                .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
                .close:hover { color: black; }
            </style>
        </head>
        <body>
            <header class="header">
                <h1>Sistema de Gestión de Sanidad Penitenciaria</h1>
            </header>
            
            <nav class="nav">
                <a href="?page=dashboard">Dashboard</a>
                <a href="?page=turnos">Turnos</a>
                <a href="?page=internos">Internos</a>
                <a href="?page=autorizaciones">Autorizaciones</a>
                <a href="?page=informes">Informes</a>
                <a href="?page=reportes">Reportes</a>
                <a href="?page=configuracion">Configuración</a>
            </nav>
            
            <div class="container">
                <div class="card">
                    <h3>Resumen General</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number">' . ($datos['total_turnos_pendientes'] ?? 0) . '</div>
                            <div>Turnos Pendientes</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">' . ($datos['autorizaciones_pendientes'] ?? 0) . '</div>
                            <div>Autorizaciones Pendientes</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">' . ($datos['traslados_hoy'] ?? 0) . '</div>
                            <div>Traslados Hoy</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">' . ($datos['internos_activos'] ?? 0) . '</div>
                            <div>Internos Activos</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Turnos por Prioridad</h3>
                    <div class="stats-grid">';
        
        $prioridades = ['ingreso', 'urgente', 'prioritario', 'normal'];
        $colores = ['priority-ingreso', 'priority-urgente', 'priority-prioritario', 'priority-normal'];
        
        foreach ($prioridades as $index => $prioridad) {
            $cantidad = $datos['turnos_por_prioridad'][$prioridad] ?? 0;
            $html .= '
                        <div class="stat-card ' . $colores[$index] . '">
                            <div class="stat-number">' . $cantidad . '</div>
                            <div>' . strtoupper($prioridad) . '</div>
                        </div>';
        }
        
        $html .= '
                    </div>
                </div>
                
                <div class="card">
                    <h3>Acciones Rápidas</h3>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="?page=turnos&action=nuevo" class="btn btn-primary">Nuevo Turno</a>
                        <a href="?page=internos&action=nuevo" class="btn btn-success">Registrar Interno</a>
                        <a href="?page=autorizaciones&action=generar" class="btn btn-warning">Generar Autorizaciones</a>
                        <a href="?page=reportes&action=diario" class="btn btn-danger">Reporte Diario</a>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Últimas Actividades</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Interno</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        if (isset($datos['ultimas_actividades'])) {
            foreach ($datos['ultimas_actividades'] as $actividad) {
                $html .= '
                            <tr>
                                <td>' . date('d/m/Y', strtotime($actividad['fecha'])) . '</td>
                                <td>' . $actividad['tipo'] . '</td>
                                <td>' . $actividad['interno'] . '</td>
                                <td>' . $actividad['descripcion'] . '</td>
                                <td><span class="badge">' . $actividad['estado'] . '</span></td>
                            </tr>';
            }
        }
        
        $html .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
