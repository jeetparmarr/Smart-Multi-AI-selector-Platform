document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const chatMessages = document.getElementById('chat-messages');
    const chatFileInput = document.getElementById('chat-file-input');
    const fileAttachBtn = document.getElementById('file-attach-btn');
    const filePreview = document.getElementById('file-preview');
    const clearChatBtn = document.getElementById('clear-chat');
    const saveChatBtn = document.getElementById('save-chat');
    const loadingOverlay = document.getElementById('loading-overlay');
    const loadingText = document.getElementById('loading-text');
    const quickModelSelect = document.getElementById('quick-model-select');
    const examplePrompts = document.querySelectorAll('.example-prompt');
    
    // State Management
    let chatContext = [];
    let chatFile = null;
    
    // Always ensure Claude is the default model
    const defaultClaudeModel = 'anthropic/claude-3-haiku';
    let storedModel = localStorage.getItem('model');
    
    // Check if stored model is valid and not empty
    if (!storedModel || storedModel === 'null' || storedModel === 'undefined' || storedModel === '') {
        storedModel = defaultClaudeModel;
        localStorage.setItem('model', defaultClaudeModel);
    }
    
    let currentModel = storedModel;
    
    // Run on page load
    console.log(`Starting with model: ${currentModel || defaultClaudeModel}`);
    
    // Ensure valid model selection
    function ensureValidModel() {
        if (!currentModel || !quickModelSelect) {
            currentModel = defaultClaudeModel;
            localStorage.setItem('model', currentModel);
            return;
        }
        
        // Check if the current model exists in options
        const modelExists = Array.from(quickModelSelect.options).some(opt => opt.value === currentModel);
        
        // If not found or empty, reset to Claude
        if (!modelExists || currentModel === '') {
            currentModel = defaultClaudeModel;
            localStorage.setItem('model', currentModel);
        }
        
        // Update UI to reflect current model
        if (quickModelSelect) quickModelSelect.value = currentModel;
    }
    
    // Call immediately
    ensureValidModel();
    
    let enableCodeHighlighting = true;
    
    // Set initial state
    if (quickModelSelect) quickModelSelect.value = currentModel;
    
    // Initialize marked.js for Markdown rendering
    if (typeof marked !== 'undefined') {
        marked.setOptions({
            renderer: new marked.Renderer(),
            highlight: function(code, lang) {
                if (enableCodeHighlighting && hljs && hljs.getLanguage(lang)) {
                    return hljs.highlight(code, { language: lang }).value;
                }
                return code;
            },
            langPrefix: 'hljs language-',
            pedantic: false,
            gfm: true,
            breaks: true,
            sanitize: false,
            smartypants: false,
            xhtml: false
        });
    }

    // MAIN FUNCTIONALITY
    
    // Send Message
    async function sendMessage() {
        const message = messageInput ? messageInput.value.trim() : '';
        if (!message && !chatFile) return;
        
        // Show loading state
        if (sendButton) {
            sendButton.disabled = true;
            sendButton.classList.add('bg-indigo-400');
            sendButton.innerHTML = '<span class="loading-dots"></span>';
        }
        
        // Add user message to chat
        addMessage(message, true);
        
        // Create typing indicator
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'typing-indicator';
        typingIndicator.innerHTML = '<span></span><span></span><span></span>';
        if (chatMessages) {
            chatMessages.appendChild(typingIndicator);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        try {
            let fileContent = null;
            
            // Process file if attached
            if (chatFile) {
                fileContent = await readFile(chatFile);
                // Clear file preview and attachment
                if (filePreview) {
                    filePreview.innerHTML = '';
                    filePreview.classList.add('hidden');
                }
                chatFile = null;
            }
            
            // Prepare request data
            const requestData = {
                message: message,
                context: chatContext,
                fileContent: fileContent,
                model: !currentModel ? defaultClaudeModel : (currentModel === 'deepsick/model' ? 'deepseek/deepseek-chat-v3-0324' : currentModel),
                routerPrompt: "You are an AI router. I will give you a list of AI services like Claude, Llama, DeepSick, etc. When I select one from the list, send my request only to the selected AI. For example, if I select 'Claude', route the message to Claude and not to any other AI."
            };
            
            // Determine which endpoint to use based on the selected model
            let endpoint = 'chat.php';
            
            if (currentModel === 'deepsick/model') {
                endpoint = 'api.php?provider=deepsick';
                console.log('Using DeepSick endpoint with deepseek-chat-v3-0324 model');
            } else if (currentModel === 'openrouter/optimus-alpha') {
                // For Optimus Alpha model, always use the bypass endpoint
                endpoint = 'bypass.php';
                console.log('Using direct bypass for Optimus Alpha');
            } else if (currentModel.startsWith('anthropic/claude-3-haiku')) {
                endpoint = 'api.php?provider=anthropic';
            } else if (currentModel.startsWith('x-ai/')) {
                endpoint = 'api.php?provider=xai';
            } else if (currentModel.startsWith('openrouter/')) {
                endpoint = 'api.php?provider=openrouter';
            } else if (currentModel.startsWith('mistralai/')) {
                endpoint = 'api.php?provider=mistral';
            } else if (currentModel.startsWith('cohere/')) {
                endpoint = 'api.php?provider=cohere';
            } else {
                endpoint = 'api.php?provider=default';
            }
            
            console.log(`Sending request to ${endpoint} with model: ${currentModel}`);
            
            // Send the request
            let response;
            let data;
            let useBypass = false;
            
            try {
                response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });
                
                // Check if the response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response: ' + await response.text());
                }
                
                data = await response.json();
                console.log('Received response:', data);
                
                // If there's a data policy error, try the bypass endpoint
                if (!response.ok && data.error && data.error.includes('data policy')) {
                    console.log('Detected data policy error, trying bypass endpoint...');
                    useBypass = true;
                }
                
                // If there's a model error, try with Claude as it's more reliable
                if (!response.ok && data.error && 
                    (data.error.includes('is not a valid model ID') || 
                     data.error.includes('No endpoints found') ||
                     data.error.includes('unavailable') ||
                     data.error.includes('API error'))) {
                    
                    // Determine which model had the error
                    const errorModel = getModelName(currentModel);
                    console.log(`Detected error with ${errorModel} model, switching to Claude...`);
                    
                    // Change UI to reflect this
                    const modelErrorMsg = document.createElement('div');
                    modelErrorMsg.className = 'message system-message';
                    modelErrorMsg.innerHTML = `<div class="p-2 bg-yellow-100 text-yellow-800 rounded mb-2">The ${errorModel} model is currently unavailable. Switching to Claude 3 Haiku...</div>`;
                    chatMessages.appendChild(modelErrorMsg);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    
                    // Update current model locally
                    currentModel = defaultClaudeModel;
                    if (quickModelSelect) quickModelSelect.value = currentModel;
                    localStorage.setItem('model', currentModel);
                    
                    // Retry the request
                    return sendMessage();
                }
            } catch (error) {
                console.error('Error in primary endpoint:', error);
                useBypass = true;
            }
            
            // If we need to use the bypass due to data policy error or other error
            if (useBypass) {
                try {
                    console.log('Using bypass endpoint...');
                    // Show message about trying bypass
                    if (data && data.error) {
                        if (typingIndicator) typingIndicator.remove();
                        
                        const bypassMsg = document.createElement('div');
                        bypassMsg.className = 'message system-message';
                        bypassMsg.innerHTML = '<div class="p-2 bg-yellow-100 text-yellow-800 rounded mb-2">Trying alternative method to bypass data policy restrictions...</div>';
                        chatMessages.appendChild(bypassMsg);
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                        
                        // Create a new typing indicator
                        const newTypingIndicator = document.createElement('div');
                        newTypingIndicator.className = 'typing-indicator';
                        newTypingIndicator.innerHTML = '<span></span><span></span><span></span>';
                        chatMessages.appendChild(newTypingIndicator);
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                        typingIndicator = newTypingIndicator;
                    }
                    
                    // Always use Claude for bypass for maximum reliability
                    let bypassData = {...requestData};
                    bypassData.model = defaultClaudeModel;
                    console.log('Using Claude model for bypass reliability');
                    
                    const bypassResponse = await fetch('bypass.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(bypassData)
                    });
                    
                    // Check if the bypass response is JSON
                    const bypassContentType = bypassResponse.headers.get('content-type');
                    if (!bypassContentType || !bypassContentType.includes('application/json')) {
                        // Log the actual response for debugging
                        const textResponse = await bypassResponse.text();
                        console.error('Non-JSON response from bypass:', textResponse);
                        
                        // Fall back to simulation
                        throw new Error('Bypass endpoint returned non-JSON response. Falling back to simulation.');
                    }
                    
                    // Update response and data with bypass results
                    response = bypassResponse;
                    data = await bypassResponse.json();
                    console.log('Received bypass response:', data);
                } catch (bypassError) {
                    console.error('Error in bypass endpoint:', bypassError);
                    
                    // Remove typing indicator
                    if (typingIndicator) typingIndicator.remove();
                    
                    // Show error to user
                    addMessage(`I'm having trouble connecting to my AI services right now. Using offline mode instead.`, false, 'system');
                    
                    // Fall back to client-side simulation
                    data = simulateResponse(message);
                    response = { ok: true }; // Simulate successful response
                }
            }
            
            // Remove typing indicator
            if (typingIndicator) typingIndicator.remove();
            
            if (response.ok) {
                // Add AI response to chat
                addMessage(data.response, false, data.model, data.timestamp);
                
                // Update chat context for conversation continuity
                chatContext.push({ role: 'user', content: message });
                chatContext.push({ role: 'assistant', content: data.response });
                
                // Keep context size manageable (last 10 messages)
                if (chatContext.length > 20) {
                    chatContext = chatContext.slice(chatContext.length - 20);
                }
            } else {
                // Check if it's a credit-related error
                if (data.error && (data.error.includes('credits') || data.error.includes('model you selected requires more credits'))) {
                    // Show a message about retrying with a different model
                    addMessage(`${data.error} Retrying with a more affordable model...`, false, 'system');
                    
                    // Wait briefly before retrying
                    setTimeout(() => {
                        sendMessage();
                    }, 1500);
                } else {
                    // For other errors, just show the message
                    addMessage(`Error: ${data.error || 'Unknown error occurred'}`, false, 'system');
                }
            }
        } catch (error) {
            console.error('Error in sendMessage:', error);
            
            // Remove typing indicator
            const indicator = document.querySelector('.typing-indicator');
            if (indicator) indicator.remove();
            
            addMessage(`Error: ${error.message}`, false, 'system');
        } finally {
            // Reset UI state
            if (messageInput) messageInput.value = '';
            if (sendButton) {
                sendButton.disabled = false;
                sendButton.classList.remove('bg-indigo-400');
                sendButton.innerHTML = '<span>Send</span><i class="fas fa-paper-plane ml-2"></i>';
            }
        }
    }
    
    // Add a message to the chat
    function addMessage(message, isUser = false, model = null, timestamp = null) {
        if (!chatMessages) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isUser ? 'user-message' : 'ai-message'}`;
        
        // Helper function to get friendly model name
        function getModelName(modelId) {
            // If modelId is null, empty or undefined, return default Claude name
            if (!modelId || modelId === '' || modelId === 'undefined' || modelId === 'null') {
                return 'Claude 3 Haiku';
            }
            
            const modelNames = {
                'anthropic/claude-3-haiku': 'Claude 3 Haiku',
                'meta-llama/llama-3-70b-instruct': 'Llama',
                'deepsick/model': 'DeepSick',
                'deepseek/deepseek-chat-v3-0324': 'DeepSick',
                'x-ai/grok-3-beta': 'Grok',
                'openrouter/optimus-alpha': 'Optimus',
                'mistralai/mistral-large': 'Mistral',
                'cohere/command-r': 'Command R'
            };
            
            // Special case for DeepSick AI when it's using the claude-3-haiku model
            if (modelId === 'anthropic/claude-3-haiku' && currentModel === 'deepsick/model') {
                return 'DeepSick AI';
            }
            
            return modelNames[modelId] || modelId;
        }
        
        // Process message content
        let processedContent = message || ''; // Ensure message is not null or undefined
        
        if (!isUser) {
            // For AI messages, process markdown
            if (typeof marked !== 'undefined' && processedContent) {
                try {
                    processedContent = marked.parse(processedContent);
                    messageDiv.classList.add('markdown-content');
                } catch (error) {
                    console.error('Error parsing markdown:', error);
                    // Fallback to plain text if markdown parsing fails
                    processedContent = `<p>${processedContent}</p>`;
                }
            }
            
            // Add model info and timestamp
            let metaInfo = '';
            if (timestamp) {
                metaInfo += `<div class="message-time">${timestamp}</div>`;
            }
            if (model && model !== 'system') {
                metaInfo += `<div class="message-model">${getModelName(model)}</div>`;
            }
            
            // Add copy button for AI messages
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-btn';
            copyBtn.innerHTML = '<i class="far fa-copy"></i>';
            copyBtn.title = 'Copy to clipboard';
            copyBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(message || '')
                    .then(() => {
                        copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                        setTimeout(() => {
                            copyBtn.innerHTML = '<i class="far fa-copy"></i>';
                        }, 2000);
                    });
            });
            messageDiv.appendChild(copyBtn);
            
            // Set inner HTML for AI message (with markdown)
            messageDiv.innerHTML = processedContent + metaInfo;
            
            // Add copy button to each code block
            messageDiv.querySelectorAll('pre').forEach(pre => {
                const codeCopyBtn = document.createElement('button');
                codeCopyBtn.className = 'code-copy-btn';
                codeCopyBtn.innerHTML = '<i class="far fa-copy"></i>';
                codeCopyBtn.title = 'Copy code';
                codeCopyBtn.addEventListener('click', () => {
                    const code = pre.querySelector('code')?.textContent || '';
                    navigator.clipboard.writeText(code)
                        .then(() => {
                            codeCopyBtn.innerHTML = '<i class="fas fa-check"></i>';
                            setTimeout(() => {
                                codeCopyBtn.innerHTML = '<i class="far fa-copy"></i>';
                            }, 2000);
                        });
                });
                pre.appendChild(codeCopyBtn);
            });
        } else {
            // For user messages, just set text content
            messageDiv.textContent = processedContent;
        }
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // File handling helpers
    function readFile(file) {
        return new Promise((resolve, reject) => {
            if (!file) {
                resolve(null);
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = (e) => {
                resolve(e.target.result);
            };
            
            reader.onerror = (e) => {
                reject(new Error('Error reading file'));
            };
            
            if (file.type.startsWith('text/') || 
                file.type === 'application/json' || 
                file.type === 'application/javascript') {
                reader.readAsText(file);
            } else {
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Function to show loading overlay
    function showLoading(message = 'Processing your request...') {
        if (loadingText) loadingText.textContent = message;
        if (loadingOverlay) {
            loadingOverlay.classList.remove('hidden');
            loadingOverlay.classList.add('flex');
        }
    }
    
    // Function to hide loading overlay
    function hideLoading() {
        if (loadingOverlay) {
            loadingOverlay.classList.remove('flex');
            loadingOverlay.classList.add('hidden');
        }
    }
    
    // EVENT BINDINGS
    
    // Send message when button is clicked
    if (sendButton) {
        sendButton.addEventListener('click', sendMessage);
    }
    
    // Send message when Enter is pressed (but allow shift+enter for new lines)
    if (messageInput) {
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    // Auto-resize the message input as user types
    if (messageInput) {
        messageInput.addEventListener('input', () => {
            // Reset height to auto to get correct scrollHeight
            messageInput.style.height = 'auto';
            
            // Set height to scrollHeight but cap it
            const maxHeight = 150;
            messageInput.style.height = Math.min(messageInput.scrollHeight, maxHeight) + 'px';
            
            // If content would cause scrolling, show scrollbar
            if (messageInput.scrollHeight > maxHeight) {
                messageInput.style.overflowY = 'auto';
            } else {
                messageInput.style.overflowY = 'hidden';
            }
            
            // Update send button state based on content
            if (sendButton) {
                if (messageInput.value.trim() || chatFile) {
                    sendButton.classList.add('bg-indigo-600');
                    sendButton.classList.remove('bg-indigo-400');
                } else {
                    sendButton.classList.add('bg-indigo-400');
                    sendButton.classList.remove('bg-indigo-600');
                }
            }
        });
    }
    
    // File attachment for chat
    if (fileAttachBtn && chatFileInput) {
        fileAttachBtn.addEventListener('click', () => {
            chatFileInput.click();
        });
    }
    
    // Handle file selection for chat
    if (chatFileInput && filePreview) {
        chatFileInput.addEventListener('change', () => {
            if (chatFileInput.files.length > 0) {
                chatFile = chatFileInput.files[0];
                
                // Show file preview
                filePreview.innerHTML = `
                    <div class="file-preview-item">
                        <i class="fas fa-file file-icon"></i>
                        <span class="file-name">${chatFile.name}</span>
                        <i class="fas fa-times file-remove"></i>
                    </div>
                `;
                filePreview.classList.remove('hidden');
                
                // Add event listener to remove button
                filePreview.querySelector('.file-remove').addEventListener('click', () => {
                    chatFile = null;
                    filePreview.innerHTML = '';
                    filePreview.classList.add('hidden');
                    chatFileInput.value = '';
                });
            }
        });
    }
    
    // Clear chat
    if (clearChatBtn && chatMessages) {
        clearChatBtn.addEventListener('click', () => {
            if (confirm('Are you sure you want to clear the entire chat history?')) {
                chatMessages.innerHTML = '';
                chatContext = [];
                
                // Add welcome message back
                const welcomeMessage = document.createElement('div');
                welcomeMessage.className = 'welcome-message mb-4 p-3 bg-indigo-50 rounded-lg border border-indigo-100';
                welcomeMessage.innerHTML = `
                    <p class="font-medium text-indigo-800">👋 Welcome to Jeet's Ultimate Super AI!</p>
                    <p class="text-gray-700 text-sm mt-1">I'm your advanced AI assistant that can route your questions to different AI models. Select a model from the dropdown menu above and ask anything!</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button class="example-prompt px-2 py-1 text-xs bg-indigo-200 hover:bg-indigo-300 rounded-full text-indigo-800 transition-colors">Explain quantum computing</button>
                        <button class="example-prompt px-2 py-1 text-xs bg-indigo-200 hover:bg-indigo-300 rounded-full text-indigo-800 transition-colors">Write a Python function to sort a list</button>
                        <button class="example-prompt px-2 py-1 text-xs bg-indigo-200 hover:bg-indigo-300 rounded-full text-indigo-800 transition-colors">Compare AI models</button>
                    </div>
                `;
                chatMessages.appendChild(welcomeMessage);
                
                // Rebind example prompts
                welcomeMessage.querySelectorAll('.example-prompt').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (messageInput) {
                            messageInput.value = btn.textContent;
                            messageInput.dispatchEvent(new Event('input'));
                            messageInput.focus();
                        }
                    });
                });
            }
        });
    }
    
    // Save chat
    if (saveChatBtn && chatMessages) {
        saveChatBtn.addEventListener('click', () => {
            // Get all messages
            const messages = chatMessages.querySelectorAll('.message');
            let chatContent = '';
            
            messages.forEach(msg => {
                if (msg.classList.contains('user-message')) {
                    chatContent += `User: ${msg.textContent}\n\n`;
                } else if (msg.classList.contains('ai-message')) {
                    // Get text content without the copy button
                    let content = msg.cloneNode(true);
                    content.querySelectorAll('.copy-btn, .code-copy-btn, .message-time, .message-model').forEach(el => el.remove());
                    chatContent += `AI: ${content.textContent}\n\n`;
                }
            });
            
            // Create and download file
            const blob = new Blob([chatContent], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `chat-${new Date().toISOString().slice(0, 10)}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
    
    // Quick model selection
    if (quickModelSelect) {
        quickModelSelect.addEventListener('change', () => {
            const selectedModel = quickModelSelect.value;
            // Validate the model selection
            if (selectedModel && selectedModel.trim() !== '') {
                currentModel = selectedModel;
                localStorage.setItem('model', currentModel);
                
                // Add a system message about model change
                const modelMessage = `Model changed to ${getModelName(currentModel)}`;
                addMessage(modelMessage, false, 'system');
            } else {
                // If empty or invalid selection, default to Claude
                currentModel = defaultClaudeModel;
                quickModelSelect.value = currentModel;
                localStorage.setItem('model', currentModel);
                
                // Add a system message about fallback to default
                const modelMessage = `No model selected. Using ${getModelName(currentModel)} as default.`;
                addMessage(modelMessage, false, 'system');
            }
        });
    }
    
    // Example prompts
    if (examplePrompts && messageInput) {
        examplePrompts.forEach(btn => {
            btn.addEventListener('click', () => {
                messageInput.value = btn.textContent;
                messageInput.dispatchEvent(new Event('input'));
                messageInput.focus();
            });
        });
    }
    
    // Initialize textarea height
    if (messageInput) {
        messageInput.dispatchEvent(new Event('input'));
    }
    
    // Global variable for app name
    const APP_NAME = "Jeet's Ultimate Super AI";
    
    // Debug
    console.log('App initialization complete!');

    // Initialize event listeners
    function initializeEventListeners() {
        // No additional event listeners needed here anymore
    }

    // Initialize
    if (chatMessages && sendButton) {
        // Add event listeners for chat functionality
        setupChatEvents();
        
        // Welcome message
        if (chatMessages.querySelectorAll('.message').length === 0) {
            addWelcomeMessage();
        }
    }
    
    // Initialize direct mode toggle and other event listeners
    initializeEventListeners();
});

