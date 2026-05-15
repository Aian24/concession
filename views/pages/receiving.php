<?php
// Pull session data
$rec_username   = $_SESSION['user']       ?? '';
$rec_store_code = $_SESSION['store_code'] ?? '';
// Use global flags from index.php; re-derive if loaded standalone (AJAX)
if (!isset($is_admin)) {
    $role            = $_SESSION['role'] ?? 'user';
    $is_full_admin   = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view   = ($role === 'admin_view');
    $is_store_admin  = ($role === 'store_admin');
    $is_admin        = ($is_full_admin || $is_admin_view || $is_store_admin);
    $can_submit      = ($role === 'user');
    $can_edit        = ($is_full_admin || $is_admin_view);
    $can_delete      = ($is_full_admin);
}

require_once 'includes/db.php';
$db = db_connect();

// ── Search & Pagination Logic ──────────────────────────────
$limit        = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 100;
$page         = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset       = ($page - 1) * $limit;
$search       = $_GET['search'] ?? '';
$start_date   = $_GET['start_date'] ?? '';
$end_date     = $_GET['end_date']   ?? '';
$store_filter = $_GET['store_filter'] ?? '';

// Build Query
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($is_store_admin || !$is_admin) {
    $where .= " AND s.store_code = ?";
    $params[] = $rec_store_code;
    $types .= "s";
} elseif ($is_admin && $store_filter !== '') {
    $where .= " AND s.store_code = ?";
    $params[] = $store_filter;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (s.os_no LIKE ? OR s.from_store LIKE ? OR s.to_store LIKE ? OR s.store_code LIKE ? OR s.username LIKE ? OR s.id LIKE ?)";
    $lk = "%" . trim($search) . "%";
    $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk;
    $types .= "ssssss";
}

if ($start_date !== '') {
    $where .= " AND s.created_at >= ?";
    $params[] = $start_date . ' 00:00:00';
    $types .= "s";
}

if ($end_date !== '') {
    $where .= " AND s.created_at <= ?";
    $params[] = $end_date . ' 23:59:59';
    $types .= "s";
}

// Get Total for Pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM receiving s $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$count_stmt->close();

