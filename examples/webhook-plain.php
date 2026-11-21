<?php

declare(strict_types=1);

use Zeny\Domain\Errors\SignatureVerificationError;
use Zeny\Zeny;

$secret = (string) getenv('ZENY_WEBHOOK_SECRET'); // whsec_...
$pdo = new PDO((string) getenv('DATABASE_DSN'), options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$rawBody = (string) file_get_contents('php://input'); // the raw body, byte for byte
try {
    $event = Zeny::webhooks()->constructEvent($rawBody, getallheaders(), $secret);
} catch (SignatureVerificationError) {
    http_response_code(400);
    exit;
}

// A unique key on id: a repeat inserts nothing and still answers 200. A failed
// insert throws, PHP answers 500 and the event comes again.
$pdo->prepare('INSERT INTO webhook_events (id, type, payload) VALUES (?, ?, ?) ON CONFLICT (id) DO NOTHING')
    ->execute([$event['id'], $event['type'], $rawBody]);

// Answered only after the insert committed. A worker processes stored events by
// type and skips types it does not know; failures there never lose the event.
http_response_code(200);
