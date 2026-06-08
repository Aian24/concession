<?php
if (!in_array('stores', $_SESSION['user_permissions'])) {
    echo "<div class='p-8 text-center text-red-400 font-bold'>Unauthorized Access</div>";
    exit;
}

require_once 'includes/db.php';
$db = db_connect();

// Search & Pagination Logic
$limit  = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
$page   = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (scode LIKE ? OR sname LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk; $params[] = $lk;
    $types .= "ss";
}

// Get Total for Pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM storecode $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$count_stmt->close();

// Fetch Rows
$stmt = $db->prepare("SELECT * FROM storecode $where ORDER BY scode ASC LIMIT ? OFFSET ?");
$p_with_limit = array_merge($params, [$limit, $offset]);
$stmt->bind_param($types . "ii", ...$p_with_limit);
$stmt->execute();
$stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_GET['ajax'])) {
    include 'views/pages/stores_table_partial.php';
    exit;
}
?>

<div class="pb-12 animate-fade-in">
    <!-- Store Management Form -->
    <div class="glass-panel border border-white/5 shadow-xl mb-10 overflow-hidden">
        <div class="px-5 py-2.5 border-b border-white/5 bg-slate-800/25 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-store text-blue-400 text-xs"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Add New Store</h3>
            </div>
        </div>

        <div class="p-6">
            <form id="store-form" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Code</label>
                    <input type="text" name="scode" id="store-scode" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium" placeholder="e.g. 14Y">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Name</label>
                    <input type="text" name="sname" id="store-sname" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium" placeholder="e.g. CASH & CARRY">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" id="submit-store-btn" class="flex-1 h-[38px] rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-[10px] font-black uppercase tracking-wider shadow-lg shadow-blue-500/10 hover:-translate-y-0.5 transition-all">
                        Save Store
                    </button>
                    <button type="button" onclick="document.getElementById('csv-upload').click()" class="h-[38px] px-4 rounded-lg bg-slate-700/40 hover:bg-slate-700/60 text-emerald-400 border border-white/10 text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2">
                        <i class="fas fa-file-csv"></i> Import CSV
                    </button>
                    <input type="file" id="csv-upload" class="hidden" accept=".csv" onchange="uploadCSV(this)">
                </div>
            </form>
        </div>
    </div>

    <!-- Store Table Filters -->
    <div class="glass-panel border border-white/5 shadow-xl overflow-hidden mt-6">
        <div class="p-5 border-b border-white/5 bg-slate-800/30 space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <i class="fas fa-list text-blue-400 text-sm"></i>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Store List</h3>
                </div>
                
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-2 mr-2">
                        <span class="text-[10px] text-gray-500 font-bold uppercase mr-1">Show</span>
                        <select name="limit" class="bg-slate-900 border border-white/10 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none">
                            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button id="bulk-delete-stores" onclick="bulkDeleteStores()" class="hidden h-8 flex items-center justify-center px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all">
                            <i class="fas fa-trash-alt mr-2"></i> DELETE SELECTED
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 bg-white/5 p-3 rounded-xl border border-white/5">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Search Stores</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Store Code or Name..." 
                               class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-xs text-white focus:outline-none focus:border-blue-500/50">
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Table Container (AJAX Target) -->
        <div id="stores-table-container">
            <?php include 'views/pages/stores_table_partial.php'; ?>
        </div>
    </div>
</div>

