<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// OpenRouter API Configuration
define('OPENROUTER_API_KEY', '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');
define('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1/chat/completions');
define('OPENROUTER_DIRECT_API_URL', 'https://openrouter.ai/api/v1/chat/completions?prompt_training=false'); // Direct access mode is always enabled

// Application settings
define('APP_NAME', 'Jeet\'s Super AI');
define('DEFAULT_MODEL', 'anthropic/claude-3-haiku'); // Claude is more reliable as default
define('FALLBACK_MODEL', 'anthropic/claude-3-haiku'); // Reliable fallback model for when others fail
define('DEFAULT_MODEL_DISPLAY_NAME', 'Claude'); // Display name for default model
define('USE_DIRECT_MODE', true); // Direct mode is always enabled - no toggle needed
define('DEFAULT_SYSTEM_PROMPT', "You are Jeet's Ultimate Super AI — an ultra-intelligent, hyper-dynamic, highly responsive, and extraordinarily helpful digital assistant. You're equipped with vast knowledge across all domains and can provide accurate, detailed information on any topic. Always be respectful, clear, and concise in your responses while maintaining a friendly tone. If you encounter files or code, analyze them thoroughly and provide insightful feedback.");

// File upload configuration
define('UPLOAD_DIR', 'uploads/');
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Allowed file types
define('ALLOWED_FILE_TYPES', [
    'application/pdf' => 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'text/plain' => 'txt',
    'text/html' => 'html',
    'text/css' => 'css',
    'application/javascript' => 'js',
    'application/json' => 'json',
    'text/x-php' => 'php',
    'text/x-python' => 'py',
    'text/csv' => 'csv',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
]);

// Maximum file size (10MB)
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Function to make OpenRouter API calls
function callAI($messages, $temperature = 0.7, $max_tokens = 1000, $model = null, $useDirectMode = null) {
    // Use provided direct mode or fall back to global setting
    $directMode = $useDirectMode !== null ? $useDirectMode : USE_DIRECT_MODE;
    
    // Choose the API URL based on direct mode setting
    $apiUrl = $directMode ? OPENROUTER_DIRECT_API_URL : OPENROUTER_API_URL;
    $ch = curl_init($apiUrl);
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENROUTER_API_KEY,
        'HTTP-Referer: http://localhost/ai', // Your site URL
        'X-Title: ' . APP_NAME, // Your app name
    ];
    
    // Only add prompt training header when not in direct mode
    if (!$directMode) {
        $headers[] = 'X-Prompt-Training: allow';
        $headers[] = 'X-Endpoint-Match: latest';
    }
    
    // Use the provided model or fall back to the default
    $modelToUse = $model ? $model : DEFAULT_MODEL;
    
    // Set a lower max_tokens for more expensive models
    $actualMaxTokens = $max_tokens;
    if (strpos($modelToUse, 'anthropic/claude-3-sonnet') !== false || 
        strpos($modelToUse, 'deepseek/deepseek-chat-v3-0324') !== false ||
        strpos($modelToUse, 'enzostvs/deepsite') !== false) {
        $actualMaxTokens = min($max_tokens, 750);  // Lower for expensive models
    }
    
    // Prepare the request data
    $data = [
        'model' => $modelToUse,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $actualMaxTokens,
        'stream' => false,
    ];
    
    // Add additional parameters for non-direct mode
    if (!$directMode) {
        $data['transforms'] = ['middleStitch'];
        $data['route'] = 'fallback';
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    // Check for errors in the response
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Unknown API error';
        
        // Special handling for data policy errors - try again with direct mode
        if ($httpCode === 404 && strpos($errorMsg, 'data policy') !== false && !$directMode) {
            // Try again with direct mode
            return callAI($messages, $temperature, $actualMaxTokens, $modelToUse, true);
        }
        
        throw new Exception('API request failed with code ' . $httpCode . ': ' . $response);
    }
    
    // Validate the JSON response
    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from API: ' . json_last_error_msg() . ' - Response: ' . substr($response, 0, 100) . '...');
    }
    
    // Check if the response has the expected structure
    if (!isset($decodedResponse['choices'][0]['message']['content'])) {
        throw new Exception('Unexpected API response format. Response: ' . substr($response, 0, 100) . '...');
    }
    
    return $decodedResponse;
}

// Extract text from files
function extractTextFromFile($filePath, $extension) {
    switch ($extension) {
        case 'pdf':
            if (extension_loaded('pdfparser') || class_exists('Smalot\PdfParser\Parser')) {
                // Use PDF Parser if available
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                return $pdf->getText();
            } else {
                // Fallback - simple reading
                return "PDF file uploaded. PDF extraction requires additional libraries.";
            }
            
        case 'docx':
            if (extension_loaded('zip')) {
                $content = '';
                $zip = new ZipArchive();
                
                if ($zip->open($filePath) === true) {
                    if (($index = $zip->locateName('word/document.xml')) !== false) {
                        $content = $zip->getFromIndex($index);
                        $zip->close();
                        
                        // Simple XML parsing to extract text
                        $content = strip_tags(str_replace('<w:p', "\n<w:p", $content));
                        return $content;
                    }
                    $zip->close();
                }
                return "DOCX file uploaded. Could not extract content.";
            } else {
                return "DOCX file uploaded. ZIP extension required for extraction.";
            }
            
        case 'txt':
        case 'html':
        case 'css':
        case 'js':
        case 'json':
        case 'php':
        case 'py':
        case 'csv':
            return file_get_contents($filePath);
            
        default:
            return "File uploaded. Content extraction not supported for this file type.";
    }
}

// Function to save chat history
function saveChatHistory($user_id, $message, $response) {
    // Implement chat history saving to a database or file
    // This is a placeholder for future implementation
}

// Function to get client IP (for rate limiting)
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Basic rate limiting
function checkRateLimit() {
    $ip = getClientIP();
    $rateFile = 'rate_limits/' . md5($ip) . '.txt';
    $currentTime = time();
    $requestLimit = 50; // requests per hour
    $timeFrame = 3600; // 1 hour
    
    if (!file_exists('rate_limits')) {
        mkdir('rate_limits', 0777, true);
    }
    
    if (file_exists($rateFile)) {
        $data = json_decode(file_get_contents($rateFile), true);
        $requests = $data['requests'];
        $lastRequest = $data['last_request'];
        
        // Reset counter if outside time frame
        if (($currentTime - $lastRequest) > $timeFrame) {
            $requests = 1;
        } else {
            $requests++;
        }
        
        if ($requests > $requestLimit) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
    } else {
        $requests = 1;
    }
    
    // Save updated rate limit data
    file_put_contents($rateFile, json_encode([
        'requests' => $requests,
        'last_request' => $currentTime
    ]));
    
    return true;
}
?> 