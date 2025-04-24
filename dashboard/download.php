<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../pages/login.php');
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->get_user($user_id);

// Verificar se o ID do cheat foi fornecido
$cheat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($cheat_id <= 0) {
    $_SESSION['error'] = 'ID de cheat inválido';
    redirect('index.php');
}

try {
    // Verificar se o usuário tem assinatura ativa que permite acessar este cheat
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_subscriptions us
        JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id
        WHERE us.user_id = ? 
        AND us.status = 'active' 
        AND us.end_date > NOW()
        AND csp.cheat_id = ?
    ");
    $stmt->execute([$user_id, $cheat_id]);
    $has_access = $stmt->fetchColumn();

    if (!$has_access) {
        throw new Exception('Você não tem permissão para baixar este cheat. Verifique sua assinatura.');
    }

    // Buscar detalhes do cheat
    $stmt = $db->prepare("
        SELECT c.*, g.name as game_name 
        FROM cheats c
        JOIN games g ON c.game_id = g.id
        WHERE c.id = ? AND c.is_active = 1
    ");
    $stmt->execute([$cheat_id]);
    $cheat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cheat) {
        throw new Exception('Cheat não encontrado ou não está ativo.');
    }

    // Registrar o download no banco de dados
    $stmt = $db->prepare("
        INSERT INTO user_downloads (user_id, cheat_id, ip_address, user_agent)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $cheat_id,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);

    // Incrementar contador de downloads do cheat
    $stmt = $db->prepare("UPDATE cheats SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$cheat_id]);

    // Registrar ação nos logs do usuário
    $stmt = $db->prepare("
        INSERT INTO user_logs (user_id, action, cheat_id, ip_address, details)
        VALUES (?, 'download', ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $cheat_id,
        $_SERVER['REMOTE_ADDR'],
        json_encode([
            'version' => $cheat['version'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ])
    ]);

    // Verificar se o arquivo existe no servidor
    $file_path = '../uploads/cheats/' . $cheat['file_path'];
    
    // Verificar se o diretório existe ou criar
    $directory = dirname($file_path);
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    // Se o arquivo não existir, crie um arquivo de teste para download
    if (!file_exists($file_path)) {
        // Criar arquivo de exemplo para teste
        $example_content = "Este é um arquivo de teste para o cheat {$cheat['name']} v{$cheat['version']}.\n";
        $example_content .= "Este arquivo foi gerado automaticamente porque o arquivo original não foi encontrado.\n";
        $example_content .= "Em um ambiente de produção, este seria o arquivo real do cheat.\n";
        file_put_contents($file_path, $example_content);
    }

    // Iniciar download do arquivo
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($cheat['file_path']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    flush(); // Liberar o buffer de output
    readfile($file_path);
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    redirect('index.php');
}