// Fallback simulation function for when the server is not available
function simulateResponse(message) {
    return new Promise((resolve) => {
        setTimeout(() => {
            let response = '';
            let modelPrefix = '';
            
            // Add model-specific prefix for non-default models
            if (currentModel !== 'google/gemini-pro') {
                if (currentModel === 'anthropic/claude-3-haiku') {
                    modelPrefix = "As Claude 3 Haiku, ";
                } else if (currentModel === 'meta-llama/llama-3-70b-instruct') {
                    modelPrefix = "As Llama, ";
                } else if (currentModel === 'deepsick/model') {
                    modelPrefix = "As DeepSick AI, ";
                } else if (currentModel === 'x-ai/grok-3-beta') {
                    modelPrefix = "As Grok, ";
                } else if (currentModel === 'openrouter/optimus-alpha') {
                    modelPrefix = "As Optimus, ";
                } else if (currentModel === 'mistralai/mistral-large') {
                    modelPrefix = "As Mistral, ";
                } else if (currentModel === 'cohere/command-r') {
                    modelPrefix = "As Command R, ";
                }
            }
            
            // Simple response generation
            if (message.toLowerCase().includes('hello') || message.toLowerCase().includes('hi')) {
                response = modelPrefix + "Hello! I'm Jeet's Ultimate Super AI. How can I help you today?";
            } else if (message.toLowerCase().includes('help')) {
                response = modelPrefix + "I can help with a wide range of tasks! You can ask me questions, request code explanations, seek advice, or just chat about various topics. What specific help do you need?";
            } else if (message.toLowerCase().includes('feature') || message.toLowerCase().includes('capabilities')) {
                response = modelPrefix + "I have several capabilities including:\n\n• Answering questions on various topics\n• Explaining complex concepts\n• Writing and debugging code\n• Generating creative content\n• Helping with data analysis\n• Providing recommendations\n\nWhat would you like to explore today?";
            } else if (message.toLowerCase().includes('code') || message.toLowerCase().includes('programming')) {
                response = modelPrefix + "Here's a simple JavaScript function example:\n\n```javascript\nfunction calculateArea(radius) {\n    return Math.PI * radius * radius;\n}\n\nconst area = calculateArea(5);\nconsole.log(`The area is ${area.toFixed(2)} square units`);\n```\n\nWould you like me to explain how this works?";
            } else if (message.toLowerCase().includes('error') || message.toLowerCase().includes('data policy')) {
                response = modelPrefix + "I'm currently running in offline mode due to connectivity issues with some AI providers. This is a simulated response. In offline mode, I can still help with basic information, but my capabilities are more limited. For full functionality, please check your internet connection and ensure Direct Mode is enabled.";
            } else {
                response = modelPrefix + "Thanks for your message! I'm currently in offline mode due to connectivity issues, but I'll try to provide a helpful response. What specifically would you like to know about?";
            }
            
            // Return in the expected format matching our API response
            resolve({
                response: response,
                model: currentModel,
                timestamp: new Date().toLocaleString()
            });
        }, 1500);
    });
} 