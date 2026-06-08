<?php
// Pull session data
$username      = $_SESSION['user']       ?? '';
$store_code    = $_SESSION['store_code'] ?? '';
// Use global flags from index.php; re-derive if loaded standalone (AJAX)
if (!isset($is_admin)) {
    $role            = $_SESSION['role'] ?? 'user';
    $is_full_admin   = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view   = ($role === 'admin_view');
    $is_store_admin  = ($role === 'store_admin');
    $is_multi_store_admin = ($role === 'multi_store_admin');
    $is_admin        = $_SESSION['is_admin'] ?? ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);
    $can_submit      = $_SESSION['can_submit'] ?? ($role === 'user');
    $can_edit        = $_SESSION['can_edit'] ?? false;
    $can_delete      = $_SESSION['can_delete'] ?? false;
}

require_once 'includes/db.php';
$db = db_connect();

// ── Search & Pagination Logic ──────────────────────────────
$is_filter_request = isset($_GET['ajax']) || isset($_GET['search']) || isset($_GET['start_date']) || isset($_GET['end_date']) || isset($_GET['store_filter']);
if ($is_filter_request) {
    $limit        = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 100;
    $page         = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $search       = $_GET['search'] ?? '';
    $start_date   = $_GET['start_date'] ?? '';
    $end_date     = $_GET['end_date']   ?? '';
    $store_filter = $_GET['store_filter'] ?? '';
    
    $_SESSION['return_limit'] = $limit;
    $_SESSION['return_page'] = $page;
    $_SESSION['return_search'] = $search;
    $_SESSION['return_start_date'] = $start_date;
    $_SESSION['return_end_date'] = $end_date;
    $_SESSION['return_store_filter'] = $store_filter;
} else {
    $limit        = $_SESSION['return_limit'] ?? 100;
    $page         = $_SESSION['return_page'] ?? 1;
    $search       = $_SESSION['return_search'] ?? '';
    $start_date   = $_SESSION['return_start_date'] ?? '';
    $end_date     = $_SESSION['return_end_date'] ?? '';
    $store_filter = $_SESSION['return_store_filter'] ?? '';
}
$offset       = ($page - 1) * $limit;

