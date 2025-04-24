<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Check admin authentication
if (!is_admin_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Get cheat_id from GET parameter
$cheat_id = isset($_GET['cheat_id']) ? (int)$_GET['cheat_id'] : 0;

// Validate the cheat_id
if ($cheat_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de cheat inválido']);
    exit;
}

try {
    // Get plans for this cheat
    $stmt = $db->prepare("
        SELECT id, name, description, price, duration_days, is_active 
        FROM cheat_subscription_plans 
        WHERE cheat_id = ? AND is_active = 1
        ORDER BY price ASC
    ");
    $stmt->execute([$cheat_id]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format price for display
    foreach ($plans as &$plan) {
        $plan['price'] = number_format($plan['price'], 2, ',', '.');
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($plans);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}