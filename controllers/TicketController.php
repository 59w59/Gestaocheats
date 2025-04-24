<?php
require_once __DIR__ . '/../models/Ticket.php';

class TicketController {
    private $ticketModel;

    public function __construct($pdo) {
        $this->ticketModel = new Ticket($pdo);
    }

    public function createTicket($title, $description) {
        if (empty($title) || empty($description)) {
            return ['error' => 'Title and description are required.'];
        }
        $ticketId = $this->ticketModel->createTicket($title, $description);
        return ['success' => 'Ticket created successfully.', 'ticket_id' => $ticketId];
    }

    public function getTickets() {
        return $this->ticketModel->getTickets();
    }
}
?>
