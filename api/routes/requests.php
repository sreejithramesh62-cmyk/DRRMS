<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $sql = "SELECT r.request_id, r.user_id, r.event_id, r.volunteer_id, r.request_message,
                 r.status, r.created_at, e.event_name
          FROM volunteer_requests r
          LEFT JOIN disasterevent e ON e.event_id = r.event_id
          ORDER BY r.created_at DESC";
  $rows = db()->query($sql)->fetchAll();
  echo json_encode($rows);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'DB error', 'detail' => $e->getMessage()]);
}
