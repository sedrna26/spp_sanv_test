<?php
// index.php - Punto de entrada
session_start();

// Incluir clases base
require_once 'config/database.php';
require_once 'router.php';

// Incluir controladores
require_once 'controllers/TurnoController.php';
require_once 'controllers/ReporteController.php';

// Incluir modelos
require_once 'models/Turno.php';
require_once 'models/Interno.php';
require_once 'models/Autorizacion.php'; // Agregamos el modelo Autorizacion
require_once 'models/Informe.php';      // También incluimos Informe por si se necesita

// Incluir vistas
require_once 'views/dashboard.php';
require_once 'views/turnos.php';

try {
    $router = new Router();
    $router->handleRequest();
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}