<?php
session_start();
require_once 'includes/db.php';

// ── Remember Me Cookies ──────────────────────────────────────
$remembered_username   = $_COOKIE['remember_username']   ?? '';
$remembered_store_code = $_COOKIE['remember_store_code'] ?? '';

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

            $store_name = $s_data['sname'] ?? '';
            if (empty($store_name)) {
                if ($store_code === 'MULTI') $store_name = 'Multiple Stores';
                elseif ($store_code === 'ALL') $store_name = 'All Stores';
            }

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user']       = $uname;
            $_SESSION['store_code'] = $store_code;
            $_SESSION['store_name'] = $store_name;
            $_SESSION['role']       = $user['role'];
            $_SESSION['avatar']     = $user['avatar'];

            $_SESSION['transaction_date'] = date('Y-m-d');

            // ── Load Multi-Store Assignments ──────────────────
            if ($user['role'] === 'multi_store_admin') {
                $assigned = get_user_assigned_stores($db, $user['id']);
                $_SESSION['assigned_stores'] = array_column($assigned, 'store_code');
                $_SESSION['assigned_stores_data'] = $assigned;
            } else {
                $_SESSION['assigned_stores'] = [];
                $_SESSION['assigned_stores_data'] = [];
            }

            // ── Remember Me ───────────────────────────────────
            if (!empty($_POST['remember_me'])) {
                $cookie_expire = time() + (30 * 24 * 60 * 60); // 30 days
                setcookie('remember_username',   $uname,      $cookie_expire, '/');
                setcookie('remember_store_code', $store_code, $cookie_expire, '/');
            } else {
                setcookie('remember_username',   '', time() - 3600, '/');
                setcookie('remember_store_code', '', time() - 3600, '/');
            }
            
            // ── Clear Filter Sessions ─────────────────────────
            $filters = [
                'history_tab', 'history_limit', 'history_page', 'history_search',
                'monitoring_status', 'monitoring_limit', 'monitoring_page', 'monitoring_search', 'monitoring_start_date', 'monitoring_end_date',
                'dashboard_start_date', 'dashboard_end_date', 'dashboard_store_code',
                'sale_limit', 'sale_page', 'sale_search', 'sale_start_date', 'sale_end_date', 'sale_store_filter',
                'return_limit', 'return_page', 'return_search', 'return_start_date', 'return_end_date', 'return_store_filter',
                'receiving_limit', 'receiving_page', 'receiving_search', 'receiving_start_date', 'receiving_end_date', 'receiving_store_filter',
                'receiving_partial_limit', 'receiving_partial_page', 'receiving_partial_search', 'receiving_partial_start_date', 'receiving_partial_end_date', 'receiving_partial_store_filter',
                'pullout_status', 'pullout_limit', 'pullout_page', 'pullout_search', 'pullout_start_date', 'pullout_end_date', 'pullout_store_filter'
            ];
            foreach ($filters as $f) {
                unset($_SESSION[$f]);
            }
            
            if ($user['role'] === 'admin' || $user['role'] === 'admin_view' || $user['role'] === 'store_admin' || $user['role'] === 'multi_store_admin' || $uname === 'admin') {
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
$is_full_admin       = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
$is_admin_view       = ($role === 'admin_view');
$is_store_admin      = ($role === 'store_admin');
$is_multi_store_admin = ($role === 'multi_store_admin');

// General admin flag for showing admin-only modules/data
$is_admin = ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);

// Can submit NEW transactions (create) - Only regular users can create entries
$can_submit = ($role === 'user');

// Can edit EXISTING records (admin_view can edit, store_admin is view-only)
$can_edit = ($is_full_admin || $is_admin_view || $is_multi_store_admin);

// Can delete records (only full admin and view-only admin)
$can_delete = ($is_full_admin || $is_admin_view);

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

$allowed_pages = ['dashboard', 'monitoring', 'sale', 'return', 'receiving', 'ros_supplies', 'submitted', 'admin', 'history', 'pullout', 'stores', 'recent_activity', 'prism_data', 'non_submission'];

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
