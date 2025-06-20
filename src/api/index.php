<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../functions.php';
require_once '../EmailService.php';
require_once '../RateLimiter.php';
require_once '../SessionManager.php';

// API Rate limiting
$rateLimiter = new RateLimiter(60, 30); // 30 requests per minute for API
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$rateLimiter->isAllowed($clientIp)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

/*
// Simple API key authentication (in production, use proper JWT or OAuth)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validApiKeys = ['demo-api-key-123', 'test-key-456']; // In production, store in database

if (!in_array($apiKey, $validApiKeys)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}
*/

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

// Remove optional leading segments like 'src' or 'api'
if (!empty($pathParts) && $pathParts[0] === 'src') {
    array_shift($pathParts);
}
if (!empty($pathParts) && $pathParts[0] === 'api') {
    array_shift($pathParts);
}

// Route API requests
switch ($pathParts[0] ?? '') {
    case 'csrf-token':
        if ($method === 'GET') {
            handleCsrfToken();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    case 'contracts':
        handleContractsAPI($method, $pathParts);
        break;
    case 'signatures':
        handleSignaturesAPI($method, $pathParts);
        break;
    case 'status':
        handleStatusAPI($method, $pathParts);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
        break;
}

function handleCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo json_encode(['token' => $_SESSION['csrf_token']]);
}

function handleContractsAPI($method, $pathParts) {
    switch ($method) {
        case 'GET':
            if (isset($pathParts[1])) {
                getContract($pathParts[1]);
            } else {
                listContracts();
            }
            break;
        case 'POST':
            createContractAPI();
            break;
        case 'PUT':
            if (isset($pathParts[1])) {
                updateContract($pathParts[1]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Contract ID required']);
            }
            break;
        case 'DELETE':
            if (isset($pathParts[1])) {
                deleteContract($pathParts[1]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Contract ID required']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function createContractAPI() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['title'], $input['contract_text'], $input['signers'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: title, contract_text, signers']);
        return;
    }
    
    // Validate signers
    if (!is_array($input['signers']) || empty($input['signers'])) {
        http_response_code(400);
        echo json_encode(['error' => 'At least one signer is required']);
        return;
    }
    
    foreach ($input['signers'] as $signer) {
        if (!isset($signer['email']) || !filter_var($signer['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email address in signers']);
            return;
        }
    }
    
    try {
        $result = create_contract($input);
        http_response_code(201);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getContract($contractId) {
    try {
        $contract = get_contract($contractId);
        if ($contract) {
            echo json_encode($contract);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Contract not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function listContracts() {
    try {
        $contracts = list_contracts();
        echo json_encode(['contracts' => $contracts]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateContract($contractId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $result = update_contract($contractId, $input);
        if ($result) {
            echo json_encode(['message' => 'Contract updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Contract not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteContract($contractId) {
    try {
        $result = delete_contract($contractId);
        if ($result) {
            echo json_encode(['message' => 'Contract deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Contract not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function signContractAPI() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['contract_id'], $input['signer_email'], $input['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: contract_id, signer_email, token']);
        return;
    }
    
    try {
        $result = sign_contract($input);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getSignatureStatus($contractId) {
    try {
        $contract = get_contract($contractId);
        if (is_array($contract)) {
            echo json_encode([
                'contract_id' => $contractId,
                'status' => $contract['status'] ?? null,
                'signers' => $contract['signers'] ?? null,
                'created_at' => $contract['created_at'] ?? null,
                'completed_at' => $contract['completed_at'] ?? null
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Invalid contract data']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
