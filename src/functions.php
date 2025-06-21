<?php

// Ensure contracts directory exists
if (!is_dir(__DIR__ . '/../data/contracts')) {
    mkdir(__DIR__ . '/../data/contracts', 0777, true);
}

// Implementação da classe EmailService no mesmo arquivo
class EmailService {
    public function sendSigningInvitation($email, $token, $contractId) {
        // Configuração básica de email
        $subject = "Convite para assinar documento";
        $baseUrl = isset($_SERVER['HTTP_HOST']) ?
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] :
            "http://localhost";

        $signUrl = "{$baseUrl}/sign.html?contract_id={$contractId}&token={$token}&email=" . urlencode($email);

        $message = "
        <html>
        <head>
            <title>Convite para assinar documento</title>
        </head>
        <body>
            <p>Olá,</p>
            <p>Você foi convidado para assinar um documento eletrônico.</p>
            <p>Para visualizar e assinar o documento, <a href='{$signUrl}'>clique aqui</a>.</p>
            <p>Ou acesse o seguinte link: {$signUrl}</p>
            <p>Este link é único e não deve ser compartilhado.</p>
        </body>
        </html>
        ";

        // Cabeçalhos para envio de email HTML
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: noreply@example.com\r\n";

        // Tenta enviar o email usando a função mail() padrão do PHP
        $sent = mail($email, $subject, $message, $headers);

        // Caso o envio falhe, registra nos logs para depuração
        if (!$sent) {
            error_log("Falha ao enviar email para: {$email}, Link: {$signUrl}");
        }

        return $sent;
    }
}

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_contract_filepath($contractId) {
    return __DIR__ . "/../data/contracts/{$contractId}.json";
}

function load_contract($contractId) {
    $file = get_contract_filepath($contractId);
    if (!file_exists($file)) {
        return false;
    }
    $json = file_get_contents($file);
    return json_decode($json, true);
}

function save_contract($contractId, $data) {
    $file = get_contract_filepath($contractId);
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if (file_put_contents($file, $json) === false) {
        error_log("Failed to save contract data to file: $file");
        return false;
    }
    return true;
}

function create_contract($data) {
    $contractId = generateUUID();
    $data['contract_id'] = $contractId;
    $data['created_at'] = date('c');
    $data['status'] = 'pending';

    // Save PDF if provided in base64 format
    if (!empty($data['pdf_base64'])) {
        $decoded = base64_decode(preg_replace('/^data:application\/pdf;base64,/', '', $data['pdf_base64']));
        if ($decoded !== false) {
            $pdfPath = __DIR__ . "/../data/contracts/{$contractId}.pdf";
            if (file_put_contents($pdfPath, $decoded) !== false) {
                $data['pdf_path'] = $pdfPath;
            }
        }
        unset($data['pdf_base64']);
    }

    // Process each signer: generate token and set initial status
    foreach ($data['signers'] as &$signer) {
        $signer['token'] = generateUUID();
        $signer['status'] = 'pending';

        // Send real email to signer
        try {
            sendRealEmail($signer['email'], $signer['token'], $contractId);
        } catch (Exception $e) {
            // Log the error but continue with contract creation
            error_log("Failed to send email to {$signer['email']}: " . $e->getMessage());
        }
    }
    unset($signer);

    // Save contract data
    if (!save_contract($contractId, $data)) {
        error_log("Could not save contract data for contract ID: $contractId");
        http_response_code(500);
        die(json_encode(['error' => 'Could not save contract data.']));
    }

    // Return contract ID and signer info (including tokens for testing)
    return [
        'contract_id' => $contractId,
        'status' => 'pending',
        'signers' => $data['signers']
    ];
}

function sign_contract($input) {
    $contractId = $input['contract_id'];
    $email = $input['signer_email'];
    $token = $input['token'];

    $contract = load_contract($contractId);
    if (!$contract) {
        http_response_code(404);
        die(json_encode(['error' => 'Contract not found.']));
    }

    // Find and verify signer
    $found = false;
    $allSigned = true;

    foreach ($contract['signers'] as &$signer) {
        if (strcasecmp($signer['email'], $email) === 0) {
            if ($signer['token'] !== $token) {
                http_response_code(401);
                die(json_encode(['error' => 'Invalid signature token.']));
            }
            if ($signer['status'] === 'signed') {
                http_response_code(400);
                die(json_encode(['error' => 'Document already signed by this signer.']));
            }
            $signer['status'] = 'signed';
            $signer['signed_at'] = date('c');
            $found = true;
        }
        // Check if any signer hasn't signed yet
        if ($signer['status'] !== 'signed') {
            $allSigned = false;
        }
    }
    unset($signer);

    if (!$found) {
        http_response_code(404);
        die(json_encode(['error' => 'Signer not found for this contract.']));
    }

    // Update contract status if all have signed
    if ($allSigned) {
        $contract['status'] = 'completed';
        $contract['completed_at'] = date('c');
    }

    // Save updated contract data
    if (!save_contract($contractId, $contract)) {
        error_log("Could not update contract data for contract ID: $contractId");
        http_response_code(500);
        die(json_encode(['error' => 'Could not update contract data.']));
    }

    return $contract;
}

function list_contracts() {
    $contracts = [];
    $dir = __DIR__ . "/../data/contracts/";
    if (is_dir($dir)) {
        foreach (glob($dir . "*.json") as $file) {
            $contract = json_decode(file_get_contents($file), true);
            if ($contract) {
                $contracts[] = $contract;
            }
        }
    }
    return $contracts;
}

function get_contract($contractId) {
    return load_contract($contractId);
}

function update_contract($contractId, $updateData) {
    $contract = load_contract($contractId);
    if (!$contract) {
        return false;
    }

    // Only allow updating certain fields
    $allowedFields = ['title', 'contract_text'];
    foreach ($allowedFields as $field) {
        if (isset($updateData[$field])) {
            $contract[$field] = $updateData[$field];
        }
    }

    $contract['updated_at'] = date('c');

    return save_contract($contractId, $contract);
}

function delete_contract($contractId) {
    $file = get_contract_filepath($contractId);
    if (file_exists($file)) {
        return unlink($file);
    }
    return false;
}

function sendRealEmail($email, $token, $contractId) {
    $emailService = new EmailService();
    return $emailService->sendSigningInvitation($email, $token, $contractId);
}

// Adicione estas funções ao final do arquivo functions.php

function generate_security_token() {
    if (!isset($_SESSION)) {
        session_start();
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['security_token'] = $token;
    $_SESSION['token_time'] = time();

    return $token;
}

function verify_security_token($token) {
    if (!isset($_SESSION)) {
        session_start();
    }

    // Verifica se o token existe na sessão
    if (!isset($_SESSION['security_token'])) {
        return false;
    }

    // Verifica se o token é válido
    if ($_SESSION['security_token'] !== $token) {
        return false;
    }

    // Verifica se o token não expirou (30 minutos)
    if (time() - $_SESSION['token_time'] > 1800) {
        return false;
    }

    return true;
}

// Função para desativar a verificação CSRF temporariamente (use com cuidado)
function disable_csrf_check() {
    if (!isset($_SESSION)) {
        session_start();
    }

    // Define um token fixo para contornar a verificação
    $_SESSION['security_token'] = 'fixed_token_for_development';
    $_SESSION['token_time'] = time() + 86400; // Expira em 24 horas

    return $_SESSION['security_token'];
}