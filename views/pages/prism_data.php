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
    $where .= " AND (item_no LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk;
    $types .= "s";
}

// Get Total for Pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM prismdata $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$count_stmt->close();

// Fetch Rows
$stmt = $db->prepare("SELECT * FROM prismdata $where ORDER BY item_no ASC LIMIT ? OFFSET ?");
$p_with_limit = array_merge($params, [$limit, $offset]);
$stmt->bind_param($types . "ii", ...$p_with_limit);
$stmt->execute();
$prism_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_GET['ajax'])) {
    include 'views/pages/prism_data_table_partial.php';
    exit;
}
?>

<div class="pb-12 animate-fade-in">
    <!-- Prism Data Form -->
    <div class="glass-panel border border-white/5 shadow-xl mb-10 overflow-hidden">
        <div class="px-5 py-2.5 border-b border-white/5 bg-slate-800/25 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-gem text-blue-400 text-xs"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Manage Prism Data</h3>
            </div>
        </div>

        <div class="p-6">
            <form id="prism-form" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Item Number</label>
                    <input type="text" name="item_no" id="prism-item-no" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium" placeholder="e.g. ITEM-001">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">SRP</label>
                    <input type="number" step="0.01" name="srp" id="prism-srp" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium" placeholder="0.00">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" id="submit-prism-btn" class="flex-1 h-[38px] rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-[10px] font-black uppercase tracking-wider shadow-lg shadow-blue-500/10 hover:-translate-y-0.5 transition-all">
                        Save Prism Data
                    </button>
                    <button type="button" onclick="document.getElementById('prism-csv-upload').click()" class="h-[38px] px-4 rounded-lg bg-slate-700/40 hover:bg-slate-700/60 text-emerald-400 border border-white/10 text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2">
                        <i class="fas fa-file-csv"></i> Import CSV
                    </button>
                    <input type="file" id="prism-csv-upload" class="hidden" accept=".csv" onchange="uploadPrismCSV(this)">
                </div>
            </form>
            <p class="text-[9px] text-gray-500 mt-3 italic font-medium tracking-wide">* CSV format: Column 1 = Item No, Column 2 = SRP (No Header)</p>
        </div>
    </div>

    <!-- Prism Table Filters -->
    <div class="glass-panel border border-white/5 shadow-xl overflow-hidden mt-6">
        <div class="p-5 border-b border-white/5 bg-slate-800/30 space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <i class="fas fa-list text-blue-400 text-sm"></i>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Prism List</h3>
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
                        <button onclick="deleteAllPrism()" class="h-8 flex items-center justify-center px-3 rounded-lg bg-red-600/10 hover:bg-red-600/20 text-red-500 border border-red-500/20 text-[10px] font-black tracking-widest transition-all mr-2">
                            <i class="fas fa-trash-sweep mr-2"></i> DELETE ALL
                        </button>
                        <button id="bulk-delete-prism" onclick="bulkDeletePrism()" class="hidden h-8 flex items-center justify-center px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all">
                            <i class="fas fa-trash-alt mr-2"></i> DELETE SELECTED
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 bg-white/5 p-3 rounded-xl border border-white/5">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Search Item Number</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Item Number..." 
                               class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-xs text-white focus:outline-none focus:border-blue-500/50">
                    </div>
                </div>
            </div>
        </div>

        <!-- Prism Table Container (AJAX Target) -->
        <div id="prism-table-container">
            <?php include 'views/pages/prism_data_table_partial.php'; ?>
        </div>
    </div>
</div>

