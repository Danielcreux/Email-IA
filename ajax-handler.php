<?php
// ajax-handler.php - Simplificado
require_once 'config/config.php';
require_once 'includes/ai-processor.php';
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    $kind = $_POST['kind'] ?? 'body';
    $textoOriginal = isset($_POST['texto']) ? trim($_POST['texto']) : '';
    
    if ($textoOriginal === '') {
        echo json_encode(['ok' => false, 'error' => 'Texto vacío', 'texto' => ''], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Llamar a la API de IA
    if ($kind === 'subject') {
        // PROMPT MEJORADO PARA ASUNTO
        $prompt = "Crea un asunto de email profesional en español: 
        1) Muy corto, sin la palabra asunto:(3-5 palabras máximo)
        2) Claro y directo
        3) Atractivo para abrir
        4) Resuma esto: " . $textoOriginal;
        
        $resultado = callAIAPI($prompt);
        
        // Asegurar que sea corto
        if ($resultado['ok']) {
            $palabras = preg_split('/\s+/', trim($resultado['texto']));
            if (count($palabras) > 5) {
                $resultado['texto'] = implode(' ', array_slice($palabras, 0, 5));
            }
        }
    } else {
        // PROMPT MEJORADO PARA CUERPO
        $prompt = "Redacta este mensaje como un email profesional perfecto en español con estas características:
        1) Lenguaje claro en primera persona y directo
        2) Gramática perfecta
        3) Estructura: saludo + contenido + despedida
        4) Tono profesional pero amigable
        5) Párrafos cortos
        6) Incluye esto: " . $textoOriginal;
        
        $resultado = callAIAPI($prompt);
        
        if ($resultado['ok']) {
            $resultado['texto'] = ajustarFormatoEmail($resultado['texto']);
        }
    }
    
    if ($resultado['ok']) {
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'ok' => false, 
            'error' => $resultado['error'],
            'texto' => $textoOriginal
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>