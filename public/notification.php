<?php
require_once __DIR__ . '/../config/db.php';

try {
  $rows = db()->query("SELECT note_id, title, message, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
  echo json_encode($rows);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
}