// Fetch Rows
$recent_stmt = $db->prepare("SELECT s.*, sc.sname FROM receiving s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
$p_with_limit = array_merge($params, [$limit, $offset]);
$recent_stmt->bind_param($types . "ii", ...$p_with_limit);
$recent_stmt->execute();
$received_items = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

$queryString = "&search=".urlencode($search)."&limit=$limit&start_date=$start_date&end_date=$end_date&store_filter=$store_filter";

// If this is an AJAX request for the table, only return the table part
if (isset($_GET['ajax'])) {
    include 'views/pages/receiving_table_partial.php';
    exit;
}
?>

<div class="pb-12 animate-fade-in">
    <?php if ($can_submit): ?>
    <!-- New Receiving Form -->
    <div class="glass-panel border border-white/5 shadow-xl mb-10 min-h-[70vh] flex flex-col">
        <div class="px-5 py-2.5 border-b border-white/5 bg-slate-800/25 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-truck-loading text-cyan-400 text-xs"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Receiving</h3>
            </div>
            <span id="entry-count-badge" class="text-[9px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 px-1.5 py-0.5 rounded">1 item</span>
        </div>

        <div class="p-4 flex-grow flex flex-col justify-start gap-4">
            <!-- Transaction Date Selector -->
            <div class="glass-panel border border-white/5 p-3 mb-2 bg-white/2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-cyan-500/10 flex items-center justify-center border border-cyan-500/20">
                            <i class="fas fa-calendar-day text-cyan-400 text-[10px]"></i>
                        </div>
                        <div>
                            <h4 class="text-[9px] font-black text-white uppercase tracking-widest leading-none mb-0.5">Transaction Date</h4>
                            <p class="text-[7px] text-gray-500 font-bold uppercase tracking-tighter">Current or previous date</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <div class="grid grid-cols-2 gap-2 w-full sm:w-64">
                            <!-- Current Date Option -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="page_date_type" value="current" checked class="peer sr-only" onchange="handleDateTypeChange(this.value)">
                                <div class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg bg-slate-900 border border-white/5 peer-checked:border-cyan-500/50 peer-checked:bg-cyan-500/10 transition-all">
                                    <i class="fas fa-clock text-[9px] text-gray-500 peer-checked:text-cyan-400"></i>
                                    <span class="text-[9px] font-bold text-gray-500 peer-checked:text-cyan-400 uppercase tracking-tighter">Current</span>
                                </div>
                            </label>

                            <!-- Backdate Option -->
                            <label class="relative cursor-pointer group" onclick="document.getElementById('page_custom_date').showPicker()">
                                <input type="radio" name="page_date_type" value="backdate" class="peer sr-only" onchange="handleDateTypeChange(this.value)">
                                <div id="backdate-btn-content" class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg bg-slate-900 border border-white/5 peer-checked:border-cyan-500/50 peer-checked:bg-cyan-500/10 transition-all overflow-hidden relative">
                                     <i class="fas fa-history text-[9px] text-gray-500 peer-checked:text-cyan-400"></i>
                                     <span id="backdate-text" class="text-[9px] font-bold text-gray-500 peer-checked:text-cyan-400 uppercase tracking-tighter">Backdate</span>
                                     <input type="date" id="page_custom_date" 
                                            max="<?= date('Y-m-d') ?>" 
                                           class="absolute inset-0 opacity-0 cursor-pointer pointer-events-none" 
                                            value="<?= date('Y-m-d') ?>"
                                            onchange="updateBackdateText(this.value)">
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function handleDateTypeChange(val) {
                    const backdateText = document.getElementById('backdate-text');
                    if (val !== 'backdate') {
                        backdateText.innerText = 'Backdate';
                    }
                }

                function updateBackdateText(val) {
                    if (!val) return;
                    const date = new Date(val);
                    const formatted = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    document.getElementById('backdate-text').innerText = formatted;
                }
            </script>

            <div>
                <div id="entry-rows" class="space-y-4">
                    <div class="entry-row glass-panel border border-white/5 shadow-lg overflow-hidden bg-[#0d1527]/30 animate-slide-in">
                        <div class="px-4 py-2 bg-white/5 border-b border-white/5 flex items-center justify-between">
                            <span class="entry-title text-[10px] font-black text-gray-500 uppercase tracking-widest">Entry #1</span>
                            <button type="button" onclick="removeRow(this)" class="remove-btn hidden text-red-500/50 hover:text-red-500 transition-colors flex items-center gap-1.5 group">
                                <span class="text-[9px] font-bold uppercase tracking-wider opacity-0 group-hover:opacity-100 transition-opacity">Remove</span>
                                <i class="fas fa-trash-alt text-[10px]"></i>
                            </button>
                        </div>
                        <div class="p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="relative">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-cyan-400/80 uppercase tracking-widest z-10">OS #</span>
                                <input type="number" name="os_no" min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-cyan-500/50 font-medium" placeholder="1001">
                            </div>
                            <div class="relative">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-cyan-400/80 uppercase tracking-widest z-10">From (Store)</span>
                                <input type="text" name="from_store" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-cyan-500/50 font-medium" placeholder="WH-01">
                            </div>
                            <div class="relative">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-cyan-400/80 uppercase tracking-widest z-10">To (Store)</span>
                                <input type="text" name="to_store" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-cyan-500/50 font-medium" placeholder="STR-01">
                            </div>
                            <div class="relative">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-cyan-400/80 uppercase tracking-widest z-10">Qty</span>
                                <input type="number" name="quantity" min="1" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-cyan-500/50 font-medium" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-white/5 pt-4">
                <button type="button" onclick="addRow()" class="w-full sm:w-auto py-2.5 px-4 rounded-lg bg-slate-700/40 hover:bg-slate-700/60 text-cyan-400 border border-white/10 text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg hover:-translate-y-0.5">
                    <i class="fas fa-plus-circle"></i> Add Another Entry
                </button>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    <div class="flex items-center justify-between sm:justify-start gap-3 bg-slate-800/40 px-3 py-2 rounded-lg border border-white/5 h-[38px]">
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Total Items:</span>
                            <span class="text-xs font-black text-white" id="summary-items">0</span>
                        </div>
                        <div class="w-px h-3 bg-white/10"></div>
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Total Qty:</span>
                            <span class="text-xs font-black text-white" id="summary-qty">0</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 w-full sm:w-auto h-[38px]">
                        <button type="button" onclick="clearForm()" class="px-3 py-0 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 text-[10px] font-bold border border-white/5 uppercase transition-all flex items-center justify-center">Clear</button>
                        <button type="button" id="submit-btn" onclick="submitReceiving()" class="px-4 py-0 rounded-lg bg-gradient-to-r from-cyan-600 to-blue-600 text-white text-[10px] font-black uppercase tracking-wider shadow-lg shadow-cyan-500/10 hover:-translate-y-0.5 transition-all flex items-center justify-center">Submit Receiving</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[100] flex flex-col items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="relative">
            <div class="w-16 h-16 border-4 border-cyan-500/20 border-t-cyan-500 rounded-full animate-spin"></div>
            <i class="fas fa-truck-loading absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-cyan-500 text-xl animate-pulse"></i>
        </div>
        <p class="mt-4 text-white font-black text-xs uppercase tracking-[0.2em] animate-pulse">Recording Receipt...</p>
    </div>

    <!-- History Container -->
    <?php if ($is_admin): ?>
    <div id="history-container" class="mt-4">
        <?php include 'views/pages/receiving_table_partial.php'; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Receiving Modal -->
<div id="edit-receiving-modal" class="fixed inset-0 z-[102] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 scale-95">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeEditReceivingModal()"></div>
    <div class="relative glass-panel border border-white/10 shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-tr from-cyan-600/20 to-blue-600/20 p-5 border-b border-white/5 relative">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center">
                    <i class="fas fa-edit text-cyan-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Edit Receiving Record</h3>
                    <p id="edit-id-label" class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-0.5"></p>
                </div>
            </div>
            <button onclick="closeEditReceivingModal()" class="absolute top-5 right-5 text-gray-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="p-6">
            <form id="edit-receiving-form" class="space-y-4">
                <input type="hidden" name="id" id="edit-id">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">OS #</label>
                        <input type="number" name="os_no" id="edit-os-no" required min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500/50 transition-all font-medium" placeholder="1001">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Quantity</label>
                        <input type="number" name="quantity" id="edit-qty" required onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500/50 transition-all font-medium" placeholder="0">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">From Store</label>
                        <input type="text" name="from_store" id="edit-from-store" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500/50 transition-all font-medium">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">To Store</label>
                        <input type="text" name="to_store" id="edit-to-store" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500/50 transition-all font-medium">
                    </div>
                </div>
                
                <button type="submit" class="w-full py-4 mt-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-cyan-500/20 hover:brightness-110 active:scale-[0.98] transition-all">
                    Update Receipt
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function updateBadge() {
        const rows = document.querySelectorAll('.entry-row');
        document.getElementById('entry-count-badge').textContent = rows.length + (rows.length === 1 ? ' item' : ' items');
        rows.forEach((r, i) => {
            r.querySelector('.entry-title').textContent = `Entry #${i + 1}`;
            const rem = r.querySelector('.remove-btn');
            rem.style.opacity = rows.length > 1 ? '1' : '0';
            rem.style.pointerEvents = rows.length > 1 ? 'all' : 'none';
        });
    }

    function updateSummary() {
        let items = 0, qty = 0;
        document.querySelectorAll('.entry-row').forEach(row => {
            const os = row.querySelector('[name="os_no"]')?.value.trim() || '';
            const q  = parseInt(row.querySelector('[name="quantity"]')?.value) || 0;
            if (os) items++;
            qty += q;
        });
        document.getElementById('summary-items').textContent = items;
        document.getElementById('summary-qty').textContent = qty;
    }

    window.addRow = function () {
        const tpl = document.querySelector('.entry-row');
        if (!tpl) return;
        const row = tpl.cloneNode(true);
        row.querySelectorAll('input').forEach(i => i.value = '');
        row.querySelectorAll('input').forEach(i => i.addEventListener('input', updateSummary));
        
        const removeBtn = row.querySelector('.remove-btn');
        if (removeBtn) removeBtn.classList.remove('hidden');
        
        document.getElementById('entry-rows').appendChild(row);
        updateBadge();
        row.querySelector('input').focus();
    };

    window.removeRow = function (btn) {
        if (typeof showConfirmModal === 'function') {
            showConfirmModal('Are you sure you want to remove this entry row?', () => {
                btn.closest('.entry-row').remove();
                updateBadge();
                updateSummary();
            }, 'Remove Entry');
        } else {
            btn.closest('.entry-row').remove();
            updateBadge();
            updateSummary();
        }
    };

    window.clearForm = function () {
        if (typeof showConfirmModal === 'function') {
            showConfirmModal('Are you sure you want to clear all current receiving data?', () => {
                const rows = document.querySelectorAll('.entry-row');
                rows.forEach((r, i) => { if (i > 0) r.remove(); });
                rows[0].querySelectorAll('input').forEach(i => i.value = '');
                updateSummary();
                updateBadge();
            }, 'Clear Form');
        } else {
            const rows = document.querySelectorAll('.entry-row');
            rows.forEach((r, i) => { if (i > 0) r.remove(); });
            rows[0].querySelectorAll('input').forEach(i => i.value = '');
            updateSummary();
            updateBadge();
        }
    };

    window.showStatusModal = function(success, message, customTitle = '') {
        if (typeof window.showStatusModal === 'function') {
            window.showStatusModal(success, message, customTitle);
        }
    };

    window.closeStatusModal = function() {
        if (typeof window.closeStatusModal === 'function') {
            window.closeStatusModal();
        }
    };

    window.submitReceiving = function () {
        const entries = [];
        document.querySelectorAll('.entry-row').forEach(row => {
            const os   = row.querySelector('[name="os_no"]').value.trim();
            const from = row.querySelector('[name="from_store"]').value.trim();
            const to   = row.querySelector('[name="to_store"]').value.trim();
            const qty  = row.querySelector('[name="quantity"]').value;
            if (os && qty > 0) {
                entries.push({ item_no: '', os_no: os, from_store: from, to_store: to, quantity: qty });
            }
        });

        if (entries.length === 0) return;
        
        const loader = document.getElementById('loading-overlay');
        loader.classList.remove('opacity-0', 'pointer-events-none');

        const dateType = document.querySelector('input[name="page_date_type"]:checked').value;
        const customDate = document.getElementById('page_custom_date').value;
        const finalDate = (dateType === 'backdate') ? customDate : '<?= date('Y-m-d') ?>';

        fetch('api/save_receiving.php', { 
            method:'POST', 
            headers:{'Content-Type':'application/json'}, 
            body:JSON.stringify({
                entries: entries,
                transaction_date: finalDate
            }) 
        })
        .then(r => r.json())
        .then(res => { 
            setTimeout(() => {
                loader.classList.add('opacity-0', 'pointer-events-none');
                showStatusModal(res.success, res.message, res.success ? 'Receipt Successful' : 'Receipt Failed');
                if (res.success) { 
                    const rows = document.querySelectorAll('.entry-row');
                    rows.forEach((r, i) => { if (i > 0) r.remove(); });
                    rows[0].querySelectorAll('input').forEach(i => i.value = '');
                    updateSummary();
                    updateBadge();
                    refreshTable(1); 
                }
            }, 600);
        })
        .catch(() => {
            loader.classList.add('opacity-0', 'pointer-events-none');
            showStatusModal(false, 'Network error. Please try again.');
        });
    };

    let filterTimer;
    function refreshTable(page = 1) {
        const container = document.getElementById('history-container');
        const search = document.querySelector('[name="search"]')?.value || '';
        const limit  = document.querySelector('[name="limit"]')?.value || 10;
        const start  = document.querySelector('[name="start_date"]')?.value || '';
        const end    = document.querySelector('[name="end_date"]')?.value || '';
        const store  = document.querySelector('[name="store_filter"]')?.value || '';
        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';
        const url = `index.php?action=receiving&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}&start_date=${start}&end_date=${end}&store_filter=${store}`;
        fetch(url).then(res => res.text()).then(html => { container.innerHTML = html; initTableEvents(); });
    }

    function initTableEvents() {
        const searchInput    = document.querySelector('[name="search"]');
        const limitSelect    = document.querySelector('[name="limit"]');
        const startInput     = document.querySelector('[name="start_date"]');
        const endInput       = document.querySelector('[name="end_date"]');
        const storeFilter    = document.querySelector('[name="store_filter"]');
        const selectAll      = document.getElementById('select-all');
        
        searchInput?.addEventListener('input', () => { clearTimeout(filterTimer); filterTimer = setTimeout(() => refreshTable(1), 300); });
        [limitSelect, startInput, endInput, storeFilter].forEach(el => el?.addEventListener('change', () => refreshTable(1)));
        
        document.querySelectorAll('#receiving-history-section .pagination-link').forEach(link => {
            link.addEventListener('click', function(e) { e.preventDefault(); refreshTable(this.getAttribute('data-page')); });
        });

        // Checkbox Logic
        const updateBulkReceivingVisibility = () => {
            const btn = document.getElementById('bulk-delete-receiving');
            if (!btn) return;
            const checkedCount = document.querySelectorAll('.record-checkbox:checked').length;
            btn.classList.toggle('hidden', checkedCount === 0);
        };

        selectAll?.addEventListener('change', function() {
            document.querySelectorAll('.record-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkReceivingVisibility();
        });

        document.getElementById('receiving-tbody')?.addEventListener('change', (e) => {
            if (e.target.classList.contains('record-checkbox')) {
                updateBulkReceivingVisibility();
            }
        });

        updateBulkReceivingVisibility();
    }

    window.bulkDeleteReceiving = function() {
        const selectedIds = Array.from(document.querySelectorAll('.record-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) return;

        showConfirmModal(
            `Are you sure you want to delete ${selectedIds.length} selected receiving records? This action cannot be undone.`,
            function() {
                const loader = document.getElementById('loading-overlay');
                if (loader) {
                    const p = loader.querySelector('p');
                    if (p) p.textContent = 'Deleting Records...';
                    loader.classList.remove('opacity-0', 'pointer-events-none');
                }

                fetch('api/bulk_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ table: 'receiving', ids: selectedIds })
                })
                .then(r => r.json())
                .then(res => {
                    if (loader) loader.classList.add('opacity-0', 'pointer-events-none');
                    showStatusModal(res.success, res.message, res.success ? 'Bulk Delete Successful' : 'Action Failed');
                    if (res.success) refreshTable();
                })
                .catch(() => {
                    if (loader) loader.classList.add('opacity-0', 'pointer-events-none');
                    showStatusModal(false, 'A network error occurred.');
                });
            },
            'Bulk Delete Records'
        );
    };

    window.deleteReceiving = function(id) {
        showConfirmModal(
            `Are you sure you want to delete Receiving Record #${id}? This action cannot be undone.`,
            function() {
                fetch(`api/delete_receiving.php?id=${id}`)
                .then(r => r.json())
                .then(res => {
                    showStatusModal(res.success, res.message, res.success ? 'Record Deleted' : 'Action Failed');
                    if (res.success) refreshTable();
                });
            },
            'Delete Record'
        );
    };

    window.editReceiving = function(id) {
        const row = document.querySelector(`.record-checkbox[value="${id}"]`)?.closest('tr');
        if (!row) return;

        const offset = <?= $is_admin ? 1 : 0 ?>;
        
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-id-label').innerText = `Record ID: ${id}`;
        document.getElementById('edit-os-no').value      = row.cells[1 + offset].innerText.trim();
        document.getElementById('edit-qty').value        = row.cells[2 + offset].innerText.trim().replace(',', '');
        document.getElementById('edit-from-store').value = row.cells[3 + offset].innerText.trim();
        document.getElementById('edit-to-store').value   = row.cells[4 + offset].innerText.trim();

        const modal = document.getElementById('edit-receiving-modal');
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('scale-100');
    };

    window.closeEditReceivingModal = function() {
        const modal = document.getElementById('edit-receiving-modal');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.remove('scale-100');
    };

    document.getElementById('edit-receiving-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            id: this.id.value,
            os_no: this.os_no.value,
            from_store: this.from_store.value,
            to_store: this.to_store.value,
            quantity: this.quantity.value
        };

        const btn = this.querySelector('button[type="submit"]');
        const origText = btn.innerText;
        btn.innerText = "SAVING...";
        btn.disabled = true;

        fetch('api/update_receiving.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
        .then(r => r.json())
        .then(res => {
            closeEditReceivingModal();
            showStatusModal(res.success, res.message, res.success ? 'Update Successful' : 'Update Failed');
            if (res.success) refreshTable();
        })
        .finally(() => {
            btn.innerText = origText;
            btn.disabled = false;
        });
    });

    document.querySelectorAll('.entry-row input').forEach(i => i.addEventListener('input', updateSummary));
    
    // ── Custom Dropdown Logic ────────────────────────────────
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('#store-filter-trigger');
        const option = e.target.closest('.store-option');
        const menu = document.getElementById('store-filter-menu');

        if (trigger) {
            if (menu) menu.classList.toggle('hidden');
        } else if (option) {
            const val = option.getAttribute('data-value');
            const label = option.getAttribute('data-label') || 'All Stores';
            const hiddenSelect = document.querySelector('select[name="store_filter"]');
            
            if (hiddenSelect) {
                hiddenSelect.value = val;
                const labelEl = document.getElementById('selected-store-label');
                if (labelEl) labelEl.textContent = label;
                if (menu) menu.classList.add('hidden');
                
                hiddenSelect.dispatchEvent(new Event('change'));
            }
        } else {
            if (menu && !menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        }
    });

    window.refreshReceivingTable = refreshTable;
    initTableEvents();
})();
</script>
