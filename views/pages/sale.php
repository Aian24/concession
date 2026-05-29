<?php
// Pull session data
$sale_username   = $_SESSION['user']       ?? '';
$sale_store_code = $_SESSION['store_code'] ?? '';
// Use global flags from index.php; re-derive if loaded standalone (AJAX)
if (!isset($is_admin)) {
    $role            = $_SESSION['role'] ?? 'user';
    $is_full_admin   = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view   = ($role === 'admin_view');
    $is_store_admin  = ($role === 'store_admin');
    $is_multi_store_admin = ($role === 'multi_store_admin');
    $is_admin        = ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);
    $can_submit      = ($role === 'user');
    $can_edit        = ($is_full_admin || $is_admin_view || $is_multi_store_admin);
    $can_delete      = ($is_full_admin);
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
    
    $_SESSION['sale_limit'] = $limit;
    $_SESSION['sale_page'] = $page;
    $_SESSION['sale_search'] = $search;
    $_SESSION['sale_start_date'] = $start_date;
    $_SESSION['sale_end_date'] = $end_date;
    $_SESSION['sale_store_filter'] = $store_filter;
} else {
    $limit        = $_SESSION['sale_limit'] ?? 100;
    $page         = $_SESSION['sale_page'] ?? 1;
    $search       = $_SESSION['sale_search'] ?? '';
    $start_date   = $_SESSION['sale_start_date'] ?? '';
    $end_date     = $_SESSION['sale_end_date'] ?? '';
    $store_filter = $_SESSION['sale_store_filter'] ?? '';
}
$offset       = ($page - 1) * $limit;

