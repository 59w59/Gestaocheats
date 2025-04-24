<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    // Validar dados
    $cheat_id = isset($_POST['cheat_id']) ? (int)$_POST['cheat_id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $duration_days = isset($_POST['duration_days']) ? (int)$_POST['duration_days'] : 0;
    $features = trim($_POST['features'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validações
    if ($cheat_id <= 0) throw new Exception('Cheat inválido');
    if (empty($name)) throw new Exception('Nome é obrigatório');
    if ($price <= 0) throw new Exception('Preço deve ser maior que zero');
    if ($duration_days <= 0) throw new Exception('Duração deve ser maior que zero');

    // Verificar se o cheat existe
    $stmt = $db->prepare("SELECT id FROM cheats WHERE id = ?");
    $stmt->execute([$cheat_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Cheat não encontrado');
    }

    // Inserir plano
    $stmt = $db->prepare("
        INSERT INTO cheat_plans (
            cheat_id, name, description, price, 
            duration_days, features, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $cheat_id,
        $name,
        $description,
        $price,
        $duration_days,
        $features,
        $is_active
    ]);

    $_SESSION['success'] = 'Plano adicionado com sucesso!';
    header("Location: cheat_plans.php?id=$cheat_id");
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: cheat_plans.php?id=$cheat_id");
    exit;
}