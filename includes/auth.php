<?php
/**
 * Arquivo de autenticação e gerenciamento de usuários
 *
 * Este arquivo contém a classe Auth que gerencia todas as funções relacionadas
 * à autenticação, registro, gerenciamento de perfil e sessões de usuários.
 */

// Iniciar a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class Auth {
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
     * Realiza o login do usuário
     *
     * @param string $username Nome de usuário ou email
     * @param string $password Senha do usuário
     * @param bool $remember Se deve lembrar o login do usuário
     * @return array|bool Dados do usuário em caso de sucesso, false em caso de falha
     */
    public function login($username, $password, $remember = false) {
        // Verifica se o input é email ou username
        $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);

        // Prepara a consulta SQL apropriada
        if ($isEmail) {
            $query = "SELECT * FROM users WHERE email = ? AND status = 'active'";
        } else {
            $query = "SELECT * FROM users WHERE username = ? AND status = 'active'";
        }

        // Executa a consulta
        $stmt = $this->db->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica se encontrou o usuário e se a senha está correta
        if ($user && password_verify($password, $user['password'])) {
            // Armazena os dados da sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];

            // Atualiza a data do último login
            $this->update_last_login($user['id']);

            // Registra no log de atividades
            $this->log_activity($user['id'], 'login', 'Login realizado com sucesso');

            // Cria token "lembrar de mim" se solicitado
            if ($remember) {
                $this->create_remember_token($user['id']);
            }

            return $user;
        }

        return false;
    }

    /**
     * Cria um token "lembrar de mim" para o usuário
     *
     * @param int $user_id ID do usuário
     * @return bool True em caso de sucesso, false em caso de falha
     */
    public function create_remember_token($user_id) {
        // Gera um token único
        $token = bin2hex(random_bytes(32));

        // Define a data de expiração (30 dias)
        $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30));

        // Armazena o token no banco de dados
        $stmt = $this->db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $result = $stmt->execute([$user_id, $token, $expires_at]);

        if ($result) {
            // Define o cookie no navegador
            $cookie_options = [
                'expires' => time() + (86400 * 30), // 30 dias
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ];

            setcookie('remember_token', $token, $cookie_options);
            return true;
        }

        return false;
    }

    /**
     * Verifica o token "lembrar de mim" e autentica o usuário
     *
     * @return bool True se o token for válido e o usuário for autenticado
     */
    public function check_remember_token() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }

        $token = $_COOKIE['remember_token'];

        // Busca o token no banco de dados
        $stmt = $this->db->prepare("
            SELECT u.* FROM remember_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.token = ? AND rt.expires_at > NOW() AND u.status = 'active'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Autentica o usuário
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];

            // Atualiza a data do último login
            $this->update_last_login($user['id']);

            // Registra no log de atividades
            $this->log_activity($user['id'], 'auto_login', 'Login automático via cookie');

            return true;
        }

        // Remove o cookie inválido
        setcookie('remember_token', '', time() - 3600, '/');
        return false;
    }

    /**
     * Realiza o logout do usuário
     *
     * @param bool $all_devices Se true, invalida todos os tokens de lembrete
     * @return bool True em caso de sucesso
     */
    public function logout($all_devices = false) {
        // Registra no log de atividades se o usuário estiver logado
        if (isset($_SESSION['user_id'])) {
            $this->log_activity($_SESSION['user_id'], 'logout', 'Logout realizado com sucesso');

            // Remove o token "lembrar de mim" atual
            if (isset($_COOKIE['remember_token'])) {
                $token = $_COOKIE['remember_token'];
                $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE token = ?");
                $stmt->execute([$token]);

                // Remove o cookie
                setcookie('remember_token', '', time() - 3600, '/');
            }

            // Remove todos os tokens se solicitado
            if ($all_devices) {
                $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }
        }

        // Destrói a sessão
        session_unset();
        session_destroy();

        return true;
    }

    /**
     * Registra um novo usuário
     *
     * @param array $user_data Dados do usuário
     * @return int|bool ID do usuário em caso de sucesso, false em caso de falha
     */
    public function register($user_data) {
        // Verifica se o username ou email já existem
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$user_data['username'], $user_data['email']]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            return false;
        }

        // Cria o hash da senha
        $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);

        // Insere o novo usuário
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $result = $stmt->execute([
            $user_data['username'],
            $user_data['email'],
            $hashed_password,
            $user_data['first_name'] ?? '',
            $user_data['last_name'] ?? ''
        ]);

        if ($result) {
            $user_id = $this->db->lastInsertId();
            $this->log_activity($user_id, 'register', 'Conta criada com sucesso');
            return $user_id;
        }

        return false;
    }

    /**
     * Atualiza o timestamp do último login de um usuário
     *
     * @param int $user_id ID do usuário
     * @return bool True em caso de sucesso
     */
    private function update_last_login($user_id) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$user_id]);
    }

    /**
     * Registra uma atividade do usuário no log
     *
     * @param int $user_id ID do usuário
     * @param string $action Ação realizada
     * @param string $description Descrição da ação
     * @return bool True em caso de sucesso
     */
    public function log_activity($user_id, $action, $description = '') {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $this->db->prepare("
            INSERT INTO user_activity_logs (user_id, action, description, ip_address)
            VALUES (?, ?, ?, ?)
        ");

        return $stmt->execute([$user_id, $action, $description, $ip_address]);
    }

    /**
     * Recupera os dados de um usuário pelo ID
     *
     * @param int $user_id ID do usuário
     * @return array|bool Dados do usuário ou false se não encontrado
     */
    public function get_user($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Inicia o processo de recuperação de senha
     *
     * @param string $email Email do usuário
     * @return bool True se o email existir e o token for gerado
     */
    public function request_password_reset($email) {
        // Verifica se o email existe
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        // Remove tokens antigos para este email
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        // Gera um novo token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        // Salva o token no banco de dados
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (email, token, created_at, expires_at)
            VALUES (?, ?, NOW(), ?)
        ");
        $result = $stmt->execute([$email, $token, $expires_at]);

        if ($result) {
            // Aqui você implementaria o envio do email com o link de recuperação
            // Exemplo: $this->send_password_reset_email($email, $token);
            $this->log_activity($user['id'], 'password_reset_request', 'Solicitação de redefinição de senha');
            return true;
        }

        return false;
    }

    /**
     * Verifica se um token de recuperação de senha é válido
     *
     * @param string $token Token de recuperação
     * @return string|bool Email do usuário se válido, false se inválido
     */
    public function validate_password_reset_token($token) {
        $stmt = $this->db->prepare("
            SELECT email FROM password_resets
            WHERE token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['email'] : false;
    }

    /**
     * Redefine a senha de um usuário
     *
     * @param string $token Token de recuperação
     * @param string $password Nova senha
     * @return bool True se a senha for alterada com sucesso
     */
    public function reset_password($token, $password) {
        // Valida o token
        $email = $this->validate_password_reset_token($token);
        if (!$email) {
            return false;
        }

        // Atualiza a senha do usuário
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $result = $stmt->execute([$hashed_password, $email]);

        if ($result) {
            // Remove o token usado
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            // Busca o ID do usuário para o log
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $this->log_activity($user['id'], 'password_reset', 'Senha redefinida com sucesso');
            }

            return true;
        }

        return false;
    }

    /**
     * Atualiza o perfil de um usuário
     *
     * @param int $user_id ID do usuário
     * @param array $user_data Novos dados do usuário
     * @return bool True se o perfil for atualizado com sucesso
     */
    public function update_profile($user_id, $user_data) {
        // Construa a query SQL dinamicamente com base nos dados fornecidos
        $set_parts = [];
        $params = [];
        
        foreach ($user_data as $key => $value) {
            $set_parts[] = "$key = ?";
            $params[] = $value;
        }
        
        // Adicione o user_id como último parâmetro
        $params[] = $user_id;
        
        $sql = "UPDATE users SET " . implode(', ', $set_parts) . ", updated_at = NOW() WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar perfil: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Altera a senha de um usuário
     *
     * @param int $user_id ID do usuário
     * @param string $current_password Senha atual
     * @param string $new_password Nova senha
     * @return bool True se a senha for alterada com sucesso
     */
    public function change_password($user_id, $current_password, $new_password) {
        // Verifica a senha atual
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            return false;
        }

        // Atualiza a senha
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $user_id]);

        if ($result) {
            $this->log_activity($user_id, 'password_change', 'Senha alterada com sucesso');
            return true;
        }

        return false;
    }

    /**
     * Verifica se o usuário está logado
     *
     * @return bool True se o usuário estiver logado
     */
    public function is_logged_in() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Verifica se o usuário logado é um administrador
     *
     * @return bool True se o usuário for administrador
     */
    public function is_admin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }

    /**
     * Verifica se o usuário tem permissão para acessar determinado recurso
     *
     * @param string $permission Permissão necessária
     * @return bool True se o usuário tiver permissão
     */
    public function has_permission($permission) {
        // Implementação simplificada - assume que admin tem todas as permissões
        if ($this->is_admin()) {
            return true;
        }

        // Aqui você implementaria um sistema mais completo de verificação de permissões
        return false;
    }
}

// Inicializa a classe Auth para uso global
$auth = new Auth();

// Verifica automaticamente o cookie "lembrar de mim" se o usuário não estiver logado
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $auth->check_remember_token();
}

/**
 * Verifica se o usuário está logado como administrador
 * @return bool Retorna true se o usuário estiver logado como admin, false caso contrário
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}
?>