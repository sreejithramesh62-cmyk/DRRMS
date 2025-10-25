<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
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
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = str_replace('/drrms/api/index.php', '', $route);

// ============================================
// ADMIN LOGIN
// ============================================
if ($route === '/admin/login' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && $password === $admin['password']) {
        echo json_encode([
            'success' => true,
            'username' => $admin['username'],
            'full_name' => $admin['full_name']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

// ============================================
// USER LOGIN
// ============================================
if ($route === '/user/login' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email_or_phone = $data['email'] ?? $data['phone'] ?? '';
    $password = $data['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM normal_user WHERE email = ? OR phone = ?");
    $stmt->execute([$email_or_phone, $email_or_phone]);
    $user = $stmt->fetch();
    
    if ($user && $password === $user['password']) {
        echo json_encode([
            'success' => true,
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

// ============================================
// USER REGISTRATION
// ============================================
if ($route === '/user/register' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO normal_user (name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['password']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// GET DISASTER EVENTS
// ============================================
if ($route === '/user/events' && $method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM disasterevent ORDER BY event_date DESC");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============================================
// GET VOLUNTEERS
// ============================================
if ($route === '/user/volunteers' && $method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM volunteers WHERE status = 'Active' ORDER BY volunteer_name");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============================================
// GET RESOURCES
// ============================================
if ($route === '/user/resources' && $method === 'GET') {
    $stmt = $pdo->query("SELECT resource_id, resource_name, SUM(quantity) as total FROM resources GROUP BY resource_name ORDER BY resource_name");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============================================
// VOLUNTEER REQUEST (USER)
// ============================================
if ($route === '/user/request_volunteer' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO volunteer_requests (user_id, event_id, event_name, request_message, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->execute([
            $data['user_id'],
            $data['event_id'],
            $data['event_name'],
            $data['message']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Volunteer request submitted']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Request failed: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// RESOURCE REQUEST (USER)
// ============================================
if ($route === '/user/request_resource' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO resource_requests (user_id, resource_name, quantity, status) VALUES (?, ?, ?, 'Pending')");
        $stmt->execute([
            $data['user_id'],
            $data['resource_name'],
            $data['quantity']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Resource request submitted']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Request failed: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// GET USER'S REQUESTS
// ============================================
if ($route === '/user/my_requests' && $method === 'GET') {
    $user_id = $_GET['user_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM volunteer_requests WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============================================
// ADMIN: GET ALL REQUESTS
// ============================================
if ($route === '/admin/requests' && $method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM volunteer_requests ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============================================
// ADMIN: APPROVE REQUEST
// ============================================
if ($route === '/admin/approve_request' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = $data['request_id'];
    $comment = $data['comment'] ?? '';
    $admin_name = $data['admin_name'] ?? 'Admin';
    
    try {
        // Update request status
        $stmt = $pdo->prepare("UPDATE volunteer_requests SET status = 'Approved', admin_comment = ?, admin_name = ? WHERE request_id = ?");
        $stmt->execute([$comment, $admin_name, $request_id]);
        
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM volunteer_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        // Create notification
        $notif_title = "Request #{$request_id} Approved";
        $notif_message = "Your volunteer request for '{$request['event_name']}' has been APPROVED. Comment: " . ($comment ?: 'na');
        
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, admin_name) VALUES (?, ?, ?)");
        $stmt->execute([$notif_title, $notif_message, $admin_name]);
        
        echo json_encode(['success' => true, 'message' => 'Request approved']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Approval failed: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: REJECT REQUEST
// ============================================
if ($route === '/admin/reject_request' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = $data['request_id'];
    $comment = $data['comment'] ?? '';
    $admin_name = $data['admin_name'] ?? 'Admin';
    
    try {
        // Update request status
        $stmt = $pdo->prepare("UPDATE volunteer_requests SET status = 'Rejected', admin_comment = ?, admin_name = ? WHERE request_id = ?");
        $stmt->execute([$comment, $admin_name, $request_id]);
        
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM volunteer_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        // Create notification
        $notif_title = "Request #{$request_id} Rejected";
        $notif_message = "Your volunteer request for '{$request['event_name']}' has been REJECTED. Reason: " . ($comment ?: 'na');
        
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, admin_name) VALUES (?, ?, ?)");
        $stmt->execute([$notif_title, $notif_message, $admin_name]);
        
        echo json_encode(['success' => true, 'message' => 'Request rejected']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Rejection failed: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: ADD DISASTER
// ============================================
if ($route === '/admin/add_disaster' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO disasterevent (event_name, event_type, location, severity, event_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['event_name'],
            $data['event_type'] ?? null,
            $data['location'] ?? null,
            $data['severity'] ?? null,
            $data['event_date'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Disaster event added']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to add disaster: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: ADD RESOURCE
// ============================================
if ($route === '/admin/add_resource' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO resources (resource_name, quantity, center_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['resource_name'],
            $data['quantity'],
            $data['center_id'] ?? 1
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Resource added']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to add resource: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: UPDATE RESOURCE
// ============================================
if ($route === '/admin/update_resource' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("UPDATE resources SET quantity = ? WHERE resource_id = ?");
        $stmt->execute([$data['quantity'], $data['resource_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Resource updated']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to update resource: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: DELETE VOLUNTEER
// ============================================
if ($route === '/admin/delete_volunteer' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM volunteers WHERE volunteer_id = ?");
        $stmt->execute([$data['volunteer_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Volunteer removed']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to remove volunteer: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: GET NOTIFICATIONS
// ============================================
if ($route === '/admin/notifications' && $method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============================================
// ADMIN: ADD NOTIFICATION
// ============================================
if ($route === '/admin/add_notification' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $admin_name = $data['admin_name'] ?? 'Admin';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, admin_name) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['title'],
            $data['message'],
            $admin_name
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Notification created']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to create notification: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: DELETE NOTIFICATION
// ============================================
if ($route === '/admin/delete_notification' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE note_id = ?");
        $stmt->execute([$data['note_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to delete notification: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADMIN: GET ALL ADMINS
// ============================================
if ($route === '/admin/get_admins' && $method === 'GET') {
    $stmt = $pdo->query("SELECT admin_id, username, full_name, created_at FROM admin ORDER BY username");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ============================================
// NEW: GET ALL USER LOGINS (ADMIN ONLY)
// ============================================
if ($route === '/admin/get_users' && $method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT user_id, name, email, phone, created_at FROM normal_user ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll();
        echo json_encode($users);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// 404 - ROUTE NOT FOUND
// ============================================
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found: ' . $route]);
exit;
?>
