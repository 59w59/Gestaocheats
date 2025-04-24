<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurações do site
define('SITE_NAME', 'GestãoCheats');
define('SITE_URL', 'http://localhost/Gestaocheats');
define('ADMIN_EMAIL', 'admin@gestaocheats.com');

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de exibição de erros (desativar em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Chaves de API (substitua por suas chaves reais)
define('STRIPE_PUBLIC_KEY', 'pk_test_sua_chave_publica');
define('STRIPE_SECRET_KEY', 'sk_test_sua_chave_secreta');
define('PAYPAL_CLIENT_ID', 'seu_client_id_paypal');
define('PAYPAL_SECRET', 'seu_secret_paypal');

// Configurações de segurança
define('HASH_COST', 12); // Custo do bcrypt

// No final do arquivo config.php, após iniciar a sessão
if (function_exists('check_remember_token')) {
    check_remember_token();
}
?>