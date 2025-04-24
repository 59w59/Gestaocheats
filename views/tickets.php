<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../controllers/TicketController.php';

$ticketController = new TicketController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $result = $ticketController->createTicket($title, $description);
}

$tickets = $ticketController->getTickets();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket System</title>
</head>
<body>
    <h1>Create Ticket</h1>
    <form method="POST">
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="description" placeholder="Description" required></textarea>
        <button type="submit">Create Ticket</button>
    </form>

    <?php if (!empty($result)): ?>
        <p><?php echo $result['success'] ?? $result['error']; ?></p>
    <?php endif; ?>

    <h1>Tickets</h1>
    <ul>
        <?php foreach ($tickets as $ticket): ?>
            <li>
                <strong><?php echo htmlspecialchars($ticket['title']); ?></strong><br>
                <?php echo htmlspecialchars($ticket['description']); ?><br>
                <em><?php echo $ticket['created_at']; ?></em>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
