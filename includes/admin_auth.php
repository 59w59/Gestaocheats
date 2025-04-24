<?php
/**
 * Arquivo de autenticação e gerenciamento de administradores
 */

// Iniciar a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class AdminAuth {
    private $db;

    /**
     * Construtor
     * Inicializa a conexão com o banco de dados
     */
    public function __construct() {
        global $db;
        $this->db = $db;
    }

    /**
     * Realiza o login do administrador
     *
     * @param string $username Nome de usuário 
     * @param string $password Senha do administrador
     * @return array|bool Dados do administrador em caso de sucesso, false em caso de falha
     */
    public function login($username, $password) {
        // Prepara a consulta SQL
        $query = "SELECT * FROM admins WHERE username = ? AND is_active = 1";

        // Executa a consulta
        $stmt = $this->db->prepare($query);
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica se encontrou o administrador e se a senha está correta
        if ($admin && password_verify($password, $admin['password'])) {
            // Armazena os dados da sessão
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            // Atualiza a data do último login
            $this->update_last_login($admin['id']);

            // Registra no log de atividades
            $this->log_activity($admin['id'], 'login', 'Login administrativo realizado com sucesso');

            return $admin;
        }

        return false;
    }

    /**
     * Atualiza o timestamp do último login de um administrador
     *
     * @param int $admin_id ID do administrador
     * @return bool True em caso de sucesso
     */
    private function update_last_login($admin_id) {
        $stmt = $this->db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$admin_id]);
    }

    /**
     * Registra uma atividade do administrador no log
     *
     * @param int $admin_id ID do administrador
     * @param string $action Ação realizada
     * @param string $description Descrição da ação
     * @return bool True em caso de sucesso
     */
    public function log_activity($admin_id, $action, $description = '') {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $this->db->prepare("
            INSERT INTO admin_logs (admin_id, action, description, ip, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([$admin_id, $action, $description, $ip_address, $user_agent]);
    }

    /**
     * Realiza o logout do administrador
     *
     * @return bool True em caso de sucesso
     */
    public function logout() {
        // Registra no log de atividades se o administrador estiver logado
        if (isset($_SESSION['admin_id'])) {
            $this->log_activity($_SESSION['admin_id'], 'logout', 'Logout administrativo realizado com sucesso');
        }

        // Destrói a sessão
        session_unset();
        session_destroy();

        return true;
    }

    /**
     * Verifica se o administrador está logado
     *
     * @return bool True se o administrador estiver logado
     */
    public function is_logged_in() {
        return isset($_SESSION['admin_id']);
    }

    /**
     * Recupera os dados de um administrador pelo ID
     *
     * @param int $admin_id ID do administrador
     * @return array|bool Dados do administrador ou false se não encontrado
     */
    public function get_admin($admin_id) {
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Inicializa a classe AdminAuth para uso global
$admin_auth = new AdminAuth();

/**
 * Verifica se o administrador está logado
 * @return bool Retorna true se o administrador estiver logado
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}
?>