<?php
$is_full_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_full_admin) {
    echo "<div class='p-8 text-center text-red-400 font-bold'>Unauthorized Access. Full Admin Required.</div>";
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
    $where .= " AND (store_code LIKE ? OR store_name LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk; $params[] = $lk;
    $types .= "ss";
}

// Get Total for Pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM boutique $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$count_stmt->close();

// Fetch Rows
$stmt = $db->prepare("SELECT * FROM boutique $where ORDER BY date DESC LIMIT ? OFFSET ?");
$p_with_limit = array_merge($params, [$limit, $offset]);
$stmt->bind_param($types . "ii", ...$p_with_limit);
$stmt->execute();
$boutique_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_GET['ajax'])) {
    include 'views/pages/boutique_data_table_partial.php';
    exit;
}
?>

<style>
    input[type="date"] { color-scheme: dark; }
    input[type="date"]::-webkit-calendar-picker-indicator { 
        cursor: pointer;
        opacity: 1 !important;
        display: block !important;
    }
</style>

<div class="pb-12 animate-fade-in">
    <!-- Boutique Data Form -->
    <div class="glass-panel border border-white/5 shadow-xl mb-10 overflow-hidden">
        <div class="px-5 py-2.5 border-b border-white/5 bg-slate-800/25 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-store text-yellow-400 text-xs"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Manage Boutique Data</h3>
            </div>
        </div>

        <div class="p-6">
            <form id="boutique-form" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium cursor-pointer" onclick="this.showPicker()">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Code</label>
                    <input type="text" name="store_code" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium" placeholder="Code">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Name</label>
                    <input type="text" name="store_name" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium" placeholder="Name">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Qty Sold</label>
                    <input type="number" name="qty_sold" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium" placeholder="0">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Amount</label>
                    <input type="number" step="0.01" name="amount" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium" placeholder="0.00">
                </div>
                <div class="col-span-full flex items-end gap-2 mt-2">
                    <button type="submit" id="submit-boutique-btn" class="flex-1 h-[38px] rounded-lg bg-gradient-to-r from-yellow-500 to-amber-600 text-white text-[10px] font-black uppercase tracking-wider shadow-lg shadow-yellow-500/10 hover:-translate-y-0.5 transition-all">
                        Save Boutique Data
                    </button>
                    <button type="button" onclick="document.getElementById('boutique-csv-upload').click()" class="h-[38px] px-4 rounded-lg bg-slate-700/40 hover:bg-slate-700/60 text-emerald-400 border border-white/10 text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2">
                        <i class="fas fa-file-csv"></i> Import CSV
                    </button>
                    <input type="file" id="boutique-csv-upload" class="hidden" accept=".csv" onchange="uploadBoutiqueCSV(this)">
                </div>
            </form>
            <p class="text-[9px] text-gray-500 mt-3 italic font-medium tracking-wide">* CSV format: Date, Store Name, Store Code, Qty Sold, Amount (No Header)</p>
        </div>
    </div>

    <!-- Boutique Table Filters -->
    <div class="glass-panel border border-white/5 shadow-xl overflow-hidden mt-6">
        <div class="p-5 border-b border-white/5 bg-slate-800/30 space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <i class="fas fa-list text-yellow-400 text-sm"></i>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Boutique Data List</h3>
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
                        <button onclick="deleteAllBoutique()" class="h-8 flex items-center justify-center px-3 rounded-lg bg-red-600/10 hover:bg-red-600/20 text-red-500 border border-red-500/20 text-[10px] font-black tracking-widest transition-all mr-2">
                            <i class="fas fa-trash-sweep mr-2"></i> DELETE ALL
                        </button>
                        <button id="bulk-delete-boutique" onclick="bulkDeleteBoutique()" class="hidden h-8 flex items-center justify-center px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all">
                            <i class="fas fa-trash-alt mr-2"></i> DELETE SELECTED
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 bg-white/5 p-3 rounded-xl border border-white/5">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Search Store Code / Name</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." 
                               class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-xs text-white focus:outline-none focus:border-yellow-500/50">
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutique Table Container (AJAX Target) -->
        <div id="boutique-table-container">
            <?php include 'views/pages/boutique_data_table_partial.php'; ?>
        </div>
    </div>
</div>

