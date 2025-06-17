<?php
require_once 'config.php';
session_start();

// Initialize chat history if not exists
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="animations.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/styles/github-dark.css">
    <style>
        #chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        #chat-messages::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
        }
        
        #chat-messages::-webkit-scrollbar-thumb {
            background: rgba(79,70,229,0.5);
            border-radius: 10px;
        }
        
        #chat-messages::-webkit-scrollbar-thumb:hover {
            background: rgba(79,70,229,0.8);
        }
        
        /* Enhanced dark mode styles */
        :root {
            --dark-bg-primary: #111827;
            --dark-bg-secondary: #1f2937;
            --dark-bg-tertiary: #374151;
            --dark-text-primary: #f9fafb;
            --dark-text-secondary: #e5e7eb;
            --dark-text-muted: #9ca3af;
            --dark-border: #4b5563;
            --dark-accent: #6366f1;
            --dark-accent-hover: #818cf8;
            --dark-card-bg: #1f2937;
            --dark-input-bg: #374151;
            --dark-code-bg: #111827;
            --dark-shadow: rgba(0, 0, 0, 0.5);
            
            /* Animation speeds */
            --transition-speed: 0.3s;
        }
        
        .dark-mode {
            background-color: var(--dark-bg-primary);
            color: var(--dark-text-primary);
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease;
        }
        
        .dark-mode .bg-white {
            background-color: var(--dark-bg-secondary);
            box-shadow: 0 10px 25px -5px var(--dark-shadow), 0 10px 10px -5px var(--dark-shadow);
            border: 1px solid var(--dark-border);
        }
        
        .dark-mode header h1 {
            color: var(--dark-accent);
            text-shadow: 0 0 10px rgba(99, 102, 241, 0.3);
        }
        
        .dark-mode header p {
            color: var(--dark-text-secondary);
        }
        
        .dark-mode .text-gray-700,
        .dark-mode .text-gray-800,
        .dark-mode .text-gray-600 {
            color: var(--dark-text-secondary) !important;
        }
        
        .dark-mode .bg-gray-50,
        .dark-mode .bg-gray-100 {
            background-color: var(--dark-bg-tertiary);
        }
        
        .dark-mode #message-input {
            background-color: var(--dark-input-bg);
            color: var(--dark-text-primary);
            border-color: var(--dark-border);
        }
        
        .dark-mode #message-input::placeholder {
            color: var(--dark-text-muted);
        }
        
        .dark-mode #message-input:focus {
            border-color: var(--dark-accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
        }
        
        .dark-mode #quick-model-select {
            background-color: var(--dark-input-bg);
            color: var(--dark-text-primary);
            border-color: var(--dark-border);
        }
        
        .dark-mode #quick-model-select option {
            background-color: var(--dark-bg-secondary);
        }
        
        .dark-mode .border,
        .dark-mode .border-t {
            border-color: var(--dark-border);
        }
        
        /* Enhanced message styling in dark mode */
        .dark-mode .welcome-message {
            background-color: rgba(79, 70, 229, 0.15);
            border-color: rgba(79, 70, 229, 0.3);
        }
        
        .dark-mode .welcome-message p.font-medium {
            color: #a5b4fc !important;
        }
        
        .dark-mode .example-prompt {
            background-color: rgba(79, 70, 229, 0.2);
            color: #c7d2fe;
        }
        
        .dark-mode .example-prompt:hover {
            background-color: rgba(79, 70, 229, 0.3);
            color: #e0e7ff;
            transform: translateY(-2px) scale(1.03);
        }
        
        .dark-mode .ai-message {
            background-color: var(--dark-bg-tertiary);
            color: var(--dark-text-primary);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .dark-mode .user-message {
            background-color: rgba(79, 70, 229, 0.3);
            color: var(--dark-text-primary);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .dark-mode .copy-message-btn {
            background-color: rgba(156, 163, 175, 0.2);
            color: var(--dark-text-muted);
        }
        
        .dark-mode .copy-message-btn:hover {
            background-color: rgba(156, 163, 175, 0.3);
            color: var(--dark-text-primary);
        }
        
        /* Code blocks in dark mode */
        .dark-mode pre {
            background-color: var(--dark-code-bg);
            border: 1px solid var(--dark-border);
        }
        
        .dark-mode code {
            color: #e0e7ff;
        }
        
        .dark-mode .language-tag {
            background-color: rgba(79, 70, 229, 0.3);
            color: #c7d2fe;
        }
        
        .dark-mode #file-attach-btn {
            background-color: var(--dark-bg-tertiary);
            color: var(--dark-text-secondary);
        }
        
        .dark-mode #file-attach-btn:hover {
            background-color: var(--dark-bg-primary);
        }
        
        .dark-mode #file-preview {
            background-color: var(--dark-bg-tertiary);
        }
        
        /* Dynamic glow effects for dark mode */
        .dark-mode .bg-indigo-600 {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            position: relative;
            overflow: hidden;
        }
        
        .dark-mode .bg-indigo-600::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 200%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.1), 
                transparent);
            animation: lightSweep 8s infinite;
        }
        
        @keyframes lightSweep {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(100%);
            }
        }
        
        /* Animated button effects for dark mode */
        .dark-mode #send-button {
            background: linear-gradient(45deg, #4f46e5, #6366f1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.5);
            border: none;
        }
        
        .dark-mode #send-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, 
                rgba(255, 255, 255, 0), 
                rgba(255, 255, 255, 0.1), 
                rgba(255, 255, 255, 0));
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        
        .dark-mode #send-button:hover::after {
            transform: translateX(100%);
        }
        
        /* Glass morphism effects */
        .bg-white.rounded-lg.shadow-lg {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .dark-mode .bg-white.rounded-lg.shadow-lg {
            background: rgba(31, 41, 55, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(75, 85, 99, 0.3);
        }
        
        .bg-indigo-600 {
            position: relative;
            overflow: hidden;
        }
        
        .bg-indigo-600::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Modernized header icons */
        .bg-indigo-600 button i {
            transition: all 0.3s ease;
        }
        
        .bg-indigo-600 button:hover i {
            text-shadow: 0 0 10px rgba(255,255,255,0.8);
            transform: scale(1.1);
        }
        
        /* Custom animation for welcome message */
        .example-prompt {
            animation-delay: calc(var(--i) * 0.1s);
            animation: fadeSlideIn 0.5s ease forwards;
            opacity: 0;
            transform: translateY(10px);
        }
        
        @keyframes fadeSlideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Improved chat bubble design */
        .message {
            position: relative;
        }
        
        .user-message::after {
            content: '';
            position: absolute;
            bottom: -8px;
            right: 15px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid var(--message-user-bg);
            transform: rotate(45deg);
        }
        
        .ai-message::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 15px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid var(--message-ai-bg);
            transform: rotate(-45deg);
        }
        
        /* Advanced message bubbles for dark mode */
        .dark-mode .user-message::after {
            border-top-color: rgba(79, 70, 229, 0.3);
        }
        
        .dark-mode .ai-message::after {
            border-top-color: var(--dark-bg-tertiary);
        }
        
        /* Dark mode toggle button enhancement */
        #theme-toggle {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .dark-mode #theme-toggle {
            background: rgba(99, 102, 241, 0.3);
        }
        
        .dark-mode #theme-toggle i {
            filter: drop-shadow(0 0 2px rgba(255, 255, 255, 0.5));
        }
        
        /* Enhanced markdown content in dark mode */
        .dark-mode .markdown-content a {
            color: #a5b4fc;
            text-decoration: underline;
            text-decoration-thickness: 1px;
            text-underline-offset: 2px;
        }
        
        .dark-mode .markdown-content a:hover {
            color: #c7d2fe;
            text-decoration-thickness: 2px;
        }
        
        .dark-mode .markdown-content table {
            border-color: var(--dark-border);
        }
        
        .dark-mode .markdown-content th {
            background-color: var(--dark-bg-tertiary);
            color: var(--dark-text-primary);
            border-color: var(--dark-border);
        }
        
        .dark-mode .markdown-content td {
            border-color: var(--dark-border);
            color: var(--dark-text-secondary);
        }
        
        /* Message metadata styling in dark mode */
        .dark-mode .message-time,
        .dark-mode .message-model {
            color: var(--dark-text-muted);
        }
        
        .dark-mode .user-message .message-time {
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Typing indicator styles for dark mode */
        .dark-mode .typing-indicator {
            background-color: var(--dark-bg-tertiary);
            border-radius: 18px;
            padding: 8px 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Scroll to bottom button in dark mode */
        .dark-mode .scroll-bottom-btn {
            background-color: var(--dark-bg-secondary);
            color: var(--dark-text-secondary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .scroll-bottom-btn:hover {
            background-color: var(--dark-accent);
            color: white;
        }
        
        /* High contrast mode toggle */
        #high-contrast-toggle {
            cursor: pointer;
            opacity: 0.8;
            transition: all 0.3s ease;
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 0.8rem;
            padding: 2px 6px;
            background: rgba(100, 100, 100, 0.1);
            border-radius: 4px;
            z-index: 100;
        }
        
        .dark-mode-high-contrast {
            --dark-bg-primary: #000000;
            --dark-bg-secondary: #121212;
            --dark-bg-tertiary: #1e1e1e;
            --dark-text-primary: #ffffff;
            --dark-text-secondary: #f0f0f0;
            --dark-accent: #8b5cf6;
            --dark-accent-hover: #a78bfa;
        }
        
        /* Page loading animation - ensure proper z-index and visibility */
        #page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
            pointer-events: none; /* Allow clicks to pass through */
        }
        
        .dark-mode #page-loader {
            background: rgba(17, 24, 39, 0.95);
            box-shadow: inset 0 0 100px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .loader-logo {
            color: var(--dark-accent);
            text-shadow: 0 0 15px rgba(99, 102, 241, 0.5);
        }
        
        .dark-mode .loading-text {
            color: var(--dark-text-secondary);
        }
        
        .dark-mode .inner-circle {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.6);
        }
        
        /* Enhanced progress bar for dark mode */
        .dark-mode #progress-bar {
            background: linear-gradient(90deg, #6366f1, #818cf8);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.7);
            height: 4px;
        }
        
        /* Loading overlay dark mode enhancements */
        .dark-mode #loading-overlay {
            background-color: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(4px);
        }
        
        .dark-mode #loading-overlay .bg-white {
            background-color: var(--dark-bg-secondary);
            border: 1px solid var(--dark-border);
        }
        
        .dark-mode #loading-overlay .text-gray-700 {
            color: var(--dark-text-secondary);
        }
        
        .dark-mode .loader {
            border: 4px solid rgba(99, 102, 241, 0.1);
            border-radius: 50%;
            border-top-color: var(--dark-accent);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
        }
        
        #page-loader.loaded {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        
        .pulse-circle {
            position: relative;
            width: 80px;
            height: 80px;
        }
        
        .pulse-circle::before, 
        .pulse-circle::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        }
        
        .pulse-circle::before {
            animation: pulse 1.8s ease-out infinite;
            opacity: 0.7;
        }
        
        .pulse-circle::after {
            animation: pulse 1.8s ease-out 0.6s infinite;
            opacity: 0.3;
        }
        
        .inner-circle {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            background: #4f46e5;
            border-radius: 50%;
            z-index: 2;
            box-shadow: 0 0 10px rgba(79, 70, 229, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .dark-mode .inner-circle {
            background: #6366f1;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.6);
        }
        
        @keyframes pulse {
            0% {
                transform: scale(0);
                opacity: 1;
            }
            100% {
                transform: scale(1);
                opacity: 0;
            }
        }
        
        .loader-logo {
            position: relative;
            z-index: 1;
            font-size: 1.5rem;
            font-weight: 700;
            color: #4f46e5;
            text-shadow: 0 1px 3px rgba(79, 70, 229, 0.3);
            animation: fadeIn 1s ease;
            margin-bottom: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .loading-text {
            color: #6b7280;
            margin-top: 1rem;
            font-size: 0.9rem;
            letter-spacing: 0.05rem;
        }
        
        .dark-mode .loading-text {
            color: #d1d5db;
        }
        
        /* Progress bar at top of page */
        #progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #818cf8);
            z-index: 10000;
            width: 0%;
            transition: width 0.2s ease;
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.5);
        }
        
        @keyframes indeterminateProgress {
            0% {
                width: 0%;
                left: 0;
            }
            50% {
                width: 40%;
            }
            100% {
                width: 100%;
            }
        }
        
        .progress-animated {
            animation: indeterminateProgress 1s ease-out forwards;
        }
        
        /* Page elements sequential loading animations - visible by default */
        header {
            opacity: 1; /* Start visible */
            transform: translateY(0);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        header.loaded {
            opacity: 1;
            transform: translateY(0);
        }
        
        .chat-card {
            opacity: 1; /* Start visible */
            transform: translateY(0);
            transition: opacity 0.5s ease, transform 0.5s ease;
            transition-delay: 0.2s;
        }
        
        .chat-card.loaded {
            opacity: 1;
            transform: translateY(0);
        }
        
        .chat-header {
            opacity: 1; /* Start visible */
            transition: opacity 0.5s ease;
            transition-delay: 0.4s;
        }
        
        .chat-header.loaded {
            opacity: 1;
        }
        
        /* Enhanced loading overlay styles */
        #loading-overlay {
            z-index: 9999; /* Ensure it's above everything */
            backdrop-filter: blur(4px);
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .loader {
            border: 4px solid rgba(79, 70, 229, 0.1);
            border-radius: 50%;
            border-top: 4px solid #4f46e5;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
        
        .fade-out {
            animation: fadeOut 0.3s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Progress Bar -->
    <div id="progress-bar"></div>
    
    <!-- Page Loading Animation -->
    <div id="page-loader">
        <div class="loader-logo"><?php echo APP_NAME; ?></div>
        <div class="pulse-circle">
            <div class="inner-circle flex items-center justify-center">
                <i class="fas fa-comment-dots text-white text-xl"></i>
            </div>
        </div>
        <div class="loading-text">Loading the chat experience...</div>
    </div>
    
    <div class="container mx-auto px-4 py-6">
        <header class="text-center mb-6">
            <h1 class="text-4xl font-bold text-indigo-600"><?php echo APP_NAME; ?></h1>
            <p class="text-gray-600 mt-2">Your advanced AI assistant for all domains</p>
        </header>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden chat-card">
            <!-- Chat Header -->
            <div class="bg-indigo-600 text-white p-4 flex justify-between items-center chat-header">
                <h2 class="text-xl font-semibold">AI Chat Interface</h2>
                <div class="flex space-x-4 items-center">
                    <button id="theme-toggle" class="p-2 hover:bg-indigo-700 rounded transition-colors" title="Toggle Dark Mode">
                        <i class="fas fa-moon"></i>
                    </button>
                    <button id="clear-chat" class="p-2 hover:bg-indigo-700 rounded transition-colors" title="Clear chat">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <button id="save-chat" class="p-2 hover:bg-indigo-700 rounded transition-colors" title="Save conversation">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </div>
            
            <!-- Chat Messages Area -->
            <div id="chat-messages" class="h-[calc(100vh-300px)] overflow-y-auto p-4 bg-gray-50 scroll-smooth">
                <div class="welcome-message mb-4 p-3 bg-indigo-50 rounded-lg border border-indigo-100">
                    <p class="font-medium text-indigo-800">👋 Welcome to <?php echo APP_NAME; ?>!</p>
                    <p class="text-gray-700 text-sm mt-1">I'm your advanced AI assistant that can route your questions to different AI models. Select a model from the dropdown menu above and ask anything!</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button class="example-prompt px-2 py-1 text-xs bg-indigo-200 hover:bg-indigo-300 rounded-full text-indigo-800 transition-colors" style="--i: 1">Explain quantum computing</button>
                        <button class="example-prompt px-2 py-1 text-xs bg-indigo-200 hover:bg-indigo-300 rounded-full text-indigo-800 transition-colors" style="--i: 2">Write a Python function to sort a list</button>
                        <button class="example-prompt px-2 py-1 text-xs bg-indigo-200 hover:bg-indigo-300 rounded-full text-indigo-800 transition-colors" style="--i: 3">Compare AI models</button>
                    </div>
                    <div class="mt-3 border-t border-indigo-100 pt-2">
                        <p class="text-gray-600 text-xs font-semibold">⚠️ Important: If you see errors, the system will automatically try a different model.</p>
                    </div>
                </div>
                <!-- Messages will appear here -->
            </div>
            
            <!-- Input Area -->
            <div class="p-4 border-t">
                <div class="flex items-center mb-2">
                    <label for="quick-model-select" class="text-sm text-gray-600 mr-2">AI:</label>
                    <select id="quick-model-select" class="text-sm border rounded-md p-1 bg-gray-50 transition-all hover:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        <option value="anthropic/claude-3-haiku">Claude</option>
                        <option value="meta-llama/llama-3-70b-instruct">Llama</option>
                        <option value="deepsick/model">DeepSick</option>
                        <option value="x-ai/grok-3-beta">Grok</option>
                        <option value="openrouter/optimus-alpha">Optimus</option>
                        <option value="mistralai/mistral-large">Mistral</option>
                        <option value="cohere/command-r">Command R</option>
                    </select>
                </div>
                <div class="relative">
                    <textarea id="message-input" 
                           class="w-full p-3 pr-24 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none transition-all"
                           placeholder="Type your message..."
                           rows="2"></textarea>
                    <div class="absolute bottom-3 right-3 flex">
                        <button id="file-attach-btn" class="bg-gray-200 text-gray-700 p-2 rounded-l-lg hover:bg-gray-300 transition-colors" title="Attach file">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button id="send-button" 
                                class="bg-indigo-600 text-white px-4 py-2 rounded-r-lg hover:bg-indigo-700 transition-colors flex items-center">
                            <span>Send</span>
                            <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </div>
                </div>
                <div id="file-preview" class="hidden mt-2 p-2 bg-gray-100 rounded-lg"></div>
            </div>
        </div>
    </div>

    <!-- Hidden file input for chat attachments -->
    <input type="file" id="chat-file-input" class="hidden" accept=".pdf,.docx,.txt,.html,.css,.js,.json,.php,.py,.csv,.jpg,.png">
    
    <!-- Loading overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg flex flex-col items-center max-w-md w-full mx-4">
            <div class="loader mb-4"></div>
            <p id="loading-text" class="text-gray-700 text-center">Processing your request...</p>
        </div>
    </div>
    
    <!-- High contrast mode toggle -->
    <div id="high-contrast-toggle" class="hidden">HC</div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/lib/core.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/lib/languages/javascript.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/lib/languages/python.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/lib/languages/php.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/lib/languages/css.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/lib/languages/xml.min.js"></script>
    
    <!-- Page refresh loader script -->
    <script>
        window.addEventListener('beforeunload', function() {
            // Animate progress bar on refresh
            const progressBar = document.getElementById('progress-bar');
            if (progressBar) {
                progressBar.classList.add('progress-animated');
            }
            
            // Show loader on page refresh
            const pageLoader = document.getElementById('page-loader');
            if (pageLoader) {
                pageLoader.classList.remove('loaded');
                pageLoader.style.opacity = '1';
                pageLoader.style.visibility = 'visible';
            } else {
                // If loader was already removed, recreate it
                const newLoader = document.createElement('div');
                newLoader.id = 'page-loader';
                newLoader.innerHTML = `
                    <div class="loader-logo"><?php echo APP_NAME; ?></div>
                    <div class="pulse-circle">
                        <div class="inner-circle flex items-center justify-center">
                            <i class="fas fa-comment-dots text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="loading-text">Refreshing...</div>
                `;
                document.body.appendChild(newLoader);
            }
            // Note: This won't prevent the refresh, it just shows the loader
        });
    </script>
    
    <!-- Dark mode particle effects -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create a canvas for particle effects in dark mode
            function setupParticles() {
                if (document.getElementById('particles-canvas')) return;
                
                const canvas = document.createElement('canvas');
                canvas.id = 'particles-canvas';
                canvas.style.position = 'fixed';
                canvas.style.top = '0';
                canvas.style.left = '0';
                canvas.style.width = '100%';
                canvas.style.height = '100%';
                canvas.style.pointerEvents = 'none';
                canvas.style.zIndex = '0';
                canvas.style.opacity = '0';
                canvas.style.transition = 'opacity 1s ease';
                document.body.prepend(canvas);
                
                const ctx = canvas.getContext('2d');
                let particles = [];
                
                // Resize canvas to match window size
                function resizeCanvas() {
                    canvas.width = window.innerWidth;
                    canvas.height = window.innerHeight;
                }
                
                // Create particle class
                class Particle {
                    constructor() {
                        this.x = Math.random() * canvas.width;
                        this.y = Math.random() * canvas.height;
                        this.size = Math.random() * 2 + 0.5;
                        this.speedX = Math.random() * 0.5 - 0.25;
                        this.speedY = Math.random() * 0.5 - 0.25;
                        this.color = `rgba(${Math.floor(Math.random() * 50) + 100}, ${Math.floor(Math.random() * 50) + 100}, ${Math.floor(Math.random() * 100) + 155}, ${Math.random() * 0.5 + 0.2})`;
                    }
                    
                    update() {
                        this.x += this.speedX;
                        this.y += this.speedY;
                        
                        // Wrap around edges
                        if (this.x > canvas.width) this.x = 0;
                        if (this.x < 0) this.x = canvas.width;
                        if (this.y > canvas.height) this.y = 0;
                        if (this.y < 0) this.y = canvas.height;
                    }
                    
                    draw() {
                        ctx.fillStyle = this.color;
                        ctx.beginPath();
                        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                        ctx.fill();
                    }
                }
                
                // Initialize particles
                function initParticles() {
                    particles = [];
                    for (let i = 0; i < 100; i++) {
                        particles.push(new Particle());
                    }
                }
                
                // Animation loop
                function animate() {
                    if (!document.body.classList.contains('dark-mode')) {
                        if (canvas.style.opacity !== '0') {
                            canvas.style.opacity = '0';
                        }
                        requestAnimationFrame(animate);
                        return;
                    }
                    
                    if (canvas.style.opacity !== '1') {
                        canvas.style.opacity = '1';
                    }
                    
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    // Update and draw particles
                    particles.forEach(particle => {
                        particle.update();
                        particle.draw();
                    });
                    
                    // Draw connections between nearby particles
                    for (let a = 0; a < particles.length; a++) {
                        for (let b = a; b < particles.length; b++) {
                            const dx = particles[a].x - particles[b].x;
                            const dy = particles[a].y - particles[b].y;
                            const distance = Math.sqrt(dx * dx + dy * dy);
                            
                            if (distance < 100) {
                                ctx.beginPath();
                                ctx.strokeStyle = `rgba(99, 102, 241, ${0.1 * (1 - distance/100)})`;
                                ctx.lineWidth = 0.5;
                                ctx.moveTo(particles[a].x, particles[a].y);
                                ctx.lineTo(particles[b].x, particles[b].y);
                                ctx.stroke();
                            }
                        }
                    }
                    
                    requestAnimationFrame(animate);
                }
                
                // Handle window resize
                window.addEventListener('resize', resizeCanvas);
                
                // Initialize
                resizeCanvas();
                initParticles();
                animate();
            }
            
            // Check if dark mode is active and setup particles
            const body = document.body;
            if (body.classList.contains('dark-mode')) {
                setupParticles();
            }
            
            // Listen for dark mode toggle
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    // If toggling to dark mode, setup particles
                    if (!body.classList.contains('dark-mode')) {
                        setTimeout(setupParticles, 100);
                    }
                });
            }
        });
    </script>
    
    <script src="app.js"></script>
    
    <!-- Initialize functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Add Dark Mode Toggle Functionality
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;
            const highContrastToggle = document.getElementById('high-contrast-toggle');
            
            // Check for saved theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            const highContrast = localStorage.getItem('highContrast') === 'true';
            
            // Apply saved theme
            if (savedTheme === 'dark') {
                body.classList.add('dark-mode');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                highContrastToggle.classList.remove('hidden');
                
                // Apply high contrast if saved
                if (highContrast) {
                    body.classList.add('dark-mode-high-contrast');
                    highContrastToggle.textContent = 'HC ON';
                    highContrastToggle.style.backgroundColor = '#8b5cf6';
                    highContrastToggle.style.color = '#ffffff';
                }
            }
            
            // Toggle dark mode with animation
            themeToggle.addEventListener('click', () => {
                // Add transition overlay
                const overlay = document.createElement('div');
                overlay.className = 'theme-transition-overlay';
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: ${body.classList.contains('dark-mode') ? '#ffffff' : '#111827'};
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    pointer-events: none;
                    z-index: 10000;
                `;
                document.body.appendChild(overlay);
                
                // Trigger transition
                setTimeout(() => {
                    overlay.style.opacity = '0.15';
                    
                    setTimeout(() => {
                        // Toggle dark mode
                        body.classList.toggle('dark-mode');
                        
                        // Show/hide high contrast toggle
                        if (body.classList.contains('dark-mode')) {
                            highContrastToggle.classList.remove('hidden');
                            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                            localStorage.setItem('theme', 'dark');
                        } else {
                            highContrastToggle.classList.add('hidden');
                            body.classList.remove('dark-mode-high-contrast');
                            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                            localStorage.setItem('theme', 'light');
                            localStorage.setItem('highContrast', 'false');
                        }
                        
                        // Update message colors when theme changes
                        if (typeof setMessageColors === 'function') {
                            setMessageColors();
                        }
                        
                        // Fade out overlay
                        overlay.style.opacity = '0';
                        setTimeout(() => {
                            overlay.remove();
                        }, 300);
                    }, 150);
                }, 50);
            });
            
            // High contrast mode toggle
            if (highContrastToggle) {
                highContrastToggle.addEventListener('click', () => {
                    if (body.classList.contains('dark-mode')) {
                        body.classList.toggle('dark-mode-high-contrast');
                        
                        if (body.classList.contains('dark-mode-high-contrast')) {
                            highContrastToggle.textContent = 'HC ON';
                            highContrastToggle.style.backgroundColor = '#8b5cf6';
                            highContrastToggle.style.color = '#ffffff';
                            localStorage.setItem('highContrast', 'true');
                        } else {
                            highContrastToggle.textContent = 'HC';
                            highContrastToggle.style.backgroundColor = 'rgba(100, 100, 100, 0.1)';
                            highContrastToggle.style.color = '';
                            localStorage.setItem('highContrast', 'false');
                        }
                    }
                });
            }
        });
    </script>
    
    <!-- Fallback to ensure UI is visible -->
    <script>
        // Ensure the UI becomes visible after a timeout (failsafe)
        setTimeout(function() {
            // Hide page loader if still visible
            const pageLoader = document.getElementById('page-loader');
            if (pageLoader) {
                pageLoader.style.display = 'none';
            }
            
            // Make sure all elements are visible
            document.querySelectorAll('.container, header, .chat-card, .chat-header, #chat-messages').forEach(function(el) {
                el.style.opacity = '1';
                el.style.transform = 'none';
                el.classList.add('loaded');
            });
            
            // Ensure loading overlay is hidden
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('hidden');
            }
        }, 1000); // 1 second failsafe (changed from 3000)
    </script>
</body>
</html> 