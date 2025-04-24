<?php
require_once __DIR__ . '/../db/connection.php';

class Ticket {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createTicket($title, $description) {
        $sql = "INSERT INTO tickets (title, description, created_at) VALUES (:title, :description, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['title' => $title, 'description' => $description]);
        return $this->pdo->lastInsertId();
    }

    public function getTickets() {
        $sql = "SELECT * FROM tickets ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
