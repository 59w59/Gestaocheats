<?php
require_once 'config.php';

class Database {
    private $host = 'localhost';
    private $db_name = 'gestaocheats';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    // Método para conectar ao banco de dados
    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo 'Erro de conexão: ' . $e->getMessage();
        }
        
        return $this->conn;
    }
}

// Inicializar a conexão com o banco de dados para uso global
$database = new Database();
$db = $database->connect();
?>