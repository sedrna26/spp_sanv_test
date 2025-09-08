<?php
// config/database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'sistema_sanidad';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function connect() {
        if ($this->pdo === null) {
            try {
                $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname};charset=utf8", 
                                   $this->username, $this->password);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Error de conexión: " . $e->getMessage());
            }
        }
        return $this->pdo;
    }
    
    public function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS juzgados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            tipo ENUM('civil', 'federal', 'penal') NOT NULL,
            requiere_expediente BOOLEAN DEFAULT FALSE,
            formato_autorizacion TEXT,
            contacto VARCHAR(255),
            email VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS internos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dni VARCHAR(20) UNIQUE NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            fecha_nacimiento DATE,
            juzgado_id INT,
            numero_expediente VARCHAR(100),
            tiene_obra_social BOOLEAN DEFAULT FALSE,
            obra_social VARCHAR(100),
            estado ENUM('activo', 'trasladado', 'liberado') DEFAULT 'activo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (juzgado_id) REFERENCES juzgados(id)
        );
        
        CREATE TABLE IF NOT EXISTS especialidades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            descripcion TEXT,
            requiere_estudios_previos BOOLEAN DEFAULT FALSE,
            estudios_requeridos TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS centros_salud (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            tipo ENUM('publico', 'privado') NOT NULL,
            direccion TEXT,
            telefono VARCHAR(50),
            contacto_0800 VARCHAR(50),
            especialidades_disponibles TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS turnos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            interno_id INT NOT NULL,
            especialidad_id INT NOT NULL,
            centro_salud_id INT,
            fecha_solicitada DATE,
            fecha_turno DATETIME,
            prioridad ENUM('normal', 'prioritario', 'urgente', 'ingreso') DEFAULT 'normal',
            estado ENUM('solicitado', 'pendiente_estudios', 'confirmado', 'realizado', 'cancelado', 'reprogramado') DEFAULT 'solicitado',
            observaciones TEXT,
            estudios_realizados BOOLEAN DEFAULT FALSE,
            requiere_autorizacion BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (interno_id) REFERENCES internos(id),
            FOREIGN KEY (especialidad_id) REFERENCES especialidades(id),
            FOREIGN KEY (centro_salud_id) REFERENCES centros_salud(id)
        );
        
        CREATE TABLE IF NOT EXISTS estudios_medicos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turno_id INT NOT NULL,
            tipo_estudio VARCHAR(100) NOT NULL,
            fecha_realizado DATE,
            resultado TEXT,
            archivo_qr VARCHAR(255),
            estado ENUM('pendiente', 'realizado', 'con_problema') DEFAULT 'pendiente',
            observaciones TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (turno_id) REFERENCES turnos(id)
        );
        
        CREATE TABLE IF NOT EXISTS autorizaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turno_id INT NOT NULL,
            juzgado_id INT NOT NULL,
            numero_autorizacion VARCHAR(100),
            fecha_solicitud DATE NOT NULL,
            fecha_autorizacion DATE,
            estado ENUM('pendiente', 'autorizado', 'rechazado') DEFAULT 'pendiente',
            archivo_pdf VARCHAR(255),
            observaciones TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (turno_id) REFERENCES turnos(id),
            FOREIGN KEY (juzgado_id) REFERENCES juzgados(id)
        );
        
        CREATE TABLE IF NOT EXISTS traslados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turno_id INT NOT NULL,
            fecha_traslado DATETIME NOT NULL,
            destino VARCHAR(255) NOT NULL,
            responsable_traslado VARCHAR(100),
            estado ENUM('programado', 'en_curso', 'realizado', 'cancelado') DEFAULT 'programado',
            motivo_cancelacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (turno_id) REFERENCES turnos(id)
        );
        
        CREATE TABLE IF NOT EXISTS informes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            turno_id INT,
            interno_id INT NOT NULL,
            tipo_informe ENUM('atencion', 'estudio', 'reprogramacion', 'acta_novedad') NOT NULL,
            contenido TEXT NOT NULL,
            fecha_informe DATE NOT NULL,
            archivo_adjunto VARCHAR(255),
            enviado_juzgado BOOLEAN DEFAULT FALSE,
            fecha_envio_juzgado DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (turno_id) REFERENCES turnos(id),
            FOREIGN KEY (interno_id) REFERENCES internos(id)
        );
        
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            rol ENUM('admin', 'medico', 'administrativo') DEFAULT 'administrativo',
            email VARCHAR(100),
            activo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        ";
        
        $this->connect()->exec($sql);
    }
}
