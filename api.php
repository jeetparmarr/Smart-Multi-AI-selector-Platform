<?php
// Include configuration file
require_once 'config.php';
session_start();

// Override USE_DIRECT_MODE with session value if set
if (isset($_SESSION['USE_DIRECT_MODE'])) {
    define('SESSION_DIRECT_MODE', $_SESSION['USE_DIRECT_MODE']);
} else {
    define('SESSION_DIRECT_MODE', USE_DIRECT_MODE); // Use default from config
}

// Ensure consistent output
ob_start();

// Set the content type to JSON
header('Content-Type: application/json');

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');

// Error handling
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
    $model = $data['model'] ?? DEFAULT_MODEL;
    
    // Ensure we always have a valid model (Claude as fallback)
    if (empty($model) || $model === 'null' || $model === 'undefined') {
        $model = DEFAULT_MODEL; // This is 'anthropic/claude-3-haiku' from config.php
    }
    
    // Get provider from query string or determine from model
    $provider = $_GET['provider'] ?? null;
    
    if (!$provider) {
        // Determine provider based on model
        if (strpos($model, 'openai') === 0) {
            $provider = 'openai';
        } elseif (strpos($model, 'anthropic') === 0) {
            $provider = 'anthropic';
        } elseif (strpos($model, 'deepsick') === 0) {
            $provider = 'deepsick';
        } elseif (strpos($model, 'x-ai') === 0) {
            $provider = 'xai';
        } elseif (strpos($model, 'openrouter') === 0) {
            $provider = 'openrouter';
        } elseif (strpos($model, 'mistralai') === 0) {
            $provider = 'mistral';
        } elseif (strpos($model, 'cohere') === 0) {
            $provider = 'cohere';
        } elseif (strpos($model, 'meta-llama') === 0) {
            $provider = 'llama';
        } else {
            $provider = 'default';
        }
    }
    
    // Prepare system prompt based on the provider
    $systemPrompt = "";
    
    switch ($provider) {
        case 'anthropic':
            $systemPrompt = "You are Claude, an AI assistant created by Anthropic. " . DEFAULT_SYSTEM_PROMPT;
            break;
        case 'deepsick':
            $systemPrompt = "You are DeepSick AI, a specialized AI assistant with advanced capabilities in medical and technical domains. " . DEFAULT_SYSTEM_PROMPT;
            break;
        case 'xai':
            $systemPrompt = "You are Grok, an AI assistant created by xAI. You are rebellious, funny, and super smart. " . DEFAULT_SYSTEM_PROMPT;
            break;
        case 'openrouter':
            $systemPrompt = "You are Optimus Alpha, an experimental AI assistant that combines the best capabilities of multiple models. " . DEFAULT_SYSTEM_PROMPT;
            break;
        case 'mistral':
            $systemPrompt = "You are Mistral AI, a powerful and efficient assistant that excels at reasoning and understanding. " . DEFAULT_SYSTEM_PROMPT;
            break;
        case 'cohere':
            $systemPrompt = "You are Command R, an assistant created by Cohere that excels at information retrieval and understanding. " . DEFAULT_SYSTEM_PROMPT;
            break;
        case 'llama':
            $systemPrompt = "You are Llama, an open source assistant created by Meta that balances intelligence with efficiency. " . DEFAULT_SYSTEM_PROMPT;
            break;
        default:
            $systemPrompt = DEFAULT_SYSTEM_PROMPT;
    }
    
    // Add router message if any is passed
    if (isset($data['routerPrompt']) && !empty($data['routerPrompt'])) {
        $systemPrompt = $data['routerPrompt'] . " " . $systemPrompt;
    }
    
    // Prepare messages for API
    $messages = [
        [
            'role' => 'system',
            'content' => $systemPrompt
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
    $modelToUse = $model;
    
    // Update model if needed based on provider
    if ($provider === 'deepsick' || $model === 'deepsick/model') {
        $modelToUse = 'deepseek/deepseek-chat-v3-0324'; // Use the correct DeepSeek model
    } else if (empty($modelToUse) || $modelToUse === 'null' || $modelToUse === 'undefined') {
        $modelToUse = FALLBACK_MODEL; // Always use Claude as fallback for empty models
    }
    
    try {
        // Call AI API with session-based direct mode setting and explicit header to disable prompt training
        $headers = [
            'X-Prompt-Training: false', // Explicitly disable prompt training via header
            'HTTP-Referer: https://jeetultimateai.com' // Use a stable referer
        ];
        
        try {
            // Use OpenRouter API for all models
            $response = callAI($messages, 0.7, 1000, $modelToUse, SESSION_DIRECT_MODE, $headers);
        } catch (Exception $modelError) {
            // If there's an error with the requested model, try with Claude as fallback
            if (strpos($modelError->getMessage(), 'is not a valid model ID') !== false || 
                strpos($modelError->getMessage(), 'No endpoints found') !== false) {
                
                error_log("Model error, falling back to " . FALLBACK_MODEL . ": " . $modelError->getMessage());
                $modelToUse = FALLBACK_MODEL; // Use the designated fallback model
                $response = callAI($messages, 0.7, 1000, $modelToUse, SESSION_DIRECT_MODE, $headers);
                
                // Add notice about fallback to the response
                $fallbackModel = getReadableModelName(FALLBACK_MODEL);
                if (isset($response['choices'][0]['message']['content'])) {
                    $response['choices'][0]['message']['content'] = "⚠️ *The requested model was unavailable. Using $fallbackModel instead.*\n\n" . $response['choices'][0]['message']['content'];
                }
            } else {
                // Re-throw if it's not a model-related error
                throw $modelError;
            }
        }
        
        // Extract the response
        if (isset($response['choices'][0]['message']['content'])) {
            $aiResponse = $response['choices'][0]['message']['content'];
        } else {
            throw new Exception('Invalid response format from AI provider');
        }
        
        // Get a readable model name - always use the original selected model for display
        $modelName = getReadableModelName($model);
        
        // Special case for DeepSick AI
        if ($provider === 'deepsick') {
            $modelName = 'DeepSick AI';
        }
        
        // Return the response
        echo json_encode([
            'response' => $aiResponse,
            'model' => $modelName,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Log the error for server-side debugging
        error_log('API Error: ' . $e->getMessage());
        
        // Extract and format the error message
        $errorMsg = $e->getMessage();
        
        // Make credit-related errors more user-friendly
        if (strpos($errorMsg, 'credits') !== false || strpos($errorMsg, 'max_tokens') !== false) {
            $errorMsg = "The AI model you selected requires more credits. The system will automatically try a different model. Please try again.";
        }
        
        // Return a proper error response
        http_response_code(500);
        echo json_encode([
            'error' => $errorMsg,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    // Log the error for server-side debugging
    error_log('API Error: ' . $e->getMessage());
    
    // Return a proper error response
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Helper function to get readable model names
function getReadableModelName($modelId) {
    // If model ID is empty, return default Claude
    if (empty($modelId) || $modelId === 'null' || $modelId === 'undefined') {
        return DEFAULT_MODEL_DISPLAY_NAME;
    }
    
    $modelNames = [
        'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
        'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet',
        'anthropic/claude-2' => 'Claude 2',
        'meta-llama/llama-3-70b-instruct' => 'Llama 3 70B',
        'meta-llama/llama-3-8b-instruct' => 'Llama 3 8B',
        'meta-llama/llama-2-70b-chat' => 'Llama 2 70B',
        'mistralai/mistral-large' => 'Mistral Large',
        'mistralai/mistral-medium' => 'Mistral Medium',
        'mistralai/mistral-small' => 'Mistral Small',
        'cohere/command-r' => 'Command R',
        'cohere/command-r-plus' => 'Command R+',
        'openrouter/optimus-alpha' => 'Optimus Alpha',
        'deepsick/model' => 'DeepSick AI',
        'deepsick/deepseek-v3' => 'DeepSick AI',
        'deepseek/deepseek-chat-v3-0324' => 'DeepSick AI',
        'enzostvs/deepsite' => 'DeepSite',
        'x-ai/grok-3-beta' => 'Grok 3'
    ];
    
    return $modelNames[$modelId] ?? $modelId;
}

// Final validation to ensure valid JSON is always returned
$output = ob_get_clean();
if (!empty($output)) {
    // Check if the output is valid JSON
    json_decode($output);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If not valid JSON, send a proper error response
        http_response_code(500);
        echo json_encode([
            'error' => 'Invalid JSON response was generated',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // If valid JSON, send it as is
        echo $output;
    }
} else {
    // If no output, send an empty but valid JSON response
    echo json_encode([
        'error' => 'No response was generated',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?> 