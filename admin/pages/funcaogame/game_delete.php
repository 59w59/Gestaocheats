<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';
require_once '../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Pegar informações do jogo antes de deletar (para imagem)
    $stmt = $db->prepare("SELECT image FROM games WHERE id = ?");
    $stmt->execute([$id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    // Deletar o jogo
    $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        // Deletar a imagem se existir
        if (!empty($game['image'])) {
            $image_path = '../../assets/images/games/' . $game['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        header('Location: games.php?success=deleted');
        exit;
    } else {
        throw new Exception('Jogo não encontrado');
    }

} catch (Exception $e) {
    error_log("Error deleting game: " . $e->getMessage());
    header('Location: games.php?error=' . urlencode($e->getMessage()));
    exit;
}