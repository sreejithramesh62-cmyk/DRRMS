<?php
require_once __DIR__ . '/../config/db.php';

$user_id = 1; // In real app, decode JWT
$input = json_decode(file_get_contents('php://input'), true);
$event_id = $input['event_id'] ?? 0;
$msg = $input['message'] ?? '';

$stmt = db()->prepare("INSERT INTO volunteer_requests (user_id,event_id,request_message) VALUES (?,?,?)");
$stmt->execute([$user_id, $event_id, $msg]);

echo json_encode(['success'=>true,'id'=>db()->lastInsertId()]);
