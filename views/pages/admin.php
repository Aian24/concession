<?php
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='glass-panel p-8 text-center animate-fade-in'><h2 class='text-2xl font-bold text-red-400 mb-2'>Access Denied</h2><p class='text-gray-400'>You do not have permission to view administrative controls.</p></div>";
    return;
}

require_once 'includes/db.php';
$db = db_connect();

// Fetch all store codes for the dropdown
$stores_res = $db->query("SELECT scode, sname FROM storecode ORDER BY sname ASC");
$store_list = $stores_res->fetch_all(MYSQLI_ASSOC);

// ── Action Handlers ──────────────────────────────────────────

// 1. CREATE or UPDATE User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $uid        = intval($_POST['user_id'] ?? 0);
    $uname      = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $role       = $_POST['role'] ?? 'user';
    $store_code = $_POST['store_code'] ?? '';

    if ($uname) {
        if ($uid > 0) {
            // UPDATE existing
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET username=?, password=?, role=?, store_code=? WHERE id=?");
                $stmt->bind_param("ssssi", $uname, $hashed, $role, $store_code, $uid);
            } else {
                $stmt = $db->prepare("UPDATE users SET username=?, role=?, store_code=? WHERE id=?");
                $stmt->bind_param("sssi", $uname, $role, $store_code, $uid);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['toast'] = "User updated successfully.";
        } else {
            // CREATE new
            $hashed = password_hash($password !== '' ? $password : '123456', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (username, password, role, store_code) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $uname, $hashed, $role, $store_code);
            $stmt->execute();
            $stmt->close();
            $_SESSION['toast'] = "User created successfully.";
        }
            echo "<script>window.location.href='admin';</script>";
            exit;
    }
}

// 2. DELETE User
if (isset($_GET['delete_user'])) {
    $uid = intval($_GET['delete_user']);
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND username != 'admin'");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->close();
    $_SESSION['toast'] = "User removed.";
    echo "<script>window.location.href='admin';</script>";
    exit;
}

// ── Data Fetching ───────────────────────────────────────────

$users_res = $db->query("
    SELECT u.id, u.username, u.store_code, sc.sname as store_name, u.role, u.created_at,
    (SELECT COUNT(*) FROM sales s WHERE s.username = u.username) as total_sales,
    (SELECT SUM(line_total) FROM sales s WHERE s.username = u.username) as revenue
    FROM users u
    LEFT JOIN storecode sc ON u.store_code = sc.scode
    ORDER BY u.created_at DESC
");
$all_users = $users_res->fetch_all(MYSQLI_ASSOC);
?>
<style>
    @media (max-width: 768px) {
        #users-management-table thead { display: none; }
        #users-management-table, #users-management-table tbody { display: block; width: 100%; }
        #users-management-table tr { 
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        #users-management-table tr:first-child { margin-top: 1.5rem; }
        #users-management-table td { 
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
        #users-management-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #users-management-table td[data-label="Select"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: auto;
            border: none;
            padding: 0;
            z-index: 10;
        }
        #users-management-table td[data-label="Select"]::before { display: none; }
        
        #users-management-table td span, 
        #users-management-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        #users-management-table td .flex-col { align-items: flex-start !important; text-align: left !important; }
        #users-management-table td[data-label="Identity"] .flex > div:first-child { display: none; }
    }
</style>
<?php
// Toast display
$toast = $_SESSION['toast'] ?? '';
unset($_SESSION['toast']);

// Editing state
$editUser = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    foreach($all_users as $u) if (intval($u['id']) === $eid) $editUser = $u;
}
?>

