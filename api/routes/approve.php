<?php
require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['request_id'] ?? 0);
$comment = $input['comment'] ?? '';

if (!$id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing request_id']);
  exit;
}

try {
  $stmt = db()->prepare("UPDATE volunteer_requests SET status='Approved' WHERE request_id=?");
  $stmt->execute([$id]);
  
  // Optional: insert comment into notifications
  if ($comment) {
    $notif = db()->prepare("INSERT INTO notifications (title, message) VALUES (?, ?)");
    $notif->execute(["Request #$id Approved", "Comment: $comment"]);
  }
  
  echo json_encode(['success' => true, 'message' => 'Request approved']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Approve failed', 'detail' => $e->getMessage()]);
}
