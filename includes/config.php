<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurações do site
define('SITE_NAME', 'GestãoCheats');
define('SITE_URL', 'https://seu-site.com'); // Substitua pelo seu domínio real
define('ADMIN_EMAIL', 'admin@gestaocheats.com');

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de exibição de erros (desativar em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurações do Mercado Pago (substitua com suas credenciais reais)
define('MP_PUBLIC_KEY', 'SUA_CHAVE_PUBLICA_MP');
define('MP_ACCESS_TOKEN', 'SEU_ACCESS_TOKEN_MP');
define('MP_CLIENT_ID', 'xxxxxxxxxxxx');
define('MP_CLIENT_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('MP_INTEGRATOR_ID', 'SEU_ID_INTEGRADOR_MP'); // opcional

// Configurações de segurança
define('HASH_COST', 12); // Custo do bcrypt

// No final do arquivo config.php, após iniciar a sessão
if (function_exists('check_remember_token')) {
    check_remember_token();
}
?>