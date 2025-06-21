<?php
// Evitar iniciar a sessão se já estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions.php';

// Configurações de cabeçalho para segurança
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Função para gerar token CSRF
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para validar token CSRF
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Obter o caminho da URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Função para lidar com o download de documentos assinados
function handleDocumentDownload($contractId) {
    // Verificar se o usuário está autenticado
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo "Acesso não autorizado";
        return;
    }

    // Obter informações do contrato a partir dos arquivos JSON
    $contract = get_contract($contractId);

    if (!$contract || ($contract['status'] ?? '') !== 'completed') {
        header('HTTP/1.1 404 Not Found');
        echo "Documento não encontrado ou não assinado";
        return;
    }

    $filePath = $contract['pdf_path'] ?? null;

    if (!file_exists($filePath)) {
        header('HTTP/1.1 404 Not Found');
        echo "Arquivo do documento não encontrado";
        return;
    }

    // Definir headers para download
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="documento_assinado.pdf"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));

    // Enviar o arquivo
    readfile($filePath);
    exit;
}

// Rotas principais
switch ($path) {
    case '/csrf-token':
        header('Content-Type: application/json');
        echo json_encode(['csrf_token' => generateCsrfToken()]);
        break;

    case '/login':
        // Tratar requisição de login
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Código de login
        } else {
            // Renderizar página de login
        }
        break;

    case '/sign':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Código para processar assinatura
        }
        break;

    case (preg_match('/^\/contract\/([0-9a-f-]+)$/', $path, $matches) ? true : false):
        $contractId = $matches[1];
        // Obter e mostrar detalhes do contrato
        break;

    case (preg_match('/^\/contract\/([0-9a-f-]+)\/document$/', $path, $matches) ? true : false):
        $contractId = $matches[1];
        // Mostrar documento para assinatura
        break;

    case (preg_match('/^\/download-document\/([0-9a-f-]+)$/', $path, $matches) ? true : false):
        $contractId = $matches[1];
        handleDocumentDownload($contractId);
        break;

    default:
        // Renderizar página inicial ou 404
        break;
}