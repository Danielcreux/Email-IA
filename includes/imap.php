<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

// ============================================
// IMAP helpers
// ============================================
function decode_imap_text(string $text, int $encoding): string
{
    switch ($encoding) {
        case 3: return base64_decode($text) ?: $text;
        case 4: return quoted_printable_decode($text);
        default: return $text;
    }
}

function get_plain_text_body($imap, int $msgNo): string
{
    $structure = @imap_fetchstructure($imap, $msgNo);
    if (!$structure) return (string)@imap_body($imap, $msgNo);

    if (!isset($structure->parts) || !is_array($structure->parts) || count($structure->parts) === 0) {
        $body = @imap_body($imap, $msgNo);
        return decode_imap_text($body, $structure->encoding ?? 0);
    }

    $partNumber = null;
    foreach ($structure->parts as $index => $part) {
        if ($part->type == 0) {
            $subtype = isset($part->subtype) ? strtoupper($part->subtype) : '';
            if ($subtype === 'PLAIN' || $subtype === '') {
                $partNumber = $index + 1;
                break;
            }
        }
    }

    if ($partNumber === null) {
        $body = @imap_body($imap, $msgNo);
        return decode_imap_text($body, $structure->encoding ?? 0);
    }

    $partBody = @imap_fetchbody($imap, $msgNo, (string)$partNumber);
    $encoding = $structure->parts[$partNumber - 1]->encoding ?? 0;

    return decode_imap_text($partBody, $encoding);
}

function getFolderCounts(): array
{
    global $allowedFolders;
    $folderCounts = array_fill_keys(array_keys($allowedFolders), 0);
    
    if (!function_exists('imap_open')) {
        return $folderCounts;
    }

    foreach ($allowedFolders as $fKey => $_label) {
        $mb = sprintf('{%s:%d%s}%s', IMAP_HOST, IMAP_PORT, IMAP_FLAGS, $fKey);
        
        // Intentar abrir la carpeta
        $tmp = @imap_open($mb, SMTP_USER, SMTP_PASS, OP_READONLY | OP_SILENT, 1);
        
        if ($tmp) {
            $chk = @imap_check($tmp);
            if ($chk && isset($chk->Nmsgs)) {
                $folderCounts[$fKey] = (int)$chk->Nmsgs;
            }
            @imap_close($tmp);
        } else {
            // Debug: registrar error
            error_log("No se pudo abrir carpeta IMAP: $fKey - " . imap_last_error());
        }
        
        // Limpiar errores después de cada intento
        imap_errors();
        imap_alerts();
    }

    return $folderCounts;
}

function fetchEmails(string $folder, int $limit = MAX_EMAILS_DISPLAY): array
{
    $emails = [];
    
    if (!function_exists('imap_open')) {
        return $emails;
    }

    // IMPORTANTE: Asegurarse de que la carpeta esté normalizada
    require_once __DIR__ . '/../config/ionos-functions.php';
    $folder = normalizeIonosFolderName($folder);
    
    $mailboxString = sprintf('{%s:%d%s}%s', IMAP_HOST, IMAP_PORT, IMAP_FLAGS, $folder);
    
    // Debug: registrar intento de conexión
    error_log("Intentando abrir carpeta IMAP: $mailboxString");
    
    // Intentar abrir con opciones simples primero
    $imap = @imap_open($mailboxString, SMTP_USER, SMTP_PASS, OP_READONLY, 1);
    
    if (!$imap) {
        // Intentar sin timeout
        $imap = @imap_open($mailboxString, SMTP_USER, SMTP_PASS, OP_READONLY, 0);
        
        if (!$imap) {
            error_log("ERROR IMAP: No se pudo abrir carpeta '$folder' - " . imap_last_error());
            // Limpiar errores
            imap_errors();
            imap_alerts();
            return $emails;
        }
    }
    
    // Obtener información de la carpeta
    $check = @imap_check($imap);
    if (!$check) {
        error_log("ERROR: No se pudo verificar carpeta '$folder'");
        @imap_close($imap);
        return $emails;
    }
    
    $totalMessages = $check->Nmsgs;
    error_log("Carpeta '$folder' tiene $totalMessages mensajes");
    
    if ($totalMessages === 0) {
        @imap_close($imap);
        return $emails;
    }
    
    // Obtener los IDs de los mensajes (más recientes primero)
    $ids = @imap_search($imap, 'ALL');
    
    if (!$ids || !is_array($ids)) {
        error_log("ERROR: No se pudieron obtener IDs de mensajes en '$folder'");
        @imap_close($imap);
        return $emails;
    }
    
    // Ordenar de más reciente a más antiguo
    rsort($ids);
    
    // Limitar el número de mensajes
    $ids = array_slice($ids, 0, min($limit, count($ids)));
    
    foreach ($ids as $msgNo) {
        try {
            // Obtener información básica del mensaje
            $overview = @imap_fetch_overview($imap, $msgNo, 0);
            
            if (!$overview || !isset($overview[0])) {
                continue;
            }
            
            $msg = $overview[0];
            
            // Decodificar asunto y remitente
            $subject = isset($msg->subject) ? imap_utf8($msg->subject) : '(sin asunto)';
            $from = isset($msg->from) ? imap_utf8($msg->from) : '(desconocido)';
            
            // Formatear fecha
            $date = isset($msg->date) ? date('d/m/Y H:i', strtotime($msg->date)) : '';
            
            $emails[] = [
                'num'      => $msgNo,
                'subject'  => $subject,
                'from'     => $from,
                'date'     => $date,
                'seen'     => !empty($msg->seen),
                'from_raw' => $msg->from ?? '',
                'subject_raw' => $msg->subject ?? ''
            ];
            
        } catch (Exception $e) {
            error_log("Error procesando mensaje $msgNo: " . $e->getMessage());
            continue;
        }
    }
    
    @imap_close($imap);
    
    // Limpiar buffer de errores
    imap_errors();
    imap_alerts();
    
    error_log("Se cargaron " . count($emails) . " mensajes de '$folder'");
    
    return $emails;
}

function getEmailDetails($imap, int $msgNo): ?array
{
    $overviewArr = @imap_fetch_overview($imap, $msgNo, 0);
    if (!$overviewArr || !isset($overviewArr[0])) {
        return null;
    }

    $ov = $overviewArr[0];
    return [
        'num'      => $msgNo,
        'subject'  => isset($ov->subject) ? imap_utf8($ov->subject) : '(sin asunto)',
        'from'     => isset($ov->from) ? imap_utf8($ov->from) : '(desconocido)',
        'date'     => isset($ov->date) ? date('d/m/Y H:i', strtotime($ov->date)) : '',
        'from_raw' => $ov->from ?? '',
        'subject_raw' => $ov->subject ?? ''
    ];
}

    