// Build Query
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($is_store_admin || (!$is_admin && !$is_multi_store_admin)) {
    $where .= " AND s.store_code = ?";
    $params[] = $sale_store_code;
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
    $where .= " AND (s.item_no LIKE ? OR s.username LIKE ? OR s.id LIKE ? OR s.store_code LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk;
    $types .= "ssss";
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
$count_stmt = $db->prepare("SELECT COUNT(*) FROM sales s $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$count_stmt->close();

// Fetch Rows
$recent_stmt = $db->prepare("SELECT s.*, sc.sname FROM sales s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
$p_with_limit = array_merge($params, [$limit, $offset]);
$recent_stmt->bind_param($types . "ii", ...$p_with_limit);
$recent_stmt->execute();
$submitted_sales = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

$queryString = "&search=".urlencode($search)."&limit=$limit&start_date=$start_date&end_date=$end_date&store_filter=$store_filter";

// If this is an AJAX request for the table, only return the table part
if (isset($_GET['ajax'])) {
    include 'views/pages/sale_table_partial.php';
    exit;
}
?>


<div class="pb-12 animate-fade-in">
    <?php if ($can_submit): ?>
    <!-- New Sale Form -->
    <div class="glass-panel border border-white/5 shadow-xl mb-10 min-h-[70vh] flex flex-col">
        <div class="px-6 py-4 border-b border-white/10 bg-slate-800/40 flex items-center justify-between relative overflow-hidden">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-green-500/10 border border-green-500/20 flex items-center justify-center">
                    <i class="fas fa-plus-circle text-green-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-white tracking-wider uppercase">New Sale Entry</h3>
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-0.5">Record current store transactions</p>
                </div>
            </div>
            <span id="entry-count-badge" class="text-[10px] font-black bg-green-500/10 text-green-400 border border-green-500/20 px-3 py-1 rounded-full shadow-lg shadow-green-500/5">1 item</span>
        </div>

        <div class="p-4 flex-grow flex flex-col justify-start gap-4">
            <!-- Transaction Date Selector -->
            <div class="glass-panel border border-white/5 p-3 mb-2 bg-white/2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-purple-500/10 flex items-center justify-center border border-purple-500/20">
                            <i class="fas fa-calendar-day text-purple-400 text-[10px]"></i>
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
                                <div class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg bg-slate-900 border border-white/5 peer-checked:border-purple-500/50 peer-checked:bg-purple-500/10 transition-all">
                                    <i class="fas fa-clock text-[9px] text-gray-500 peer-checked:text-purple-400"></i>
                                    <span class="text-[9px] font-bold text-gray-500 peer-checked:text-purple-400 uppercase tracking-tighter">Current</span>
                                </div>
                            </label>

                            <!-- Backdate Option -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="page_date_type" value="backdate" class="peer sr-only" onchange="handleDateTypeChange(this.value)">
                                <div id="backdate-btn-content" class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg bg-slate-900 border border-white/5 peer-checked:border-purple-500/50 peer-checked:bg-purple-500/10 transition-all overflow-hidden relative">
                                    <i class="fas fa-history text-[9px] text-gray-500 peer-checked:text-purple-400"></i>
                                    <span id="backdate-text" class="text-[9px] font-bold text-gray-500 peer-checked:text-purple-400 uppercase tracking-tighter">Backdate</span>
                                    <input type="date" id="page_custom_date" 
                                           max="<?= date('Y-m-d') ?>" 
                                           class="absolute inset-0 w-full h-full opacity-[0.01] cursor-pointer z-10" 
                                           value="<?= date('Y-m-d') ?>"
                                           onchange="updateBackdateText(this.value)"
                                           onclick="document.querySelector('input[name=\'page_date_type\'][value=\'backdate\']').checked = true; handleDateTypeChange('backdate'); try{this.showPicker();}catch(e){}">
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
                    const [year, month, day] = val.split('-');
                    const date = new Date(year, month - 1, day);
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
                        <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="relative flex items-center">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-green-400/80 uppercase tracking-widest z-10">Item #</span>
                                <input type="number" name="item_no" min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-l-xl px-4 py-2.5 flex-1 text-xs text-white focus:outline-none focus:border-green-500/50 font-medium" placeholder="100123">
                                <button type="button" onclick="startBarcodeScanForRow(this)" class="h-[38px] bg-purple-600/20 border border-l-0 border-white/10 px-3 rounded-r-xl text-purple-400 hover:bg-purple-600/30 transition-all">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <div class="relative">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-green-400/80 uppercase tracking-widest z-10">Price (₱)</span>
                                <input type="number" name="amount_sold" step="0.01" min="0" onkeydown="if(['e','E','+','-'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-green-500/50 font-medium" placeholder="0.00">
                            </div>
                            <div class="relative">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-green-400/80 uppercase tracking-widest z-10">Qty</span>
                                <input type="number" name="quantity" min="0" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-green-500/50 font-medium" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-white/5 pt-4">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="button" onclick="addRow()" class="flex-1 sm:flex-initial h-[42px] px-4 rounded-lg bg-slate-700/40 hover:bg-slate-700/60 text-green-400 border border-white/10 text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg hover:-translate-y-0.5">
                        <i class="fas fa-plus-circle"></i> Add Another Entry
                    </button>
                    
                    <button type="button" onclick="startBarcodeScan('item_no')" class="sm:hidden h-[42px] px-4 rounded-lg bg-purple-600/40 hover:bg-purple-600/60 text-purple-400 border border-white/10 text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg hover:-translate-y-0.5">
                        <i class="fas fa-camera"></i> Scan
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    <div class="flex flex-wrap items-center justify-between sm:justify-start gap-3 bg-slate-800/40 px-3 py-2 rounded-lg border border-white/5 min-h-[38px]">
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Items:</span>
                            <span class="text-xs font-black text-white" id="summary-items">0</span>
                        </div>
                        <div class="w-px h-3 bg-white/10"></div>
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Qty:</span>
                            <span class="text-xs font-black text-white" id="summary-qty">0</span>
                        </div>
                        <div class="w-px h-3 bg-white/10"></div>
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <span class="text-[9px] text-green-500/70 font-bold uppercase tracking-tight">Total:</span>
                            <span class="text-xs font-black text-green-400" id="summary-total">₱0.00</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 w-full sm:w-auto h-[38px]">
                        <button type="button" onclick="clearForm()" class="px-3 py-0 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 text-[10px] font-bold border border-white/5 uppercase transition-all flex items-center justify-center">Clear</button>
                        <button type="button" id="submit-btn" onclick="submitSale()" class="px-4 py-0 rounded-lg bg-gradient-to-r from-green-600 to-emerald-600 text-white text-[10px] font-black uppercase tracking-wider shadow-lg shadow-green-500/10 hover:-translate-y-0.5 transition-all flex items-center justify-center">Submit Sale</button>
                    </div>
                </div>
            </div>

            <div id="sale-toast" class="hidden mt-2 px-3 py-1.5 rounded text-[10px] font-bold flex items-center gap-2"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[100] flex flex-col items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="relative">
            <div class="w-16 h-16 border-4 border-green-500/20 border-t-green-500 rounded-full animate-spin"></div>
            <i class="fas fa-shopping-cart absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-green-500 text-xl animate-pulse"></i>
        </div>
        <p class="mt-4 text-white font-black text-xs uppercase tracking-[0.2em] animate-pulse">Processing Transaction...</p>
    </div>



    <!-- Edit Sale Modal -->
    <div id="edit-modal" class="fixed inset-0 z-[102] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-500 scale-95">
        <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" onclick="closeEditModal()"></div>
        <div class="relative glass-panel border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.5)] w-full max-w-md mx-4 overflow-hidden transform transition-all duration-500">
            <!-- Decorative Glows -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/20 rounded-full blur-[80px] pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-indigo-500/20 rounded-full blur-[80px] pointer-events-none"></div>

            <div class="bg-gradient-to-br from-blue-600/30 via-indigo-600/20 to-violet-600/30 p-6 border-b border-white/10 relative">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                        <i class="fas fa-edit text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-white tracking-wider uppercase">Edit Sale Record</h3>
                        <p id="edit-id-label" class="text-[10px] text-blue-300/70 font-bold uppercase tracking-[0.1em] mt-0.5">Reference ID: #0000</p>
                    </div>
                </div>
                <button onclick="closeEditModal()" class="absolute top-6 right-6 w-8 h-8 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="p-8">
                <form id="edit-sale-form" class="space-y-6">
                    <input type="hidden" name="id" id="edit-id">
                    
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Item Serial #</label>
                        <div class="relative group">
                            <i class="fas fa-barcode absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                            <input type="number" name="item_no" id="edit-item-no" required min="0" 
                                   oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" 
                                   class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold" 
                                   placeholder="100123">
                        </div>
                    </div>

                    <?php if ($is_admin || $is_multi_store_admin): ?>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Store</label>
                        <div class="relative group" id="store-filter-container">
                            <i class="fas fa-store absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors z-10"></i>
                            
                            <!-- Custom Trigger -->
                            <div id="store-filter-trigger" class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-sm text-white flex items-center justify-between cursor-pointer focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold">
                                <span id="selected-store-label" class="truncate opacity-80">Select Store...</span>
                                <i class="fas fa-chevron-down text-gray-500 pointer-events-none text-[10px]"></i>
                            </div>

                            <!-- Custom Menu -->
                            <div id="store-filter-menu" class="absolute top-[calc(100%+8px)] right-0 min-w-[280px] w-full bg-[#0f172a] border border-white/10 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto overflow-x-hidden backdrop-blur-xl">
                                <div class="sticky top-0 bg-[#0f172a] p-3 border-b border-white/5 z-20">
                                    <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-3 text-[11px] text-white focus:outline-none focus:border-blue-500/50" placeholder="Search store..." autocomplete="off">
                                </div>
                                <?php 
                                $stores_list = $db->query("SELECT scode, sname FROM storecode ORDER BY scode ASC")->fetch_all(MYSQLI_ASSOC);
                                foreach ($stores_list as $st): 
                                    if ($is_multi_store_admin && !in_array($st['scode'], $_SESSION['assigned_stores'] ?? [])) continue;
                                    $displayName = $st['scode'] . " - " . $st['sname'];
                                ?>
                                    <div class="store-option px-5 py-3.5 text-[12px] text-white hover:bg-white/5 cursor-pointer flex flex-col justify-center transition-all border-b border-white/5 last:border-0" 
                                         data-value="<?= htmlspecialchars($st['scode']) ?>" 
                                         data-label="<?= htmlspecialchars($displayName) ?>">
                                        <span class="font-bold truncate"><?= htmlspecialchars($st['scode']) ?></span>
                                        <span class="text-[10px] text-gray-500 truncate uppercase tracking-tighter mt-0.5"><?= htmlspecialchars($st['sname']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Hidden select for form logic -->
                            <select name="store_code" id="edit-store" class="hidden">
                                <option value="" disabled selected>Select Store...</option>
                                <?php foreach ($stores_list as $st): 
                                    if ($is_multi_store_admin && !in_array($st['scode'], $_SESSION['assigned_stores'] ?? [])) continue;
                                ?>
                                    <option value="<?= htmlspecialchars($st['scode']) ?>"><?= htmlspecialchars($st['scode']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Sale Price (₱)</label>
                            <div class="relative group">
                                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 font-bold group-focus-within:text-blue-400 transition-colors">₱</span>
                                <input type="number" name="amount_sold" id="edit-amount" step="0.01" required 
                                       class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-10 pr-5 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold" 
                                       placeholder="0.00">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Quantity</label>
                            <div class="relative group">
                                <i class="fas fa-layer-group absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                                <input type="number" name="quantity" id="edit-qty" required 
                                       class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold" 
                                       placeholder="0">
                            </div>
                        </div>
                        <div class="space-y-2 col-span-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Transaction Date</label>
                            <div class="relative group">
                                <i class="fas fa-calendar-day absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                                <input type="date" name="created_at" id="edit-date" required 
                                       class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-12 pr-10 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:hover:opacity-100 [&::-webkit-calendar-picker-indicator]:transition-opacity" 
                                       onclick="this.showPicker()">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full py-4 mt-8 rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-blue-500/20 hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-3">
                        <span>Update Transaction</span>
                        <i class="fas fa-check-circle text-[10px] text-white/50"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>




    <!-- Submitted Sales Container -->
    <?php if ($is_admin): ?>
    <div id="submitted-sales-container" class="mt-4">
        <?php include 'views/pages/sale_table_partial.php'; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    // ── Form Logic ───────────────────────────────────────────
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
        let items = 0, qty = 0, total = 0;
        document.querySelectorAll('.entry-row').forEach(row => {
            const itm = row.querySelector('[name="item_no"]').value.trim();
            const amt = parseFloat(row.querySelector('[name="amount_sold"]').value) || 0;
            const q   = parseInt(row.querySelector('[name="quantity"]').value) || 0;
            if (itm) items++;
            qty += q;
            total += amt * q;
        });
        document.getElementById('summary-items').textContent = items;
        document.getElementById('summary-qty').textContent = qty;
        document.getElementById('summary-total').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
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
        const itemInput = row.querySelector('[name="item_no"]');
        itemInput.addEventListener('input', function() { lookupPrismPrice(this); });
        itemInput.focus();
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
            showConfirmModal('Are you sure you want to clear all current entry data?', () => {
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



    window.startBarcodeScanForRow = function(btn) {
        const input = btn.closest('.relative').querySelector('input[name="item_no"]');
        if (typeof window.startBarcodeScan === 'function') {
            window.startBarcodeScan('item_no', input);
        }
    };

    window.submitSale = function () {
        const entries = [];
        document.querySelectorAll('.entry-row').forEach(row => {
            const item = row.querySelector('[name="item_no"]').value.trim();
            const amt  = row.querySelector('[name="amount_sold"]').value;
            const qty  = row.querySelector('[name="quantity"]').value;
            if (item) entries.push({ item_no: item, amount_sold: amt, quantity: qty });
        });

        if (entries.length === 0) return;
        
        // Show Loader
        const loader = document.getElementById('loading-overlay');
        const p = loader.querySelector('p');
        if (p) p.textContent = 'Processing Transaction...';
        loader.classList.remove('opacity-0', 'pointer-events-none');

        const dateType = document.querySelector('input[name="page_date_type"]:checked').value;
        const customDate = document.getElementById('page_custom_date').value;
        const finalDate = (dateType === 'backdate') ? customDate : '<?= date('Y-m-d') ?>';

        fetch('api/save_sale.php', { 
            method:'POST', 
            headers:{'Content-Type':'application/json'}, 
            body:JSON.stringify({
                entries: entries,
                transaction_date: finalDate
            }) 
        })
        .then(r => r.json())
        .then(res => { 
            setTimeout(() => { // Small delay for "premium" feel
                loader.classList.add('opacity-0', 'pointer-events-none');
                showStatusModal(res.success, res.message, res.success ? 'Sale Successful' : 'Sale Failed');
                if (res.success) { 
                    const rows = document.querySelectorAll('.entry-row');
                    rows.forEach((r, i) => { if (i > 0) r.remove(); });
                    rows[0].querySelectorAll('input').forEach(i => i.value = '');
                    updateSummary();
                    updateBadge();
                    
                    const searchInput = document.querySelector('[name="search"]');
                    const startInput  = document.querySelector('[name="start_date"]');
                    const endInput    = document.querySelector('[name="end_date"]');
                    if (searchInput) searchInput.value = '';
                    if (startInput)  startInput.value = '';
                    if (endInput)    endInput.value = '';
                    refreshTable(1); 
                }
            }, 600);
        });
    };

    window.bulkDeleteSales = function () {
        const selectedIds = Array.from(document.querySelectorAll('.sale-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) return;

        showConfirmModal(
            `Are you sure you want to delete ${selectedIds.length} selected sale records? This action cannot be undone.`,
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
                    body: JSON.stringify({ table: 'sales', ids: selectedIds })
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

    window.deleteSale = function (id) {
        showConfirmModal(
            `Are you sure you want to delete Sale Record #${id}? This action cannot be undone.`,
            function() {
                fetch(`api/delete_sale.php?id=${id}`)
                .then(r => r.json())
                .then(res => {
                    showStatusModal(res.success, res.message, res.success ? 'Record Deleted' : 'Action Failed');
                    if (res.success) refreshTable();
                });
            },
            'Delete Record'
        );
    };

    window.editSale = function (id, storeCode = null) {
        const row = document.querySelector(`input[value="${id}"].sale-checkbox`)?.closest('tr');
        if (!row) return;

        // Extract data from row columns (careful with indexing if Store column exists)
        const offset = <?= $is_admin ? 1 : 0 ?>;
        const itemNo = row.cells[1 + offset].innerText;
        const price  = row.cells[2 + offset].innerText.replace('₱', '').replace(',', '');
        const qty    = row.cells[3 + offset].innerText;
        
        // Extract raw date from data-label or find a way to get the machine-readable date
        // The date cell is at 6 + offset
        const dateCell = row.cells[6 + offset];
        // We can use a trick: the original date was formatted, but maybe we can find it in a hidden span or similar.
        // Actually, let's just use the current value and try to parse it, or better, we can add a data-date attribute in the partial.
        // For now, I'll add the data-date attribute to the partial in the next step.
        const rawDate = dateCell.getAttribute('data-date') || '';

        document.getElementById('edit-id').value = id;
        document.getElementById('edit-id-label').innerText = `Record ID: ${id}`;
        document.getElementById('edit-item-no').value = itemNo;
        document.getElementById('edit-amount').value = price;
        document.getElementById('edit-qty').value = qty;
        document.getElementById('edit-date').value = rawDate;
        
        if (storeCode && document.getElementById('edit-store')) {
            document.getElementById('edit-store').value = storeCode;
            const container = document.getElementById('edit-store').closest('#store-filter-container');
            if (container) {
                const labelEl = container.querySelector('#selected-store-label');
                const menu = container.querySelector('#store-filter-menu');
                if (labelEl && menu) {
                    const opt = menu.querySelector(`.store-option[data-value="${storeCode}"]`);
                    if (opt) {
                        labelEl.textContent = opt.getAttribute('data-label');
                    } else {
                        labelEl.textContent = storeCode;
                    }
                }
            }
        }

        const modal = document.getElementById('edit-modal');
        // Move modal to body to escape transform container and fix scroll visibility
        document.body.appendChild(modal);
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('scale-100', 'flex');
        modal.classList.remove('hidden');
    };

    window.closeEditModal = function() {
        const modal = document.getElementById('edit-modal');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.remove('scale-100');
    };

    document.getElementById('edit-sale-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            id: this.id.value,
            item_no: this.item_no.value,
            amount_sold: this.amount_sold.value,
            quantity: this.quantity.value,
            created_at: this.created_at.value
        };
        
        if (this.store_code) {
            data.store_code = this.store_code.value;
        }

        const btn = this.querySelector('button[type="submit"]');
        const origText = btn.innerText;
        btn.innerText = "SAVING...";
        btn.disabled = true;

        fetch('api/update_sale.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
        .then(r => r.json())
        .then(res => {
            closeEditModal();
            showStatusModal(res.success, res.message, res.success ? 'Update Successful' : 'Update Failed');
            if (res.success) refreshTable();
        })
        .finally(() => {
            btn.innerText = origText;
            btn.disabled = false;
        });
    });

    let filterTimer;
    function refreshTable(page = 1) {
        const container = document.getElementById('submitted-sales-container');
        const searchInputEl = document.querySelector('[name="search"]');
        const search = searchInputEl?.value || '';
        const limit  = document.querySelector('[name="limit"]')?.value || 100;
        const start  = document.querySelector('[name="start_date"]')?.value || '';
        const end    = document.querySelector('[name="end_date"]')?.value || '';
        const store  = document.querySelector('[name="store_filter"]')?.value || '';
        
        const isSearchFocused = document.activeElement === searchInputEl;

        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';
        const url = `index.php?action=sale&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}&start_date=${start}&end_date=${end}&store_filter=${store}`;
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
        const searchInput    = document.querySelector('[name="search"]');
        const limitSelect    = document.querySelector('[name="limit"]');
        const startInput     = document.querySelector('[name="start_date"]');
        const endInput       = document.querySelector('[name="end_date"]');
        const storeFilter    = document.querySelector('[name="store_filter"]');
        const selectAll      = document.getElementById('selectAll');
        
        searchInput?.addEventListener('input', () => { clearTimeout(filterTimer); filterTimer = setTimeout(() => refreshTable(1), 300); });
        [limitSelect, startInput, endInput, storeFilter].forEach(el => el?.addEventListener('change', () => refreshTable(1)));
        document.querySelectorAll('#submitted-sales-section .pagination-link').forEach(link => {
            link.addEventListener('click', function(e) { e.preventDefault(); refreshTable(this.getAttribute('data-page')); });
        });

        // Checkbox Logic
        const updateBulkDeleteVisibility = () => {
            const btn = document.getElementById('bulk-delete-sales');
            if (!btn) return;
            const checkedCount = document.querySelectorAll('.sale-checkbox:checked').length;
            btn.classList.toggle('hidden', checkedCount === 0);
        };

        selectAll?.addEventListener('change', function() {
            document.querySelectorAll('.sale-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkDeleteVisibility();
        });

        document.getElementById('submitted-tbody')?.addEventListener('change', (e) => {
            if (e.target.classList.contains('sale-checkbox')) {
                updateBulkDeleteVisibility();
            }
        });

        updateBulkDeleteVisibility();
    }

    window.updateSummary = function() {
        let items = 0, qty = 0, total = 0;
        document.querySelectorAll('.entry-row').forEach(row => {
            const itm = row.querySelector('[name="item_no"]').value.trim();
            const amt = parseFloat(row.querySelector('[name="amount_sold"]').value) || 0;
            const q   = parseInt(row.querySelector('[name="quantity"]').value) || 0;
            if (itm) items++;
            qty += q;
            total += amt * q;
        });
        document.getElementById('summary-items').textContent = items;
        document.getElementById('summary-qty').textContent = qty;
        document.getElementById('summary-total').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits:2});
    };

    window.lookupPrismPrice = function(input) {
        const item_no = input.value.trim();
        if (item_no.length < 3) return; // Minimum length to trigger lookup

        const row = input.closest('.entry-row');
        if (!row) return;

        const amountInput = row.querySelector('[name="amount_sold"]');
        if (!amountInput) return;

        fetch(`api/get_prism_price.php?item_no=${encodeURIComponent(item_no)}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                amountInput.value = res.srp;
                window.updateSummary();
                // Visual feedback
                amountInput.classList.add('ring-2', 'ring-green-500/50');
                setTimeout(() => amountInput.classList.remove('ring-2', 'ring-green-500/50'), 1000);
            }
        });
    };

    // Attach listener to initial rows
    document.querySelectorAll('[name="item_no"]').forEach(input => {
        input.addEventListener('input', function() {
            window.lookupPrismPrice(this);
        });
    });

    // Global listener for any input change in the entry rows
    document.getElementById('entry-rows')?.addEventListener('input', (e) => {
        if (e.target.tagName === 'INPUT') window.updateSummary();
    });
    document.getElementById('entry-rows')?.addEventListener('change', (e) => {
        if (e.target.tagName === 'INPUT') window.updateSummary();
    });

    initTableEvents();
    window.refreshSaleTable = refreshTable;



    initTableEvents();
    window.refreshSaleTable = refreshTable;
})();
</script>
