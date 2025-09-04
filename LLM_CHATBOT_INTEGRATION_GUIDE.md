# 🤖 LLM Chatbot Integration Guide

## Overview
This document describes a reusable LLM chatbot system extracted from the PlayerProfit Betting Tracker. The system supports multiple AI providers and features a floating chat interface that can be integrated into any web application.

## Features
- **Multi-Provider Support**: OpenAI GPT-4, Anthropic Claude, Google Gemini
- **Secure API Key Management**: AES-256-GCM encryption with session storage
- **Floating Chat Widget**: Minimizable, responsive UI component
- **System Prompt Customization**: Easy adaptation for different use cases
- **Batch Processing**: Handles large text inputs automatically
- **Mobile Responsive**: Full mobile support with responsive design

## Architecture Components

### 1. Core Files to Extract
```
/includes/ApiKeyManager.php          # Secure API key management
/index.php (lines 1350-2050)       # LLM integration methods
/assets/css/modern-ui.css           # Floating chat styles
JavaScript functions:               # Chat widget functionality
  - sendFloatingChatMessage()
  - addFloatingChatMessage()
  - openFloatingChat()
  - minimizeFloatingChat()
```

### 2. API Integration Methods

#### OpenAI Integration
```php
private function callOpenAI($prompt, $apiKey) {
    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 4000,
        'temperature' => 0.1
    ];
    
    return $this->makeLLMRequest('https://api.openai.com/v1/chat/completions', $data, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
}
```

#### Anthropic Claude Integration
```php
private function callAnthropic($prompt, $apiKey) {
    $data = [
        'model' => 'claude-3-sonnet-20240229',
        'max_tokens' => 4000,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ];
    
    return $this->makeLLMRequest('https://api.anthropic.com/v1/messages', $data, [
        'x-api-key: ' . $apiKey,
        'Content-Type: application/json',
        'anthropic-version: 2023-06-01'
    ]);
}
```

#### Google Gemini Integration
```php
private function callGoogle($prompt, $apiKey) {
    $data = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ]
    ];
    
    return $this->makeLLMRequest(
        "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
        $data,
        ['Content-Type: application/json']
    );
}
```

### 3. Security Features

#### API Key Encryption (AES-256-GCM)
```php
class ApiKeyManager {
    private function encrypt($data) {
        $iv = random_bytes(16);
        $tag = '';
        $encrypted = openssl_encrypt($data, 'AES-256-GCM', $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }
    
    private function decrypt($data) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-GCM', $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : null;
    }
}
```

#### Session-Based Storage
- API keys stored in encrypted $_SESSION
- No database persistence required
- Auto-cleanup on logout
- Server-specific encryption keys

### 4. Floating Chat Widget HTML Structure
```html
<!-- Floating Chat Widget -->
<div id="floating-chat-widget" class="floating-chat-widget minimized">
    <!-- Toggle Button (when minimized) -->
    <div id="chat-toggle-btn" class="chat-toggle-btn" onclick="toggleFloatingChat()">
        <div class="chat-icon">🤖</div>
    </div>
    
    <!-- Chat Window (when expanded) -->
    <div id="chat-window" class="chat-window">
        <div class="chat-header">
            <span>🤖 AI Assistant</span>
            <div class="chat-controls">
                <button onclick="minimizeFloatingChat()">−</button>
                <button onclick="showApiManagement()">⚙️</button>
                <button onclick="closeFloatingChat()">×</button>
            </div>
        </div>
        
        <div id="float-chat-messages" class="chat-messages-area"></div>
        
        <div class="chat-input-area">
            <textarea id="float-chat-input" placeholder="Type your message... (Shift+Enter for new line)"
                     onkeydown="handleChatKeydown(event)"></textarea>
            <button onclick="sendFloatingChatMessage(event)">Send</button>
        </div>
    </div>
</div>
```

### 5. CSS Styling (Glass Morphism)
```css
.floating-chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 10000;
    background: rgba(26, 26, 31, 0.85);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.floating-chat-widget.minimized {
    width: 60px;
    height: 60px;
}

.floating-chat-widget.expanded {
    width: 450px;
    height: 500px;
}
```

## Installation Guide

### Step 1: Extract Core Components
1. Copy `ApiKeyManager.php` to your project's includes folder
2. Extract LLM integration methods from the main class
3. Copy floating chat CSS styles
4. Extract JavaScript chat functions

### Step 2: Customize System Prompt
Replace the betting-specific system prompt with your application's prompt:

```php
// Original (betting-focused)
$systemPrompt = "You are an expert betting data analyst...";

// New (customizable for any domain)
$systemPrompt = "You are an AI assistant for [YOUR_APP_NAME]. Your role is to:
1. [Primary function]
2. [Secondary function]
3. [Additional capabilities]

Guidelines:
- [Behavior rules]
- [Output format]
- [Constraints]";
```

### Step 3: Update API Integration
Modify the API calling method to use your custom prompt:

```php
public function chatWithLLM($userMessage, $apiKey, $provider, $systemPrompt = null) {
    if (!$systemPrompt) {
        $systemPrompt = $this->getDefaultSystemPrompt(); // Your app's prompt
    }
    
    $fullPrompt = $systemPrompt . "\n\nUser: " . $userMessage . "\n\nAssistant:";
    return $this->callLLMAPI($fullPrompt, $apiKey, $provider);
}
```

