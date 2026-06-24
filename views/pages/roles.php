<?php
if (!in_array('roles', $_SESSION['user_permissions'])) {
    echo "<div class='glass-panel p-8 text-center animate-fade-in'><h2 class='text-2xl font-bold text-red-400 mb-2'>Access Denied</h2><p class='text-gray-400'>You do not have permission to view roles management.</p></div>";
    return;
}

require_once 'includes/db.php';
$db = db_connect();

// ── Action Handlers ──────────────────────────────────────────

// 1. CREATE or UPDATE Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role'])) {
    $rid          = intval($_POST['role_id'] ?? 0);
    $role_name    = trim($_POST['role_name'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    
    // Permissions (array of page keys)
    $permissions = $_POST['permissions'] ?? [];
    $perm_json   = json_encode($permissions);

    $can_submit = isset($_POST['can_submit']) ? 1 : 0;
    $can_edit   = isset($_POST['can_edit']) ? 1 : 0;
    $can_delete = isset($_POST['can_delete']) ? 1 : 0;
    $is_admin   = isset($_POST['is_admin']) ? 1 : 0;

    if ($role_name && $display_name) {
        try {
            if ($rid > 0) {
                // UPDATE existing
                // Prevent editing 'admin' core role's internal name just in case, but display name and permissions are fine
                $stmt = $db->prepare("UPDATE roles SET role_name=?, display_name=?, permissions=?, can_submit=?, can_edit=?, can_delete=?, is_admin=? WHERE id=?");
                $stmt->bind_param("sssiiiii", $role_name, $display_name, $perm_json, $can_submit, $can_edit, $can_delete, $is_admin, $rid);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast'] = "Role updated successfully.";
            } else {
                // CREATE new
                $stmt = $db->prepare("INSERT INTO roles (role_name, display_name, permissions, can_submit, can_edit, can_delete, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssiiii", $role_name, $display_name, $perm_json, $can_submit, $can_edit, $can_delete, $is_admin);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast'] = "Role created successfully.";
            }

            // Immediately update the current user's session if they are editing their own active role
            if (isset($_SESSION['role']) && $_SESSION['role'] === $role_name) {
                $_SESSION['user_permissions'] = $permissions;
                $_SESSION['can_submit'] = (bool)$can_submit;
                $_SESSION['can_edit']   = (bool)$can_edit;
                $_SESSION['can_delete'] = (bool)$can_delete;
                $_SESSION['is_admin']   = (bool)$is_admin;
            }

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $_SESSION['toast_error'] = "Role internal name '{$role_name}' already exists!";
            } else {
                $_SESSION['toast_error'] = "Database error: " . $e->getMessage();
            }
            unset($_SESSION['toast']);
        }
        echo "<script>window.location.href='roles';</script>";
        exit;
    }
}

// 2. DELETE Role
if (isset($_GET['delete_role'])) {
    $rid = intval($_GET['delete_role']);
    
    // Prevent deleting core admin role
    $check = $db->query("SELECT role_name FROM roles WHERE id = $rid")->fetch_assoc();
    if ($check && $check['role_name'] === 'admin') {
        $_SESSION['toast_error'] = "Cannot delete the core administrator role.";
    } else {
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['toast'] = "Role removed.";
    }
    echo "<script>window.location.href='roles';</script>";
    exit;
}

// ── Data Fetching ───────────────────────────────────────────

$roles_res = $db->query("
    SELECT r.*,
    (SELECT COUNT(*) FROM users u WHERE u.role = r.role_name) as user_count
    FROM roles r
    ORDER BY r.created_at ASC
");
$all_roles = $roles_res->fetch_all(MYSQLI_ASSOC);

$all_modules = [
    'dashboard' => 'Dashboard',
    'monitoring' => 'Monitoring',
    'history' => 'Today\'s Transact',
    'create_sale' => 'Create Sales',
    'sale' => 'Sales (Data Table)',
    'create_return' => 'Create Return',
    'return' => 'Return (Data Table)',
    'create_receiving' => 'Create Receiving',
    'receiving' => 'Receiving (Data Table)',
    'create_pullout' => 'Create Pullout',
    'pullout' => 'Pullout (Data Table)',
    'create_ros_supplies' => 'Create ROS Supplies',
    'ros_supplies' => 'ROS Supplies (Data Table)',
    'non_submission' => 'Non-Submission',
    'admin' => 'Manage Users',
    'roles' => 'Manage Roles',
    'stores' => 'Manage Stores',
    'prism_data' => 'Manage Prism Data',
    'boutique_data' => 'Manage Boutique Data',
    'recent_activity' => 'Recent Activity',
    'server_health' => 'Server Health'
];

// Toast display
$toast = $_SESSION['toast'] ?? '';
unset($_SESSION['toast']);

// Editing state
$editRole = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    foreach($all_roles as $r) if (intval($r['id']) === $eid) $editRole = $r;
}
?>

