<?php
// config/database.php - Configuración para Base de Datos SPP Integrada
class Database {
    private $host = 'localhost';
    private $dbname = 'spp'; // Cambiado para usar la base existente
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $this->username, $this->password);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // Configurar timezone para MySQL
                $this->pdo->exec("SET time_zone = '-03:00'");
                
            } catch (PDOException $e) {
                error_log("Error de conexión a base de datos: " . $e->getMessage());
                die("Error de conexión: No se puede conectar a la base de datos");
            }
        }
        return $this->pdo;
    }
    
    /**
     * Verificar si las tablas de sanidad existen
     */
    public function verificarTablasExistentes() {
        $pdo = $this->connect();
        $tablasRequeridas = [
            'especialidades',
            'centros_salud', 
            'turnos_medicos',
            'autorizaciones_medicas',
            'traslados_medicos',
            'informes_medicos',
            'estudios_medicos',
            'historial_medico'
        ];
        
        $tablasExistentes = [];
        $tablasFaltantes = [];
        
        foreach ($tablasRequeridas as $tabla) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :tabla");
            $stmt->execute(['tabla' => $tabla]);
            
            if ($stmt->rowCount() > 0) {
                $tablasExistentes[] = $tabla;
            } else {
                $tablasFaltantes[] = $tabla;
            }
        }
        
        return [
            'existentes' => $tablasExistentes,
            'faltantes' => $tablasFaltantes,
            'total_requeridas' => count($tablasRequeridas),
            'completitud' => (count($tablasExistentes) / count($tablasRequeridas)) * 100
        ];
    }
    
    /**
     * Crear las tablas necesarias para el sistema de sanidad
     * Integrado con la estructura SPP existente
     */
    public function crearTablasIntegracion() {
        $pdo = $this->connect();
        
        try {
            $pdo->beginTransaction();
            
            // 1. Crear tabla de especialidades
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `especialidades` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `nombre` varchar(100) NOT NULL,
              `descripcion` text,
              `requiere_estudios_previos` tinyint(1) DEFAULT 0,
              `estudios_requeridos` text,
              `activo` tinyint(1) DEFAULT 1,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_especialidades_nombre` (`nombre`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // 2. Crear tabla de centros de salud
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `centros_salud` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `nombre` varchar(255) NOT NULL,
              `tipo` enum('publico','privado') NOT NULL,
              `direccion` text,
              `telefono` varchar(50),
              `contacto_0800` varchar(50),
              `especialidades_disponibles` text,
              `activo` tinyint(1) DEFAULT 1,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // 3. Crear tabla de turnos médicos (integrada con SPP)
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `turnos_medicos` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `id_ppl` int(11) NOT NULL COMMENT 'Referencia al interno en tabla persona',
              `especialidad_id` int(11) NOT NULL,
              `centro_salud_id` int(11) DEFAULT NULL,
              `fecha_solicitada` date NOT NULL,
              `fecha_turno` datetime DEFAULT NULL,
              `prioridad` enum('normal','prioritario','urgente','ingreso') DEFAULT 'normal',
              `estado` enum('solicitado','pendiente_estudios','confirmado','realizado','cancelado','reprogramado','no_asistio') DEFAULT 'solicitado',
              `observaciones` text,
              `estudios_realizados` tinyint(1) DEFAULT 0,
              `requiere_autorizacion` tinyint(1) DEFAULT 1,
              `motivo_consulta` text,
              `usuario_carga` int(11) DEFAULT NULL,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `fk_turnos_ppl` (`id_ppl`),
              KEY `fk_turnos_especialidad` (`especialidad_id`),
              KEY `fk_turnos_centro` (`centro_salud_id`),
              KEY `idx_fecha_turno` (`fecha_turno`),
              KEY `idx_prioridad` (`prioridad`),
              KEY `idx_estado` (`estado`),
              FOREIGN KEY (`id_ppl`) REFERENCES `persona` (`id`) ON DELETE CASCADE,
              FOREIGN KEY (`especialidad_id`) REFERENCES `especialidades` (`id`),
              FOREIGN KEY (`centro_salud_id`) REFERENCES `centros_salud` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // 4. Crear tabla de autorizaciones médicas
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `autorizaciones_medicas` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `turno_id` int(11) NOT NULL,
              `id_ppl` int(11) NOT NULL,
              `id_juzgado` int(11) NOT NULL,
              `numero_autorizacion` varchar(100),
              `fecha_solicitud` date NOT NULL,
              `fecha_autorizacion` date DEFAULT NULL,
              `fecha_vencimiento` date DEFAULT NULL,
              `estado` enum('pendiente','autorizado','rechazado','vencido') DEFAULT 'pendiente',
              `documento_solicitud` varchar(255),
              `documento_autorizacion` varchar(255),
              `observaciones` text,
              `usuario_solicita` int(11) DEFAULT NULL,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `fk_autorizaciones_turno` (`turno_id`),
              KEY `fk_autorizaciones_ppl` (`id_ppl`),
              KEY `fk_autorizaciones_juzgado` (`id_juzgado`),
              KEY `idx_estado_fecha` (`estado`, `fecha_solicitud`),
              FOREIGN KEY (`turno_id`) REFERENCES `turnos_medicos` (`id`) ON DELETE CASCADE,
              FOREIGN KEY (`id_ppl`) REFERENCES `persona` (`id`) ON DELETE CASCADE,
              FOREIGN KEY (`id_juzgado`) REFERENCES `juzgado` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // 5. Crear tabla de traslados médicos
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `traslados_medicos` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `turno_id` int(11) NOT NULL,
              `id_ppl` int(11) NOT NULL,
              `fecha_traslado` datetime NOT NULL,
              `hora_salida` time DEFAULT NULL,
              `hora_regreso` time DEFAULT NULL,
              `destino` varchar(255) NOT NULL,
              `responsable_traslado` varchar(100),
              `vehiculo` varchar(100),
              `custodia_asignada` text,
              `estado` enum('programado','en_curso','realizado','cancelado') DEFAULT 'programado',
              `motivo_cancelacion` text,
              `observaciones` text,
              `costo_traslado` decimal(10,2) DEFAULT 0.00,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `fk_traslados_turno` (`turno_id`),
              KEY `fk_traslados_ppl` (`id_ppl`),
              KEY `idx_fecha_traslado` (`fecha_traslado`),
              FOREIGN KEY (`turno_id`) REFERENCES `turnos_medicos` (`id`) ON DELETE CASCADE,
              FOREIGN KEY (`id_ppl`) REFERENCES `persona` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // 6. Crear tabla de informes médicos
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `informes_medicos` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `turno_id` int(11) DEFAULT NULL,
              `id_ppl` int(11) NOT NULL,
              `tipo_informe` enum('atencion','estudio','reprogramacion','acta_novedad','emergencia','seguimiento') NOT NULL,
              `contenido` text NOT NULL,
              `diagnostico` text,
              `tratamiento_indicado` text,
              `medicamentos` text,
              `proxima_cita` date DEFAULT NULL,
              `fecha_informe` date NOT NULL,
              `archivo_adjunto` varchar(255),
              `enviado_juzgado` tinyint(1) DEFAULT 0,
              `fecha_envio_juzgado` datetime DEFAULT NULL,
              `medico_responsable` varchar(100),
              `usuario_carga` int(11) DEFAULT NULL,
              `estado` enum('borrador','finalizado','enviado') DEFAULT 'borrador',
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `fk_informes_turno` (`turno_id`),
              KEY `fk_informes_ppl` (`id_ppl`),
              KEY `idx_tipo_fecha` (`tipo_informe`, `fecha_informe`),
              FOREIGN KEY (`turno_id`) REFERENCES `turnos_medicos` (`id`) ON DELETE SET NULL,
              FOREIGN KEY (`id_ppl`) REFERENCES `persona` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // 7. Crear tabla de estudios médicos
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `estudios_medicos` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `turno_id` int(11) NOT NULL,
              `id_ppl` int(11) NOT NULL,
              `tipo_estudio` varchar(100) NOT NULL,
              `fecha_solicitado` date NOT NULL,
              `fecha_realizado` date DEFAULT NULL,
              `resultado` text,
              `archivo_resultado` varchar(255),
              `codigo_qr` varchar(255),
              `estado` enum('pendiente','realizado','con_problema','cancelado') DEFAULT 'pendiente',
              `observaciones` text,
              `costo` decimal(10,2) DEFAULT 0.00,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `fk_estudios_turno` (`turno_id`),
              KEY `fk_estudios_ppl` (`id_ppl`),
              FOREIGN KEY (`turno_id`) REFERENCES `turnos_medicos` (`id`) ON DELETE CASCADE,
              FOREIGN KEY (`id_ppl`) REFERENCES `persona` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // 8. Crear tabla de historial médico
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `historial_medico` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `id_ppl` int(11) NOT NULL,
              `fecha_evento` date NOT NULL,
              `tipo_evento` enum('consulta','emergencia','internacion','cirugia','estudio','vacunacion','otro') NOT NULL,
              `descripcion` text NOT NULL,
              `especialidad` varchar(100),
              `centro_salud` varchar(255),
              `medico` varchar(100),
              `diagnostico` text,
              `tratamiento` text,
              `archivo_adjunto` varchar(255),
              `observaciones` text,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `fk_historial_ppl` (`id_ppl`),
              KEY `idx_fecha_evento` (`fecha_evento`),
              FOREIGN KEY (`id_ppl`) REFERENCES `persona` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            $pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creando tablas de integración: " . $e->getMessage());
            throw new Exception("Error al crear las tablas de sanidad: " . $e->getMessage());
        }
    }
    
    /**
     * Modificar tablas existentes para agregar campos de sanidad
     */
    public function modificarTablasExistentes() {
        $pdo = $this->connect();
        
        try {
            // Modificar tabla juzgado para campos médicos
            $pdo->exec("
            ALTER TABLE `juzgado` 
            ADD COLUMN IF NOT EXISTS `requiere_expediente_medico` tinyint(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS `formato_autorizacion_medica` text,
            ADD COLUMN IF NOT EXISTS `email_autorizaciones` varchar(100),
            ADD COLUMN IF NOT EXISTS `telefono_autorizaciones` varchar(50),
            ADD COLUMN IF NOT EXISTS `horario_atencion` varchar(100),
            ADD COLUMN IF NOT EXISTS `dias_respuesta_promedio` int(3) DEFAULT 5
            ");
            
            // Modificar tabla persona para datos médicos
            $pdo->exec("
            ALTER TABLE `persona`
            ADD COLUMN IF NOT EXISTS `tipo_sangre` varchar(5),
            ADD COLUMN IF NOT EXISTS `obra_social` varchar(100),
            ADD COLUMN IF NOT EXISTS `numero_afiliado` varchar(50),
            ADD COLUMN IF NOT EXISTS `tiene_pami` tinyint(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS `contacto_emergencia` varchar(100),
            ADD COLUMN IF NOT EXISTS `telefono_emergencia` varchar(50),
            ADD COLUMN IF NOT EXISTS `alergias` text,
            ADD COLUMN IF NOT EXISTS `medicamentos_habituales` text,
            ADD COLUMN IF NOT EXISTS `enfermedades_cronicas` text,
            ADD COLUMN IF NOT EXISTS `ultima_revision_medica` date
            ");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error modificando tablas existentes: " . $e->getMessage());
            // No lanzamos excepción aquí porque las columnas pueden ya existir
            return false;
        }
    }
    
    /**
     * Crear vista para internos con datos médicos integrados
     */
    public function crearVistasIntegracion() {
        $pdo = $this->connect();
        
        try {
            $pdo->exec("
            CREATE OR REPLACE VIEW `vista_internos_sanidad` AS
            SELECT 
                p.id,
                p.nombre,
                p.apellido,
                p.ci as dni,
                p.fecha_nac,
                p.tipo_sangre,
                p.obra_social,
                p.numero_afiliado,
                p.tiene_pami,
                p.contacto_emergencia,
                p.telefono_emergencia,
                p.alergias,
                p.medicamentos_habituales,
                p.enfermedades_cronicas,
                p.ultima_revision_medica,
                sl.situacionlegal,
                sl.id_juzgado,
                j.juzgado as juzgado_nombre,
                j.requiere_expediente_medico,
                ca.sector,
                ca.pabellon,
                ca.num_celda,
                ca.fecha as fecha_ultimo_alojamiento,
                CASE 
                    WHEN ca.libertad = 1 THEN 'liberado'
                    WHEN ca.anulado = 1 THEN 'trasladado'
                    ELSE 'activo'
                END as estado_interno
            FROM persona p
            LEFT JOIN situacionlegal sl ON p.id = sl.id_ppl
            LEFT JOIN juzgado j ON sl.id_juzgado = j.id
            LEFT JOIN cambiosdealojamientos ca ON p.id = ca.cod_ppl AND ca.bandera = 1
            WHERE p.id > 0
            ");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error creando vistas: " . $e->getMessage());
            throw new Exception("Error al crear las vistas de integración: " . $e->getMessage());
        }
    }
    
    /**
     * Insertar datos iniciales de especialidades y centros de salud
     */
    public function insertarDatosIniciales() {
        $pdo = $this->connect();
        
        try {
            $pdo->beginTransaction();
            
            // Verificar si ya hay datos
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM especialidades");
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                // Insertar especialidades
                $especialidades = [
                    ['Clínica Médica', 'Medicina general y atención primaria', 0, null],
                    ['Cardiología', 'Especialidad del corazón y sistema cardiovascular', 1, 'Electrocardiograma, Radiografía de tórax'],
                    ['Traumatología', 'Especialidad de huesos, músculos y articulaciones', 1, 'Radiografías de la zona afectada'],
                    ['Oftalmología', 'Especialidad de los ojos y la visión', 0, null],
                    ['Odontología', 'Salud bucal y dental', 0, null],
                    ['Psiquiatría', 'Salud mental y trastornos psiquiátricos', 0, null],
                    ['Neurología', 'Sistema nervioso central y periférico', 1, 'Tomografía computada, Resonancia magnética'],
                    ['Dermatología', 'Enfermedades de la piel', 0, null],
                    ['Urología', 'Sistema urogenital', 1, 'Análisis de orina, Ecografía'],
                    ['Ginecología', 'Salud reproductiva femenina', 1, 'Papanicolau, Ecografía ginecológica'],
                    ['Endocrinología', 'Sistema hormonal y metabólico', 1, 'Análisis de laboratorio completo'],
                    ['Gastroenterología', 'Sistema digestivo', 1, 'Análisis de laboratorio, Ecografía abdominal'],
                    ['Neumología', 'Sistema respiratorio', 1, 'Radiografía de tórax, Espirometría'],
                    ['Cirugía General', 'Procedimientos quirúrgicos generales', 1, 'Análisis prequirúrgicos completos']
                ];
                
                $stmtEsp = $pdo->prepare("INSERT INTO especialidades (nombre, descripcion, requiere_estudios_previos, estudios_requeridos) VALUES (?, ?, ?, ?)");
                
                foreach ($especialidades as $esp) {
                    $stmtEsp->execute($esp);
                }
            }
            
            // Verificar centros de salud
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM centros_salud");
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                // Insertar centros de salud
                $centros = [
                    ['Hospital Dr. Marcial Quiroga', 'publico', 'Av. Córdoba 2150, San Juan', '0264-4221234', '0800-555-0001'],
                    ['Hospital Rawson', 'publico', 'Av. San Martín 890, Rawson', '0264-4223456', '0800-555-0002'],
                    ['Clínica Privada Médica', 'privado', 'Calle Mendoza 567, San Juan', '0264-4225678', '0800-555-0003'],
                    ['Centro de Salud Zonal Norte', 'publico', 'Av. Libertador 1234, San Juan', '0264-4224567', '0800-555-0004']
                ];
                
                $stmtCentro = $pdo->prepare("INSERT INTO centros_salud (nombre, tipo, direccion, telefono, contacto_0800) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($centros as $centro) {
                    $stmtCentro->execute($centro);
                }
            }
            
            $pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error insertando datos iniciales: " . $e->getMessage());
            throw new Exception("Error al insertar datos iniciales: " . $e->getMessage());
        }
    }
    
    /**
     * Ejecutar la instalación completa de la integración
     */
    public function instalarIntegracionCompleta() {
        try {
            // 1. Crear tablas nuevas
            $this->crearTablasIntegracion();
            
            // 2. Modificar tablas existentes
            $this->modificarTablasExistentes();
            
            // 3. Crear vistas
            $this->crearVistasIntegracion();
            
            // 4. Insertar datos iniciales
            $this->insertarDatosIniciales();
            
            return [
                'success' => true,
                'message' => 'Integración SPP-Sanidad completada exitosamente',
                'verificacion' => $this->verificarTablasExistentes()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en la integración: ' . $e->getMessage(),
                'verificacion' => $this->verificarTablasExistentes()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de la base de datos integrada
     */
    public function obtenerEstadisticasIntegracion() {
        $pdo = $this->connect();
        
        try {
            $stats = [];
            
            // Estadísticas de internos
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM vista_internos_sanidad WHERE estado_interno = 'activo'");
            $stats['internos_activos'] = $stmt->fetchColumn();
            
            // Estadísticas de turnos
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM turnos_medicos WHERE estado IN ('solicitado', 'confirmado')");
            $stats['turnos_pendientes'] = $stmt->fetchColumn();
            
            // Estadísticas de autorizaciones
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM autorizaciones_medicas WHERE estado = 'pendiente'");
            $stats['autorizaciones_pendientes'] = $stmt->fetchColumn();
            
            // Traslados de hoy
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM traslados_medicos WHERE DATE(fecha_traslado) = CURDATE()");
            $stats['traslados_hoy'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Método para debugging - mostrar estructura de tablas
     */
    public function mostrarEstructuraTablas($tabla = null) {
        $pdo = $this->connect();
        
        $tablasCheck = $tabla ? [$tabla] : [
            'persona', 'juzgado', 'especialidades', 'centros_salud', 
            'turnos_medicos', 'autorizaciones_medicas', 'traslados_medicos',
            'informes_medicos', 'estudios_medicos', 'historial_medico'
        ];
        
        $estructura = [];
        
        foreach ($tablasCheck as $t) {
            try {
                $stmt = $pdo->query("DESCRIBE `$t`");
                $estructura[$t] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $estructura[$t] = "Tabla no existe: " . $e->getMessage();
            }
        }
        
        return $estructura;
    }
}

// Clase auxiliar para manejo de errores específicos de la integración
class DatabaseIntegrationException extends Exception {
    private $context;
    
    public function __construct($message, $context = [], $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    public function getContext() {
        return $this->context;
    }
}