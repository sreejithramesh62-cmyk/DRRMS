<?php
require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$pass = $input['password'] ?? '';
$role = $input['role'] ?? '';

if ($role === 'ADMIN') {
  $stmt = db()->prepare("SELECT admin_id, username FROM admin WHERE username=? AND password=?");
  $stmt->execute([$username, $pass]);
  $user = $stmt->fetch();
  if ($user) {
    echo json_encode(['success'=>true,'role'=>'ADMIN','name'=>$user['username']]);
    exit;
  }
} else {
  $stmt = db()->prepare("SELECT user_id, name FROM normal_user WHERE (email=? OR phone=?) AND password=?");
  $stmt->execute([$username, $username, $pass]);
  $user = $stmt->fetch();
  if ($user) {
    echo json_encode(['success'=>true,'role'=>'USER','name'=>$user['name']]);
    exit;
  }
}
http_response_code(401);
echo json_encode(['success'=>false,'error'=>'Invalid']);
