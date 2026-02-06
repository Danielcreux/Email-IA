<?php
// includes/ai-processor.php
require_once __DIR__ . '/../config/constants.php'; 
require_once __DIR__ . '/functions.php';

// ============================================
// FUNCIÓN BASE PARA LLAMADAS A LA API
// ============================================
function callAIAPI(string $question): array
{
    global $API_URL, $API_KEY;
    
    if (!isset($API_URL) || !isset($API_KEY) || empty($API_URL) || empty($API_KEY)) {
        return ['ok' => false, 'error' => 'Configuración de API no definida'];
    }
    
    // DEBUG: Log la petición
    error_log("API Call: $API_URL - Question: " . substr($question, 0, 100));
    
    $ch = curl_init($API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'question' => $question,
            'api_key'  => $API_KEY
        ]),       
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],

        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // DEBUG: Log la respuesta
    error_log("API Response - Status: $status, Response: " . substr($response ?? '', 0, 200));
    
    if ($error) {
        return ['ok' => false, 'error' => "Error cURL: $error"];
    }
    
    if ($status !== 200) {
        return ['ok' => false, 'error' => "HTTP $status - " . ($response ?: 'Sin respuesta')];
    }
    
    if (empty($response) || trim($response) === '') {
        return ['ok' => false, 'error' => "Respuesta vacía de la API"];
    }
    // Eliminar cualquier HTML accidental
    $response = trim($response);

    // Si empieza por < → es HTML → error
    if ($response !== '' && $response[0] === '<') {
        error_log('Respuesta HTML inesperada de la API: ' . substr($response, 0, 200));
        return ['ok' => false, 'error' => 'Respuesta no válida del servidor IA'];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg() . " - Raw: " . $response);
        return ['ok' => false, 'error' => "Error JSON: " . json_last_error_msg()];
    }
    
    if (!isset($data['answer']) || !is_string($data['answer'])) {
        return ['ok' => false, 'error' => "Respuesta inválida - falta campo 'answer'"];
    }
    
    return ['ok' => true, 'texto' => trim($data['answer'])];
}