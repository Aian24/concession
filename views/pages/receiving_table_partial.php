<?php
// Ensure $received_items is available (already fetched in receiving.php)
// or re-fetch if this is an AJAX request
if (!isset($received_items)) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once 'includes/db.php'; // Adjusted path for better consistency
    $db = db_connect();
    $rec_store_code = $_SESSION['store_code'] ?? '';
    $role           = $_SESSION['role'] ?? 'user';
    $is_full_admin  = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view  = ($role === 'admin_view');
    $is_store_admin = ($role === 'store_admin');
    $is_multi_store_admin = ($role === 'multi_store_admin');
    $is_admin       = $_SESSION['is_admin'] ?? ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);
    $can_edit       = $_SESSION['can_edit'] ?? false;
    $can_delete     = $_SESSION['can_delete'] ?? false;
    
    $is_filter_request = isset($_GET['ajax']) || isset($_GET['search']) || isset($_GET['start_date']) || isset($_GET['end_date']) || isset($_GET['store_filter']);
    if ($is_filter_request) {
        $limit        = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $page         = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
        $search       = $_GET['search'] ?? '';
        $start_date   = $_GET['start_date'] ?? '';
        $end_date     = $_GET['end_date']   ?? '';
        $store_filter = $_GET['store_filter'] ?? '';
        
        $_SESSION['receiving_partial_limit'] = $limit;
        $_SESSION['receiving_partial_page'] = $page;
        $_SESSION['receiving_partial_search'] = $search;
        $_SESSION['receiving_partial_start_date'] = $start_date;
        $_SESSION['receiving_partial_end_date'] = $end_date;
        $_SESSION['receiving_partial_store_filter'] = $store_filter;
    } else {
        $limit        = $_SESSION['receiving_partial_limit'] ?? 10;
        $page         = $_SESSION['receiving_partial_page'] ?? 1;
        $search       = $_SESSION['receiving_partial_search'] ?? '';
        $start_date   = $_SESSION['receiving_partial_start_date'] ?? '';
        $end_date     = $_SESSION['receiving_partial_end_date'] ?? '';
        $store_filter = $_SESSION['receiving_partial_store_filter'] ?? '';
    }
    $offset       = ($page - 1) * $limit;

    // Build Query
    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    if ($is_store_admin || (!$is_admin && !$is_multi_store_admin)) {
        $where .= " AND s.store_code = ?";
        $params[] = $rec_store_code;
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
        $where .= " AND (s.os_no LIKE ? OR s.from_store LIKE ? OR s.to_store LIKE ? OR s.store_code LIKE ? OR s.username LIKE ? OR s.id LIKE ?)";
        $lk = "%" . trim($search) . "%";
        $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk;
        $types .= "ssssss";
    }
    if ($start_date !== '') {
        $where .= " AND s.created_at >= ?"; $params[] = $start_date . ' 00:00:00'; $types .= "s";
    }
    if ($end_date !== '') {
        $where .= " AND s.created_at <= ?"; $params[] = $end_date . ' 23:59:59'; $types .= "s";
    }

    $count_stmt = $db->prepare("SELECT COUNT(*) FROM receiving s $where");
    if (!empty($params)) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_row()[0];
    $total_pages = max(1, ceil($total_rows / $limit));
    $count_stmt->close();

    $recent_stmt = $db->prepare("SELECT s.*, sc.sname, fsc.sname as from_sname, tsc.sname as to_sname FROM receiving s LEFT JOIN storecode sc ON s.store_code = sc.scode LEFT JOIN storecode fsc ON s.from_store = fsc.scode LEFT JOIN storecode tsc ON s.to_store = tsc.scode $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
    $p_with_limit = array_merge($params, [$limit, $offset]);
    $recent_stmt->bind_param($types . "ii", ...$p_with_limit);
    $recent_stmt->execute();
    $received_items = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $recent_stmt->close();
}
?>

