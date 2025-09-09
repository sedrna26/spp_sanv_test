<?php
// controllers/ReporteControllerSPP.php - Controlador de reportes adaptado
class ReporteControllerSPP {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Estadísticas generales del sistema de sanidad
     */
    public function estadisticasGenerales($fechaInicio = null, $fechaFin = null) {
        try {
            $pdo = $this->db->connect();
            $whereClause = "";
            $params = [];
            
            if ($fechaInicio && $fechaFin) {
                $whereClause = "WHERE tm.created_at BETWEEN :fecha_inicio AND :fecha_fin";
                $params = [
                    'fecha_inicio' => $fechaInicio . ' 00:00:00',
                    'fecha_fin' => $fechaFin . ' 23:59:59'
                ];
            }
            
            $estadisticas = [];
            
            // Total de internos activos con datos médicos
            $stmt = $pdo->query("
                SELECT COUNT(*) as total
                FROM vista_internos_sanidad 
                WHERE estado_interno = 'activo'
            ");
            $estadisticas['internos_activos'] = $stmt->fetchColumn();
            
            // Internos con obra social
            $stmt = $pdo->query("
                SELECT COUNT(*) as total
                FROM vista_internos_sanidad 
                WHERE estado_interno = 'activo' 
                AND (obra_social IS NOT NULL OR tiene_pami = 1)
            ");
            $estadisticas['internos_con_cobertura'] = $stmt->fetchColumn();
            
            // Turnos por estado
            $stmt = $pdo->prepare("
                SELECT estado, COUNT(*) as cantidad 
                FROM turnos_medicos tm 
                {$whereClause}
                GROUP BY estado
                ORDER BY cantidad DESC
            ");
            $stmt->execute($params);
            $estadisticas['turnos_por_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Turnos por prioridad
            $stmt = $pdo->prepare("
                SELECT prioridad, COUNT(*) as cantidad 
                FROM turnos_medicos tm 
                {$whereClause}
                GROUP BY prioridad
                ORDER BY 
                    CASE prioridad 
                        WHEN 'ingreso' THEN 1
                        WHEN 'urgente' THEN 2
                        WHEN 'prioritario' THEN 3
                        WHEN 'normal' THEN 4
                    END
            ");
            $stmt->execute($params);
            $estadisticas['turnos_por_prioridad'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Especialidades más solicitadas
            $stmt = $pdo->prepare("
                SELECT 
                    e.nombre, 
                    COUNT(tm.id) as cantidad,
                    AVG(DATEDIFF(COALESCE(tm.fecha_turno, CURDATE()), tm.fecha_solicitada)) as promedio_dias_espera
                FROM especialidades e
                JOIN turnos_medicos tm ON e.id = tm.especialidad_id
                {$whereClause}
                GROUP BY e.id, e.nombre
                ORDER BY cantidad DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            $estadisticas['especialidades_mas_solicitadas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Autorizaciones por estado
            $stmt = $pdo->query("
                SELECT estado, COUNT(*) as cantidad
                FROM autorizaciones_medicas
                GROUP BY estado
            ");
            $estadisticas['autorizaciones_por_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Juzgados con más solicitudes médicas
            $stmt = $pdo->query("
                SELECT 
                    j.juzgado,
                    COUNT(am.id) as total_autorizaciones,
                    AVG(DATEDIFF(COALESCE(am.fecha_autorizacion, CURDATE()), am.fecha_solicitud)) as promedio_dias_respuesta
                FROM juzgado j
                LEFT JOIN autorizaciones_medicas am ON j.id = am.id_juzgado
                GROUP BY j.id, j.juzgado
                HAVING total_autorizaciones > 0
                ORDER BY total_autorizaciones DESC
                LIMIT 10
            ");
            $estadisticas['juzgados_mas_solicitudes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $estadisticas;
            
        } catch (Exception $e) {
            error_log("Error generando estadísticas generales: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Reporte de turnos por sector penitenciario
     */
    public function reporteTurnosPorSector($fechaInicio, $fechaFin) {
        try {
            $pdo = $this->db->connect();
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.descripcio as sector,
                    COUNT(tm.id) as total_turnos,
                    SUM(CASE WHEN tm.estado = 'realizado' THEN 1 ELSE 0 END) as realizados,
                    SUM(CASE WHEN tm.estado = 'no_asistio' THEN 1 ELSE 0 END) as no_asistieron,
                    SUM(CASE WHEN tm.prioridad = 'urgente' THEN 1 ELSE 0 END) as urgentes
                FROM sectores s
                LEFT JOIN cambiosdealojamientos ca ON s.codigo = ca.sector AND ca.bandera = 1
                LEFT JOIN turnos_medicos tm ON ca.cod_ppl = tm.id_ppl
                    AND tm.created_at BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY s.codigo, s.descripcio
                ORDER BY total_turnos DESC
            ");
            
            $stmt->execute([
                'fecha_inicio' => $fechaInicio . ' 00:00:00',
                'fecha_fin' => $fechaFin . ' 23:59:59'
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error generando reporte por sector: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Reporte de eficiencia de autorizaciones judiciales
     */
    public function reporteEficienciaAutorizaciones($fechaInicio, $fechaFin) {
        try {
            $pdo = $this->db->connect();
            
            $stmt = $pdo->prepare("
                SELECT 
                    j.juzgado,
                    COUNT(am.id) as total_solicitudes,
                    SUM(CASE WHEN am.estado = 'autorizado' THEN 1 ELSE 0 END) as autorizadas,
                    SUM(CASE WHEN am.estado = 'rechazado' THEN 1 ELSE 0 END) as rechazadas,
                    SUM(CASE WHEN am.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    AVG(CASE 
                        WHEN am.estado = 'autorizado' 
                        THEN DATEDIFF(am.fecha_autorizacion, am.fecha_solicitud) 
                        ELSE NULL 
                    END) as promedio_dias_autorizacion,
                    MIN(CASE 
                        WHEN am.estado = 'autorizado' 
                        THEN DATEDIFF(am.fecha_autorizacion, am.fecha_solicitud) 
                        ELSE NULL 
                    END) as minimo_dias,
                    MAX(CASE 
                        WHEN am.estado = 'autorizado' 
                        THEN DATEDIFF(am.fecha_autorizacion, am.fecha_solicitud) 
                        ELSE NULL 
                    END) as maximo_dias
                FROM juzgado j
                LEFT JOIN autorizaciones_medicas am ON j.id = am.id_juzgado
                    AND am.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY j.id, j.juzgado
                HAVING total_solicitudes > 0
                ORDER BY promedio_dias_autorizacion ASC
            ");
            
            $stmt->execute([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error generando reporte de eficiencia: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Dashboard ejecutivo con métricas clave
     */
    public function dashboardEjecutivo() {
        try {
            $pdo = $this->db->connect();
            $dashboard = [];
            
            // Métricas básicas
            $stmt = $pdo->query("SELECT COUNT(*) FROM vista_internos_sanidad WHERE estado_interno = 'activo'");
            $dashboard['total_internos_activos'] = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM turnos_medicos WHERE estado IN ('solicitado', 'confirmado')");
            $dashboard['total_turnos_pendientes'] = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM autorizaciones_medicas WHERE estado = 'pendiente'");
            $dashboard['total_autorizaciones_pendientes'] = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM traslados_medicos WHERE DATE(fecha_traslado) = CURDATE()");
            $dashboard['traslados_hoy'] = $stmt->fetchColumn();
            
            // Turnos urgentes sin atender
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM turnos_medicos 
                WHERE prioridad IN ('urgente', 'ingreso') 
                AND estado IN ('solicitado', 'confirmado')
                AND DATEDIFF(CURDATE(), fecha_solicitada) > 2
            ");
            $dashboard['urgentes_sin_atender'] = $stmt->fetchColumn();
            
            // Promedio de días de espera por especialidad
            $stmt = $pdo->query("
                SELECT 
                    e.nombre,
                    AVG(DATEDIFF(COALESCE(tm.fecha_turno, CURDATE()), tm.fecha_solicitada)) as promedio_espera,
                    COUNT(tm.id) as total_turnos
                FROM especialidades e
                LEFT JOIN turnos_medicos tm ON e.id = tm.especialidad_id 
                    AND tm.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY e.id, e.nombre
                HAVING total_turnos > 0
                ORDER BY promedio_espera DESC
                LIMIT 5
            ");
            $dashboard['especialidades_mayor_espera'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Evolución mensual de turnos (últimos 6 meses)
            $stmt = $pdo->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as mes,
                    COUNT(*) as total_turnos,
                    SUM(CASE WHEN estado = 'realizado' THEN 1 ELSE 0 END) as realizados,
                    SUM(CASE WHEN estado = 'no_asistio' THEN 1 ELSE 0 END) as no_asistieron
                FROM turnos_medicos
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY mes
            ");
            $dashboard['evolucion_mensual'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top 5 internos con más turnos médicos
            $stmt = $pdo->query("
                SELECT 
                    p.apellido, p.nombre, p.ci,
                    COUNT(tm.id) as total_turnos,
                    MAX(tm.created_at) as ultimo_turno
                FROM persona p
                JOIN turnos_medicos tm ON p.id = tm.id_ppl
                WHERE tm.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY p.id, p.apellido, p.nombre, p.ci
                ORDER BY total_turnos DESC
                LIMIT 5
            ");
            $dashboard['internos_mas_turnos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agregar datos de internos con más turnos
            $dashboard['internos_mas_turnos'] = $this->obtenerInternosConMasTurnos();
            
            // Agregar datos de especialidades con mayor espera
            $dashboard['especialidades_mayor_espera'] = $this->obtenerEspecialidadesMayorEspera();
            
            // Alertas y notificaciones
            $alertas = [];
            
            // Autorizaciones vencidas
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM autorizaciones_medicas 
                WHERE estado = 'autorizado' AND fecha_vencimiento < CURDATE()
            ");
            $autorizaciones_vencidas = $stmt->fetchColumn();
            if ($autorizaciones_vencidas > 0) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'mensaje' => "{$autorizaciones_vencidas} autorizaciones han vencido",
                    'accion' => 'revisar_autorizaciones'
                ];
            }
            
            // Turnos urgentes antiguos
            if ($dashboard['urgentes_sin_atender'] > 0) {
                $alertas[] = [
                    'tipo' => 'danger',
                    'mensaje' => "{$dashboard['urgentes_sin_atender']} turnos urgentes llevan más de 2 días sin atender",
                    'accion' => 'revisar_urgentes'
                ];
            }
            
            // Internos sin atención médica reciente
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM vista_internos_sanidad v
                WHERE v.estado_interno = 'activo'
                AND NOT EXISTS (
                    SELECT 1 FROM turnos_medicos tm 
                    WHERE tm.id_ppl = v.id 
                    AND tm.estado = 'realizado'
                    AND tm.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                )
            ");
            $sin_atencion_reciente = $stmt->fetchColumn();
            if ($sin_atencion_reciente > 10) {
                $alertas[] = [
                    'tipo' => 'info',
                    'mensaje' => "{$sin_atencion_reciente} internos no han tenido atención médica en los últimos 6 meses",
                    'accion' => 'revisar_sin_atencion'
                ];
            }
            
            $dashboard['alertas'] = $alertas;
            
            return $dashboard;
            
        } catch (Exception $e) {
            error_log("Error generando dashboard ejecutivo: " . $e->getMessage());
            return [];
        }
    }
    
    private function obtenerInternosConMasTurnos() {
        try {
            $sql = "SELECT 
                        i.apellido,
                        i.nombre,
                        COUNT(t.id) as total_turnos
                    FROM internos i
                    JOIN turnos_medicos t ON t.interno_id = i.id
                    WHERE t.fecha_turno >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                    GROUP BY i.id, i.apellido, i.nombre
                    ORDER BY total_turnos DESC
                    LIMIT 5";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // En caso de error, retornar array vacío
            return [];
        }
    }
    
    private function obtenerEspecialidadesMayorEspera() {
        try {
            $sql = "SELECT 
                        e.nombre,
                        AVG(DATEDIFF(t.fecha_turno, t.fecha_solicitada)) as promedio_espera
                    FROM turnos_medicos t
                    JOIN especialidades e ON t.especialidad_id = e.id
                    WHERE t.estado = 'realizado'
                    AND t.fecha_turno >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                    GROUP BY e.id, e.nombre
                    HAVING promedio_espera > 0
                    ORDER BY promedio_espera DESC
                    LIMIT 5";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // En caso de error, retornar array vacío
            return [];
        }
    }
    
    /**
     * Reporte de costos médicos (si se implementa el tracking de costos)
     */
    public function reporteCostosMedicos($fechaInicio, $fechaFin) {
        try {
            $pdo = $this->db->connect();
            
            $stmt = $pdo->prepare("
                SELECT 
                    e.nombre as especialidad,
                    COUNT(tm.id) as total_turnos,
                    SUM(COALESCE(em.costo, 0)) as costo_estudios,
                    SUM(COALESCE(trm.costo_traslado, 0)) as costo_traslados,
                    (SUM(COALESCE(em.costo, 0)) + SUM(COALESCE(trm.costo_traslado, 0))) as costo_total
                FROM turnos_medicos tm
                JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN estudios_medicos em ON tm.id = em.turno_id
                LEFT JOIN traslados_medicos trm ON tm.id = trm.turno_id
                WHERE tm.created_at BETWEEN :fecha_inicio AND :fecha_fin
                AND tm.estado = 'realizado'
                GROUP BY e.id, e.nombre
                ORDER BY costo_total DESC
            ");
            
            $stmt->execute([
                'fecha_inicio' => $fechaInicio . ' 00:00:00',
                'fecha_fin' => $fechaFin . ' 23:59:59'
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error generando reporte de costos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Exportar datos para análisis externo
     */
    public function exportarDatosPorPeriodo($fechaInicio, $fechaFin, $formato = 'json') {
        try {
            $pdo = $this->db->connect();
            
            $stmt = $pdo->prepare("
                SELECT 
                    tm.id as turno_id,
                    tm.fecha_solicitada,
                    tm.fecha_turno,
                    tm.prioridad,
                    tm.estado,
                    p.apellido,
                    p.nombre,
                    p.ci as dni,
                    p.obra_social,
                    p.tiene_pami,
                    e.nombre as especialidad,
                    cs.nombre as centro_salud,
                    cs.tipo as tipo_centro,
                    j.juzgado,
                    ca.sector,
                    ca.pabellon,
                    CASE 
                        WHEN am.estado IS NOT NULL THEN am.estado
                        ELSE 'no_requiere'
                    END as estado_autorizacion,
                    DATEDIFF(COALESCE(tm.fecha_turno, CURDATE()), tm.fecha_solicitada) as dias_espera
                FROM turnos_medicos tm
                JOIN persona p ON tm.id_ppl = p.id
                JOIN especialidades e ON tm.especialidad_id = e.id
                LEFT JOIN centros_salud cs ON tm.centro_salud_id = cs.id
                LEFT JOIN situacionlegal sl ON p.id = sl.id_ppl
                LEFT JOIN juzgado j ON sl.id_juzgado = j.id
                LEFT JOIN cambiosdealojamientos ca ON p.id = ca.cod_ppl AND ca.bandera = 1
                LEFT JOIN autorizaciones_medicas am ON tm.id = am.turno_id
                WHERE tm.created_at BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY tm.created_at
            ");
            
            $stmt->execute([
                'fecha_inicio' => $fechaInicio . ' 00:00:00',
                'fecha_fin' => $fechaFin . ' 23:59:59'
            ]);
            
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            switch ($formato) {
                case 'csv':
                    return $this->convertirACSV($datos);
                case 'excel':
                    return $this->convertirAExcel($datos);
                default:
                    return $datos;
            }
            
        } catch (Exception $e) {
            error_log("Error exportando datos: " . $e->getMessage());
            return [];
        }
    }
    
    // Métodos auxiliares para exportación
    private function convertirACSV($datos) {
        if (empty($datos)) return '';
        
        $csv = '';
        
        // Headers
        $headers = array_keys($datos[0]);
        $csv .= implode(';', $headers) . "\n";
        
        // Datos
        foreach ($datos as $fila) {
            $csv .= implode(';', array_map(function($valor) {
                return '"' . str_replace('"', '""', $valor) . '"';
            }, $fila)) . "\n";
        }
        
        return $csv;
    }
    
    private function convertirAExcel($datos) {
        // Implementación básica - en producción usar una librería como PhpSpreadsheet
        return $this->convertirACSV($datos);
    }
}