<div class="mb-8 flex justify-between items-end">
    <div>
        <h2 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500 mb-1">Role Management</h2>
        <p class="text-gray-400">Add, edit, or remove system roles and assign specific page permissions.</p>
    </div>
    <?php if ($toast): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-2 rounded-xl text-sm font-semibold animate-bounce-short">
            <i class="fas fa-check-circle mr-2"></i><?= $toast ?>
        </div>
    <?php endif; ?>
    <?php if ($toast_error = $_SESSION['toast_error'] ?? ''): unset($_SESSION['toast_error']); ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-2 rounded-xl text-sm font-semibold animate-bounce-short">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($toast_error) ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Role Form (Add/Edit) -->
    <div class="lg:col-span-1">
        <div class="glass-panel border border-white/5 shadow-2xl sticky top-24 z-10 overflow-visible">
            <div class="p-5 border-b border-white/5 bg-slate-800/40 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas <?= $editRole ? 'fa-user-shield text-orange-400' : 'fa-plus-circle text-purple-400' ?>"></i>
                    <h3 class="font-semibold text-white"><?= $editRole ? 'Edit Role' : 'Create New Role' ?></h3>
                </div>
                <?php if($editRole): ?>
                    <a href="roles" class="text-xs text-gray-500 hover:text-white transition-colors">Cancel</a>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="p-6 space-y-4 overflow-visible">
                <input type="hidden" name="role_id" value="<?= $editRole['id'] ?? 0 ?>">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Internal Name (No Spaces)</label>
                    <input type="text" name="role_name" required class="input-modern w-full" 
                           placeholder="e.g. area_manager" value="<?= htmlspecialchars($editRole['role_name'] ?? '') ?>"
                           <?= ($editRole['role_name'] ?? '') === 'admin' ? 'readonly' : '' ?>>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Display Name</label>
                    <input type="text" name="display_name" required class="input-modern w-full" 
                           placeholder="e.g. Area Manager" value="<?= htmlspecialchars($editRole['display_name'] ?? '') ?>">
                </div>

                <div class="pt-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Global Privileges</label>
                    <div class="space-y-2 bg-slate-900/50 p-3 rounded-xl border border-white/5">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_admin" class="custom-checkbox" <?= ($editRole['is_admin'] ?? false) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium text-gray-300">Enable Access to All Stores Data (Corporate View)</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer hidden">
                            <input type="checkbox" name="can_submit" class="custom-checkbox" <?= ($editRole['can_submit'] ?? false) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium text-gray-300">Can Submit New Records (Legacy)</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="can_edit" class="custom-checkbox" <?= ($editRole['can_edit'] ?? false) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium text-gray-300">Can Edit Existing Records</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="can_delete" class="custom-checkbox" <?= ($editRole['can_delete'] ?? false) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium text-gray-300">Can Delete Records</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Page Access Permissions</label>
                    <div class="bg-slate-900 border border-white/10 rounded-xl overflow-hidden flex flex-col max-h-64 overflow-y-auto p-2 space-y-1">
                        <?php 
                        $current_perms = json_decode($editRole['permissions'] ?? '[]', true) ?: [];
                        foreach($all_modules as $key => $title): 
                            $checked = in_array($key, $current_perms) ? 'checked' : '';
                        ?>
                            <label class="flex items-center gap-3 p-2 hover:bg-white/5 rounded-lg cursor-pointer transition-colors group">
                                <input type="checkbox" name="permissions[]" value="<?= $key ?>" <?= $checked ?> class="custom-checkbox">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-white group-hover:text-purple-400 transition-colors"><?= htmlspecialchars($title) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="save_role" class="w-full py-3 rounded-xl font-bold transition-all shadow-lg shadow-purple-500/20 hover:-translate-y-0.5 <?= $editRole ? 'bg-gradient-to-r from-orange-500 to-amber-600 text-white' : 'bg-gradient-to-r from-purple-600 to-pink-600 text-white' ?>">
                        <?= $editRole ? 'Update Role' : 'Create Role' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Roles List -->
    <div class="lg:col-span-2">
        <div class="glass-panel border border-white/5 shadow-2xl overflow-hidden">
            <div class="p-5 border-b border-white/5 bg-slate-800/40 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-shield-alt text-purple-400"></i>
                    <h3 class="font-semibold text-white">System Roles</h3>
                </div>
                <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500"><?= count($all_roles) ?> Roles</span>
            </div>
            
            <style>
                @media (max-width: 768px) {
                    #roles-table thead { display: none; }
                    #roles-table, #roles-table tbody { display: block; width: 100%; }
                    #roles-table tr { 
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 12px;
                        margin-bottom: 1.5rem; 
                        margin-left: 1.25rem;
                        margin-right: 1.25rem;
                        border: 1px solid rgba(255,255,255,0.08); 
                        border-radius: 1.5rem; 
                        padding: 1.25rem; 
                        background: linear-gradient(145deg, rgba(30, 41, 59, 0.4), rgba(15, 23, 42, 0.4));
                        position: relative;
                        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
                    }
                    #roles-table tr:first-child { margin-top: 1.5rem; }
                    #roles-table td { 
                        display: flex; 
                        flex-direction: column;
                        justify-content: flex-start; 
                        align-items: flex-start; 
                        padding: 0; 
                        border: none; 
                        white-space: normal;
                        min-width: 0;
                        grid-column: span 1 !important;
                    }
                    #roles-table td::before { 
                        content: attr(data-label); 
                        font-weight: 900; 
                        text-transform: uppercase; 
                        font-size: 7px; 
                        color: #64748b; 
                        letter-spacing: 0.1em;
                        margin-bottom: 4px;
                        opacity: 0.8;
                    }
                    
                    #roles-table td[data-label="Actions"] {
                        position: absolute;
                        top: 1rem;
                        right: 1rem;
                        width: auto;
                        border: none;
                        padding: 0;
                        z-index: 10;
                    }
                    #roles-table td[data-label="Actions"]::before { display: none; }
                    #roles-table td[data-label="Actions"] .flex { 
                        justify-content: flex-end; 
                        overflow: visible !important;
                    }
                    #roles-table td[data-label="Actions"] button,
                    #roles-table td[data-label="Actions"] a {
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        padding: 0 !important;
                        margin: 0 !important;
                    }
                    #roles-table td[data-label="Actions"] button i,
                    #roles-table td[data-label="Actions"] a i {
                        display: block !important;
                        line-height: 1 !important;
                        margin: auto !important;
                    }
                    
                    #roles-table td span, 
                    #roles-table td div { 
                        font-size: 10px !important; 
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    #roles-table td[data-label="Role Display Name"] { grid-column: span 2 !important; }
                }
            </style>
            <div class="overflow-x-auto min-h-[300px]">
                <table id="roles-table" class="w-full text-left border-collapse glass-table whitespace-nowrap">
                    <thead>
                        <tr>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase">Role Display Name</th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase">Internal Key</th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase text-center">Users Assigned</th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach($all_roles as $r): ?>
                        <tr class="hover:bg-white/5 transition-colors group border-b border-white/5 last:border-0 border-r border-transparent hover:border-r-purple-500/50">
                            <td class="p-4" data-label="Role Display Name">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-purple-700 to-pink-600 border border-white/10 flex items-center justify-center text-white font-bold text-xs">
                                        <?= strtoupper(substr($r['display_name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold tracking-wide"><?= htmlspecialchars($r['display_name']) ?></span>
                                        <div class="flex gap-1 mt-1">
                                            <?php if ($r['is_admin']) echo '<span class="text-[8px] px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-400 font-bold uppercase">Admin</span>'; ?>
                                            <?php if ($r['can_submit']) echo '<span class="text-[8px] px-1.5 py-0.5 rounded bg-green-500/20 text-green-400 font-bold uppercase">Submit</span>'; ?>
                                            <?php if ($r['can_edit']) echo '<span class="text-[8px] px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400 font-bold uppercase">Edit</span>'; ?>
                                            <?php if ($r['can_delete']) echo '<span class="text-[8px] px-1.5 py-0.5 rounded bg-red-500/20 text-red-400 font-bold uppercase">Delete</span>'; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4" data-label="Internal Key">
                                <span class="text-gray-400 font-mono text-xs"><?= htmlspecialchars($r['role_name']) ?></span>
                            </td>
                            <td class="p-4 md:text-center" data-label="Users Assigned">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold <?= $r['user_count'] > 0 ? 'bg-blue-500/20 text-blue-400' : 'bg-slate-700 text-gray-400' ?>">
                                    <?= $r['user_count'] ?> Users
                                </span>
                            </td>
                            <td class="p-4" data-label="Actions">
                                <div class="flex justify-end gap-2">
                                    <a href="roles?edit=<?= $r['id'] ?>" class="w-8 h-8 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 transition-colors flex items-center justify-center" title="Edit Role">
                                        <i class="fas fa-edit text-xs"></i>
                                    </a>
                                    <?php if($r['role_name'] !== 'admin'): ?>
                                        <?php if($r['user_count'] == 0): ?>
                                            <button onclick="showConfirmModal('Permanently delete this role?', () => { showGlobalLoader('DELETING ROLE...'); window.location.href='roles?delete_role=<?= $r['id'] ?>'; }, 'Delete Role')" 
                                                    class="w-8 h-8 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 transition-colors flex items-center justify-center" title="Delete Role">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="showStatusModal(false, 'Cannot delete role because <?= $r['user_count'] ?> users are assigned to it.')" 
                                                    class="w-8 h-8 rounded-lg bg-gray-500/10 text-gray-500 cursor-not-allowed flex items-center justify-center" title="Cannot delete (in use)">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
