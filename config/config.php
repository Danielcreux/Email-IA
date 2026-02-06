<?php
require_once 'constants.php';
require_once 'ionos-functions.php'; 

// Carpetas permitidas 
$allowedFolders = [
    'INBOX'              => 'Recibidos',
    'Elementos enviados' => 'Enviados',      // Nombre real de IONOS
    'Borradores'         => 'Borradores',    // Nombre real de IONOS
    'Papelera'           => 'Papelera',      // Nombre real de IONOS
    'Spam'               => 'Spam'           // Opcional
];

// Configuración de la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true);
}

// Configurar manejo de errores
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);