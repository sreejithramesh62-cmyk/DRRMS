<?php
require_once __DIR__ . '/../config/db.php';

try {
  $rows = db()->query("SELECT volunteer_id, volunteer_name, contact, status FROM volunteers")->fetchAll();
  echo json_encode($rows);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
}