<style>
/* Custom Scrollbar for the table */
.overflow-x-auto::-webkit-scrollbar { height: 6px; }
.overflow-x-auto::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
.overflow-x-auto::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
.overflow-x-auto::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

/* Date picker appearance */
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1);
    opacity: 0.6;
    cursor: pointer;
    transition: all 0.2s;
}
input[type="date"]::-webkit-calendar-picker-indicator:hover {
    opacity: 1;
    transform: scale(1.1);
}

@media (max-width: 768px) {
    #receiving-history-table thead { display: none; }
    #receiving-history-table, #receiving-history-table tbody { display: block; width: 100%; }
    #receiving-history-table tr { 
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
    #receiving-history-table tr:first-child { margin-top: 1.5rem; }
    #receiving-history-table td { 
        display: flex; 
        flex-direction: column;
        justify-content: flex-start; 
        align-items: flex-start; 
        padding: 0; 
        border: none; 
        white-space: normal;
        min-width: 0;
        grid-column: span 1 !important;
        text-align: left !important;
    }
    #receiving-history-table td::before { 
        content: attr(data-label); 
        font-weight: 900; 
        text-transform: uppercase; 
        font-size: 7px; 
        color: #64748b; 
        letter-spacing: 0.1em;
        margin-bottom: 4px;
        opacity: 0.8;
    }
    
    #receiving-history-table td[data-label="Select"] {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: auto;
        border: none;
        padding: 0;
        z-index: 10;
    }
    #receiving-history-table td[data-label="Select"]::before { display: none; }
    
    #receiving-history-table td span, 
    #receiving-history-table td div { 
        font-size: 10px !important; 
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    #receiving-history-table td .flex-col { align-items: flex-start !important; text-align: left !important; }
    #receiving-history-table td .mx-auto { margin-left: 0 !important; }
    #receiving-history-table td[data-label="Username"] .flex span:first-child { display: none; }
}
</style>

