<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Check rate limit
    checkRateLimit();
    
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['file'];
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds limit of ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }
    
    // Validate file type
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!isset(ALLOWED_FILE_TYPES[$fileType]) && !in_array(pathinfo($file['name'], PATHINFO_EXTENSION), array_values(ALLOWED_FILE_TYPES))) {
        throw new Exception('Unsupported file type: ' . $fileType);
    }
    
    // Generate unique filename
    $extension = isset(ALLOWED_FILE_TYPES[$fileType]) ? ALLOWED_FILE_TYPES[$fileType] : pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Extract content from file
    $content = extractTextFromFile($filepath, $extension);
    
    // Prepare a prompt based on file type
    $promptPrefix = "You are a file analysis expert. ";
    
    switch ($extension) {
        case 'php':
        case 'js':
        case 'html':
        case 'css':
        case 'py':
            $promptPrefix .= "Analyze this code for quality, potential bugs, and suggest improvements: ";
            break;
        case 'pdf':
        case 'docx':
        case 'txt':
            $promptPrefix .= "Analyze and summarize this document: ";
            break;
        case 'csv':
        case 'json':
            $promptPrefix .= "Analyze this data structure and provide insights: ";
            break;
        case 'jpg':
        case 'png':
            $promptPrefix .= "This is an image file. Please acknowledge that image content analysis is not available: ";
            break;
    }
    
    // Limit content length to avoid token limits
    $maxContentLength = 10000;
    if (strlen($content) > $maxContentLength) {
        $content = substr($content, 0, $maxContentLength) . "\n\n[Content truncated due to length...]";
    }
    
    // Call AI API for analysis
    $messages = [
        ['role' => 'system', 'content' => $promptPrefix],
        ['role' => 'user', 'content' => $content]
    ];
    
    $response = callAI($messages, 0.7, 2000);
    
    // Get file info for the response
    $fileInfo = [
        'name' => $file['name'],
        'type' => $fileType,
        'size' => formatFileSize($file['size']),
        'extension' => $extension
    ];
    
    // Return the analysis
    echo json_encode([
        'summary' => $response['choices'][0]['message']['content'],
        'fileInfo' => $fileInfo,
        'originalContent' => $content,
        'model' => $response['model'] ?? DEFAULT_MODEL
    ]);
    
    // Delete the file after processing (optional, based on your storage needs)
    unlink($filepath);
    
} catch (Exception $e) {
    error_log('Upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

// Helper function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}
?> 