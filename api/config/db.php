<?php
function db() {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=drrms2;charset=utf8mb4',
        'root',
        '',
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
    } catch (PDOException $e) {
      header('Content-Type: application/json');
      http_response_code(500);
      die(json_encode(['error' => 'Database connection failed', 'detail' => $e->getMessage()]));
    }
  }
  return $pdo;
}
