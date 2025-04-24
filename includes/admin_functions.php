
<?php
/**
 * Arquivo de funções administrativas
 *
 * Este arquivo contém funções específicas para o painel administrativo,
 * como estatísticas, relatórios e funções de gerenciamento.
 */

// Impedir acesso direto ao arquivo
if (!defined('INCLUDED_FROM_INDEX')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

/**
 * Obtém o número total de usuários registrados
 *
 * @return int Número total de usuários
 */
function get_total_users() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    return $stmt->fetchColumn();
}

/**
 * Obtém o número total de assinaturas ativas
 *
 * @return int Número de assinaturas ativas
 */
function get_active_subscriptions() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM user_subscriptions WHERE status = 'active'");
    return $stmt->fetchColumn();
}

/**
 * Obtém o número de novos registros nos últimos X dias
 *
 * @param int $days Número de dias para verificar
 * @return int Número de registros nos últimos X dias
 */
function get_recent_registrations($days = 7) {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    return $stmt->fetchColumn();
}

/**
 * Obtém a receita mensal atual
 *
 * @return float Valor total da receita do mês atual
 */
function get_monthly_revenue() {
    global $db;
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount), 0) 
        FROM payments 
        WHERE status = 'completed' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    return $stmt->fetchColumn();
}

/**
 * Obtém o número total de downloads de cheats
 *
 * @return int Número total de downloads
 */
function get_total_downloads() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM user_downloads");
    return $stmt->fetchColumn();
}

/**
 * Obtém o número de tickets de suporte pendentes
 *
 * @return int Número de tickets pendentes
 */
function get_pending_support_tickets() {
    global $db;
    $stmt = $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'");
    return $stmt->fetchColumn();
}

/**
 * Obtém dados para o gráfico de receita
 *
 * @param int $days Número de dias para incluir no gráfico
 * @return array Array contendo datas e valores de receita
 */
function get_revenue_chart_data($days = 30) {
    global $db;
    $result = [
        'dates' => [],
        'values' => []
    ];
    
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COALESCE(SUM(amount), 0) as daily_revenue
        FROM 
            payments
        WHERE 
            status = 'completed' 
            AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL ? DAY)
        GROUP BY 
            DATE(created_at)
        ORDER BY 
            date ASC
    ");
    
    $stmt->execute([$days]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preencher os dias que não têm dados
    $start_date = new DateTime(date('Y-m-d', strtotime("-{$days} days")));
    $end_date = new DateTime(date('Y-m-d'));
    $interval = new DateInterval('P1D');
    $date_range = new DatePeriod($start_date, $interval, $end_date);
    
    $revenue_by_date = [];
    foreach ($data as $row) {
        $revenue_by_date[$row['date']] = $row['daily_revenue'];
    }
    
    foreach ($date_range as $date) {
        $date_str = $date->format('Y-m-d');
        $result['dates'][] = $date->format('d/m');
        $result['values'][] = isset($revenue_by_date[$date_str]) ? (float)$revenue_by_date[$date_str] : 0;
    }
    
    return $result;
}

/**
 * Obtém dados para o gráfico de usuários
 *
 * @param int $days Número de dias para incluir no gráfico
 * @return array Array contendo datas, novos usuários e novas assinaturas
 */
function get_users_chart_data($days = 30) {
    global $db;
    $result = [
        'dates' => [],
        'new_users' => [],
        'new_subscriptions' => []
    ];
    
    // Obter datas
    $start_date = new DateTime(date('Y-m-d', strtotime("-{$days} days")));
    $end_date = new DateTime(date('Y-m-d'));
    $interval = new DateInterval('P1D');
    $date_range = new DatePeriod($start_date, $interval, $end_date);
    
    foreach ($date_range as $date) {
        $date_str = $date->format('Y-m-d');
        $result['dates'][] = $date->format('d/m');
        
        // Novos usuários para esta data
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM users 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date_str]);
        $result['new_users'][] = (int)$stmt->fetchColumn();
        
        // Novas assinaturas para esta data
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_subscriptions 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date_str]);
        $result['new_subscriptions'][] = (int)$stmt->fetchColumn();
    }
    
    return $result;
}

/**
 * Obtém dados para o gráfico de downloads
 *
 * @param int $days Número de dias para incluir no gráfico
 * @return array Array contendo datas e contagens de downloads
 */
function get_downloads_chart_data($days = 30) {
    global $db;
    $result = [
        'dates' => [],
        'values' => []
    ];
    
    // Obter datas
    $start_date = new DateTime(date('Y-m-d', strtotime("-{$days} days")));
    $end_date = new DateTime(date('Y-m-d'));
    $interval = new DateInterval('P1D');
    $date_range = new DatePeriod($start_date, $interval, $end_date);
    
    foreach ($date_range as $date) {
        $date_str = $date->format('Y-m-d');
        $result['dates'][] = $date->format('d/m');
        
        // Downloads para esta data
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_downloads 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date_str]);
        $result['values'][] = (int)$stmt->fetchColumn();
    }
    
    return $result;
}

