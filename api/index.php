<?php
// Complete DRRMS API v3.0 Final - With Admin Full Name Tracking
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$base = '/drrms/api/index.php';
if (strpos($path, $base) === 0) {
  $path = substr($path, strlen($base));
}

// Debug logging - remove after testing
error_log("Full URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));
error_log("Parsed path: " . $path);

function getDB() {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=drrms2;charset=utf8mb4',
        'root',
        '',
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false
        ]
      );
    } catch (PDOException $e) {
      http_response_code(500);
      die(json_encode(['error' => 'Database connection failed', 'detail' => $e->getMessage()]));
    }
  }
  return $pdo;
}

// ==================== ROUTES ====================

if ($path === '' || $path === '/') {
  echo json_encode([
    'service' => 'DRRMS API',
    'status' => 'OK',
    'version' => '3.0',
    'time' => date('Y-m-d H:i:s')
  ]);
  exit;
}

// UPDATED: Login with full_name
if ($path === '/auth/login') {
  $input = json_decode(file_get_contents('php://input'), true);
  $user = $input['username'] ?? '';
  $pass = $input['password'] ?? '';
  $role = $input['role'] ?? '';
  
  try {
    if ($role === 'ADMIN') {
      $stmt = getDB()->prepare("SELECT admin_id, username, full_name FROM admin WHERE username=? AND password=?");
      $stmt->execute([$user, $pass]);
      if ($row = $stmt->fetch()) {
        echo json_encode([
          'success' => true,
          'role' => 'ADMIN',
          'name' => $row['username'],
          'full_name' => $row['full_name'] ?? $row['username'],
          'id' => $row['admin_id']
        ]);
        exit;
      }
    } else {
      $stmt = getDB()->prepare("SELECT user_id, name FROM normal_user WHERE (email=? OR phone=?) AND password=?");
      $stmt->execute([$user, $user, $pass]);
      if ($row = $stmt->fetch()) {
        echo json_encode([
          'success' => true,
          'role' => 'USER',
          'name' => $row['name'],
          'id' => $row['user_id']
        ]);
        exit;
      }
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Login failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/auth/register') {
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
    $stmt = getDB()->prepare("INSERT INTO normal_user (name, email, phone, password) VALUES (?,?,?,?)");
    $stmt->execute([$name, $email, $phone, $pass]);
    echo json_encode(['success' => true, 'message' => 'User registered', 'id' => getDB()->lastInsertId()]);
  } catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Registration failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/user/events') {
  try {
    $rows = getDB()->query("SELECT * FROM disasterevent ORDER BY event_date DESC")->fetchAll();
    echo json_encode($rows);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/user/volunteers') {
  try {
    $rows = getDB()->query("SELECT volunteer_id, volunteer_name, contact, status FROM volunteers")->fetchAll();
    echo json_encode($rows);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/user/resources') {
  try {
    $rows = getDB()->query("SELECT resource_id, resource_name, quantity_available AS total FROM resources")->fetchAll();
    echo json_encode($rows);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/notifications') {
  try {
    $rows = getDB()->query("SELECT note_id, title, message, is_read, created_at, admin_name FROM notifications ORDER BY created_at DESC LIMIT 50")->fetchAll();
    echo json_encode($rows);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/requests') {
  try {
    $sql = "SELECT r.request_id, r.user_id, r.event_id, r.volunteer_id, r.request_message,
                   r.status, r.created_at, r.admin_name, COALESCE(e.event_name, 'Unknown') as event_name
            FROM volunteer_requests r
            LEFT JOIN disasterevent e ON e.event_id = r.event_id
            ORDER BY r.created_at DESC";
    $rows = getDB()->query($sql)->fetchAll();
    echo json_encode($rows);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/get_admins') {
  try {
    $rows = getDB()->query("SELECT admin_id, username, full_name FROM admin")->fetchAll();
    echo json_encode($rows);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

// UPDATED: Approve with full admin name
if ($path === '/admin/approve_request') {
  $input = json_decode(file_get_contents('php://input'), true);
  $id = (int)($input['request_id'] ?? 0);
  $comment = $input['comment'] ?? '';
  $admin_name = $input['admin_name'] ?? 'Admin';
  
  if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing request_id']);
    exit;
  }
  
  try {
    getDB()->prepare("UPDATE volunteer_requests SET status='Approved', admin_name=? WHERE request_id=?")
      ->execute([$admin_name, $id]);
    
    $req = getDB()->prepare("SELECT r.*, u.name as user_name, e.event_name 
                             FROM volunteer_requests r
                             LEFT JOIN normal_user u ON u.user_id = r.user_id
                             LEFT JOIN disasterevent e ON e.event_id = r.event_id
                             WHERE r.request_id=?");
    $req->execute([$id]);
    $reqData = $req->fetch();
    
    $notifMsg = "Your volunteer request for '" . ($reqData['event_name'] ?? 'Event') . "' has been APPROVED";
    if ($comment) $notifMsg .= ". Comment: " . $comment;
    
    getDB()->prepare("INSERT INTO notifications (title, message, admin_name) VALUES (?, ?, ?)")
      ->execute(["Request #$id Approved", $notifMsg, $admin_name]);
    
    echo json_encode(['success' => true, 'message' => 'Request approved by ' . $admin_name]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Approve failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

// UPDATED: Reject with full admin name
if ($path === '/admin/reject_request') {
  $input = json_decode(file_get_contents('php://input'), true);
  $id = (int)($input['request_id'] ?? 0);
  $comment = $input['comment'] ?? '';
  $admin_name = $input['admin_name'] ?? 'Admin';
  
  if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing request_id']);
    exit;
  }
  
  try {
    getDB()->prepare("UPDATE volunteer_requests SET status='Rejected', admin_name=? WHERE request_id=?")
      ->execute([$admin_name, $id]);
    
    $req = getDB()->prepare("SELECT r.*, u.name as user_name, e.event_name 
                             FROM volunteer_requests r
                             LEFT JOIN normal_user u ON u.user_id = r.user_id
                             LEFT JOIN disasterevent e ON e.event_id = r.event_id
                             WHERE r.request_id=?");
    $req->execute([$id]);
    $reqData = $req->fetch();
    
    $notifMsg = "Your volunteer request for '" . ($reqData['event_name'] ?? 'Event') . "' has been REJECTED";
    if ($comment) $notifMsg .= ". Reason: " . $comment;
    
    getDB()->prepare("INSERT INTO notifications (title, message, admin_name) VALUES (?, ?, ?)")
      ->execute(["Request #$id Rejected", $notifMsg, $admin_name]);
    
    echo json_encode(['success' => true, 'message' => 'Request rejected by ' . $admin_name]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Reject failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/delete_notification') {
  $input = json_decode(file_get_contents('php://input'), true);
  $id = (int)($input['note_id'] ?? 0);
  
  if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing note_id']);
    exit;
  }
  
  try {
    getDB()->prepare("DELETE FROM notifications WHERE note_id=?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Notification deleted']);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/add_disaster') {
  $input = json_decode(file_get_contents('php://input'), true);
  $name = $input['event_name'] ?? '';
  $type = $input['event_type'] ?? '';
  $loc = $input['location'] ?? '';
  $date = $input['event_date'] ?? date('Y-m-d');
  $sev = $input['severity'] ?? 'Medium';
  
  if (!$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Event name required']);
    exit;
  }
  
  try {
    $stmt = getDB()->prepare("INSERT INTO disasterevent (event_name, event_type, location, event_date, severity) VALUES (?,?,?,?,?)");
    $stmt->execute([$name, $type, $loc, $date, $sev]);
    echo json_encode(['success' => true, 'message' => 'Disaster added successfully', 'id' => getDB()->lastInsertId()]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Add failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/user/request_volunteer') {
  $input = json_decode(file_get_contents('php://input'), true);
  $user_id = (int)($input['user_id'] ?? 1);
  $event_id = (int)($input['event_id'] ?? 0);
  $message = $input['message'] ?? '';
  
  try {
    $stmt = getDB()->prepare("INSERT INTO volunteer_requests (user_id, event_id, request_message, status) VALUES (?,?,?,'Pending')");
    $stmt->execute([$user_id, $event_id, $message]);
    echo json_encode(['success' => true, 'message' => 'Volunteer request submitted', 'id' => getDB()->lastInsertId()]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Request failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/add_resource') {
  $input = json_decode(file_get_contents('php://input'), true);
  $name = $input['resource_name'] ?? '';
  $qty = (int)($input['quantity'] ?? 0);
  $center_id = (int)($input['center_id'] ?? 1);
  
  if (!$name || $qty <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Resource name and quantity required']);
    exit;
  }
  
  try {
    $stmt = getDB()->prepare("INSERT INTO resources (resource_name, quantity_available, center_id) VALUES (?,?,?)");
    $stmt->execute([$name, $qty, $center_id]);
    echo json_encode(['success' => true, 'message' => 'Resource added', 'id' => getDB()->lastInsertId()]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Add failed', 'detail' => $e->getMessage()]);
  }
  exit;
}


if ($path === '/admin/add_resource') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['resource_name'] ?? '';
    $qty = (int)($input['quantity'] ?? 0);
    
    if (!$name || $qty <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource name and quantity required']);
        exit;
    }
    
    try {
        // Check if we have relief centers in the database
        $centerCheck = getDB()->query("SELECT center_id FROM reliefcenter LIMIT 1")->fetch();
        
        if ($centerCheck) {
            // If relief center exists, use it
            $center_id = $centerCheck['center_id'];
            $stmt = getDB()->prepare("INSERT INTO resources (resource_name, quantity_available, center_id) VALUES (?,?,?)");
            $stmt->execute([$name, $qty, $center_id]);
        } else {
            // If no relief center exists, insert without center_id (allow NULL)
            $stmt = getDB()->prepare("INSERT INTO resources (resource_name, quantity_available) VALUES (?,?)");
            $stmt->execute([$name, $qty]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Resource added successfully!', 'id' => getDB()->lastInsertId()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Add failed', 'detail' => $e->getMessage()]);
    }
    exit;
}


if ($path === '/user/request_resource') {
  $input = json_decode(file_get_contents('php://input'), true);
  $user_id = (int)($input['user_id'] ?? 1);
  $resource_name = $input['resource_name'] ?? '';
  $quantity = (int)($input['quantity'] ?? 0);
  $camp_location = $input['camp_location'] ?? '';
  $urgency = $input['urgency'] ?? 'Medium';
  $message = $input['message'] ?? '';
  
  if(!$resource_name || $quantity <= 0){
    http_response_code(400);
    echo json_encode(['error' => 'Resource name and quantity required']);
    exit;
  }
  
  try{
    $stmt = getDB()->prepare("INSERT INTO resource_requests (user_id, resource_name, quantity, camp_location, urgency, message, status) VALUES (?,?,?,?,?,?,'Pending')");
    $stmt->execute([$user_id, $resource_name, $quantity, $camp_location, $urgency, $message]);
    
    getDB()->prepare("INSERT INTO notifications (title, message) VALUES (?, ?)")
      ->execute(['New Resource Request', "$resource_name x$quantity requested for $camp_location"]);
    
    echo json_encode(['success' => true, 'message' => 'Resource request submitted', 'id' => getDB()->lastInsertId()]);
  } catch(Exception $e){
    http_response_code(500);
    echo json_encode(['error' => 'Request failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/user/message_admin') {
  $input = json_decode(file_get_contents('php://input'), true);
  $user_id = (int)($input['user_id'] ?? 1);
  $admin_id = (int)($input['admin_id'] ?? 0);
  $subject = $input['subject'] ?? '';
  $message = $input['message'] ?? '';
  
  if(!$admin_id || !$subject || !$message){
    http_response_code(400);
    echo json_encode(['error' => 'All fields required']);
    exit;
  }
  
  try{
    $stmt = getDB()->prepare("INSERT INTO admin_messages (user_id, admin_id, subject, message, status) VALUES (?,?,?,?,'Unread')");
    $stmt->execute([$user_id, $admin_id, $subject, $message]);
    
    $adminName = getDB()->prepare("SELECT username, full_name FROM admin WHERE admin_id=?");
    $adminName->execute([$admin_id]);
    $admin = $adminName->fetch();
    
    getDB()->prepare("INSERT INTO notifications (title, message) VALUES (?, ?)")
      ->execute(['New Message', "User sent message to " . ($admin['full_name'] ?? $admin['username'] ?? 'Admin')]);
    
    echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'id' => getDB()->lastInsertId()]);
  } catch(Exception $e){
    http_response_code(500);
    echo json_encode(['error' => 'Send failed', 'detail' => $e->getMessage()]);
  }
  exit;
}
if ($path === '/admin/get_users') {
  try {
    $rows = getDB()->query("SELECT user_id, name, email, phone, created_at FROM normal_user ORDER BY created_at DESC")->fetchAll();
    echo json_encode($rows);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/delete_user') {
  $input = json_decode(file_get_contents('php://input'), true);
  $id = (int)($input['user_id'] ?? 0);
  
  if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id']);
    exit;
  }
  
  try {
    getDB()->prepare("DELETE FROM normal_user WHERE user_id=?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'User deleted']);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed', 'detail' => $e->getMessage()]);
  }
  exit;
}


if ($path === '/admin/delete_volunteer') {
  $input = json_decode(file_get_contents('php://input'), true);
  $id = (int)($input['volunteer_id'] ?? 0);
  
  if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing volunteer_id']);
    exit;
  }
  
  try {
    getDB()->prepare("DELETE FROM volunteers WHERE volunteer_id=?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Volunteer removed']);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed', 'detail' => $e->getMessage()]);
  }
  exit;
}
if ($path === '/admin/delete_user') {
  $input = json_decode(file_get_contents('php://input'), true);
  $id = (int)($input['user_id'] ?? 0);
  
  if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id']);
    exit;
  }
  
  try {
    getDB()->prepare("DELETE FROM normal_user WHERE user_id=?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'User deleted']);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed', 'detail' => $e->getMessage()]);
  }
  exit;
}


// UPDATED: Add notification with full admin name
if ($path === '/admin/add_notification') {
  $input = json_decode(file_get_contents('php://input'), true);
  $title = $input['title'] ?? '';
  $message = $input['message'] ?? '';
  $admin_name = $input['admin_name'] ?? null;
  
  if (!$title || !$message) {
    http_response_code(400);
    echo json_encode(['error' => 'Title and message required']);
    exit;
  }
  
  try {
    $stmt = getDB()->prepare("INSERT INTO notifications (title, message, admin_name) VALUES (?, ?, ?)");
    $stmt->execute([$title, $message, $admin_name]);
    echo json_encode(['success' => true, 'message' => 'Notification created', 'id' => getDB()->lastInsertId()]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Create failed', 'detail' => $e->getMessage()]);
  }
  exit;
}

if ($path === '/admin/resource_requests') {
  try{
    $sql = "SELECT r.*, u.name as user_name FROM resource_requests r
            LEFT JOIN normal_user u ON u.user_id = r.user_id
            ORDER BY r.created_at DESC";
    $rows = getDB()->query($sql)->fetchAll();
    echo json_encode($rows);
  } catch(Exception $e){
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}



if ($path === '/admin/messages') {
  try{
    $sql = "SELECT m.*, u.name as user_name, a.username as admin_name, a.full_name as admin_full_name
            FROM admin_messages m
            LEFT JOIN normal_user u ON u.user_id = m.user_id
            LEFT JOIN admin a ON a.admin_id = m.admin_id
            ORDER BY m.created_at DESC";
    $rows = getDB()->query($sql)->fetchAll();
    echo json_encode($rows);
  } catch(Exception $e){
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'detail' => $e->getMessage()]);
  }
  exit;
}
// Get all registered users (admin only)
// NEW: Get all user logins (admin only)
if ($route === '/admin/get_users' && $method === 'GET') {
    $stmt = $pdo->query("SELECT user_id, name, email, phone, created_at FROM normal_user ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll());
    exit;
}


// 404
http_response_code(404);
echo json_encode(['error' => 'Route not found', 'path' => $path]);
