<?php
// ============================================
// CONFIGURACIÓN SMTP
// ============================================
define('SMTP_HOST', 'smtp.ionos.es');
define('SMTP_PORT', 587);
define('SMTP_USER', 'Tucorreo');
define('SMTP_PASS', 'tucontrasena');
define('SMTP_SECURE', 'tls'); 
define('SMTP_FROM', 'Tucorreo');
define('SMTP_FROM_NAME', 'tu nombre');

// ============================================
// CONFIGURACIÓN IMAP
// ============================================
define('IMAP_HOST', 'imap.ionos.es');
define('IMAP_PORT', 993);
define('IMAP_FLAGS', '/imap/ssl');

// ============================================
// CONFIGURACIÓN DE LA API REMOTA (IA)
// ============================================
$API_URL = "https://tu url.ngrok-free.dev/api.php";
$API_KEY = "claveapi";

// ============================================
// CONFIGURACIÓN GENERAL
// ============================================
define('MAX_EMAILS_DISPLAY', 80);
define('HEARTBEAT_INTERVAL', 3000); // ms

// ============================================
// BASE URL FIX - PARA PROBLEMA DE ENLACES
// ============================================
define('BASE_URL', dirname($_SERVER['PHP_SELF']));
?>