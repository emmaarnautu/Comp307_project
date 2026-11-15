<?php

require_once "db.php";
function create_ticket($userId){
    global $pdo;
    $ticket = bin2hex(random_bytes(32));
    $ticketHash = hash('sha256', $ticket);

    $stmt = $pdo->prepare("
        INSERT INTO tickets (user_id, ticket_code, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");

    $stmt->execute([$userId, $ticketHash]);

    return $ticket;
}



