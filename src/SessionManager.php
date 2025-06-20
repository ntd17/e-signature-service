<?php

class SessionManager {
    private $sessionTimeout;
    private $sessionPath;

    public function __construct($timeout = 3600) { // Default 1 hour timeout
        $this->sessionTimeout = $timeout;
        $this->sessionPath = __DIR__ . '/../data/sessions/';
        
        if (!is_dir($this->sessionPath)) {
            mkdir($this->sessionPath, 0777, true);
        }
    }

    public function createSigningSession($contractId, $email, $token) {
        $sessionId = bin2hex(random_bytes(32));
        $sessionData = [
            'contract_id' => $contractId,
            'email' => $email,
            'token' => $token,
            'created_at' => time(),
            'expires_at' => time() + $this->sessionTimeout
        ];

        $file = $this->sessionPath . $sessionId . '.json';
        file_put_contents($file, json_encode($sessionData));
        return $sessionId;
    }

    public function validateSession($sessionId) {
        $file = $this->sessionPath . $sessionId . '.json';
        if (!file_exists($file)) {
            return false;
        }

        $sessionData = json_decode(file_get_contents($file), true);
        if (!$sessionData) {
            return false;
        }

        // Check if session has expired
        if (time() > $sessionData['expires_at']) {
            unlink($file); // Remove expired session
            return false;
        }

        return $sessionData;
    }

    public function clearSession($sessionId) {
        $file = $this->sessionPath . $sessionId . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Cleanup expired sessions
    public function cleanupExpiredSessions() {
        foreach (glob($this->sessionPath . "*.json") as $file) {
            $sessionData = json_decode(file_get_contents($file), true);
            if ($sessionData && time() > $sessionData['expires_at']) {
                unlink($file);
            }
        }
    }
}
