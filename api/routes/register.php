<?php
require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['name'] ?? '';
$email = $input['email'] ?? '';
$phone = $input['phone'] ?? '';
$pass = $input['password'] ?? '';

if (!$name || !$email || !$phone || !$pass) {
  http_response_code(400);
  echo json_encode(['error' => 'All fields required']);
  exit;
}

try {
  $stmt = db()->prepare("INSERT INTO normal_user (name,email,phone,password) VALUES (?,?,?,?)");
  $stmt->execute([$name, $email, $phone, $pass]);
  echo json_encode(['success'=>true,'message'=>'Registered']);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error'=>'Email or phone already exists']);
}
