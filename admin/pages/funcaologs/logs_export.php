<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar autenticação do admin
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

// Obter filtros da URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

try {
    // Construir a consulta SQL
    $sql = "SELECT l.*, u.username 
            FROM activity_logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (l.action LIKE ? OR l.ip_address LIKE ? OR u.username LIKE ?)";
        $search_param = "%$search%";
        $params = array_fill(0, 3, $search_param);
    }

    if (!empty($type)) {
        $sql .= " AND l.type = ?";
        $params[] = $type;
    }

    if (!empty($date_start)) {
        $sql .= " AND DATE(l.created_at) >= ?";
        $params[] = $date_start;
    }

    if (!empty($date_end)) {
        $sql .= " AND DATE(l.created_at) <= ?";
        $params[] = $date_end;
    }

    // Ordenação
    $allowed_sort_fields = ['created_at', 'type', 'action', 'ip_address', 'username'];
    $sort = in_array($sort, $allowed_sort_fields) ? $sort : 'created_at';
    $order = in_array($order, ['ASC', 'DESC']) ? $order : 'DESC';

    $sql .= " ORDER BY l.$sort $order";

    // Executar a consulta
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Configurar cabeçalhos para download do CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=logs_' . date('Y-m-d_His') . '.csv');

    // Criar arquivo CSV
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM para Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Cabeçalhos das colunas
    fputcsv($output, [
        'Data/Hora',
        'Tipo',
        'Usuário',
        'Ação',
        'IP',
        'Detalhes'
    ]);

    // Dados
    foreach ($logs as $log) {
        fputcsv($output, [
            date('d/m/Y H:i:s', strtotime($log['created_at'])),
            ucfirst($log['type']),
            $log['username'] ?? 'Sistema',
            $log['action'],
            $log['ip_address'],
            $log['details']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Error exporting logs: " . $e->getMessage());
    header('Location: ../logs.php?error=export_failed');
    exit;
}