<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    die('Direct access not permitted');
}
require_once(__DIR__ . '/../../includes/config.php');
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-header">
        <div class="admin-logo">
            <span class="logo-text"><?php echo SITE_NAME; ?></span>
            <span class="admin-badge">ADMIN</span>
        </div>
        <button class="sidebar-toggle d-md-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="admin-nav">
        <div class="nav-section">
            <h6 class="nav-section-title">Principal</h6>
            <ul>
                <li>
                    <a href="/Gestaocheats/admin/index.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="/Gestaocheats/admin/pages/users.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Usuários
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <h6 class="nav-section-title">Gerenciamento</h6>
            <ul>
                <li>
                    <a href="/Gestaocheats/admin/pages/subscriptions.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'subscriptions.php' ? 'active' : ''; ?>">
                        <i class="fas fa-credit-card"></i> Assinaturas
                    </a>
                </li>
                <li>
                    <a href="/Gestaocheats/admin/pages/plans.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'plans.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i> Planos
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <h6 class="nav-section-title">Conteúdo</h6>
            <ul>
                <li>
                    <a href="/Gestaocheats/admin/pages/games.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'games.php' ? 'active' : ''; ?>">
                        <i class="fas fa-gamepad"></i> Jogos
                    </a>
                </li>
                <li>
                    <a href="/Gestaocheats/admin/pages/cheats.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'cheats.php' ? 'active' : ''; ?>">
                        <i class="fas fa-code"></i> Cheats
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <h6 class="nav-section-title">Sistema</h6>
            <ul>
                <li>
                    <a href="/Gestaocheats/admin/pages/transactions.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i> Transações
                    </a>
                </li>
                <li>
                    <a href="/Gestaocheats/admin/pages/support.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'active' : ''; ?>">
                        <i class="fas fa-headset"></i> Suporte
                        <?php if (function_exists('get_pending_support_tickets') && get_pending_support_tickets() > 0): ?>
                            <span class="badge bg-danger"><?php echo get_pending_support_tickets(); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="/Gestaocheats/admin/pages/logs.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Logs
                    </a>
                </li>
                <li>
                    <a href="/Gestaocheats/admin/pages/settings.php" 
                       class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="admin-sidebar-footer">
        <a href="/Gestaocheats/admin/logout.php" class="btn btn-logout" title="Sair">
            <i class="fas fa-sign-out-alt"></i> <span class="btn-text">Sair</span>
        </a>
    </div>
</aside>