<div class="mb-8 flex justify-between items-end">
    <div>
        <h2 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500 mb-1">User Management</h2>
        <p class="text-gray-400">Add, edit, or remove users and monitor their sales performance.</p>
    </div>
    <?php if ($toast): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-2 rounded-xl text-sm font-semibold animate-bounce-short">
            <i class="fas fa-check-circle mr-2"></i><?= $toast ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- User Form (Add/Edit) -->
    <div class="lg:col-span-1">
        <div class="glass-panel border border-white/5 shadow-2xl sticky top-24 z-10 overflow-visible">
            <div class="p-5 border-b border-white/5 bg-slate-800/40 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas <?= $editUser ? 'fa-user-edit text-orange-400' : 'fa-user-plus text-purple-400' ?>"></i>
                    <h3 class="font-semibold text-white"><?= $editUser ? 'Edit User' : 'Create New User' ?></h3>
                </div>
                <?php if($editUser): ?>
                    <a href="admin" class="text-xs text-gray-500 hover:text-white transition-colors">Cancel</a>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="p-6 space-y-4 overflow-visible">
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?? 0 ?>">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Username</label>
                    <input type="text" name="username" required class="input-modern w-full" 
                           placeholder="Enter username" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                        <?= $editUser ? 'New Password (leave blank to keep)' : 'Password' ?>
                    </label>
                    <input type="password" name="password" <?= $editUser ? '' : 'required' ?> class="input-modern w-full" 
                           placeholder="<?= $editUser ? '••••••••' : 'Enter password' ?>">
                </div>
                

                
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Permission Role</label>
                    <select name="role" class="input-modern w-full appearance-none bg-slate-900">
                        <option value="user" <?= ($editUser['role']??'') === 'user' ? 'selected' : '' ?>>Standard User</option>
                        <option value="admin" <?= ($editUser['role']??'') === 'admin' ? 'selected' : '' ?>>System Administrator</option>
                        <option value="admin_view" <?= ($editUser['role']??'') === 'admin_view' ? 'selected' : '' ?>>View-Only Admin (All Stores)</option>
                        <option value="store_admin" <?= ($editUser['role']??'') === 'store_admin' ? 'selected' : '' ?>>Store Admin (Specific Store Only)</option>
                    </select>
                </div>

                <div class="relative" id="store-select-container">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Assigned Store</label>
                    <div class="relative">
                        <input type="text" id="store-search-input" class="input-modern w-full pr-10" 
                               placeholder="Search store..." 
                               value="<?= htmlspecialchars($editUser['store_name'] ?? ($editUser['store_code'] ?? '')) ?>"
                               autocomplete="off">
                        <input type="hidden" name="store_code" id="store-code-hidden" value="<?= htmlspecialchars($editUser['store_code'] ?? '') ?>">
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none">
                            <i class="fas fa-search text-xs"></i>
                        </div>
                        
                        <!-- Search Results Dropdown -->
                        <div id="store-results" class="hidden absolute left-0 right-0 top-full mt-2 bg-slate-900/95 border border-white/10 shadow-2xl max-h-48 overflow-y-auto z-[999] rounded-xl backdrop-blur-xl">
                            <?php foreach($store_list as $s): ?>
                                <div class="store-item p-3 hover:bg-white/10 cursor-pointer border-b border-white/5 transition-colors group" 
                                     data-code="<?= htmlspecialchars($s['scode']) ?>" 
                                     data-name="<?= htmlspecialchars($s['sname']) ?>">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-white group-hover:text-purple-400 transition-colors"><?= htmlspecialchars($s['sname']) ?></span>
                                        <span class="text-[9px] text-gray-500 font-bold tracking-widest uppercase"><?= htmlspecialchars($s['scode']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div id="no-results" class="hidden p-4 text-center text-xs text-gray-500 italic">No stores found...</div>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" name="save_user" class="w-full py-3 rounded-xl font-bold transition-all shadow-lg shadow-purple-500/20 hover:-translate-y-0.5 <?= $editUser ? 'bg-gradient-to-r from-orange-500 to-amber-600 text-white' : 'bg-gradient-to-r from-purple-600 to-pink-600 text-white' ?>">
                        <?= $editUser ? 'Update Account' : 'Register Account' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="lg:col-span-2">
        <div class="glass-panel border border-white/5 shadow-2xl overflow-hidden">
            <div class="p-5 border-b border-white/5 bg-slate-800/40 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-users text-purple-400"></i>
                    <h3 class="font-semibold text-white">System Users</h3>
                </div>
                <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500"><?= count($all_users) ?> Accounts</span>
            </div>
            
            <div class="p-4 border-b border-white/5 bg-slate-800/20 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 text-xs text-gray-400 font-medium">
                        <span>Show</span>
                        <select id="user-entries" class="bg-slate-900 border border-white/10 rounded-lg px-2 py-1 focus:outline-none focus:border-purple-500 text-white font-bold">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <span>entries</span>
                    </div>
                    <button id="bulk-delete-btn" onclick="bulkDeleteUsers()" class="hidden h-8 px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all flex items-center gap-2">
                        <i class="fas fa-trash-alt"></i> DELETE SELECTED
                    </button>
                </div>
                <div class="relative w-full sm:w-64">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                    <input type="text" id="user-search" placeholder="Search users..." class="w-full bg-slate-900 border border-white/10 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-purple-500/50 transition-all font-medium">
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse glass-table whitespace-nowrap" id="users-management-table">
                    <thead>
                        <tr>
                            <th class="p-4 w-10 text-center">
                                <input type="checkbox" id="selectAllUsers" class="rounded border-white/20 bg-slate-900 text-purple-500 focus:ring-purple-500/20">
                            </th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase">Identity</th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase">Store</th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase">Performance</th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase">Created</th>
                            <th class="p-4 font-semibold text-gray-400 text-[10px] tracking-widest uppercase text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach($all_users as $u): ?>
                        <tr class="hover:bg-white/5 transition-colors group">
                            <td class="p-4 text-center" data-label="Select">
                                <?php if($u['username'] !== 'admin'): ?>
                                    <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="user-checkbox rounded border-white/20 bg-slate-900 text-purple-500 focus:ring-purple-500/20">
                                <?php endif; ?>
                            </td>
                            <td class="p-4" data-label="Identity">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-slate-700 to-slate-800 border border-white/10 flex items-center justify-center text-white font-bold text-xs">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold tracking-wide"><?= htmlspecialchars($u['username']) ?></span>
                                        <span class="text-[9px] font-extrabold uppercase tracking-widest <?= $u['role'] === 'admin' ? 'text-purple-400' : 'text-gray-500' ?>">
                                            <?= $u['role'] ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4" data-label="Store">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold tracking-wide text-xs"><?= htmlspecialchars($u['store_name'] ?: ($u['store_code'] ?: 'N/A')) ?></span>
                                    <span class="text-[9px] font-extrabold uppercase tracking-widest text-gray-500">
                                        <?= htmlspecialchars($u['store_code'] ?: '---') ?>
                                    </span>
                                </div>
                            </td>

                            <td class="p-4" data-label="Performance">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-[2px] bg-white/5 hidden md:block"></div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-shopping-cart text-[10px] text-gray-500"></i>
                                            <span class="text-white font-bold"><?= $u['total_sales'] ?></span>
                                        </div>
                                        <span class="text-emerald-400 font-bold text-[10px]">₱<?= number_format($u['revenue'] ?? 0, 2) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-gray-500 text-[10px]" data-label="Created">
                                <?= date('M d, Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="p-4" data-label="Actions">
                                <div class="flex justify-end md:justify-end gap-2">
                                    <a href="admin?edit=<?= $u['id'] ?>" class="w-8 h-8 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 transition-colors flex items-center justify-center" title="Edit User">
                                        <i class="fas fa-edit text-xs"></i>
                                    </a>
                                    <?php if($u['username'] !== 'admin'): ?>
                                        <button onclick="showConfirmModal('Permanently delete this user? All associated history will remain but the account will be gone.', () => { showGlobalLoader('DELETING USER...'); window.location.href='admin?delete_user=<?= $u['id'] ?>'; }, 'Delete User')" 
                                                class="w-8 h-8 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 transition-colors flex items-center justify-center" title="Delete User">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t border-white/5 bg-slate-800/20 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest text-center md:text-left" id="user-table-info">
                    Showing 0 to 0 of 0 entries
                </div>
                <div class="flex items-center gap-1" id="user-pagination">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const tableBody = document.querySelector('.glass-table tbody');
    if (!tableBody) return;

    const originalRows = Array.from(tableBody.querySelectorAll('tr'));
    const searchInput = document.getElementById('user-search');
    const entriesSelect = document.getElementById('user-entries');
    const tableInfo = document.getElementById('user-table-info');
    const paginationContainer = document.getElementById('user-pagination');
    const selectAllCheckbox = document.getElementById('selectAllUsers');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    
    if (!searchInput || !entriesSelect || !tableInfo || !paginationContainer) return;

    let filteredRows = [...originalRows];
    let currentPage = 1;
    let entriesPerPage = parseInt(entriesSelect.value);

    // Bulk delete handlers
    function updateBulkDeleteVisibility() {
        const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
        if (checkedCount > 0) {
            bulkDeleteBtn.classList.remove('hidden');
        } else {
            bulkDeleteBtn.classList.add('hidden');
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkDeleteVisibility();
        });
    }

    tableBody.addEventListener('change', (e) => {
        if (e.target.classList.contains('user-checkbox')) {
            updateBulkDeleteVisibility();
        }
    });

    window.bulkDeleteUsers = function() {
        const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) return;

        showConfirmModal(`Are you sure you want to delete ${selectedIds.length} selected users? This action cannot be undone.`, () => {
            showGlobalLoader('DELETING USERS...');
            fetch('api/bulk_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ table: 'users', ids: selectedIds })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    window.location.reload();
                } else {
                    hideGlobalLoader();
                    showStatusModal(false, res.message || 'Failed to delete users.');
                }
            })
            .catch(() => {
                hideGlobalLoader();
                showStatusModal(false, 'A network error occurred.');
            });
        }, 'Bulk Delete Users');
    };

    function updateTable() {
        const totalRows = filteredRows.length;
        const totalPages = Math.ceil(totalRows / entriesPerPage) || 1;
        
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * entriesPerPage;
        const end = Math.min(start + entriesPerPage, totalRows);

        originalRows.forEach(r => r.style.display = 'none');

        for (let i = start; i < end; i++) {
            if (filteredRows[i]) {
                filteredRows[i].style.display = '';
            }
        }

        tableInfo.textContent = totalRows > 0 
            ? `Showing ${start + 1} to ${end} of ${totalRows} entries` 
            : `Showing 0 to 0 of 0 entries`;

        paginationContainer.innerHTML = '';
        
        if (totalPages <= 1) return;

        const prevBtn = document.createElement('button');
        prevBtn.className = `w-8 h-8 rounded-lg flex items-center justify-center text-xs border border-white/5 transition-all font-bold ${currentPage === 1 ? 'text-gray-600 cursor-not-allowed bg-white/5' : 'text-gray-400 hover:bg-purple-500 hover:text-white bg-white/10'}`;
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => { currentPage--; updateTable(); };
        paginationContainer.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            const isActive = i === currentPage;
            pageBtn.className = `w-8 h-8 rounded-lg text-xs border font-bold transition-all ${isActive ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white border-transparent shadow-lg shadow-purple-500/20' : 'text-gray-400 border-white/5 hover:bg-white/10 bg-white/5'}`;
            pageBtn.textContent = i;
            pageBtn.onclick = () => { currentPage = i; updateTable(); };
            paginationContainer.appendChild(pageBtn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.className = `w-8 h-8 rounded-lg flex items-center justify-center text-xs border border-white/5 transition-all font-bold ${currentPage === totalPages ? 'text-gray-600 cursor-not-allowed bg-white/5' : 'text-gray-400 hover:bg-purple-500 hover:text-white bg-white/10'}`;
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => { currentPage++; updateTable(); };
        paginationContainer.appendChild(nextBtn);
    }

    function filterTable() {
        const query = searchInput.value.toLowerCase().trim();
        
        filteredRows = originalRows.filter(row => {
            const userSpan = row.querySelector('.flex.flex-col span.text-white');
            const roleSpan = row.querySelector('.flex.flex-col span.text-\\[9px\\]');
            const storeSpan = row.cells[2] ? row.cells[2].querySelector('span.text-white') : null;
            const storeCodeSpan = row.cells[2] ? row.cells[2].querySelector('span.text-\\[9px\\]') : null;
            
            const username = userSpan ? userSpan.textContent.toLowerCase() : '';
            const role = roleSpan ? roleSpan.textContent.toLowerCase() : '';
            const store = storeSpan ? storeSpan.textContent.toLowerCase() : '';
            const storeCode = storeCodeSpan ? storeCodeSpan.textContent.toLowerCase() : '';
            
            return username.includes(query) || role.includes(query) || store.includes(query) || storeCode.includes(query);
        });

        currentPage = 1;
        updateTable();
    }

    searchInput.addEventListener('input', filterTable);
    entriesSelect.addEventListener('change', () => {
        entriesPerPage = parseInt(entriesSelect.value);
        currentPage = 1;
        updateTable();
    });

    updateTable();
    updateBulkDeleteVisibility();

    // Store Searchable Dropdown Logic
    const storeSearchInput = document.getElementById('store-search-input');
    const storeCodeHidden = document.getElementById('store-code-hidden');
    const storeResults = document.getElementById('store-results');
    const storeItems = document.querySelectorAll('.store-item');
    const noResults = document.getElementById('no-results');

    if (storeSearchInput) {
        storeSearchInput.addEventListener('focus', () => {
            storeResults.classList.remove('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#store-select-container')) {
                storeResults.classList.add('hidden');
            }
        });

        storeSearchInput.addEventListener('input', () => {
            const val = storeSearchInput.value.toLowerCase().trim();
            let count = 0;

            storeItems.forEach(item => {
                const name = item.getAttribute('data-name').toLowerCase();
                const code = item.getAttribute('data-code').toLowerCase();

                if (name.includes(val) || code.includes(val)) {
                    item.classList.remove('hidden');
                    count++;
                } else {
                    item.classList.add('hidden');
                }
            });

            if (count === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
            storeResults.classList.remove('hidden');
        });

        storeItems.forEach(item => {
            item.addEventListener('click', () => {
                const name = item.getAttribute('data-name');
                const code = item.getAttribute('data-code');

                storeSearchInput.value = name;
                storeCodeHidden.value = code;
                storeResults.classList.add('hidden');
            });
        });
    }
});
</script>

</body>
</html>
