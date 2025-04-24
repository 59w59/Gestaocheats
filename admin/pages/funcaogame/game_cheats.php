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
    $game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($game_id <= 0) {
        throw new Exception('ID do jogo inválido');
    }

    // Buscar informações do jogo
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        throw new Exception('Jogo não encontrado');
    }

    // Buscar cheats do jogo
    $stmt = $db->prepare("
        SELECT c.* 
        FROM cheats c 
        WHERE c.game_id = ? 
        ORDER BY c.name ASC
    ");
    $stmt->execute([$game_id]);
    $cheats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error in game_cheats.php: " . $e->getMessage());
    header('Location: games.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cheats - <?php echo htmlspecialchars($game['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/variables.css">
    <link rel="stylesheet" href="../../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../../assets/css/custom.css">
    <link rel="stylesheet" href="../../..assets/css/scroll.css">
</head>

<body class="admin-page">
    <?php include '../../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>
                        <i class="fas fa-code"></i>
                        Cheats para <?php echo htmlspecialchars($game['name']); ?>
                    </h3>
                    <div>
                        <a href="../games.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                        <a href="../funcaocheat/cheat_add.php?game_id=<?php echo $game_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Novo Cheat
                        </a>
                    </div>
                </div>

                <div class="admin-card-body">
                    <?php if (empty($cheats)): ?>
                        <div class="empty-state">
                            <i class="fas fa-code fa-3x mb-3"></i>
                            <p>Nenhum cheat cadastrado para este jogo</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Versão</th>
                                        <th>Status</th>
                                        <th>Downloads</th>
                                        <th>Última Atualização</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cheats as $cheat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cheat['name']); ?></td>
                                            <td><?php echo htmlspecialchars($cheat['version']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $cheat['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $cheat['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($cheat['download_count']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($cheat['updated_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="cheat_edit.php?id=<?php echo $cheat['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="cheat_plans.php?id=<?php echo $cheat['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-tags"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDeleteCheat(<?php echo $cheat['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDeleteCheat(id) {
            if (confirm('Tem certeza que deseja excluir este cheat? Esta ação não pode ser desfeita.')) {
                window.location.href = `cheat_delete.php?id=${id}`;
            }
        }
    </script>
</body>
</html>