<!-- Edit Prism Modal -->
<div id="edit-prism-modal" class="fixed inset-0 z-[102] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 scale-95">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeEditPrismModal()"></div>
    <div class="relative glass-panel border border-white/10 shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-tr from-blue-600/20 to-indigo-600/20 p-5 border-b border-white/5 relative">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                    <i class="fas fa-edit text-blue-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Edit Prism Data</h3>
                </div>
            </div>
            <button onclick="closeEditPrismModal()" class="absolute top-5 right-5 text-gray-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="p-6">
            <form id="edit-prism-form" class="space-y-4">
                <input type="hidden" name="id" id="edit-prism-id">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Item Number</label>
                    <input type="text" name="item_no" id="edit-prism-item-no" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">SRP</label>
                    <input type="number" step="0.01" name="srp" id="edit-prism-srp" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 font-medium">
                </div>
                
                <button type="submit" class="w-full py-4 mt-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-blue-500/20 hover:brightness-110 active:scale-[0.98] transition-all">
                    Update Prism Data
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function refreshPrismTable(page = 1) {
        const container = document.getElementById('prism-table-container');
        const search = document.querySelector('[name="search"]')?.value || '';
        const limit  = document.querySelector('[name="limit"]')?.value || 10;
        
        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';
        
        const url = `index.php?action=prism_data&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}&t=${new Date().getTime()}`;
        fetch(url).then(res => res.text()).then(html => { 
            container.innerHTML = html; 
            initRefreshableEvents(); 
        });
    }

    function initRefreshableEvents() {
        document.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                refreshPrismTable(this.getAttribute('data-page'));
            });
        });

        // Checkbox Logic
        const selectAll = document.getElementById('selectAll');
        const bulkBtn = document.getElementById('bulk-delete-prism');
        
        selectAll?.addEventListener('change', function() {
            document.querySelectorAll('.prism-checkbox').forEach(cb => cb.checked = this.checked);
            toggleBulkBtn();
        });

        document.querySelectorAll('.prism-checkbox').forEach(cb => {
            cb.addEventListener('change', toggleBulkBtn);
        });

        function toggleBulkBtn() {
            const checked = document.querySelectorAll('.prism-checkbox:checked').length;
            if (bulkBtn) bulkBtn.classList.toggle('hidden', checked === 0);
        }
    }

    function initPersistentEvents() {
        const searchInput = document.querySelector('[name="search"]');
        const limitSelect = document.querySelector('[name="limit"]');
        let filterTimer;

        searchInput?.addEventListener('input', () => {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => refreshPrismTable(1), 300);
        });

        limitSelect?.addEventListener('change', () => refreshPrismTable(1));
    }

    window.uploadPrismCSV = function(input) {
        if (!input.files || !input.files[0]) return;
        
        const formData = new FormData();
        formData.append('csv', input.files[0]);
        
        showGlobalLoader("Uploading CSV...");
        
        fetch('api/upload_prism_csv.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            hideGlobalLoader();
            showStatusModal(res.success, res.message, res.success ? 'Upload Success' : 'Upload Failed');
            if (res.success) refreshPrismTable();
            input.value = '';
        })
        .catch(err => {
            hideGlobalLoader();
            showStatusModal(false, "Network error occurred.");
            input.value = '';
        });
    };

    window.editPrism = function(id, item_no, srp) {
        document.getElementById('edit-prism-id').value = id;
        document.getElementById('edit-prism-item-no').value = item_no;
        document.getElementById('edit-prism-srp').value = srp;
        
        const modal = document.getElementById('edit-prism-modal');
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('scale-100');
    };

    window.closeEditPrismModal = function() {
        const modal = document.getElementById('edit-prism-modal');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.remove('scale-100');
    };

    window.deletePrism = function(id) {
        showConfirmModal(`Are you sure you want to delete this record?`, () => {
            fetch(`api/delete_prism.php?id=${id}`)
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Record Deleted' : 'Error');
                if (res.success) refreshPrismTable();
            });
        }, 'Delete Prism Data');
    };

    window.deleteAllPrism = function() {
        const countText = document.getElementById('total-prism-count')?.textContent || 'all';
        const totalNum = countText.includes('of') ? countText.split('of')[1].trim() : 'all';
        
        showConfirmModal(`<span class="text-red-500 font-bold">CRITICAL ACTION:</span> This will permanently delete <strong>${totalNum}</strong> records. This cannot be undone!`, () => {
            showGlobalLoader("CLEARING ALL DATA...");
            fetch('api/truncate_prism.php')
            .then(r => r.json())
            .then(res => {
                hideGlobalLoader();
                showStatusModal(res.success, res.message, res.success ? 'Table Cleared' : 'Error');
                if (res.success) refreshPrismTable();
            });
        }, 'DELETE ALL PRISM DATA');
    };

    window.bulkDeletePrism = function() {
        const ids = Array.from(document.querySelectorAll('.prism-checkbox:checked')).map(cb => cb.value);
        if (ids.length === 0) return;

        showConfirmModal(`Delete ${ids.length} selected records?`, () => {
            fetch('api/bulk_delete_prism.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids })
            })
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Records Deleted' : 'Error');
                if (res.success) refreshPrismTable();
            });
        }, 'Bulk Delete');
    };

    document.getElementById('prism-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const btn = document.getElementById('submit-prism-btn');
        btn.disabled = true;
        btn.innerText = "SAVING...";

        fetch('api/save_prism.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            showStatusModal(res.success, res.message, res.success ? 'Data Saved' : 'Error');
            if (res.success) {
                this.reset();
                refreshPrismTable();
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = "Save Prism Data";
        });
    });

    document.getElementById('edit-prism-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        fetch('api/save_prism.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            showStatusModal(res.success, res.message, res.success ? 'Data Updated' : 'Error');
            if (res.success) {
                closeEditPrismModal();
                refreshPrismTable();
            }
        });
    });

    initPersistentEvents();
    initRefreshableEvents();
})();
</script>