<!-- Edit Store Modal -->
<div id="edit-store-modal" class="fixed inset-0 z-[102] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 scale-95">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeEditStoreModal()"></div>
    <div class="relative glass-panel border border-white/10 shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-tr from-blue-600/20 to-indigo-600/20 p-5 border-b border-white/5 relative">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                    <i class="fas fa-edit text-blue-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Edit Store</h3>
                </div>
            </div>
            <button onclick="closeEditStoreModal()" class="absolute top-5 right-5 text-gray-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="p-6">
            <form id="edit-store-form" class="space-y-4">
                <input type="hidden" name="old_scode" id="edit-old-scode">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Code</label>
                    <input type="text" name="scode" id="edit-store-scode" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Name</label>
                    <input type="text" name="sname" id="edit-store-sname" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium">
                </div>
                
                <button type="submit" class="w-full py-4 mt-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-blue-500/20 hover:brightness-110 active:scale-[0.98] transition-all">
                    Update Store
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function refreshTable(page = 1) {
        const container = document.getElementById('stores-table-container');
        if (!container) return;
        const search = document.querySelector('[name="search"]')?.value || '';
        const limit  = document.querySelector('[name="limit"]')?.value || 10;
        
        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';
        
        const url = `index.php?action=stores&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}`;
        fetch(url).then(res => res.text()).then(html => { 
            container.innerHTML = html; 
            initRefreshableEvents(); 
        });
    }

    function initRefreshableEvents() {
        document.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                refreshTable(this.getAttribute('data-page'));
            });
        });

        // Checkbox Logic
        const selectAll = document.getElementById('selectAll');
        const bulkBtn = document.getElementById('bulk-delete-stores');
        
        selectAll?.addEventListener('change', function() {
            document.querySelectorAll('.store-checkbox').forEach(cb => cb.checked = this.checked);
            toggleBulkBtn();
        });

        document.querySelectorAll('.store-checkbox').forEach(cb => {
            cb.addEventListener('change', toggleBulkBtn);
        });

        function toggleBulkBtn() {
            const checked = document.querySelectorAll('.store-checkbox:checked').length;
            if (bulkBtn) bulkBtn.classList.toggle('hidden', checked === 0);
        }
    }

    function initPersistentEvents() {
        const searchInput = document.querySelector('[name="search"]');
        const limitSelect = document.querySelector('[name="limit"]');
        let filterTimer;

        searchInput?.addEventListener('input', () => {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => refreshTable(1), 800);
        });

        limitSelect?.addEventListener('change', () => refreshTable(1));
    }

    window.uploadCSV = function(input) {
        if (!input.files || !input.files[0]) return;
        
        const formData = new FormData();
        formData.append('csv', input.files[0]);
        
        showGlobalLoader("Uploading CSV...");
        
        fetch('api/upload_stores_csv.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            hideGlobalLoader();
            showStatusModal(res.success, res.message, res.success ? 'Upload Success' : 'Upload Failed');
            if (res.success) refreshTable();
            input.value = '';
        })
        .catch(err => {
            hideGlobalLoader();
            showStatusModal(false, "Network error occurred.");
            input.value = '';
        });
    };

    window.editStore = function(scode, sname) {
        document.getElementById('edit-old-scode').value = scode;
        document.getElementById('edit-store-scode').value = scode;
        document.getElementById('edit-store-sname').value = sname;
        
        const modal = document.getElementById('edit-store-modal');
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('scale-100');
    };

    window.closeEditStoreModal = function() {
        const modal = document.getElementById('edit-store-modal');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.remove('scale-100');
    };

    window.deleteStore = function(scode) {
        showConfirmModal(`Are you sure you want to delete store ${scode}?`, () => {
            fetch(`api/delete_store.php?scode=${encodeURIComponent(scode)}`)
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Store Deleted' : 'Error');
                if (res.success) refreshTable();
            });
        }, 'Delete Store');
    };

    window.bulkDeleteStores = function() {
        const scodes = Array.from(document.querySelectorAll('.store-checkbox:checked')).map(cb => cb.value);
        if (scodes.length === 0) return;

        showConfirmModal(`Delete ${scodes.length} selected stores?`, () => {
            fetch('api/bulk_delete_stores.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scodes: scodes })
            })
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Stores Deleted' : 'Error');
                if (res.success) refreshTable();
            });
        }, 'Bulk Delete');
    };

    document.getElementById('store-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const btn = document.getElementById('submit-store-btn');
        btn.disabled = true;
        btn.innerText = "SAVING...";

        fetch('api/save_store.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            showStatusModal(res.success, res.message, res.success ? 'Store Saved' : 'Error');
            if (res.success) {
                this.reset();
                refreshTable();
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = "Save Store";
        });
    });

    document.getElementById('edit-store-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        fetch('api/save_store.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            showStatusModal(res.success, res.message, res.success ? 'Store Updated' : 'Error');
            if (res.success) {
                closeEditStoreModal();
                refreshTable();
            }
        });
    });

    initPersistentEvents();
    initRefreshableEvents();
})();
</script>
