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
        // Fetch user along with dynamic role permissions
        $stmt = $db->prepare("
            SELECT u.id, u.password, u.store_code, u.role, u.avatar, 
                   r.permissions, r.can_submit, r.can_edit, r.can_delete, r.is_admin 
            FROM users u 
            LEFT JOIN roles r ON u.role = r.role_name 
            WHERE u.username = ? LIMIT 1
        ");
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
            
            // ── Dynamic Permissions ───────────────────────────
            $_SESSION['user_permissions'] = json_decode($user['permissions'] ?? '[]', true) ?: ['sale', 'return', 'receiving', 'ros_supplies', 'history'];
            $_SESSION['can_submit'] = (bool)($user['can_submit'] ?? 1);
            $_SESSION['can_edit']   = (bool)($user['can_edit'] ?? 0);
            $_SESSION['can_delete'] = (bool)($user['can_delete'] ?? 0);
            $_SESSION['is_admin']   = (bool)($user['is_admin'] ?? 0);

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
                'pullout_status', 'pullout_limit', 'pullout_page', 'pullout_search', 'pullout_start_date', 'pullout_end_date', 'pullout_store_filter',
                'ns_limit', 'ns_page', 'ns_search', 'ns_start_date', 'ns_end_date', 'ns_store_filter'
            ];
            foreach ($filters as $f) {
                unset($_SESSION[$f]);
            }
            
            // Redirect to first available page based on permissions
            $perm = $_SESSION['user_permissions'];
            if (in_array('dashboard', $perm)) {
                header("Location: dashboard");
            } elseif (in_array('history', $perm)) {
                header("Location: history");
            } elseif (!empty($perm)) {
                header("Location: " . $perm[0]);
            } else {
                header("Location: sale");
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
$is_full_admin        = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
$is_admin_view        = ($role === 'admin_view');
$is_store_admin       = ($role === 'store_admin');
$is_multi_store_admin = ($role === 'multi_store_admin');

// ── Always reload permissions fresh from DB (so role edits take effect immediately) ──
$db = db_connect();
$_perm_stmt = $db->prepare("SELECT permissions, can_submit, can_edit, can_delete, is_admin FROM roles WHERE role_name = ? LIMIT 1");
$_perm_stmt->bind_param("s", $role);
$_perm_stmt->execute();
$_role_row = $_perm_stmt->get_result()->fetch_assoc();
$_perm_stmt->close();

if ($_role_row) {
    $user_permissions = json_decode($_role_row['permissions'] ?? '[]', true) ?: [];
    $is_admin   = (bool)$_role_row['is_admin'];
    $can_submit = (bool)$_role_row['can_submit'];
    $can_edit   = (bool)$_role_row['can_edit'];
    $can_delete = (bool)$_role_row['can_delete'];
    // Keep session in sync so partials that check $_SESSION work
    $_SESSION['user_permissions'] = $user_permissions;
    $_SESSION['is_admin']   = $is_admin;
    $_SESSION['can_submit'] = $can_submit;
    $_SESSION['can_edit']   = $can_edit;
    $_SESSION['can_delete'] = $can_delete;
} else {
    // Fallback for legacy roles not yet in the roles table
    $user_permissions = $_SESSION['user_permissions'] ?? ['sale', 'return', 'receiving', 'ros_supplies', 'history'];
    $is_admin   = $_SESSION['is_admin']   ?? ($is_full_admin || $role === 'admin_view' || $role === 'store_admin' || $role === 'multi_store_admin');
    $can_submit = $_SESSION['can_submit'] ?? ($role === 'user');
    $can_edit   = $_SESSION['can_edit']   ?? ($is_full_admin || $role === 'admin_view' || $role === 'multi_store_admin');
    $can_delete = $_SESSION['can_delete'] ?? ($is_full_admin || $role === 'admin_view');
}

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

$allowed_pages = [
    'dashboard', 'monitoring', 'history', 'admin', 'stores', 'roles', 'recent_activity', 'prism_data', 'boutique_data', 'non_submission',
    'sale', 'create_sale', 
    'return', 'create_return', 
    'receiving', 'create_receiving', 
    'pullout', 'create_pullout', 
    'ros_supplies', 'create_ros_supplies',
    'submitted', 'server_health'
];

// Check if the requested action is globally valid, AND if the user has permission to view it
$has_access = in_array($action, $allowed_pages) && in_array($action, $user_permissions);

if (!$has_access) {
    if (in_array('dashboard', $user_permissions)) $action = 'dashboard';
    else if (in_array('history', $user_permissions)) $action = 'history';
    else $action = $user_permissions[0] ?? 'dashboard';
}

// Map virtual create_* actions to their base files
$target_file = $action;
$can_submit = false;
$show_history_table = true;

if (strpos($action, 'create_') === 0) {
    $target_file = substr($action, 7); // e.g., 'create_sale' -> 'sale'
    $can_submit = true;
    $show_history_table = false; // Only show the creation form, not the table
} else if (in_array($action, ['sale', 'return', 'receiving', 'pullout', 'ros_supplies'])) {
    $can_submit = false; // Force form off
    $show_history_table = true; // Show data table
}

// Check for AJAX requests (to bypass layout)
if (isset($_GET['ajax']) && in_array($action, $allowed_pages)) {
    require "views/pages/{$target_file}.php";
    exit;
}

require 'views/layout.php';