/**
 * Obtém atividades recentes dos usuários
 *
 * @param int $limit Número máximo de atividades a retornar
 * @return array Lista de atividades recentes
 */
function get_recent_activities($limit = 10) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            al.*, 
            u.username,
            u.id as user_id
        FROM 
            user_activity_logs al
        LEFT JOIN 
            users u ON al.user_id = u.id
        ORDER BY 
            al.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtém usuários registrados recentemente
 *
 * @param int $limit Número máximo de usuários a retornar
 * @return array Lista de usuários recentes
 */
function get_recent_users($limit = 5) {
    global $db;
    $stmt = $db->prepare("
        SELECT * FROM users
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtém notificações do painel administrativo
 *
 * @param int $limit Número máximo de notificações
 * @return array Lista de notificações
 */
function get_admin_notifications($limit = 5) {
    global $db;
    $notifications = [];
    
    // Tickets de suporte novos
    $stmt = $db->prepare("
        SELECT 
            id, 
            subject, 
            created_at,
            'support' as type
        FROM 
            support_tickets
        WHERE 
            status = 'open'
        ORDER BY 
            created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $support_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($support_tickets as $ticket) {
        $notifications[] = [
            'message' => "Novo ticket: {$ticket['subject']}",
            'link' => "pages/support_ticket.php?id={$ticket['id']}",
            'icon' => 'fas fa-headset',
            'icon_class' => 'bg-danger',
            'created_at' => $ticket['created_at'],
        ];
    }
    
    // Novas assinaturas
    $stmt = $db->prepare("
        SELECT 
            s.id,
            u.username,
            p.name as plan_name,
            s.created_at
        FROM 
            user_subscriptions s
        JOIN 
            users u ON s.user_id = u.id
        JOIN 
            subscription_plans p ON s.plan_id = p.id
        ORDER BY 
            s.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subscriptions as $subscription) {
        $notifications[] = [
            'message' => "{$subscription['username']} assinou {$subscription['plan_name']}",
            'link' => "pages/subscriptions.php?id={$subscription['id']}",
            'icon' => 'fas fa-credit-card',
            'icon_class' => 'bg-success',
            'created_at' => $subscription['created_at'],
        ];
    }
    
    // Ordenar por data (mais recentes primeiro)
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($notifications, 0, $limit);
}

/**
 * Obtém o avatar do usuário
 *
 * @param int $user_id ID do usuário
 * @return string URL do avatar do usuário
 */
function get_user_avatar($user_id) {
    // Aqui poderia verificar se o usuário tem um avatar personalizado
    // Por padrão, retorna um avatar genérico
    return "../assets/images/default-avatar.png";
}

/**
 * Converte timestamp em string amigável (há X minutos, há X horas, etc)
 *
 * @param string $datetime Data/hora a converter
 * @param bool $full Mostrar texto completo
 * @return string String formatada
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'ano',
        'm' => 'mês',
        'w' => 'semana',
        'd' => 'dia',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? ($k == 'm' ? 'es' : 's') : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'há ' . implode(', ', $string) : 'agora';
}

/**
 * Registra uma ação no log de administração
 *
 * @param int $admin_id ID do administrador
 * @param string $action Ação realizada
 * @param string $details Detalhes da ação
 * @return bool Sucesso ou falha na operação
 */
function log_admin_action($admin_id, $action, $details = '') {
    global $db;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO admin_logs 
        (admin_id, action, details, ip, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$admin_id, $action, $details, $ip, $user_agent]);
}

/**
 * Verifica se um administrador tem a permissão necessária
 *
 * @param string $required_role Papel necessário (super_admin, admin, editor)
 * @return bool Verdadeiro se tiver permissão, falso caso contrário
 */
function admin_has_permission($required_role = 'admin') {
    if (!isset($_SESSION['admin_role'])) {
        return false;
    }
    
    $role_hierarchy = [
        'super_admin' => 3,
        'admin' => 2,
        'editor' => 1
    ];
    
    $admin_role = $_SESSION['admin_role'];
    
    // Verificar se o papel existe na hierarquia
    if (!isset($role_hierarchy[$admin_role]) || !isset($role_hierarchy[$required_role])) {
        return false;
    }
    
    // Comparar níveis na hierarquia
    return $role_hierarchy[$admin_role] >= $role_hierarchy[$required_role];
}

/**
 * Formata um valor em Reais
 *
 * @param float $value Valor a ser formatado
 * @param bool $with_symbol Incluir o símbolo R$
 * @return string Valor formatado
 */
function format_currency($value, $with_symbol = true) {
    $formatted = number_format($value, 2, ',', '.');
    return $with_symbol ? "R$ {$formatted}" : $formatted;
}
?>