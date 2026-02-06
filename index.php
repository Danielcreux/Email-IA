<?php

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);



// Habilitar buffer de salida
ob_start();

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/imap.php';
require_once 'includes/smtp.php';
require_once 'includes/ai-processor.php';




// ============================================
// OBTENER DATOS PRINCIPALES
// ============================================
$folder = getCurrentFolder();
$selectedMsgNo = getSelectedMessage();

// Inicializar variables
$imapError = '';
$emails = [];
$selectedEmail = null;
$selectedBody = '';
$selectedSummary = '';
$folderCounts = [];

// Cargar emails si IMAP está disponible
if (function_exists('imap_open')) {
    try {
        // Obtener lista de emails
        $emails = fetchEmails($folder);
        $folderCounts = getFolderCounts();
        
        // Obtener detalles del email seleccionado
        if ($selectedMsgNo) {
            $mailboxString = sprintf('{%s:%d%s}%s', IMAP_HOST, IMAP_PORT, IMAP_FLAGS, $folder);
            $imap = @imap_open($mailboxString, SMTP_USER, SMTP_PASS, OP_READONLY, 1);
            
            if ($imap) {
                $selectedEmail = getEmailDetails($imap, $selectedMsgNo);
                if ($selectedEmail) {
                    $selectedBody = get_plain_text_body($imap, $selectedMsgNo);
                    
                    // Generar resumen si hay contenido
                    if (!empty(trim($selectedBody))) {
                        $resumen = procesar_resumen_email($selectedBody);
                        $selectedSummary = $resumen['ok'] ? $resumen['texto'] : '';
                    }
                }
                @imap_close($imap);
            }
        }
    } catch (Exception $e) {
        $imapError = 'Error al cargar mensajes: ' . $e->getMessage();
        error_log("IMAP Exception: " . $e->getMessage());
        $folderCounts = array_fill_keys(array_keys($allowedFolders), 0);
    }
} else {
    $imapError = '✗ La extensión IMAP de PHP no está disponible.';
    $folderCounts = array_fill_keys(array_keys($allowedFolders), 0);
}

// Determinar modos
$composeMode = isset($_GET['compose']) && $_GET['compose'] === '1';
$replyMode = $selectedEmail && isset($_GET['reply']) && $_GET['reply'] === '1';

// Manejar envío de correo
$sendResult = handleEmailSend();
$sendMessage = $sendResult['message'] ?? null;
$sendOk = $sendResult['ok'] ?? null;

// Prellenar campos para respuesta
$prefillTo = '';
$prefillSubjectRaw = '';
$prefillSubjectFinal = '';

if ($selectedEmail && $replyMode) {
    $fromRaw = $selectedEmail['from_raw'];
    $addrList = @imap_rfc822_parse_adrlist($fromRaw, '');
    if ($addrList && isset($addrList[0]->mailbox, $addrList[0]->host)) {
        $prefillTo = $addrList[0]->mailbox . '@' . $addrList[0]->host;
    } else {
        $prefillTo = $selectedEmail['from'];
    }
    $prefillSubjectRaw = 'Re: ' . $selectedEmail['subject'];
    $prefillSubjectFinal = $prefillSubjectRaw;
}

// Limpiar buffer
ob_end_clean();

// ============================================
// RENDERIZAR PÁGINA
// ============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail IA - Correo Empresarial</title>
    <link rel="stylesheet" href="styles/gmail-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<div id="container">
    <?php include 'templates/nav.php'; ?>
    <?php include 'templates/email-list.php'; ?>
    <?php include 'templates/content.php'; ?>
</div>

</body>
</html>