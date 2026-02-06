<?php
// ============================================
// FUNCIONES DE UTILIDAD
// ============================================
function safeHtml($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// FUNCIÓN CORREGIDA PARA CREAR URLS SEGURAS
function buildUrl($params = []) {
    $base = 'index.php';
    $defaultFolder = isset($params['folder']) ? $params['folder'] : 'INBOX';
    
    // Normalizar carpeta
    $params['folder'] = normalizeIonosFolderName($defaultFolder);
    
    // Codificar correctamente todos los parámetros
    $queryParts = [];
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $queryParts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
    }
    
    return $base . (count($queryParts) ? '?' . implode('&', $queryParts) : '');
}

// FUNCIÓN PARA ENLACES DE EMAIL - CORREGIDA
function getEmailLink($folder, $msgNo, $additionalParams = []) {
    $params = array_merge(['folder' => $folder, 'msg' => $msgNo], $additionalParams);
    return buildUrl($params);
}

// FUNCIÓN PARA ENLACES DE CARPETA - CORREGIDA
function getFolderLink($folder) {
    return buildUrl(['folder' => $folder]);
}

function getCurrentFolder() {
    global $allowedFolders;
    $folder = $_GET['folder'] ?? 'INBOX';
    
    // Normalizar el nombre de la carpeta para IONOS
    require_once __DIR__ . '/../config/ionos-functions.php';
    $folder = normalizeIonosFolderName($folder);
    
    // Verificar que la carpeta existe en allowedFolders
    return isset($allowedFolders[$folder]) ? $folder : 'INBOX';
}

function isComposeMode() {
    return isset($_GET['compose']) && $_GET['compose'] === '1';
}

function getSelectedMessage() {
    return isset($_GET['msg']) ? (int)$_GET['msg'] : null;
}

function isReplyMode($selectedEmail) {
    return ($selectedEmail !== null) && isset($_GET['reply']) && $_GET['reply'] === '1';
}

// ============================================
// FORMATEO DEL CUERPO COMO CORREO ELEGANTE
// ============================================
function ajustarFormatoEmail(string $texto): string {
    if (empty(trim($texto))) return '';
    
    $texto = preg_replace("/\r\n|\r/", "\n", trim($texto));
    $lineas = explode("\n", $texto);
    
    // Limpiar líneas vacías múltiples
    $resultado = [];
    $vacias = 0;
    foreach ($lineas as $linea) {
        $trimmed = trim($linea);
        if ($trimmed === '') {
            $vacias++;
            if ($vacias > 1) continue;
        } else {
            $vacias = 0;
        }
        $resultado[] = $linea;
    }
    
    return implode("\n", $resultado);
}

// ============================================
// FUNCIÓN MEJORADA PARA RESUMEN DE EMAIL
// ============================================
function procesar_resumen_email(string $texto): array {
    $texto = trim($texto);
    if (empty($texto)) {
        return ['ok' => false, 'error' => 'Texto vacío', 'texto' => ''];
    }
    
    // PROMPT MEJORADO PARA RESUMEN
    $prompt = "Como asistente ejecutivo, crea un resumen EXCELENTE de este email con estas características:
    1. EXTREMADAMENTE BREVE: 2-3 frases máximo
    2. CLARO Y DIRECTIVO: Destaca los puntos clave
    3. ESTRUCTURADO: Puntos principales primero
    4. UTIL: Que permita entender el contenido sin leer todo
    5. EN ESPAÑOL: Lenguaje natural
    
    Contenido del email: " . substr($texto, 0, 1500);
    
    return callAIAPI($prompt);
}

// ============================================
// MANEJAR ENVÍO DE EMAIL
// ============================================
function handleEmailSend() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email']) && $_POST['send_email'] === '1') {
        $to = $_POST['to'] ?? '';
        $subject = $_POST['final_subject'] ?? '';
        $body = $_POST['final_body'] ?? '';

        $subject = trim($subject);
        $body = trim($body);

        if ($to === '' || $subject === '' || $body === '') {
            return [
                'ok' => false,
                'message' => 'Para, asunto y cuerpo son obligatorios.'
            ];
        } else {
            require_once __DIR__ . '/smtp.php';
            $result = smtp_send_email($to, $subject, $body);
            return [
                'ok' => $result['ok'],
                'message' => $result['ok']
                    ? '✓ Correo enviado correctamente a ' . safeHtml($to) . '.'
                    : '✗ Error al enviar: ' . $result['error']
            ];
        }
    }
    return null;
}

// ============================================
// FUNCIONES DE VALIDACIÓN DE EMAIL
// ============================================
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeEmail($email) {
    return trim(filter_var($email, FILTER_SANITIZE_EMAIL));
}

// ============================================
// FUNCIONES DE SEGURIDAD DE URL
// ============================================
function safeRedirect($url) {
    // Validar que la URL sea interna
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        header('Location: ' . $url);
        exit;
    }
}
?>