<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Get cheat info before deletion
    $stmt = $db->prepare("SELECT file_path, image FROM cheats WHERE id = ?");
    $stmt->execute([$id]);
    $cheat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cheat) {
        throw new Exception('Cheat não encontrado');
    }

    // Delete cheat from database
    $stmt = $db->prepare("DELETE FROM cheats WHERE id = ?");
    $stmt->execute([$id]);

    // Delete associated files
    if (!empty($cheat['file_path'])) {
        $file_path = '../../../uploads/cheats/' . $cheat['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    if (!empty($cheat['image'])) {
        $image_path = '../../../assets/images/cheats/' . $cheat['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    header('Location: cheats.php?success=deleted');
    exit;

} catch (Exception $e) {
    error_log("Error deleting cheat: " . $e->getMessage());
    header('Location: cheats.php?error=' . urlencode($e->getMessage()));
    exit;
}