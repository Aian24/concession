<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$current_username = $_SESSION['user'];
$new_username     = trim($_POST['username']         ?? '');
$current_pw       = $_POST['current_password']      ?? '';
$new_pw           = $_POST['new_password']          ?? '';
$confirm_pw       = $_POST['confirm_password']      ?? '';

if ($current_pw === '') {
    echo json_encode(['success' => false, 'message' => 'Current password is required to save changes.']);
    exit;
}

// 1. Verify Current Password
$stmt = $db->prepare("SELECT id, password, avatar FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $current_username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($current_pw, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
    exit;
}

$user_id = $user['id'];
$updates = [];
$params  = [];
$types   = "";

// 2. Handle Username Change
if ($new_username !== '' && $new_username !== $current_username) {
    // Check if taken
    $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->bind_param("si", $new_username, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already taken.']);
        exit;
    }
    $check->close();
    
    $updates[] = "username = ?";
    $params[]  = $new_username;
    $types    .= "s";
}

// 3. Handle Password Change
if ($new_pw !== '') {
    if ($new_pw !== $confirm_pw) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }
    if (strlen($new_pw) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit;
    }
    $updates[] = "password = ?";
    $params[]  = password_hash($new_pw, PASSWORD_DEFAULT);
    $types    .= "s";
}

// 4. Handle Avatar Upload
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file_tmp  = $_FILES['avatar']['tmp_name'];
    $file_name = $_FILES['avatar']['name'];
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed)) {
        $new_file_name = "avatar_" . $user_id . "_" . time() . "." . $ext;
        $upload_path = "../images/avatars/" . $new_file_name;
        $db_path = "images/avatars/" . $new_file_name;
        
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Delete old avatar if exists
            if (!empty($user['avatar'])) {
                $old_path = "../" . $user['avatar'];
                if (file_exists($old_path)) @unlink($old_path);
            }
            $updates[] = "avatar = ?";
            $params[]  = $db_path;
            $types    .= "s";
            
            $_SESSION['avatar'] = $db_path;
        }
    }
}

if (empty($updates)) {
    echo json_encode(['success' => true, 'message' => 'No changes were made.']);
    exit;
}

// 5. Execute Updates
$sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
$params[] = $user_id;
$types   .= "i";

$upd = $db->prepare($sql);
$upd->bind_param($types, ...$params);

if ($upd->execute()) {
    if ($new_username !== '' && $new_username !== $current_username) {
        $_SESSION['user'] = $new_username;
    }
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
}
$upd->close();
?>
