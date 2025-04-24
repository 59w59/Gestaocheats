<?php
// filepath: c:\xampp\htdocs\Gestaocheats\admin\pages\funcaosupport\update_tables.php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../../../admin/login.php');
    exit;
}

$messages = [];
$errors = [];

try {
    // Verificar se a coluna ticket_id existe na tabela support_tickets
    $stmt = $db->prepare("SHOW COLUMNS FROM support_tickets LIKE 'ticket_id'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Alterar a tabela para adicionar coluna ticket_id
        $db->exec("
            ALTER TABLE `support_tickets`
            ADD COLUMN `ticket_id` VARCHAR(20) NOT NULL AFTER `id`
        ");
        $messages[] = "Coluna ticket_id adicionada à tabela support_tickets.";
        
        // Atualizar tickets existentes com IDs de ticket formatados
        $db->exec("
            UPDATE `support_tickets`
            SET `ticket_id` = CONCAT('TK-', LPAD(HEX(id), 8, '0'))
        ");
        $messages[] = "IDs de ticket formatados criados para todos os tickets existentes.";
    } else {
        $messages[] = "A coluna ticket_id já existe na tabela support_tickets.";
    }
    
    // Verificar se a coluna category existe
    $stmt = $db->prepare("SHOW COLUMNS FROM support_tickets LIKE 'category'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Alterar a tabela para adicionar coluna category
        $db->exec("
            ALTER TABLE `support_tickets`
            ADD COLUMN `category` ENUM('technical','billing','account','other') NOT NULL DEFAULT 'technical' AFTER `message`
        ");
        $messages[] = "Coluna category adicionada à tabela support_tickets.";
    } else {
        $messages[] = "A coluna category já existe na tabela support_tickets.";
    }
    
    // Adicionar índices para melhor performance
    try {
        $db->exec("ALTER TABLE `support_tickets` ADD INDEX `idx_ticket_id` (`ticket_id`)");
        $messages[] = "Índice idx_ticket_id adicionado.";
    } catch (PDOException $e) {
        $messages[] = "O índice idx_ticket_id já existe ou houve um erro ao criá-lo.";
    }
    
    try {
        $db->exec("ALTER TABLE `support_tickets` ADD INDEX `idx_status` (`status`)");
        $messages[] = "Índice idx_status adicionado.";
    } catch (PDOException $e) {
        $messages[] = "O índice idx_status já existe ou houve um erro ao criá-lo.";
    }
    
    try {
        $db->exec("ALTER TABLE `support_tickets` ADD INDEX `idx_category` (`category`)");
        $messages[] = "Índice idx_category adicionado.";
    } catch (PDOException $e) {
        $messages[] = "O índice idx_category já existe ou houve um erro ao criá-lo.";
    }
    
    try {
        $db->exec("ALTER TABLE `support_tickets` ADD INDEX `idx_priority` (`priority`)");
        $messages[] = "Índice idx_priority adicionado.";
    } catch (PDOException $e) {
        $messages[] = "O índice idx_priority já existe ou houve um erro ao criá-lo.";
    }
    
    $messages[] = "Atualização das tabelas concluída com sucesso!";

} catch (PDOException $e) {
    $errors[] = "Erro ao atualizar tabelas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualização de Tabelas - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/variables.css">
    <link rel="stylesheet" href="../../../assets/css/AdminPanelStyles.css">
</head>
<body class="admin-page">
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>Atualização das Tabelas do Sistema</h3>
                    </div>
                    <div class="admin-card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h4>Erros encontrados:</h4>
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($messages)): ?>
                            <div class="alert alert-success">
                                <h4>Atualizações realizadas:</h4>
                                <ul>
                                    <?php foreach ($messages as $message): ?>
                                        <li><?php echo htmlspecialchars($message); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="../../index.php" class="btn btn-primary">Voltar ao Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>