<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Check rate limit
    checkRateLimit();
    
    // Get the POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['message'])) {
        throw new Exception('Message is required');
    }
    
    $message = $data['message'];
    $context = $data['context'] ?? [];
    $fileContent = $data['fileContent'] ?? null;
    
    // Prepare messages for API
    $messages = [
        [
            'role' => 'system',
            'content' => DEFAULT_SYSTEM_PROMPT
        ]
    ];
    
    // Add context messages if any
    foreach ($context as $msg) {
        $messages[] = $msg;
    }
    
    // If file content is provided, include it in the message
    if ($fileContent) {
        $message = "Please analyze the following file content:\n\n" . $fileContent . "\n\n" . $message;
    }
    
    // Add the current message
    $messages[] = [
        'role' => 'user',
        'content' => $message
    ];
    
    // Call AI API
    $response = callAI($messages);
    
    // Extract the response
    $aiResponse = $response['choices'][0]['message']['content'];
    
    // Save chat history (optional, for future implementation)
    // saveChatHistory(session_id(), $message, $aiResponse);
    
    // Return the response
    echo json_encode([
        'response' => $aiResponse,
        'model' => $response['model'] ?? DEFAULT_MODEL,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 