<?php
session_start();
require_once 'includes/db.php';

$action = $_GET['action'] ?? 'sale';

// ── Logout ───────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ./");
    exit;
}

// ── Login POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $uname      = trim($_POST['username']   ?? '');
    $password   = trim($_POST['password']   ?? '');
    $store_code = trim($_POST['store_code'] ?? '');
    $login_error = '';

    if ($uname === '' || $password === '' || $store_code === '') {
        $login_error = 'All fields are required.';
    } else {
        $db   = db_connect();
        $stmt = $db->prepare("SELECT id, password, store_code, role, avatar FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $uname);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // Fetch Store Name
            $s_stmt = $db->prepare("SELECT sname FROM storecode WHERE scode = ? LIMIT 1");
            $s_stmt->bind_param("s", $store_code);
            $s_stmt->execute();
            $s_data = $s_stmt->get_result()->fetch_assoc();
            $s_stmt->close();

            $_SESSION['user']       = $uname;
            $_SESSION['store_code'] = $store_code;
            $_SESSION['store_name'] = $s_data['sname'] ?? '';
            $_SESSION['role']       = $user['role'];
            $_SESSION['avatar']     = $user['avatar'];

            $_SESSION['transaction_date'] = date('Y-m-d');
            
            if ($user['role'] === 'admin' || $user['role'] === 'admin_view' || $user['role'] === 'store_admin' || $uname === 'admin') {
                header("Location: dashboard");
            } else {
                header("Location: history");
            }
            exit;

        } else {
            $login_error = 'Incorrect username or password.';
        }
    }

    require 'views/login.php';
    exit;
}

// ── Role & Permission Flags ──────────────────────────────────
$role = $_SESSION['role'] ?? 'user';
$is_full_admin   = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
$is_admin_view   = ($role === 'admin_view');
$is_store_admin  = ($role === 'store_admin');

// General admin flag for showing admin-only modules/data
$is_admin = ($is_full_admin || $is_admin_view || $is_store_admin);

// Can submit NEW transactions (create) - Only regular users can create entries
$can_submit = ($role === 'user');

// Can edit EXISTING records (admin_view can edit, store_admin is view-only)
$can_edit = ($is_full_admin || $is_admin_view);

// Can delete records (only full admin)
$can_delete = ($is_full_admin);

// ── Guard ─────────────────────────────────────────────────────
if (!isset($_SESSION['user'])) {
    require 'views/login.php';
    exit;
}

// ── Post/Redirect/Get Filter Persistence ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'history') {
        if (isset($_POST['tab']))    $_SESSION['history_tab']    = $_POST['tab'];
        if (isset($_POST['limit']))  $_SESSION['history_limit']  = intval($_POST['limit']);
        if (isset($_POST['page']))   $_SESSION['history_page']   = intval($_POST['page']);
        if (isset($_POST['search'])) $_SESSION['history_search'] = trim($_POST['search']);
        
        header("Location: history");
        exit;
    }
    
    if ($action === 'monitoring') {
        if (isset($_POST['status']))     $_SESSION['monitoring_status']     = $_POST['status'];
        if (isset($_POST['limit']))      $_SESSION['monitoring_limit']      = intval($_POST['limit']);
        if (isset($_POST['page']))       $_SESSION['monitoring_page']       = intval($_POST['page']);
        if (isset($_POST['search']))     $_SESSION['monitoring_search']     = trim($_POST['search']);
        if (isset($_POST['start_date'])) $_SESSION['monitoring_start_date'] = $_POST['start_date'];
        if (isset($_POST['end_date']))   $_SESSION['monitoring_end_date']   = $_POST['end_date'];
        
        header("Location: monitoring");
        exit;
    }
}

// ── Routing ───────────────────────────────────────────────────
$allowed_pages = ['dashboard', 'monitoring', 'sale', 'return', 'receiving', 'ros_supplies', 'submitted', 'admin', 'history', 'pullout', 'stores'];

if (!in_array($action, $allowed_pages)) {
    $is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $action = $is_admin ? 'dashboard' : 'history';
}

// Check for AJAX requests (to bypass layout)
if (isset($_GET['ajax']) && in_array($action, $allowed_pages)) {
    require "views/pages/{$action}.php";
    exit;
}

require 'views/layout.php';
