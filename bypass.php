<?php
// This is a special bypass endpoint to handle requests without prompt training
// Force error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly, capture them in the JSON response

// Include configuration file
require_once 'config.php';

// Ensure we always return JSON no matter what
header('Content-Type: application/json');

// Start output buffering to capture any unwanted output
ob_start();

try {
    // Get the POST data
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No input data received');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    if (!isset($data['message']) || !isset($data['model'])) {
        throw new Exception('Message and model are required');
    }
    
    $message = $data['message'];
    $model = $data['model'];
    $context = $data['context'] ?? [];
    
    // Create messages array with context
    $messages = [
        ['role' => 'system', 'content' => DEFAULT_SYSTEM_PROMPT]
    ];
    
    // Add context messages
    if (!empty($context) && is_array($context)) {
        foreach ($context as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = $msg;
            }
        }
    }
    
    // Add current message
    $messages[] = ['role' => 'user', 'content' => $message];
    
    // Try multiple methods to access OpenRouter API
    $methods = [
        // First attempt: Try with prompt_training=false
        [
            'url' => 'https://openrouter.ai/api/v1/chat/completions?prompt_training=false',
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'HTTP-Referer: https://jeetultimateai.com',
                'X-Title: ' . APP_NAME,
                'X-Prompt-Training: false'
            ],
            'data' => [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'stream' => false,
                'prompt_training' => false
            ]
        ],
        // For Optimus Alpha, try specific config
        [
            'url' => 'https://openrouter.ai/api/v1/chat/completions',
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'HTTP-Referer: https://jeetultimateai.com',
                'X-Title: ' . APP_NAME,
            ],
            'data' => [
                'model' => 'openrouter/optimus-alpha', // Force model
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'stream' => false,
                'route' => 'openrouter/optimus-alpha' // Force direct routing
            ]
        ],
        // Second attempt: Standard endpoint
        [
            'url' => 'https://openrouter.ai/api/v1/chat/completions',
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'HTTP-Referer: https://jeetultimateai.com',
                'X-Title: ' . APP_NAME
            ],
            'data' => [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'stream' => false
            ]
        ],
        // Fallback to reliable model
        [
            'url' => 'https://openrouter.ai/api/v1/chat/completions?route=fallback',
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'HTTP-Referer: https://jeetultimateai.com',
                'X-Title: ' . APP_NAME
            ],
            'data' => [
                'model' => 'google/gemini-pro', // Fallback to reliable model
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 800,
                'stream' => false
            ]
        ]
    ];
    
    $success = false;
    $response = null;
    $httpCode = 0;
    $errorMessage = '';
    
    // Try each method until one works
    foreach ($methods as $method) {
        try {
            $ch = curl_init($method['url']);
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($method['data']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $method['headers']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $errorMessage = 'cURL error: ' . curl_error($ch);
                curl_close($ch);
                continue; // Try next method
            }
            
            curl_close($ch);
            
            // Check if we got a successful response
            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                
                // Validate response structure
                if (isset($responseData['choices'][0]['message']['content'])) {
                    $success = true;
                    break; // We found a working method
                } else {
                    $errorMessage = 'Invalid response structure';
                    continue; // Try next method
                }
            } else {
                $errorMessage = 'HTTP error: ' . $httpCode;
                continue; // Try next method
            }
        } catch (Exception $e) {
            $errorMessage = 'Exception: ' . $e->getMessage();
            continue; // Try next method
        }
    }
    
    // If none of the methods worked, throw an exception
    if (!$success) {
        throw new Exception('All API methods failed. Last error: ' . $errorMessage);
    }
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    // Parse the response
    $responseData = json_decode($response, true);
    
    // Extract the content
    $aiResponse = $responseData['choices'][0]['message']['content'];
    
    // Get model info if available
    $usedModel = $responseData['model'] ?? $model;
    
    // Return a success response
    echo json_encode([
        'response' => $aiResponse,
        'model' => $usedModel,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Clean any output that might have been generated
    ob_end_clean();
    
    // Return a proper error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Bypass error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?> 