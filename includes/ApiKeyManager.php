<?php
class ApiKeyManager {
    private $encryptionKey;
    
    public function __construct() {
        // Use server-specific key or environment variable
        $this->encryptionKey = $_ENV['API_ENCRYPTION_KEY'] ?? $this->generateServerKey();
    }
    
    /**
     * Generate a server-specific encryption key
     */
    private function generateServerKey() {
        // Use server-specific data to generate consistent key
        $serverData = $_SERVER['SERVER_NAME'] . $_SERVER['HTTP_HOST'] . __DIR__;
        return hash('sha256', $serverData . 'playerprofit_salt_2025');
    }
    
    /**
     * Encrypt and store API key in session
     */
    public function storeApiKey($provider, $apiKey) {
        if (empty($apiKey)) return false;
        
        // Encrypt the API key
        $encrypted = $this->encrypt($apiKey);
        
        // Store in session until logout (no fixed expiration)
        $_SESSION['api_keys'][$provider] = [
            'key' => $encrypted,
            'expires' => null, // No expiration - persist until session ends
            'hash' => substr(hash('sha256', $apiKey), 0, 8) // For verification
        ];
        
        return true;
    }
    
    /**
     * Retrieve and decrypt API key from session
     */
    public function getApiKey($provider) {
        if (!isset($_SESSION['api_keys'][$provider])) {
            return null;
        }
        
        $stored = $_SESSION['api_keys'][$provider];
        
        // Check expiration (only if expiration is set)
        if ($stored['expires'] !== null && time() > $stored['expires']) {
            unset($_SESSION['api_keys'][$provider]);
            return null;
        }
        
        // Decrypt and return
        return $this->decrypt($stored['key']);
    }
    
    /**
     * Check if API key exists and is valid
     */
    public function hasValidApiKey($provider) {
        $key = $this->getApiKey($provider);
        return !empty($key) && strlen($key) > 10;
    }
    
    /**
     * Get masked API key for display (shows first 8 chars + ***)
     */
    public function getMaskedKey($provider) {
        if (!isset($_SESSION['api_keys'][$provider])) {
            return null;
        }
        
        $hash = $_SESSION['api_keys'][$provider]['hash'];
        return $hash . '***';
    }
    
    /**
     * Clear API key from session
     */
    public function clearApiKey($provider) {
        unset($_SESSION['api_keys'][$provider]);
    }
    
    /**
     * Clear all API keys
     */
    public function clearAllApiKeys() {
        unset($_SESSION['api_keys']);
    }
    
    /**
     * Encrypt data using AES-256-GCM
     */
    private function encrypt($data) {
        $iv = random_bytes(16);
        $tag = '';
        $encrypted = openssl_encrypt($data, 'AES-256-GCM', $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt data using AES-256-GCM
     */
    private function decrypt($data) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-GCM', $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : null;
    }
    
    /**
     * Get session info for debugging
     */
    public function getSessionInfo() {
        $info = [];
        if (isset($_SESSION['api_keys'])) {
            foreach ($_SESSION['api_keys'] as $provider => $data) {
                $info[$provider] = [
                    'has_key' => true,
                    'expires_in' => $data['expires'] === null ? 'until logout' : max(0, $data['expires'] - time()) . ' seconds',
                    'masked' => $this->getMaskedKey($provider)
                ];
            }
        }
        return $info;
    }
}
?>