### Step 4: Configure Security Headers
Add these CSP headers for API access:

```php
header("Content-Security-Policy: default-src 'self'; 
       script-src 'self' 'unsafe-inline' 'unsafe-eval'; 
       connect-src 'self' https://api.openai.com https://api.anthropic.com https://generativelanguage.googleapis.com");
```

### Step 5: Initialize Chat Widget
```javascript
// Initialize chat widget on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set default minimized state
    const widget = document.getElementById('floating-chat-widget');
    if (widget) {
        widget.classList.add('minimized');
    }
});
```

## Customization Examples

### Customer Service Bot
```php
$systemPrompt = "You are a helpful customer service AI assistant for [COMPANY_NAME]. 
Your role is to:
1. Answer customer questions about products and services
2. Help troubleshoot common issues  
3. Escalate complex problems to human agents
4. Maintain a friendly, professional tone

Guidelines:
- Always greet customers warmly
- Ask clarifying questions when needed
- Provide step-by-step solutions
- If you can't help, offer to connect them with a human agent";
```

### Code Review Assistant
```php
$systemPrompt = "You are an expert code review assistant. Your role is to:
1. Analyze code for bugs, security issues, and best practices
2. Suggest improvements and optimizations
3. Explain complex code concepts clearly
4. Provide actionable feedback

Guidelines:
- Focus on critical issues first
- Explain the 'why' behind suggestions
- Provide code examples when helpful
- Be constructive and educational";
```

### Content Creator Helper
```php
$systemPrompt = "You are a creative writing assistant for content creators. Your role is to:
1. Help brainstorm content ideas
2. Improve writing clarity and engagement
3. Suggest SEO optimizations
4. Provide feedback on drafts

Guidelines:
- Match the user's tone and style
- Offer specific, actionable suggestions
- Consider target audience needs
- Balance creativity with practical advice";
```

## API Provider Configuration

### Required API Keys
- **OpenAI**: Get from https://platform.openai.com/api-keys
- **Anthropic**: Get from https://console.anthropic.com/
- **Google**: Get from https://ai.google.dev/

### Rate Limiting & Costs
- Implement usage tracking for cost control
- Add rate limiting per user/session
- Consider provider-specific limits:
  - OpenAI: 3,500 requests/minute
  - Anthropic: 1,000 requests/minute  
  - Google: 300 requests/minute

### Error Handling
```php
private function handleAPIError($response, $provider) {
    if (!$response) {
        return ['success' => false, 'error' => "Failed to connect to {$provider} API"];
    }
    
    // Provider-specific error handling
    switch ($provider) {
        case 'openai':
            if (isset($response['error'])) {
                return ['success' => false, 'error' => $response['error']['message']];
            }
            break;
        // Add other providers...
    }
    
    return ['success' => true, 'response' => $response];
}
```

## Mobile Responsiveness
The chat widget automatically adapts to mobile screens:
- Full-screen on mobile devices
- Touch-friendly controls
- Optimized input handling
- Responsive typography

## Advanced Features

### Batch Processing
For large inputs, the system automatically splits content:
```php
public function chatWithLLMBatched($userMessage, $apiKey, $provider) {
    $chunks = $this->splitDataForAI($userMessage);
    $allResponses = [];
    
    foreach ($chunks as $chunk) {
        $response = $this->chatWithLLM($chunk, $apiKey, $provider);
        $allResponses[] = $response;
    }
    
    return $this->mergeResponses($allResponses);
}
```

### Message History
Add conversation memory by storing chat history:
```javascript
let chatHistory = [];

function addFloatingChatMessage(message, sender) {
    chatHistory.push({
        message: message,
        sender: sender,
        timestamp: new Date()
    });
    
    // Limit history to last 10 messages
    if (chatHistory.length > 10) {
        chatHistory = chatHistory.slice(-10);
    }
}
```

## Deployment Checklist

- [ ] Copy all required PHP files
- [ ] Update system prompts for your use case
- [ ] Configure API provider endpoints
- [ ] Set up secure API key storage
- [ ] Add appropriate CSP headers
- [ ] Test chat widget responsiveness
- [ ] Implement error handling
- [ ] Add usage monitoring
- [ ] Test on mobile devices
- [ ] Configure rate limiting

## Support & Maintenance

### Regular Updates Needed
- Monitor API provider changes
- Update model versions
- Review security patches
- Optimize performance
- Update system prompts based on usage

### Monitoring
- Track API usage and costs
- Monitor error rates
- Log user interactions (anonymized)
- Performance metrics
- Security audit logs

## Conclusion

This LLM chatbot system provides a robust, secure foundation for adding AI capabilities to any web application. The modular design allows easy customization while maintaining security best practices and multi-provider support.

The floating chat interface provides an excellent user experience that doesn't interfere with your main application while keeping AI assistance readily available.

---
*This guide extracted from PlayerProfit Betting Tracker v2.0 - Adapt the system prompts and styling to match your application's needs.*