// Build Query
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($is_store_admin || (!$is_admin && !$is_multi_store_admin)) {
    $where .= " AND s.store_code = ?";
    $params[] = $store_code;
    $types .= "s";
} elseif ($is_multi_store_admin) {
    $assigned = $_SESSION['assigned_stores'] ?? [];
    if ($store_filter !== '' && in_array($store_filter, $assigned)) {
        $where .= " AND s.store_code = ?";
        $params[] = $store_filter;
        $types .= "s";
    } else {
        $where .= build_multi_store_clause('s.store_code', $assigned);
    }
} elseif ($is_admin && $store_filter !== '') {
    $where .= " AND s.store_code = ?";
    $params[] = $store_filter;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (s.return_item LIKE ? OR s.exchange_item LIKE ? OR s.username LIKE ? OR s.id LIKE ? OR s.store_code LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk;
    $types .= "sssss";
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
$count_stmt = $db->prepare("SELECT COUNT(*) FROM returns s $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$count_stmt->close();

// Fetch Rows
$recent_stmt = $db->prepare("SELECT s.*, sc.sname FROM returns s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
$p_with_limit = array_merge($params, [$limit, $offset]);
$recent_stmt->bind_param($types . "ii", ...$p_with_limit);
$recent_stmt->execute();
$submitted_records = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

// For AJAX
if (isset($_GET['ajax'])) {
    include 'views/pages/return_table_partial.php';
    exit;
}
?>

<div class="pb-12 animate-fade-in">
    <!-- Unified Return Form -->
    <?php if ($can_submit): ?>
    <div class="glass-panel border border-white/5 shadow-xl mb-10 min-h-[70vh] flex flex-col overflow-hidden">
        <div class="px-5 py-2.5 border-b border-white/5 bg-slate-800/25 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-undo text-orange-400 text-xs"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Product Returns</h3>
            </div>
            <span id="entry-count-badge" class="text-[9px] font-bold bg-orange-500/10 text-orange-400 border border-orange-500/20 px-1.5 py-0.5 rounded">1 item</span>
        </div>

        <div class="p-4 flex-grow flex flex-col justify-start gap-4">
            <!-- Transaction Date Selector -->
            <div class="glass-panel border border-white/5 p-3 mb-2 bg-white/2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-orange-500/10 flex items-center justify-center border border-orange-500/20">
                            <i class="fas fa-calendar-day text-orange-400 text-[10px]"></i>
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
                                <div class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg border transition-all bg-slate-900 border-white/5 text-gray-500 peer-checked:!border-orange-500/50 peer-checked:!bg-orange-500/10 peer-checked:!text-orange-400">
                                    <i class="fas fa-clock text-[9px]"></i>
                                    <span class="text-[9px] font-bold uppercase tracking-tighter">Current</span>
                                </div>
                            </label>

                            <!-- Backdate Option -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="page_date_type" value="backdate" class="peer sr-only" onchange="handleDateTypeChange(this.value)">
                                <div id="backdate-btn-content" class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg border transition-all overflow-hidden relative bg-slate-900 border-white/5 text-gray-500 peer-checked:!border-orange-500/50 peer-checked:!bg-orange-500/10 peer-checked:!text-orange-400">
                                     <i class="fas fa-history text-[9px]"></i>
                                     <span id="backdate-text" class="text-[9px] font-bold uppercase tracking-tighter">Backdate</span>
                                     <input type="text" id="page_custom_date" 
                                            class="absolute inset-0 w-full h-full opacity-[0.01] cursor-pointer z-10" 
                                            value="<?= date('Y-m-d') ?>">
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
                    } else {
                        const customDate = document.getElementById('page_custom_date').value;
                        if (customDate) updateBackdateText(customDate);
                    }
                    sessionStorage.setItem('page_date_type', val);
                }

                function updateBackdateText(val) {
                    if (!val) return;
                    const [year, month, day] = val.split('-');
                    const date = new Date(year, month - 1, day);
                    const formatted = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    document.getElementById('backdate-text').innerText = formatted;
                    sessionStorage.setItem('page_custom_date', val);
                    sessionStorage.setItem('page_date_type', 'backdate');
                    
                    const radio = document.querySelector('input[name="page_date_type"][value="backdate"]');
                    if (radio && !radio.checked) {
                        radio.checked = true;
                        handleDateTypeChange('backdate');
                    }
                }

                (function() {
                    const savedType = sessionStorage.getItem('page_date_type');
                    const savedDate = sessionStorage.getItem('page_custom_date');
                    
                    if (savedDate) {
                        const dateInput = document.getElementById('page_custom_date');
                        if (dateInput) dateInput.value = savedDate;
                    }
                    
                    if (savedType === 'backdate') {
                        const backdateRadio = document.querySelector('input[name="page_date_type"][value="backdate"]');
                        if (backdateRadio) {
                            backdateRadio.checked = true;
                            setTimeout(() => handleDateTypeChange('backdate'), 0);
                        }
                    } else if (savedType === 'current') {
                        const currentRadio = document.querySelector('input[name="page_date_type"][value="current"]');
                        if (currentRadio) {
                            currentRadio.checked = true;
                            setTimeout(() => handleDateTypeChange('current'), 0);
                        }
                    }

                    // Setup custom Flatpickr for Backdate with Cancel/Set buttons
                    setTimeout(() => {
                        if (typeof flatpickr !== 'undefined') {
                            const dateEl = document.getElementById('page_custom_date');
                            if (dateEl._flatpickr) {
                                dateEl._flatpickr.destroy();
                            }
                            flatpickr(dateEl, {
                                dateFormat: "Y-m-d",
                                disableMobile: true,
                                closeOnSelect: false,
                                maxDate: "today",
                                onChange: function(selectedDates, dateStr, instance) {
                                    instance.close();
                                    updateBackdateText(dateStr);
                                },
                                onReady: function(selectedDates, dateStr, instance) {
                                    const btnContainer = document.createElement("div");
                                    btnContainer.className = "flex items-center justify-between p-2 mt-1 border-t border-white/10";
                                    btnContainer.innerHTML = `
                                        <button type="button" class="px-3 py-1.5 rounded bg-white/5 hover:bg-white/10 text-gray-400 text-[10px] font-bold uppercase transition-all fp-cancel">Cancel</button>
                                        <button type="button" class="px-3 py-1.5 rounded bg-orange-600 hover:bg-orange-500 text-white text-[10px] font-bold uppercase transition-all fp-set">Today</button>
                                    `;
                                    instance.calendarContainer.appendChild(btnContainer);
                                    
                                    btnContainer.querySelector('.fp-cancel').addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        instance.close();
                                        const radio = document.querySelector('input[name="page_date_type"][value="current"]');
                                        if (radio) {
                                            radio.checked = true;
                                            handleDateTypeChange('current');
                                        }
                                    });
                                    btnContainer.querySelector('.fp-set').addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        const now = new Date();
                                        instance.setDate(now, false);
                                        instance.close();
                                        const dStr = instance.formatDate(now, "Y-m-d");
                                        updateBackdateText(dStr);
                                    });
                                }
                            });
                        }
                    }, 500);
                })();
            </script>

            <div id="entry-container" class="space-y-4">
                <!-- Cards will be injected here -->
            </div>

            <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-white/5 pt-4">
                <button onclick="addRow()" class="w-full sm:w-auto py-2.5 px-4 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 transition-all shadow-lg shadow-orange-600/20 active:scale-95">
                    <i class="fas fa-plus-circle"></i> Add Another Entry
                </button>
                
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    <div class="flex items-center justify-between sm:justify-start gap-3 bg-slate-950/40 px-3 py-2 rounded-lg border border-white/5 h-[38px] text-[10px] font-black uppercase tracking-widest">
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Items:</span>
                            <span class="text-xs font-black text-white" id="stat-items">0</span>
                        </div>
                        <div class="w-[1px] h-3 bg-white/10"></div>
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Total:</span>
                            <span class="text-xs font-black text-orange-400" id="stat-total">₱0.00</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 w-full sm:w-auto h-[38px]">
                        <button onclick="clearAllEntries()" class="px-3 py-0 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 text-[10px] font-bold border border-white/5 uppercase transition-all flex items-center justify-center">Clear</button>
                        <button id="submit-btn" onclick="submitReturnBatch()" class="px-4 py-0 rounded-lg bg-gradient-to-r from-orange-600 to-red-600 text-white text-[10px] font-black uppercase tracking-wider shadow-lg shadow-orange-500/20 hover:-translate-y-0.5 transition-all flex items-center justify-center">Submit Records</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- History Container -->
    <?php if ($show_history_table): ?>
    <div id="history-container">
        <?php include 'views/pages/return_table_partial.php'; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal & Loader UI -->