<div id="receiving-history-section" class="glass-panel border border-white/5 shadow-xl overflow-hidden animate-fade-in">
    <div class="px-5 py-4 border-b border-white/5 bg-slate-800/25 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center border border-cyan-500/20">
                <i class="fas fa-history text-cyan-400 text-xs"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-wider">Receiving History</h3>
                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-0.5">Track inbound stock transfers</p>
            </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 bg-slate-900/50 border border-white/5 rounded-lg px-2 py-1">
                <span class="text-[9px] font-bold text-gray-500 uppercase">Show</span>
                <select name="limit" class="bg-transparent text-xs font-bold text-white focus:outline-none cursor-pointer">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit == 100 || empty($limit) ? 'selected' : '' ?>>100</option>
                </select>
            </div>
            <div class="flex gap-1 items-center">
                <button id="bulk-delete-receiving" onclick="bulkDeleteReceiving()" class="hidden h-[34px] flex items-center justify-center px-3 rounded-md bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all mr-2">
                    <i class="fas fa-trash-alt mr-2"></i> DELETE SELECTED
                </button>
                <button onclick="runExportReceiving('csv')" class="px-3 py-1.5 rounded-md bg-white/5 hover:bg-emerald-500/10 border border-white/5 hover:border-emerald-500/20 text-xs font-bold text-emerald-400 transition-all">CSV</button>
                <button onclick="runExportReceiving('xls')" class="px-3 py-1.5 rounded-md bg-white/5 hover:bg-blue-500/10 border border-white/5 hover:border-blue-500/20 text-xs font-bold text-blue-400 transition-all">XLS</button>
                <button onclick="runExportReceiving('txt')" class="px-3 py-1.5 rounded-md bg-white/5 hover:bg-slate-500/10 border border-white/5 hover:border-slate-500/20 text-xs font-bold text-slate-400 transition-all">TXT</button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="p-4 border-b border-white/5 bg-slate-800/10">
        <div class="grid grid-cols-2 <?= $is_admin ? 'lg:grid-cols-6' : 'lg:grid-cols-5' ?> gap-3 bg-white/5 p-3 rounded-xl border border-white/5">
            <div class="space-y-1">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Search</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="OS, Item, Store or ID..." class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-[10px] text-white focus:outline-none focus:border-cyan-500/50 transition-all">
                </div>
            </div>

            <?php if ($is_admin): ?>
            <div class="space-y-1 relative" id="store-filter-container">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Filter</label>
                <?php
                $q_params = [];
                $q_types = "";
                $q_where = "WHERE 1=1";

                if ($is_multi_store_admin) {
                    $assigned = $_SESSION['assigned_stores'] ?? [];
                    $q_where .= build_multi_store_clause('s.store_code', $assigned);
                }

                if (!empty($start_date)) {
                    $q_where .= " AND s.created_at >= ?";
                    $q_params[] = $start_date . ' 00:00:00';
                    $q_types .= "s";
                }
                if (!empty($end_date)) {
                    $q_where .= " AND s.created_at <= ?";
                    $q_params[] = $end_date . ' 23:59:59';
                    $q_types .= "s";
                }

                $stores_sql = "SELECT s.store_code, sc.sname, SUM(s.quantity) as total_qty 
                               FROM receiving s 
                               LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci
                               $q_where 
                               GROUP BY s.store_code, sc.sname 
                               ORDER BY s.store_code";

                $stores_stmt = $db->prepare($stores_sql);
                if (!empty($q_params)) {
                    $stores_stmt->bind_param($q_types, ...$q_params);
                }
                $stores_stmt->execute();
                $stores_res = $stores_stmt->get_result();
                $stores_data = $stores_res->fetch_all(MYSQLI_ASSOC);
                $stores_stmt->close();

                $current_label = "All Stores";
                if ($store_filter) {
                    foreach($stores_data as $row) {
                        if ($row['store_code'] === $store_filter) {
                            $current_label = $row['store_code'] . ($row['sname'] ? " - " . $row['sname'] : "");
                            break;
                        }
                    }
                }
                ?>
                <!-- Custom Trigger -->
                <div id="store-filter-trigger" class="w-full bg-slate-900/80 border border-white/10 rounded-lg px-3 py-1.5 h-8 text-[10px] text-white focus:outline-none focus:border-cyan-500/50 transition-all cursor-pointer flex items-center justify-between hover:bg-white/5">
                    <span id="selected-store-label" class="truncate font-bold opacity-80"><?= htmlspecialchars($current_label) ?></span>
                    <i class="fas fa-chevron-down text-[9px] text-gray-500 ml-2"></i>
                </div>

                <!-- Custom Menu -->
                <div id="store-filter-menu" class="absolute top-[calc(100%+4px)] right-0 min-w-[280px] w-full bg-[#0f172a] border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto overflow-x-hidden backdrop-blur-xl">
                    <div class="sticky top-0 bg-[#0f172a] p-2 border-b border-white/5 z-20">
                        <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-[10px] text-white focus:outline-none focus:border-cyan-500/50" placeholder="Search store..." autocomplete="off">
                    </div>
                    <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $store_filter === '' ? 'bg-cyan-500/10' : '' ?>" data-value="">
                        <span class="font-bold">All Stores</span>
                    </div>
                    <?php foreach($stores_data as $st): 
                        $sel = ($store_filter == $st['store_code']);
                        $displayName = $st['store_code'] . ($st['sname'] ? " - " . $st['sname'] : "");
                        $qty = number_format($st['total_qty'] ?? 0);
                    ?>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $sel ? 'bg-cyan-500/10' : '' ?>" 
                             data-value="<?= htmlspecialchars($st['store_code']) ?>" 
                             data-label="<?= htmlspecialchars($displayName) ?>">
                            <div class="flex flex-col min-w-0 flex-1 mr-4">
                                <span class="font-bold truncate"><?= htmlspecialchars($st['store_code']) ?></span>
                                <?php if ($st['sname']): ?>
                                    <span class="text-[9px] text-gray-500 truncate uppercase tracking-tighter"><?= htmlspecialchars($st['sname']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Total Qty: <span class="text-cyan-400 font-black ml-1"><?= $qty ?></span></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hidden select for filtering logic -->
                <select name="store_filter" class="hidden">
                    <option value="" <?= $store_filter === '' ? 'selected' : '' ?>>All Stores</option>
                    <?php foreach($stores_data as $st): ?>
                        <option value="<?= htmlspecialchars($st['store_code']) ?>" <?= $store_filter == $st['store_code'] ? 'selected' : '' ?>><?= htmlspecialchars($st['store_code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="space-y-1">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">From Date</label>
                <div class="relative">
                    <input type="date" name="start_date" value="<?= $start_date ?>" onclick="this.showPicker()" placeholder="mm/dd/yyyy" class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-3 pr-8 py-1.5 h-8 text-[10px] text-white focus:outline-none focus:border-cyan-500/50 transition-all cursor-pointer">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <i class="fas fa-calendar-alt text-gray-500 text-[10px]"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">To Date</label>
                <div class="relative">
                    <input type="date" name="end_date" value="<?= $end_date ?>" onclick="this.showPicker()" placeholder="mm/dd/yyyy" class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-3 pr-8 py-1.5 h-8 text-[10px] text-white focus:outline-none focus:border-cyan-500/50 transition-all cursor-pointer">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <i class="fas fa-calendar-alt text-gray-500 text-[10px]"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-1">
                <?php 
                $currYr = 2026;
                $selMinYr = !empty($start_date) ? date('Y', strtotime($start_date)) : $currYr;
                $selMaxYr = !empty($end_date) ? date('Y', strtotime($end_date)) : $currYr;
                $selected_years_count = max(0, $selMaxYr - $selMinYr + 1);
                $yr_hint = $selected_years_count > 1 ? "($selected_years_count selected)" : "";
                ?>
                <div class="relative w-full">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Quick Year <span class="text-cyan-400/60" id="table-yr-multi-hint"><?= $yr_hint ?></span></label>
                    <button type="button" onclick="resetTableQuickYear()" class="absolute right-1 top-0 mt-0 text-gray-500 hover:text-red-400 transition-colors" title="Reset Year"><i class="fas fa-sync-alt text-[9px]"></i></button>
                </div>
                <div class="shrink-0 flex items-center bg-slate-900/80 border border-white/10 rounded-lg shadow-inner h-8 relative z-20 overflow-hidden w-full">
                    <button type="button" onclick="scrollTableQuickYears(-1)" class="absolute left-0 z-10 h-full px-1.5 bg-gradient-to-r from-slate-900 via-slate-900/90 to-transparent flex items-center justify-start text-gray-400 hover:text-white transition-all"><i class="fas fa-chevron-left text-[8px]"></i></button>
                    
                    <div id="table-years-container" class="flex items-center gap-1 overflow-x-auto hide-scrollbar scroll-smooth w-full px-5">
                        <?php 
                        for($y = $currYr - 4; $y <= $currYr + 2; $y++):
                            $is_active = ($y >= $selMinYr && $y <= $selMaxYr);
                        ?>
                        <button type="button" data-year="<?= $y ?>" onclick="toggleTableYear(this, <?= $y ?>)" class="flex-shrink-0 w-[45px] py-1 rounded text-[9px] font-bold uppercase tracking-wider transition-all table-year-btn text-center select-none border <?= $is_active ? '!bg-cyan-500/20 !text-cyan-400 shadow-sm !border-cyan-500/50 active-year' : 'text-gray-500 border-transparent hover:text-cyan-400 hover:bg-white/5' ?>">
                            <?= $y ?>
                        </button>
                        <?php endfor; ?>
                    </div>
                    
                    <button type="button" onclick="scrollTableQuickYears(1)" class="absolute right-0 z-10 h-full px-1.5 bg-gradient-to-l from-slate-900 via-slate-900/90 to-transparent flex items-center justify-end text-gray-400 hover:text-white transition-all"><i class="fas fa-chevron-right text-[8px]"></i></button>
                </div>
            </div>

            <div class="space-y-1">
                <?php
                $selMinM = !empty($start_date) ? date('m', strtotime($start_date)) : null;
                $selMaxM = !empty($end_date) ? date('m', strtotime($end_date)) : null;
                $selected_months_count = ($selMinM && $selMaxM) ? max(0, (int)$selMaxM - (int)$selMinM + 1) : 0;
                $mo_hint = $selected_months_count > 1 ? "($selected_months_count selected)" : "";
                ?>
                <div class="relative w-full">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Quick Month <span class="text-cyan-400/60" id="table-mo-multi-hint"><?= $mo_hint ?></span></label>
                    <button type="button" onclick="resetTableQuickMonth()" class="absolute right-1 top-0 mt-0 text-gray-500 hover:text-red-400 transition-colors" title="Reset Month"><i class="fas fa-sync-alt text-[9px]"></i></button>
                </div>
                <div class="shrink-0 flex items-center bg-slate-900/80 border border-white/10 rounded-lg shadow-inner h-8 relative z-20 overflow-hidden w-full">
                    <button type="button" onclick="scrollTableQuickMonths(-1)" class="absolute left-0 z-10 h-full px-1.5 bg-gradient-to-r from-slate-900 via-slate-900/90 to-transparent flex items-center justify-start text-gray-400 hover:text-white transition-all"><i class="fas fa-chevron-left text-[8px]"></i></button>
                    
                    <div id="table-months-container" class="flex items-center gap-1 overflow-x-auto hide-scrollbar scroll-smooth w-full px-5">
                        <?php 
                        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        foreach($months as $idx => $m): 
                            $m_num = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                            $is_active = ($selMinM && $selMaxM && $m_num >= $selMinM && $m_num <= $selMaxM && !empty($start_date)); 
                        ?>
                        <button type="button" data-month="<?= $m_num ?>" onclick="toggleTableMonth(this, '<?= $m_num ?>')" class="flex-shrink-0 w-[38px] py-1 rounded text-[9px] font-bold uppercase tracking-wider transition-all table-month-btn text-center select-none border <?= $is_active ? '!bg-cyan-500/20 !text-cyan-400 shadow-sm !border-cyan-500/50 active-month' : 'text-gray-500 border-transparent hover:text-cyan-400 hover:bg-white/5' ?>">
                            <?= $m ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" onclick="scrollTableQuickMonths(1)" class="absolute right-0 z-10 h-full px-1.5 bg-gradient-to-l from-slate-900 via-slate-900/90 to-transparent flex items-center justify-end text-gray-400 hover:text-white transition-all"><i class="fas fa-chevron-right text-[8px]"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto min-h-[300px] w-full">
        <table class="w-full text-left border-collapse glass-table" id="receiving-history-table">
            <thead>
                <tr>
                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 w-10 text-center">
                        <input type="checkbox" id="select-all" class="rounded border-white/10 bg-slate-900 text-cyan-500 focus:ring-cyan-500/20">
                    </th>
                    <?php if ($is_admin): ?>
                        <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Store</th>
                    <?php endif; ?>

                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">TF#</th>
                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Qty</th>
                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Source (From)</th>
                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Destination (To)</th>
                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Username</th>
                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Date Received</th>
                    <?php if ($is_full_admin): ?>
                        <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Timestamp</th>
                    <?php endif; ?>
                    <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center">Status</th>
                    <?php if ($can_edit): ?>
                        <th class="px-5 py-3 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5 text-center min-w-[100px]">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="receiving-tbody" class="divide-y divide-white/5">
                <?php if (empty($received_items)): ?>
                    <tr>
                        <td colspan="<?= ($is_admin ? 10 : 9) + ($is_full_admin ? 1 : 0) + ($can_edit ? 1 : 0) ?>" class="px-5 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center gap-2 opacity-20">
                                <i class="fas fa-inbox text-4xl text-gray-500"></i>
                                <span class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">No records found</span>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $total_qty = 0;
                    foreach ($received_items as $r): 
                        $total_qty += $r['quantity'];
                    ?>
                        <tr class="hover:bg-white/5 transition-colors border-b border-white/5 last:border-0 group">
                        <td class="px-5 py-3.5 text-center" data-label="Select">
                            <input type="checkbox" name="received_ids[]" value="<?= $r['id'] ?>" class="record-checkbox rounded border-white/10 bg-slate-900 text-cyan-500 focus:ring-cyan-500/20">
                        </td>
                        <?php if ($is_admin): ?>
                            <td class="px-5 py-3.5 text-center" data-label="Store">
                                <div class="flex flex-col md:items-center items-end text-right md:text-center">
                                    <span class="font-bold text-gray-300 text-[11px]"><?= htmlspecialchars($r['store_code']) ?></span>
                                    <?php if (!empty($r['sname'])): ?>
                                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter mx-auto"><?= htmlspecialchars($r['sname']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                        
                        <td class="px-5 py-3.5 font-bold text-cyan-300 tracking-wide text-center" data-label="TF#"><?= htmlspecialchars($r['os_no']) ?></td>
                        <td class="px-5 py-3.5 text-center" data-label="Qty">
                            <span class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-400 font-black text-[11px]"><?= $r['quantity'] ?></span>
                        </td>
                        <td class="px-5 py-3.5 text-center" data-label="Source (From)">
                            <div class="flex flex-col md:items-center">
                                <span class="text-gray-300 font-bold text-xs uppercase"><?= htmlspecialchars($r['from_store'] ?: 'N/A') ?></span>
                                <?php if (!empty($r['from_sname'])): ?>
                                    <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter mx-auto"><?= htmlspecialchars($r['from_sname']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-center" data-label="Destination (To)">
                            <div class="flex flex-col md:items-center">
                                <span class="text-cyan-400 font-bold text-xs uppercase"><?= htmlspecialchars($r['to_store'] ?: 'N/A') ?></span>
                                <?php if (!empty($r['to_sname'])): ?>
                                    <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter mx-auto"><?= htmlspecialchars($r['to_sname']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-center" data-label="Username">
                            <div class="flex items-center gap-2 justify-center">
                                <div class="w-6 h-6 rounded-full bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-[10px] text-cyan-400 font-bold"><?= strtoupper($r['username'][0]) ?></div>
                                <span class="text-gray-400 text-xs font-medium"><?= htmlspecialchars($r['username']) ?></span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-center" data-label="Date Received" data-date="<?= date('Y-m-d', strtotime($r['created_at'])) ?>">
                            <div class="flex flex-col md:flex-row md:items-center justify-center gap-0.5 md:gap-1">
                                <span class="text-gray-300 text-[11px] font-medium whitespace-nowrap"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                            </div>
                        </td>
                        <?php if ($is_full_admin): ?>
                        <td class="px-5 py-3.5 text-center" data-label="Timestamp">
                            <?php if ($r['system_timestamp']): ?>
                                <span class="text-gray-400 text-[11px] font-medium whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($r['system_timestamp'])) ?></span>
                            <?php else: ?>
                                <span class="text-gray-500/50 text-[10px] uppercase font-bold tracking-widest italic">N/A</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="px-5 py-3.5 text-center" data-label="Status">
                            <?php if ($r['is_exported']): ?>
                                <div class="w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 mx-auto" title="Already Exported">
                                    <i class="fas fa-check-double text-[9px]"></i>
                                </div>
                            <?php else: ?>
                                <div class="w-6 h-6 rounded-lg bg-slate-500/10 border border-white/5 flex items-center justify-center text-gray-600 mx-auto" title="Pending Export">
                                    <i class="fas fa-clock text-[9px]"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php if ($can_edit): ?>
                            <td class="px-5 py-3.5 text-center" data-label="Actions">
                                <div class="flex items-center justify-center md:justify-center gap-2">
                                    <button onclick="editReceiving(<?= $r['id'] ?>)" class="w-7 h-7 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 transition-all flex items-center justify-center" title="Edit Record"><i class="fas fa-edit text-[10px]"></i></button>
                                    <?php if ($can_delete): ?>
                                    <button onclick="deleteReceiving(<?= $r['id'] ?>)" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-all flex items-center justify-center" title="Delete Record"><i class="fas fa-trash-alt text-[10px]"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="px-5 py-4 border-t border-white/5 bg-slate-800/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-col md:flex-row items-center gap-2 md:gap-4 text-center md:text-left">
            <span class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                Page <?= $page ?> of <?= $total_pages ?> <span class="mx-2 opacity-20">|</span> Result: <?= $total_rows ?> entries
            </span>
            
            <?php if (!empty($received_items)): ?>
                <div class="h-4 w-px bg-white/10 hidden md:block"></div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-tighter">Total Qty:</span>
                        <span class="text-[11px] font-black text-white"><?= number_format($total_qty) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-1">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <button data-page="<?= $i ?>" class="pagination-link w-7 h-7 rounded flex items-center justify-center text-[10px] font-bold transition-all <?= $i == $page ? 'bg-cyan-500 text-white shadow-lg shadow-cyan-500/20' : 'bg-white/5 text-gray-500 hover:bg-white/10' ?>">
                    <?= $i ?>
                </button>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
function runExportReceivingFn(type) {
    const hasData = document.querySelectorAll('.record-checkbox').length > 0;
    const selected = Array.from(document.querySelectorAll('.record-checkbox:checked')).map(cb => cb.value);
    
    if (!hasData && selected.length === 0) {
        if (typeof showStatusModal === 'function') {
            showStatusModal(false, 'No data available to export.', 'Export Failed');
        } else {
            alert('No data available to export.');
        }
        return;
    }

    const loader = document.getElementById('loading-overlay');
    const search = document.querySelector('[name="search"]')?.value || '';
    const start_date = document.querySelector('[name="start_date"]')?.value || '';
    const end_date = document.querySelector('[name="end_date"]')?.value || '';
    const store_filter = document.querySelector('[name="store_filter"]')?.value || '';

    let url = `api/export_receiving.php?type=${type}&search=${encodeURIComponent(search)}&start_date=${start_date}&end_date=${end_date}&store_filter=${store_filter}&ids=${selected.join(',')}`;
    
    if (typeof openGlobalFilenameModal === 'function') {
        openGlobalFilenameModal(type, 'receiving_data', function(filename) {
            if (filename) url += '&filename=' + encodeURIComponent(filename);
            
            if (loader) {
                const p = loader.querySelector('p');
                if (p) p.textContent = 'Preparing ' + type.toUpperCase() + ' File...';
                loader.classList.remove('opacity-0', 'pointer-events-none');
            }
            
            setTimeout(() => {
                window.location.href = url;
                setTimeout(() => {
                    if (loader) loader.classList.add('opacity-0', 'pointer-events-none');
                    if (typeof showStatusModal === 'function') {
                        showStatusModal(true, 'Receiving data has been exported successfully!', 'Export Success');
                    }
                    if (typeof refreshReceivingTable === 'function') refreshReceivingTable();
                    else if (typeof window.refreshReceivingTable === 'function') window.refreshReceivingTable();
                }, 3000);
            }, 800);
        });
    }
}
window.runExportReceiving = runExportReceivingFn;
</script>
