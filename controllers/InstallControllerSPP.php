<?php
// controllers/InstallControllerSPP.php - Controlador para instalación
class InstallControllerSPP {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Verificar estado de la instalación
     */
    public function verificarInstalacion() {
        try {
            $verificacion = $this->db->verificarTablasExistentes();
            
            $estado = [
                'base_datos_conectada' => true,
                'tablas_creadas' => $verificacion['completitud'] == 100,
                'completitud' => $verificacion['completitud'],
                'tablas_existentes' => $verificacion['existentes'],
                'tablas_faltantes' => $verificacion['faltantes']
            ];
            
            // Verificar datos iniciales
            $pdo = $this->db->connect();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM especialidades");
            $estado['especialidades_cargadas'] = $stmt->fetchColumn() > 0;
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM centros_salud");
            $estado['centros_salud_cargados'] = $stmt->fetchColumn() > 0;
            
            $estado['instalacion_completa'] = $estado['tablas_creadas'] && 
                                            $estado['especialidades_cargadas'] && 
                                            $estado['centros_salud_cargados'];
            
            return $estado;
            
        } catch (Exception $e) {
            return [
                'base_datos_conectada' => false,
                'error' => $e->getMessage(),
                'instalacion_completa' => false
            ];
        }
    }
    
    /**
     * Ejecutar instalación completa
     */
    public function ejecutarInstalacion() {
        try {
            $resultado = $this->db->instalarIntegracionCompleta();
            
            if ($resultado['success']) {
                // Crear usuario administrador por defecto si no existe
                $this->crearUsuarioAdmin();
                
                // Verificar instalación
                $verificacion = $this->verificarInstalacion();
                
                return array_merge($resultado, ['verificacion_final' => $verificacion]);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Error en instalación completa: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error durante la instalación: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear usuario administrador para sanidad si no existe
     */
    private function crearUsuarioAdmin() {
        try {
            $pdo = $this->db->connect();
            
            // Verificar si existe un usuario admin para sanidad
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM usuarios 
                WHERE nombre_usuario = 'admin_sanidad' OR correo LIKE '%sanidad%'
            ");
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                // Crear usuario admin para sanidad
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (id_persona, id_rol, nombre_usuario, contrasena, correo, activo)
                    VALUES (1, 1, 'admin_sanidad', :password, 'admin.sanidad@sistema.local', 1)
                ");
                
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt->execute(['password' => $password]);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error creando usuario admin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Migrar datos existentes si es necesario
     */
    public function migrarDatos() {
        try {
            $pdo = $this->db->connect();
            $migraciones = [];
            
            // Migración 1: Actualizar campos médicos en persona si están vacíos
            $stmt = $pdo->exec("
                UPDATE persona SET 
                    tipo_sangre = 'O+',
                    ultima_revision_medica = DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                WHERE tipo_sangre IS NULL
                AND id IN (
                    SELECT DISTINCT id_ppl FROM situacionlegal WHERE situacionlegal = 'Procesado'
                )
                LIMIT 10
            ");
            $migraciones['personas_actualizadas'] = $stmt;
            
            // Migración 2: Crear turnos de ejemplo si no hay datos
            $stmt = $pdo->query("SELECT COUNT(*) FROM turnos_medicos");
            if ($stmt->fetchColumn() == 0) {
                $this->crearTurnosEjemplo();
                $migraciones['turnos_ejemplo_creados'] = true;
            }
            
            return [
                'success' => true,
                'migraciones' => $migraciones,
                'message' => 'Migración de datos completada'
            ];
            
        } catch (Exception $e) {
            error_log("Error en migración de datos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error durante la migración: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear algunos turnos de ejemplo para testing
     */
    private function crearTurnosEjemplo() {
        try {
            $pdo = $this->db->connect();
            
            // Obtener IDs necesarios
            $stmt = $pdo->query("SELECT id FROM persona WHERE id > 0 LIMIT 1");
            $personaId = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT id FROM especialidades WHERE nombre = 'Clínica Médica' LIMIT 1");
            $especialidadId = $stmt->fetchColumn();
            
            if ($personaId && $especialidadId) {
                $stmt = $pdo->prepare("
                    INSERT INTO turnos_medicos (
                        id_ppl, especialidad_id, fecha_solicitada, prioridad,
                        observaciones, motivo_consulta
                    ) VALUES 
                    (?, ?, CURDATE(), 'normal', 'Turno de ejemplo - revisión general', 'Control médico general'),
                    (?, ?, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'prioritario', 'Turno de ejemplo - seguimiento', 'Control de seguimiento')
                ");
                
                $stmt->execute([$personaId, $especialidadId, $personaId, $especialidadId]);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error creando turnos de ejemplo: " . $e->getMessage());
            return false;
        }
    }
}