<div id="loading-overlay" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[100] flex flex-col items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="relative">
        <div class="w-16 h-16 border-4 border-orange-500/20 border-t-orange-500 rounded-full animate-spin"></div>
        <i class="fas fa-undo absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-orange-500 text-xl animate-pulse"></i>
    </div>
    <p class="mt-4 text-white font-black text-xs uppercase tracking-[0.2em] animate-pulse">Processing...</p>
</div>

    </div>
</div>

<!-- Edit Return Modal -->
<div id="edit-return-modal" class="fixed inset-0 z-[102] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 scale-95">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeEditReturnModal()"></div>
    <div class="relative glass-panel border border-white/10 shadow-2xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="bg-gradient-to-tr from-blue-600/20 to-indigo-600/20 p-5 border-b border-white/5 relative">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                    <i class="fas fa-edit text-blue-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Edit Return Record</h3>
                    <p id="edit-id-label" class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-0.5"></p>
                </div>
            </div>
            <button onclick="closeEditReturnModal()" class="absolute top-5 right-5 text-gray-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="p-6">
            <form id="edit-return-form">
                <input type="hidden" name="id" id="edit-id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Return Column -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-white/5 pb-2">
                            <i class="fas fa-undo text-orange-400 text-xs"></i>
                            <span class="text-[10px] font-black text-white uppercase tracking-widest">Return Info</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Item #</label>
                                <input type="number" name="return_item" id="edit-return-item" min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium" placeholder="100123">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Qty</label>
                                <input type="number" name="quantity" id="edit-qty" min="0" max="1" maxlength="1" oninput="this.value = this.value.replace(/[^01]/g, '').substring(0, 1);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium" placeholder="0">
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Amount (₱)</label>
                            <input type="number" name="return_amount" id="edit-return-amount" step="0.01" onkeydown="if(['e','E','+','-'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium" placeholder="0.00">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Reason</label>
                            <input type="text" name="reason" id="edit-reason" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium">
                        </div>
                    </div>

                    <!-- Exchange Column -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-white/5 pb-2">
                            <i class="fas fa-sync-alt text-blue-400 text-xs"></i>
                            <span class="text-[10px] font-black text-white uppercase tracking-widest">Exchange Info</span>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Exchange Name</label>
                            <input type="text" name="exchange_name" id="edit-ex-name" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Item #</label>
                                <input type="number" name="exchange_item" id="edit-ex-item" min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium" placeholder="100123">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Qty</label>
                                <input type="number" name="exchange_quantity" id="edit-ex-qty" min="0" max="1" maxlength="1" oninput="this.value = this.value.replace(/[^01]/g, '').substring(0, 1);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium" placeholder="0">
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Amount (₱)</label>
                            <input type="number" name="exchange_amount" id="edit-ex-amount" step="0.01" onkeydown="if(['e','E','+','-'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium" placeholder="0.00">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Transaction Date</label>
                            <div class="relative group">
                                <i class="fas fa-calendar-day absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                                <input type="date" name="created_at" id="edit-date" required class="w-full bg-slate-900 border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500/50 transition-all font-medium cursor-pointer" onclick="this.showPicker()">
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full py-4 mt-8 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-blue-500/20 hover:brightness-110 active:scale-[0.98] transition-all">
                    Update Return Record
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    let rowId = 0;

    window.addRow = function() {
        const container = document.getElementById('entry-container');
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'glass-panel border border-white/5 shadow-lg overflow-hidden animate-slide-in';
        div.id = `row-${++rowId}`;
        div.innerHTML = `
            <div class="px-4 py-2 bg-white/5 border-b border-white/5 flex items-center justify-between">
                <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Entry #${rowId}</span>
                ${rowId === 1 ? '' : `
                <button onclick="removeRow(${rowId})" class="text-red-500/50 hover:text-red-500 transition-colors flex items-center gap-1.5 group">
                    <span class="text-[9px] font-bold uppercase tracking-wider opacity-0 group-hover:opacity-100 transition-opacity">Remove</span>
                    <i class="fas fa-trash-alt text-[10px]"></i>
                </button>
                `}
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-0 divide-x divide-white/5">
                <!-- Return Section -->
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-undo text-orange-400 text-[10px]"></i>
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Return Item</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative mt-2">
                            <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-orange-400/80 uppercase tracking-widest z-10">Item #</span>
                            <input type="number" min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6); lookupPrismPrice(this, '.entry-amt');" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="entry-item bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-orange-500/50" placeholder="100123">
                        </div>
                        <div class="relative mt-2">
                            <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-orange-400/80 uppercase tracking-widest z-10">Qty</span>
                            <input type="number" min="0" max="1" maxlength="1" class="entry-qty bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-orange-500/50" placeholder="0" oninput="this.value = this.value.replace(/[^01]/g, '').substring(0, 1); updateStats();" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();">
                        </div>
                    </div>
                    <div class="relative mt-2">
                        <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-orange-400/80 uppercase tracking-widest z-10">Amount</span>
                        <input type="number" step="0.01" class="entry-amt bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-orange-500/50" placeholder="0.00" oninput="updateStats()" onkeydown="if(['e','E','+','-'].includes(event.key)) event.preventDefault();">
                    </div>
                    <div class="relative mt-2">
                        <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-orange-400/80 uppercase tracking-widest z-10">Reason</span>
                        <input type="text" class="entry-reason bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-orange-500/50" placeholder="Reason for return...">
                    </div>
                </div>

                <!-- Exchange Section -->
                <div class="p-4 space-y-3 bg-blue-600/[0.02]">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-sync-alt text-blue-400 text-[10px]"></i>
                            <span class="text-[10px] font-black text-white uppercase tracking-widest">Exchange Details</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer" title="Enable Exchange">
                            <input type="checkbox" class="sr-only peer entry-ex-toggle" onchange="toggleExchange(${rowId}, this.checked); updateStats();">
                            <div class="w-7 h-4 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-500"></div>
                        </label>
                    </div>
                    
                    <div id="exchange-inputs-${rowId}" class="space-y-3 opacity-50 pointer-events-none transition-opacity duration-300">
                        <div class="relative mt-2">
                            <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-blue-400/80 uppercase tracking-widest z-10">Exchange</span>
                            <input type="text" class="entry-ex-name bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-blue-500/50" placeholder="Item Replacement" value="Exchange to other brand">
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="relative mt-2">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-blue-400/80 uppercase tracking-widest z-10">Item #</span>
                                <input type="number" min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6); lookupPrismPrice(this, '.entry-ex-amt');" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="entry-ex-item bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-blue-500/50" placeholder="100123">
                            </div>
                            <div class="relative mt-2">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-blue-400/80 uppercase tracking-widest z-10">Qty</span>
                                <input type="number" min="0" max="1" maxlength="1" class="entry-ex-qty bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-blue-500/50" placeholder="0" oninput="this.value = this.value.replace(/[^01]/g, '').substring(0, 1); updateStats();" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();">
                            </div>
                            <div class="relative mt-2">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-blue-400/80 uppercase tracking-widest z-10">Amount</span>
                                <input type="number" step="0.01" class="entry-ex-amt bg-slate-900 border border-white/10 rounded-lg px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-blue-500/50" placeholder="0.00" oninput="updateStats()" onkeydown="if(['e','E','+','-'].includes(event.key)) event.preventDefault();">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
        updateStats();
    };

    window.toggleExchange = function(rowId, isChecked) {
        const wrapper = document.getElementById(`exchange-inputs-${rowId}`);
        if (!wrapper) return;
        if (isChecked) {
            wrapper.classList.remove('opacity-50', 'pointer-events-none');
        } else {
            wrapper.classList.add('opacity-50', 'pointer-events-none');
            wrapper.querySelector('.entry-ex-item').value = '';
            wrapper.querySelector('.entry-ex-qty').value = '';
            wrapper.querySelector('.entry-ex-amt').value = '';
            wrapper.querySelector('.entry-ex-name').value = 'Exchange to other brand';
        }
    };

    window.removeRow = function(id) {
        if (typeof showConfirmModal === 'function') {
            showConfirmModal('Are you sure you want to remove this return entry?', () => {
                const el = document.getElementById(`row-${id}`);
                if (!el) return;
                el.classList.add('opacity-0', 'scale-95');
                setTimeout(() => {
                    el.remove();
                    if (document.querySelectorAll('#entry-container > div').length === 0) addRow();
                    updateStats();
                }, 200);
            }, 'Remove Entry');
        } else {
            const el = document.getElementById(`row-${id}`);
            if (!el) return;
            el.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                el.remove();
                if (document.querySelectorAll('#entry-container > div').length === 0) addRow();
                updateStats();
            }, 200);
        }
    };

    window.lookupPrismPrice = function(input, targetSelector) {
        const item_no = input.value.trim();
        if (item_no.length < 3) return;

        const row = input.closest('div[id^="row-"]');
        if (!row) return;

        const amountInput = row.querySelector(targetSelector);
        if (!amountInput) return;

        fetch(`api/get_prism_price.php?item_no=${encodeURIComponent(item_no)}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                amountInput.value = res.srp;
                window.updateStats();
                amountInput.classList.add('ring-2', 'ring-green-500/50');
                setTimeout(() => amountInput.classList.remove('ring-2', 'ring-green-500/50'), 1000);
            }
        }).catch(err => console.error("Prism price lookup failed", err));
    };

    window.updateStats = function() {
        const cards = document.querySelectorAll('#entry-container > div');
        let totalAmt = 0;
        let totalQty = 0;
        cards.forEach(c => {
            const rQty  = parseInt(c.querySelector('.entry-qty').value) || 0;
            const rAmt  = parseFloat(c.querySelector('.entry-amt').value) || 0;
            const exToggle = c.querySelector('.entry-ex-toggle');
            const hasEx = exToggle ? exToggle.checked : false; 
            const exQty = hasEx ? (parseInt(c.querySelector('.entry-ex-qty').value) || 0) : 0;
            const exAmt = hasEx ? (parseFloat(c.querySelector('.entry-ex-amt').value) || 0) : 0;
            
            // Total items involved
            totalQty += (rQty + exQty);
            // Return is negative, Exchange is positive
            totalAmt += (exAmt - rAmt);
        });
        
        const count = cards.length;
        document.getElementById('stat-items').textContent = totalQty;
        
        const totalDisplay = document.getElementById('stat-total');
        if (totalAmt < 0) {
            totalDisplay.classList.replace('text-orange-400', 'text-red-400');
            totalDisplay.textContent = '-₱' + Math.abs(totalAmt).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        } else {
            totalDisplay.classList.replace('text-red-400', 'text-orange-400');
            totalDisplay.textContent = '₱' + totalAmt.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        }
        
        const badge = document.getElementById('entry-count-badge');
        if (badge) {
            badge.textContent = count + (count === 1 ? ' item' : ' items');
        }
    };

    window.clearAllEntries = function() {
        if (typeof showConfirmModal === 'function') {
            showConfirmModal('Are you sure you want to clear all return entries?', () => {
                const container = document.getElementById('entry-container');
                if (container) container.innerHTML = '';
                addRow();
            }, 'Clear Entries');
        } else {
            const container = document.getElementById('entry-container');
            if (container) container.innerHTML = '';
            addRow();
        }
    };

    window.submitReturnBatch = function() {
        const cards = document.querySelectorAll('#entry-container > div');
        const entries = [];
        let valid = true;

        cards.forEach(c => {
            const item = c.querySelector('.entry-item').value.trim();
            const qty  = c.querySelector('.entry-qty').value;
            const amt = c.querySelector('.entry-amt').value;
            const reason = c.querySelector('.entry-reason').value.trim();
            
            const exToggle = c.querySelector('.entry-ex-toggle');
            const hasExToggle = exToggle ? exToggle.checked : true;
            
            let ex_name = '';
            let ex_item = '';
            let ex_qty = '';
            let ex_amt = '';

            if (hasExToggle) {
                ex_name = c.querySelector('.entry-ex-name').value.trim();
                ex_item = c.querySelector('.entry-ex-item').value.trim();
                ex_qty = c.querySelector('.entry-ex-qty').value;
                ex_amt = c.querySelector('.entry-ex-amt').value;
            }

            const has_return = (item !== '' && (amt !== '' || qty !== ''));
            const has_exchange = hasExToggle && (ex_name !== '' || ex_item !== '' || ex_amt !== '' || ex_qty !== '');

            if (has_return || has_exchange) {
                // If return is partially filled, it's invalid
                if (item !== '' && amt === '' && qty === '') { valid = false; }
                
                entries.push({
                    return_item: item,
                    quantity: qty,
                    return_amount: amt,
                    reason: reason,
                    is_exchange: has_exchange ? 1 : 0,
                    exchange_name: ex_name,
                    exchange_item: ex_item,
                    exchange_quantity: ex_qty,
                    exchange_amount: ex_amt || 0
                });
            }
        });

        if (!valid) {
            showStatusModal(false, 'Please complete both Item # and Amount if you are filling a section.', 'Input Missing');
            return;
        }

        if (entries.length === 0) {
            showStatusModal(false, 'Please add at least one entry (Return or Exchange).', 'Empty List');
            return;
        }

        const loader = document.getElementById('loading-overlay');
        loader.classList.remove('opacity-0', 'pointer-events-none');

        const dateType = document.querySelector('input[name="page_date_type"]:checked').value;
        const customDate = document.getElementById('page_custom_date').value;
        const finalDate = (dateType === 'backdate') ? customDate : '<?= date('Y-m-d') ?>';

        fetch('api/save_return.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                entries: entries,
                transaction_date: finalDate
            })
        })
        .then(r => r.json())
        .then(res => {
            setTimeout(() => {
                loader.classList.add('opacity-0', 'pointer-events-none');
                showStatusModal(res.success, res.message, res.success ? 'Return Successful' : 'Record Failed');
                if (res.success) {
                    const container = document.getElementById('entry-container');
                    if (container) container.innerHTML = '';
                    addRow();
                    if (typeof refreshTable === 'function') refreshTable(1);
                }
            }, 800);
        })
        .catch(() => {
            loader.classList.add('opacity-0', 'pointer-events-none');
            showStatusModal(false, 'Network error.', 'Error');
        });
    };
    window.refreshReturnTable = refreshTable;

    // Initialize with 1 row
    if (document.getElementById('entry-container')) {
        addRow();
    }

    let filterTimer;
    function refreshTable(page = 1) {
        const container = document.getElementById('history-container');
        if (!container) return;
        const searchInputEl = document.querySelector('[name="search"]');
        const search    = searchInputEl?.value || '';
        const limit     = document.querySelector('[name="limit"]')?.value || 10;
        const start     = document.querySelector('[name="start_date"]')?.value || '';
        const end       = document.querySelector('[name="end_date"]')?.value || '';
        const store     = document.querySelector('[name="store_filter"]')?.value || '';
        
        const isSearchFocused = document.activeElement === searchInputEl;
        
        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';

        const url = `index.php?action=return&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}&start_date=${start}&end_date=${end}&store_filter=${store}`;
        fetch(url).then(res => res.text()).then(html => {
            container.innerHTML = html;
            initTableEvents();
            if (isSearchFocused) {
                const newSearchInput = document.querySelector('[name="search"]');
                if (newSearchInput) {
                    newSearchInput.focus();
                    const val = newSearchInput.value;
                    newSearchInput.value = '';
                    newSearchInput.value = val;
                }
            }
        });
    }

    function initTableEvents() {
        if (typeof window.initFlatpickr === 'function') window.initFlatpickr();
        const searchInput = document.querySelector('[name="search"]');
        const limitSelect = document.querySelector('[name="limit"]');
        const startInput  = document.querySelector('[name="start_date"]');
        const endInput    = document.querySelector('[name="end_date"]');
        const storeFilter = document.querySelector('[name="store_filter"]');
        const selectAll   = document.getElementById('selectAll');
        
        searchInput?.addEventListener('input', () => {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => refreshTable(1), 800);
        });
        [limitSelect, startInput, endInput, storeFilter].forEach(el => el?.addEventListener('change', () => refreshTable(1)));
        
        document.querySelectorAll('#history-container .pagination-link').forEach(link => {
            link.addEventListener('click', function(e) { e.preventDefault(); refreshTable(this.getAttribute('data-page')); });
        });

        // Checkbox Logic
        const updateBulkReturnVisibility = () => {
            const btn = document.getElementById('bulk-delete-returns');
            if (!btn) return;
            const checkedCount = document.querySelectorAll('.record-checkbox:checked').length;
            btn.classList.toggle('hidden', checkedCount === 0);
        };

        selectAll?.addEventListener('change', function() {
            document.querySelectorAll('.record-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkReturnVisibility();
        });

        document.getElementById('history-tbody')?.addEventListener('change', (e) => {
            if (e.target.classList.contains('record-checkbox')) {
                updateBulkReturnVisibility();
            }
        });

        updateBulkReturnVisibility();
    }

    window.bulkDeleteReturns = function() {
        const selectedIds = Array.from(document.querySelectorAll('.record-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) return;

        showConfirmModal(
            `Are you sure you want to delete ${selectedIds.length} selected return records? This action cannot be undone.`,
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
                    body: JSON.stringify({ table: 'returns', ids: selectedIds })
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

    window.deleteReturn = function(id) {
        showConfirmModal(
            `Are you sure you want to delete Return Record #${id}? This action cannot be undone.`,
            function() {
                fetch(`api/delete_return.php?id=${id}`)
                .then(r => r.json())
                .then(res => {
                    showStatusModal(res.success, res.message, res.success ? 'Record Deleted' : 'Action Failed');
                    if (res.success) refreshTable();
                });
            },
            'Delete Record'
        );
    };

    window.editReturn = function(id) {
        const row = document.querySelector(`.record-checkbox[value="${id}"]`)?.closest('tr');
        if (!row) return;

        const offset = <?= $is_admin ? 1 : 0 ?>;
        
        // Populate form
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-id-label').innerText = `Record ID: ${id}`;
        
        // Helper to get text or empty
        const getVal = (idx) => {
            const raw = row.cells[idx].innerText.trim();
            return (raw === '—' || raw === 'N/A' || raw.includes('Exchange Only')) ? '' : raw.replace('₱', '').replace(',', '');
        };

        document.getElementById('edit-return-item').value   = getVal(1 + offset);
        const qty = getVal(2 + offset);
        document.getElementById('edit-qty').value           = qty;
        const retAmt = getVal(3 + offset);
        document.getElementById('edit-return-amount').value = retAmt ? Math.abs(parseFloat(retAmt)) : '';
        document.getElementById('edit-reason').value        = getVal(4 + offset);
        document.getElementById('edit-ex-name').value       = getVal(5 + offset);
        document.getElementById('edit-ex-item').value       = getVal(6 + offset).replace('#', '');
        document.getElementById('edit-ex-qty').value        = qty; // Best guess since only one Qty column
        document.getElementById('edit-ex-amount').value     = getVal(7 + offset);

        const dateCell = row.cells[10 + offset];
        const rawDate = dateCell.getAttribute('data-date') || '';
        document.getElementById('edit-date').value = rawDate;

        const modal = document.getElementById('edit-return-modal');
        // Move modal to body to escape transform container and fix scroll visibility
        document.body.appendChild(modal);
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('scale-100', 'flex');
        modal.classList.remove('hidden');
    };

    window.closeEditReturnModal = function() {
        const modal = document.getElementById('edit-return-modal');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.remove('scale-100');
    };

    document.getElementById('edit-return-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            id: this.id.value,
            return_item: this.return_item.value,
            quantity: this.quantity.value,
            return_amount: this.return_amount.value,
            reason: this.reason.value,
            exchange_name: this.exchange_name.value,
            exchange_item: this.exchange_item.value,
            exchange_quantity: this.exchange_quantity.value,
            exchange_amount: this.exchange_amount.value,
            created_at: this.created_at.value
        };

        const btn = this.querySelector('button[type="submit"]');
        const origText = btn.innerText;
        btn.innerText = "SAVING...";
        btn.disabled = true;

        fetch('api/update_return.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
        .then(r => r.json())
        .then(res => {
            closeEditReturnModal();
            showStatusModal(res.success, res.message, res.success ? 'Update Successful' : 'Update Failed');
            if (res.success) refreshTable();
        })
        .finally(() => {
            btn.innerText = origText;
            btn.disabled = false;
        });
    });

    // Modal Helpers
    window.showStatusModal = function(success, message, customTitle = '') {
        if (typeof window.showStatusModal === 'function') {
            window.showStatusModal(success, message, customTitle);
        }
    };

    initTableEvents();
    window.refreshTable = refreshTable;
})();
</script>

