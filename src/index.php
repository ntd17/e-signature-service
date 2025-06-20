<?php
require_once 'functions.php';
require_once 'EmailService.php';
require_once 'RateLimiter.php';
require_once 'SessionManager.php';

// Initialize session manager
$sessionManager = new SessionManager(3600); // 1 hour timeout

// Initialize rate limiter
$rateLimiter = new RateLimiter(60, 10); // 10 requests per minute

// Initialize session for CSRF protection
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// For local development or non-HTTPS environments, disable secure cookie to allow session cookie over HTTP
if (php_sapi_name() === 'cli-server' || $_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    $secureCookie = false;
}

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $secureCookie,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set CORS headers for API endpoints
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origin");
if ($origin !== '*') {
    header("Access-Control-Allow-Credentials: true");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
    header("Access-Control-Max-Age: 86400"); // 24 hours cache
    exit(0);
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route API requests
if (strpos($uri, '/api/') === 0) {
    require_once __DIR__ . '/api/index.php';
    exit;
}

// Redirect root to index.html
if ($uri === '/') {
    header('Location: /public/index.html');
    exit;
}

// Serve static files from public directory
if (strpos($uri, '/public/') === 0) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $content_types = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json'
        ];
        $content_type = $content_types[$ext] ?? 'text/plain';
        header("Content-Type: $content_type");
        readfile($file);
        exit;
    }
}

// API request handling
header("Content-Type: application/json");

// Get client IP for rate limiting
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

// Check rate limit
if (!$rateLimiter->isAllowed($clientIp)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Validate CSRF token for POST requests
if ($method === 'POST') {
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';
    
    if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token']);
        exit;
    }
}

// Clean up expired sessions periodically (1% chance)
if (mt_rand(1, 100) === 1) {
    $sessionManager->cleanupExpiredSessions();
}

// Input sanitization function
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

try {
    if ($method == 'POST' && $uri == '/contract') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['title'], $input['contract_text'], $input['signers']) || !is_array($input['signers'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid contract data.']);
            exit;
        }

        // Sanitize input
        $input = sanitizeInput($input);
        
        // Validate email addresses
        foreach ($input['signers'] as $signer) {
            if (!filter_var($signer['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid email address provided.']);
                exit;
            }
        }

        $result = create_contract($input);
        http_response_code(201);
        echo json_encode($result);
        exit;

    } elseif ($method == 'POST' && $uri == '/sign') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['contract_id'], $input['signer_email'], $input['token'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid signature data.']);
            exit;
        }

        // Create or validate signing session
        $sessionId = $_COOKIE['signing_session'] ?? null;
        if ($sessionId) {
            $sessionData = $sessionManager->validateSession($sessionId);
            if (!$sessionData || 
                $sessionData['contract_id'] !== $input['contract_id'] || 
                $sessionData['email'] !== $input['signer_email'] || 
                $sessionData['token'] !== $input['token']) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid or expired signing session.']);
                exit;
            }
        } else {
            $sessionId = $sessionManager->createSigningSession(
                $input['contract_id'],
                $input['signer_email'],
                $input['token']
            );
            setcookie('signing_session', $sessionId, [
                'expires' => time() + 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
                'secure' => true
            ]);
        }

        // Sanitize input
        $input = sanitizeInput($input);
        
        // Validate email
        if (!filter_var($input['signer_email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email address.']);
            exit;
        }

        try {
            $result = sign_contract($input);
            // Clear session after successful signing
            $sessionManager->clearSession($sessionId);
            setcookie('signing_session', '', time() - 3600, '/');
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;

    } elseif ($method == 'GET' && preg_match('#^/contract/([^/]+)$#', $uri, $matches)) {
        $contractId = sanitizeInput($matches[1]);
        $contract = load_contract($contractId);
        if (!$contract) {
            http_response_code(404);
            echo json_encode(['error' => 'Contract not found.']);
            exit;
        }
        echo json_encode($contract);
        exit;

    } elseif ($method == 'GET' && preg_match('#^/contract/([^/]+)/document$#', $uri, $matches)) {
        $contractId = sanitizeInput($matches[1]);
        $contract = load_contract($contractId);
        if (!$contract) {
            http_response_code(404);
            echo json_encode(['error' => 'Contract not found.']);
            exit;
        }
        header("Content-Type: text/plain");
        echo $contract['contract_text'];
        exit;

    } elseif ($method == 'GET' && $uri == '/contracts') {
        $contracts = list_contracts();
        echo json_encode($contracts);
        exit;

    } elseif ($method == 'GET' && $uri == '/csrf-token') {
        echo json_encode(['token' => $_SESSION['csrf_token']]);
        exit;
    } elseif ($method == 'GET' && $uri == '/session/validate') {
        $sessionId = $_COOKIE['signing_session'] ?? null;
        if (!$sessionId) {
            http_response_code(401);
            echo json_encode(['valid' => false]);
            exit;
        }
        
        $sessionData = $sessionManager->validateSession($sessionId);
        echo json_encode([
            'valid' => (bool)$sessionData,
            'expires_at' => $sessionData ? $sessionData['expires_at'] : null
        ]);
        exit;

    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}