<!-- Edit Boutique Modal -->
<div id="edit-boutique-modal" class="fixed inset-0 z-[102] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 scale-95">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeEditBoutiqueModal()"></div>
    <div class="relative glass-panel border border-white/10 shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-tr from-yellow-500/20 to-amber-600/20 p-5 border-b border-white/5 relative">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center">
                    <i class="fas fa-edit text-yellow-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Edit Boutique Data</h3>
                </div>
            </div>
            <button onclick="closeEditBoutiqueModal()" class="absolute top-5 right-5 text-gray-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="p-6">
            <form id="edit-boutique-form" class="space-y-4">
                <input type="hidden" name="id" id="edit-boutique-id">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Date</label>
                    <input type="date" name="date" id="edit-boutique-date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Code</label>
                        <input type="text" name="store_code" id="edit-boutique-code" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Name</label>
                        <input type="text" name="store_name" id="edit-boutique-name" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Qty Sold</label>
                        <input type="number" name="qty_sold" id="edit-boutique-qty" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Amount</label>
                        <input type="number" step="0.01" name="amount" id="edit-boutique-amount" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-yellow-500/50 font-medium">
                    </div>
                </div>
                
                <button type="submit" class="w-full py-4 mt-2 rounded-xl bg-gradient-to-r from-yellow-500 to-amber-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-yellow-500/20 hover:brightness-110 active:scale-[0.98] transition-all">
                    Update Boutique Data
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function refreshBoutiqueTable(page = 1) {
        const container = document.getElementById('boutique-table-container');
        const search = document.querySelector('[name="search"]')?.value || '';
        const limit  = document.querySelector('[name="limit"]')?.value || 10;
        
        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';
        
        const url = `index.php?action=boutique_data&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}&t=${new Date().getTime()}`;
        fetch(url).then(res => res.text()).then(html => { 
            container.innerHTML = html; 
            initRefreshableEvents(); 
        });
    }

    function initRefreshableEvents() {
        document.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                refreshBoutiqueTable(this.getAttribute('data-page'));
            });
        });

        const selectAll = document.getElementById('selectAll');
        const bulkBtn = document.getElementById('bulk-delete-boutique');
        
        selectAll?.addEventListener('change', function() {
            document.querySelectorAll('.boutique-checkbox').forEach(cb => cb.checked = this.checked);
            toggleBulkBtn();
        });

        document.querySelectorAll('.boutique-checkbox').forEach(cb => {
            cb.addEventListener('change', toggleBulkBtn);
        });

        function toggleBulkBtn() {
            const checked = document.querySelectorAll('.boutique-checkbox:checked').length;
            if (bulkBtn) bulkBtn.classList.toggle('hidden', checked === 0);
        }
    }

    function initPersistentEvents() {
        const searchInput = document.querySelector('[name="search"]');
        const limitSelect = document.querySelector('[name="limit"]');
        let filterTimer;

        searchInput?.addEventListener('input', () => {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => refreshBoutiqueTable(1), 300);
        });

        limitSelect?.addEventListener('change', () => refreshBoutiqueTable(1));
    }

    window.uploadBoutiqueCSV = function(input) {
        if (!input.files || !input.files[0]) return;
        
        const formData = new FormData();
        formData.append('csv', input.files[0]);
        
        showGlobalLoader("Uploading CSV...");
        
        fetch('api/upload_boutique_csv.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            hideGlobalLoader();
            showStatusModal(res.success, res.message, res.success ? 'Upload Success' : 'Upload Failed');
            if (res.success) refreshBoutiqueTable();
            input.value = '';
        })
        .catch(err => {
            hideGlobalLoader();
            showStatusModal(false, "Network error occurred.");
            input.value = '';
        });
    };

    window.editBoutique = function(id, date, storeCode, storeName, qtySold, amount) {
        document.getElementById('edit-boutique-id').value = id;
        document.getElementById('edit-boutique-date').value = date;
        document.getElementById('edit-boutique-code').value = storeCode;
        document.getElementById('edit-boutique-name').value = storeName;
        document.getElementById('edit-boutique-qty').value = qtySold;
        document.getElementById('edit-boutique-amount').value = amount;
        
        const modal = document.getElementById('edit-boutique-modal');
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('scale-100');
    };

    window.closeEditBoutiqueModal = function() {
        const modal = document.getElementById('edit-boutique-modal');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.remove('scale-100');
    };

    window.deleteBoutique = function(id) {
        showConfirmModal(`Are you sure you want to delete this record?`, () => {
            fetch(`api/delete_boutique.php?id=${id}`)
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Record Deleted' : 'Error');
                if (res.success) refreshBoutiqueTable();
            });
        }, 'Delete Boutique Data');
    };

    window.deleteAllBoutique = function() {
        const countText = document.getElementById('total-boutique-count')?.textContent || 'all';
        const totalNum = countText.includes('of') ? countText.split('of')[1].trim() : 'all';
        
        showConfirmModal(`<span class="text-red-500 font-bold">CRITICAL ACTION:</span> This will permanently delete <strong>${totalNum}</strong> records. This cannot be undone!`, () => {
            showGlobalLoader("CLEARING ALL DATA...");
            fetch('api/truncate_boutique.php')
            .then(r => r.json())
            .then(res => {
                hideGlobalLoader();
                showStatusModal(res.success, res.message, res.success ? 'Table Cleared' : 'Error');
                if (res.success) refreshBoutiqueTable();
            });
        }, 'DELETE ALL BOUTIQUE DATA');
    };

    window.bulkDeleteBoutique = function() {
        const ids = Array.from(document.querySelectorAll('.boutique-checkbox:checked')).map(cb => cb.value);
        if (ids.length === 0) return;

        showConfirmModal(`Delete ${ids.length} selected records?`, () => {
            fetch('api/bulk_delete_boutique.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids })
            })
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Records Deleted' : 'Error');
                if (res.success) refreshBoutiqueTable();
            });
        }, 'Bulk Delete');
    };

    document.getElementById('boutique-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const btn = document.getElementById('submit-boutique-btn');
        btn.disabled = true;
        btn.innerText = "SAVING...";

        fetch('api/save_boutique.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            showStatusModal(res.success, res.message, res.success ? 'Data Saved' : 'Error');
            if (res.success) {
                this.reset();
                refreshBoutiqueTable();
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = "Save Boutique Data";
        });
    });

    document.getElementById('edit-boutique-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        fetch('api/save_boutique.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            showStatusModal(res.success, res.message, res.success ? 'Data Updated' : 'Error');
            if (res.success) {
                closeEditBoutiqueModal();
                refreshBoutiqueTable();
            }
        });
    });

    initPersistentEvents();
    initRefreshableEvents();
})();
</script>
