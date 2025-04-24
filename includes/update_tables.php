<?php
define('INCLUDED_FROM_INDEX', true);
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Verificar se o usuário está autenticado como admin ou se a execução é forçada
$is_admin = isset($_SESSION['admin_id']);
$force_update = isset($_GET['force']) && $_GET['force'] == 'true';

if (!$is_admin && !$force_update) {
    echo "Acesso negado. Apenas administradores podem executar esta atualização.";
    exit;
}

$messages = [];
$errors = [];

try {
    echo "<h2>Atualizando tabela users...</h2>";
    
    // Verificar e adicionar coluna email_notifications
    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) NOT NULL DEFAULT 1");
        $messages[] = "Coluna 'email_notifications' adicionada ou já existente na tabela users.";
        echo "Coluna 'email_notifications' adicionada com sucesso.<br>";
    } catch (PDOException $e) {
        $result = $db->query("SHOW COLUMNS FROM users LIKE 'email_notifications'");
        if ($result->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1");
            $messages[] = "Coluna 'email_notifications' adicionada à tabela users.";
            echo "Coluna 'email_notifications' adicionada.<br>";
        }
    }
    
    // Verificar e adicionar coluna profile_visibility
    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_visibility VARCHAR(20) NOT NULL DEFAULT 'private'");
        $messages[] = "Coluna 'profile_visibility' adicionada ou já existente na tabela users.";
        echo "Coluna 'profile_visibility' adicionada com sucesso.<br>";
    } catch (PDOException $e) {
        $result = $db->query("SHOW COLUMNS FROM users LIKE 'profile_visibility'");
        if ($result->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN profile_visibility VARCHAR(20) NOT NULL DEFAULT 'private'");
            $messages[] = "Coluna 'profile_visibility' adicionada à tabela users.";
            echo "Coluna 'profile_visibility' adicionada.<br>";
        }
    }
    
    // Verificar e adicionar coluna two_factor_enabled
    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0");
        $messages[] = "Coluna 'two_factor_enabled' adicionada ou já existente na tabela users.";
        echo "Coluna 'two_factor_enabled' adicionada com sucesso.<br>";
    } catch (PDOException $e) {
        $result = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
        if ($result->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0");
            $messages[] = "Coluna 'two_factor_enabled' adicionada à tabela users.";
            echo "Coluna 'two_factor_enabled' adicionada.<br>";
        }
    }
    
    // Verificar e adicionar coluna theme_preference
    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS theme_preference VARCHAR(20) NOT NULL DEFAULT 'dark'");
        $messages[] = "Coluna 'theme_preference' adicionada ou já existente na tabela users.";
        echo "Coluna 'theme_preference' adicionada com sucesso.<br>";
    } catch (PDOException $e) {
        $result = $db->query("SHOW COLUMNS FROM users LIKE 'theme_preference'");
        if ($result->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN theme_preference VARCHAR(20) NOT NULL DEFAULT 'dark'");
            $messages[] = "Coluna 'theme_preference' adicionada à tabela users.";
            echo "Coluna 'theme_preference' adicionada.<br>";
        }
    }
    
    echo "<p style='color:green'>Atualização concluída com sucesso!</p>";
    echo "<a href='../dashboard/settings.php'>Voltar para a página de configurações</a>";
    $messages[] = "Atualização das tabelas concluída com sucesso!";

} catch (PDOException $e) {
    $errors[] = "Erro ao atualizar tabelas: " . $e->getMessage();
    echo "<p style='color:red'>Erro ao atualizar banco de dados: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualização de Tabelas - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card bg-dark border-light">
                    <div class="card-header bg-primary text-white">
                        <h3>Atualização das Tabelas do Sistema</h3>
                    </div>
                    <div class="card-body">
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
                            <a href="<?php echo $is_admin ? '../admin/index.php' : '../dashboard/settings.php'; ?>" class="btn btn-primary">Voltar ao Sistema</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>