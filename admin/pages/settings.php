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

// Processar atualizações de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'site_name' => $_POST['site_name'] ?? '',
            'site_description' => $_POST['site_description'] ?? '',
            'support_email' => $_POST['support_email'] ?? '',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'registration_enabled' => isset($_POST['registration_enabled']) ? 1 : 0,
            'max_login_attempts' => (int)$_POST['max_login_attempts'] ?? 5,
            'session_lifetime' => (int)$_POST['session_lifetime'] ?? 1440,
            'default_currency' => $_POST['default_currency'] ?? 'BRL',
            'date_format' => $_POST['date_format'] ?? 'd/m/Y',
            'time_format' => $_POST['time_format'] ?? 'H:i',
            'timezone' => $_POST['timezone'] ?? 'America/Sao_Paulo'
        ];

        // Atualizar cada configuração
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) 
                                VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        // Upload do logo se fornecido
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['site_logo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $upload_path = '../../assets/images/';
                $new_filename = 'logo.' . $ext;
                
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_path . $new_filename)) {
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) 
                                        VALUES ('site_logo', ?) 
                                        ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$new_filename, $new_filename]);
                }
            }
        }

        $success_message = "Configurações atualizadas com sucesso!";
    } catch (PDOException $e) {
        error_log("Error in settings.php: " . $e->getMessage());
        $error_message = "Ocorreu um erro ao salvar as configurações.";
    }
}

// Carregar configurações atuais
try {
    $stmt = $db->query("SELECT * FROM settings");
    $current_settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error loading settings: " . $e->getMessage());
    $error_message = "Erro ao carregar as configurações.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/variables.css">
    <link rel="stylesheet" href="../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <link rel="stylesheet" href="../../assets/css/scroll.css">
</head>

<body class="admin-page">
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Configurações do Sistema</h3>
                </div>
                <div class="admin-card-body">
                    <form action="./funcaosettings/update_settings.php" method="POST" enctype="multipart/form-data">
                        <!-- Configurações Gerais -->
                        <div class="settings-section">
                            <h4>Configurações Gerais</h4>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="site_name">Nome do Site</label>
                                        <input type="text" id="site_name" name="site_name" class="form-control"
                                               value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="site_description">Descrição do Site</label>
                                        <input type="text" id="site_description" name="site_description" class="form-control"
                                               value="<?php echo htmlspecialchars($current_settings['site_description'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="site_logo">Logo do Site</label>
                                        <input type="file" id="site_logo" name="site_logo" class="form-control" accept="image/*">
                                        <?php if (!empty($current_settings['site_logo'])): ?>
                                            <div class="current-logo mt-2">
                                                <img src="../../assets/images/<?php echo htmlspecialchars($current_settings['site_logo']); ?>" 
                                                     alt="Logo atual" style="max-height: 50px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="support_email">Email de Suporte</label>
                                        <input type="email" id="support_email" name="support_email" class="form-control"
                                               value="<?php echo htmlspecialchars($current_settings['support_email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configurações de Sistema -->
                        <div class="settings-section">
                            <h4>Configurações de Sistema</h4>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" class="form-check-input"
                                               <?php echo ($current_settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">Modo de Manutenção</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" id="registration_enabled" name="registration_enabled" class="form-check-input"
                                               <?php echo ($current_settings['registration_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="registration_enabled">Permitir Novos Registros</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="max_login_attempts">Tentativas Máximas de Login</label>
                                        <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-control"
                                               value="<?php echo htmlspecialchars($current_settings['max_login_attempts'] ?? '5'); ?>"
                                               min="1" max="10">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="session_lifetime">Duração da Sessão (minutos)</label>
                                        <input type="number" id="session_lifetime" name="session_lifetime" class="form-control"
                                               value="<?php echo htmlspecialchars($current_settings['session_lifetime'] ?? '1440'); ?>"
                                               min="5" max="10080">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configurações Regionais -->
                        <div class="settings-section">
                            <h4>Configurações Regionais</h4>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="default_currency">Moeda Padrão</label>
                                        <select id="default_currency" name="default_currency" class="form-select">
                                            <option value="BRL" <?php echo ($current_settings['default_currency'] ?? '') === 'BRL' ? 'selected' : ''; ?>>Real (BRL)</option>
                                            <option value="USD" <?php echo ($current_settings['default_currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>Dólar (USD)</option>
                                            <option value="EUR" <?php echo ($current_settings['default_currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date_format">Formato de Data</label>
                                        <select id="date_format" name="date_format" class="form-select">
                                            <option value="d/m/Y" <?php echo ($current_settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/AAAA</option>
                                            <option value="m/d/Y" <?php echo ($current_settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/AAAA</option>
                                            <option value="Y-m-d" <?php echo ($current_settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>AAAA-MM-DD</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="timezone">Fuso Horário</label>
                                        <select id="timezone" name="timezone" class="form-select">
                                            <?php
                                            $timezones = DateTimeZone::listIdentifiers();
                                            $current_timezone = $current_settings['timezone'] ?? 'America/Sao_Paulo';
                                            foreach ($timezones as $timezone) {
                                                echo '<option value="' . htmlspecialchars($timezone) . '"' . 
                                                     ($timezone === $current_timezone ? ' selected' : '') . '>' . 
                                                     htmlspecialchars($timezone) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Configurações
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Restaurar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Backup Section -->
            <div class="admin-card mt-4">
                <div class="admin-card-header">
                    <h3>Backup do Sistema</h3>
                </div>
                <div class="admin-card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="backup-info">
                                <h5>Último Backup</h5>
                                <p>
                                    <?php
                                    $last_backup = $current_settings['last_backup'] ?? null;
                                    if ($last_backup) {
                                        echo 'Realizado em: ' . date('d/m/Y H:i', strtotime($last_backup));
                                    } else {
                                        echo 'Nenhum backup realizado.';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="./funcaosettings/backup_generate.php" class="btn btn-success">
                                <i class="fas fa-download"></i> Gerar Novo Backup
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview da imagem do logo
        document.getElementById('site_logo').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentLogo = document.querySelector('.current-logo');
                    if (currentLogo) {
                        currentLogo.innerHTML = `<img src="${e.target.result}" alt="Nova logo" style="max-height: 50px;">`;
                    }
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        // Confirmar reset do formulário
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja restaurar todas as configurações?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>