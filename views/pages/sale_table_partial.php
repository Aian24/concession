<?php
if (!isset($can_delete)) {
    $role           = $_SESSION['role'] ?? 'user';
    $is_full_admin  = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view  = ($role === 'admin_view');
    $is_store_admin = ($role === 'store_admin');
    $is_multi_store_admin = ($role === 'multi_store_admin');
    $is_admin       = ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);
    $can_edit       = ($is_full_admin || $is_admin_view || $is_multi_store_admin);
    $can_delete     = ($is_full_admin);
}
// Calculate Grand Totals based on current filters
$grand_total_qty = 0;
$grand_total_amount = 0;
$affected_stores_count = 0;
$affected_stores_list = '';

if (isset($where) && isset($params) && isset($types)) {
    $sum_stmt = $db->prepare("SELECT SUM(s.quantity), SUM(s.line_total), COUNT(DISTINCT s.store_code), GROUP_CONCAT(DISTINCT CONCAT(s.store_code, COALESCE(CONCAT(' (', sc.sname, ')'), '')) SEPARATOR '||') FROM sales s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci $where");
    if (!empty($params)) $sum_stmt->bind_param($types, ...$params);
    $sum_stmt->execute();
    $sum_res = $sum_stmt->get_result()->fetch_row();
    $grand_total_qty = $sum_res[0] ?? 0;
    $grand_total_amount = $sum_res[1] ?? 0;
    $affected_stores_count = $sum_res[2] ?? 0;
    $affected_raw = $sum_res[3] ?? '';
    
    // Build styled HTML for affected stores
    $affected_stores_html = '';
    if ($affected_raw) {
        $arr = explode('||', $affected_raw);
        $styled_arr = [];
        foreach ($arr as $store_str) {
            $parts = explode(' ', $store_str, 2);
            if (count($parts) === 2) {
                $styled_arr[] = "<span class='text-white font-bold'>" . htmlspecialchars($parts[0]) . "</span> <span class='opacity-75'>" . htmlspecialchars($parts[1]) . "</span>";
            } else {
                $styled_arr[] = "<span class='text-white font-bold'>" . htmlspecialchars($store_str) . "</span>";
            }
        }
        $affected_stores_html = implode('', array_map(function($html) {
            return "<div class='bg-white/5 rounded px-2 py-1.5 truncate text-left border border-white/5' title='".htmlspecialchars(strip_tags($html))."'>" . $html . "</div>";
        }, $styled_arr));
    }
    
    $sum_stmt->close();
}

$display_store_title = "Stores Affected";
$display_store_label = $affected_stores_count . " Stores";

if (!empty($store_filter)) {
    $display_store_label = $store_filter;
    $display_store_title = "Store Filter";
} elseif (!$is_admin && !$is_multi_store_admin) {
    $display_store_label = ($sale_store_code ?? $_SESSION['store_code'] ?? 'Unknown');
    $display_store_title = "Store";
} elseif ($is_store_admin) {
    $display_store_label = ($sale_store_code ?? $_SESSION['store_code'] ?? 'Unknown');
    $display_store_title = "Store";
} elseif ($affected_stores_count == 1) {
    $display_store_label = $affected_stores_list;
    $display_store_title = "Store Affected";
}

$missing_stores_count = 0;
$missing_stores_list = '';
$is_single_day = (!empty($start_date) && !empty($end_date) && $start_date === $end_date);

if ($is_single_day && ($is_admin || $is_multi_store_admin) && empty($store_filter)) {
    $missing_sql = "SELECT sc.scode, sc.sname 
                    FROM storecode sc 
                    WHERE sc.scode NOT IN (
                        SELECT DISTINCT s.store_code 
                        FROM sales s 
                        WHERE s.created_at >= ? AND s.created_at <= ?
                    )
                    AND sc.scode NOT IN ('HO', 'HEADOFFICE', 'HEAD OFFICE')
                    AND (sc.sname IS NULL OR (sc.sname NOT LIKE '%Head Office%' AND sc.sname NOT LIKE '%HO%'))";
    $m_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    $m_types = "ss";

    if ($is_multi_store_admin) {
        $assigned = $_SESSION['assigned_stores'] ?? [];
        $missing_sql .= build_multi_store_clause('sc.scode', $assigned);
    }
    
    $missing_stmt = $db->prepare($missing_sql);
    $missing_stmt->bind_param($m_types, ...$m_params);
    $missing_stmt->execute();
    $m_res = $missing_stmt->get_result();
    
    $missing_arr = [];
    while ($row = $m_res->fetch_assoc()) {
        $missing_arr[] = "<span class='text-white font-bold'>" . htmlspecialchars($row['scode']) . "</span>" . ($row['sname'] ? " <span class='opacity-75'>(" . htmlspecialchars($row['sname']) . ")</span>" : "");
    }
    $missing_stmt->close();
    
    $missing_stores_count = count($missing_arr);
    $missing_stores_html = implode('', array_map(function($html) {
        return "<div class='bg-red-500/10 rounded px-2 py-1.5 truncate text-left border border-red-500/10' title='".htmlspecialchars(strip_tags($html))."'>" . $html . "</div>";
    }, $missing_arr));
}
?>
<style>
    @media (max-width: 768px) {
        #submitted-history-table thead { display: none; }
        #submitted-history-table, #submitted-history-table tbody { display: block; width: 100%; }
        #submitted-history-table tr { 
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
        #submitted-history-table td { 
            display: flex; 
            flex-direction: column;
            justify-content: flex-start; 
            align-items: flex-start; 
            padding: 0; 
            border: none; 
            white-space: normal;
            min-width: 0;
            text-align: left !important;
        }
        #submitted-history-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        /* Layout: Strict 3 items per row */
        #submitted-history-table td { 
            grid-column: span 1 !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Specific Cell Alignments */
        #submitted-history-table td[data-label="Select"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: auto;
            border: none;
            padding: 0;
            z-index: 10;
        }
        #submitted-history-table td[data-label="Select"]::before { display: none; }
        
        /* Ensure content doesn't overflow and text is readable */
        #submitted-history-table td span, 
        #submitted-history-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        #submitted-history-table td .flex-col { align-items: flex-start !important; text-align: left !important; }
        #submitted-history-table td .mx-auto { margin-left: 0 !important; }
        #submitted-history-table tr:first-child { margin-top: 1.5rem; }
        
        /* Hide avatar in mobile */
        #submitted-history-table td[data-label="Submitted By"] .flex span:first-child { display: none; }
    }
</style>

<div class="glass-panel border border-white/5 shadow-xl rounded-2xl mt-6" id="submitted-sales-section">
    <div class="p-5 border-b border-white/5 bg-slate-800/30 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="flex items-center gap-2.5">
                    <i class="fas fa-history text-green-400 text-sm"></i>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase whitespace-nowrap">Submitted Sales</h3>
                </div>
                
                <!-- Grand Totals Badges -->
                <div class="hidden lg:block w-px h-8 bg-white/10"></div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 pb-1 -mb-1 w-full">
                    <div class="shrink-0 bg-slate-900/50 border border-white/5 rounded-lg px-3 py-1.5 flex flex-col justify-center shadow-inner">
                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-widest leading-none mb-1">Total Sales</span>
                        <span class="text-sm font-black text-green-400 leading-none">₱<?= number_format($grand_total_amount, 2) ?></span>
                    </div>
                    <div class="shrink-0 bg-slate-900/50 border border-white/5 rounded-lg px-3 py-1.5 flex flex-col justify-center shadow-inner">
                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-widest leading-none mb-1">Total Qty</span>
                        <span class="text-sm font-black text-white leading-none"><?= number_format($grand_total_qty) ?></span>
                    </div>
                    <div class="shrink-0 relative group bg-slate-900/50 border border-white/5 rounded-lg px-3 py-1.5 flex flex-col justify-center shadow-inner cursor-default">
                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-widest leading-none mb-1"><?= htmlspecialchars($display_store_title) ?></span>
                        <span class="text-sm font-bold text-gray-300 leading-none"><?= htmlspecialchars($display_store_label) ?></span>
                        
                        <?php if (!empty($affected_stores_html) && $display_store_title !== 'Store Filter' && $display_store_title !== 'Store Affected'): ?>
                        <!-- Custom Tooltip -->
                        <div class="absolute top-full left-1/2 -translate-x-1/2 mt-3 min-w-[400px] max-w-xl w-max p-4 bg-slate-900 border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-[100] pointer-events-none">
                            <h4 class="text-xs font-black text-white uppercase tracking-widest mb-3 border-b border-white/5 pb-2">Affected Stores</h4>
                            <div class="grid grid-cols-3 gap-2 text-[11px] text-gray-400 font-medium">
                                <?= $affected_stores_html ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($missing_stores_count > 0): ?>
                    <div class="shrink-0 relative group bg-red-500/10 border border-red-500/20 rounded-lg px-3 py-1.5 flex flex-col justify-center shadow-inner cursor-default">
                        <span class="text-[9px] text-red-400 font-bold uppercase tracking-widest leading-none mb-1">No Submissions</span>
                        <span class="text-sm font-bold text-red-300 leading-none"><?= $missing_stores_count ?> Stores</span>

                        <!-- Custom Tooltip -->
                        <div class="absolute top-full left-1/2 -translate-x-1/2 mt-3 min-w-[400px] max-w-xl w-max p-4 bg-[#1e0f14] border border-red-500/20 rounded-xl shadow-[0_20px_50px_rgba(220,38,38,0.2)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-[100] pointer-events-none">
                            <h4 class="text-xs font-black text-red-400 uppercase tracking-widest mb-3 border-b border-red-500/10 pb-2">Stores with No Submissions</h4>
                            <div class="grid grid-cols-3 gap-2 text-[11px] text-gray-400 font-medium">
                                <?= $missing_stores_html ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
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
                    <?php if ($can_delete): ?>
                    <button id="bulk-delete-sales" onclick="bulkDeleteSales()" class="hidden h-8 flex items-center justify-center px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all mr-2">
                        <i class="fas fa-trash-alt mr-2"></i> DELETE SELECTED
                    </button>
                    <?php endif; ?>
                    <button onclick="runExportSales('csv')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-green-500/10 hover:bg-green-500/20 text-green-400 border border-green-500/20 text-[10px] font-black tracking-widest transition-all">CSV</button>
                    <button onclick="runExportSales('xls')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-[10px] font-black tracking-widest transition-all">XLS</button>
                    <button onclick="runExportSales('txt')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 text-[10px] font-black tracking-widest transition-all">TXT</button>
                </div>
            </div>
        </div>

        <!-- Live Filters Grid -->
        <style>
            input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
        </style>
        <div class="grid grid-cols-2 <?= $is_admin ? 'lg:grid-cols-4' : 'lg:grid-cols-3' ?> gap-3 bg-white/5 p-3 rounded-xl border border-white/5">
            <div class="space-y-1">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Search</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Item # or User..." 
                           class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-[10px] text-white focus:outline-none focus:border-green-500/50">
                </div>
            </div>

            <?php if ($is_full_admin || $is_admin_view || $is_multi_store_admin): ?>
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

                $stores_sql = "SELECT s.store_code, sc.sname, SUM(s.quantity) as total_qty, SUM(s.line_total) as total_amount 
                               FROM sales s 
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

                // Find current selected label
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
                <div id="store-filter-trigger" class="w-full bg-slate-900/80 border border-white/10 rounded-lg px-3 py-1.5 h-8 text-xs text-white flex items-center justify-between cursor-pointer focus:border-purple-500/50 transition-all hover:bg-white/5">
                    <span id="selected-store-label" class="truncate font-bold opacity-80"><?= htmlspecialchars($current_label) ?></span>
                    <i class="fas fa-chevron-down text-[9px] text-gray-500 ml-2"></i>
                </div>

                <!-- Custom Menu -->
                <div id="store-filter-menu" class="absolute top-[calc(100%+4px)] right-0 min-w-[280px] w-full bg-[#0f172a] border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto overflow-x-hidden backdrop-blur-xl">
                    <div class="sticky top-0 bg-[#0f172a] p-2 border-b border-white/5 z-20">
                        <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-[10px] text-white focus:outline-none focus:border-purple-500/50" placeholder="Search store..." autocomplete="off">
                    </div>
                    <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $store_filter === '' ? 'bg-purple-500/10' : '' ?>" data-value="">
                        <span class="font-bold">All Stores</span>
                    </div>
                    <?php foreach($stores_data as $st): 
                        $sel = ($store_filter == $st['store_code']);
                        $displayName = $st['store_code'] . ($st['sname'] ? " - " . $st['sname'] : "");
                        $qty = number_format($st['total_qty']);
                        $amt = number_format($st['total_amount'], 2);
                    ?>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $sel ? 'bg-purple-500/10' : '' ?>" 
                             data-value="<?= htmlspecialchars($st['store_code']) ?>" 
                             data-label="<?= htmlspecialchars($displayName) ?>">
                            <div class="flex flex-col min-w-0 flex-1 mr-4">
                                <span class="font-bold truncate"><?= htmlspecialchars($st['store_code']) ?></span>
                                <?php if ($st['sname']): ?>
                                    <span class="text-[9px] text-gray-500 truncate uppercase tracking-tighter"><?= htmlspecialchars($st['sname']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Total Qty: <span class="text-emerald-400 font-black ml-1"><?= $qty ?></span></span>
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight mt-0.5">Total Amount: <span class="text-emerald-400 font-black ml-1">₱<?= $amt ?></span></span>
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
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" onclick="this.showPicker()"
                       class="w-full bg-slate-900/80 border border-white/10 rounded-lg px-3 py-1.5 h-8 text-xs text-white focus:outline-none cursor-pointer">
            </div>

            <div class="space-y-1">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">To Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" onclick="this.showPicker()"
                       class="w-full bg-slate-900/80 border border-white/10 rounded-lg px-3 py-1.5 h-8 text-xs text-white focus:outline-none cursor-pointer">
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto min-h-[300px]">
        <table class="w-full text-left border-collapse glass-table whitespace-nowrap" id="submitted-history-table">
            <thead>
                <tr>
                    <th class="px-5 py-3 w-10 text-center">
                        <input type="checkbox" id="selectAll" class="rounded border-white/20 bg-slate-900 text-green-500 focus:ring-offset-slate-900">
                    </th>
                    <?php if ($is_admin): ?>
                        <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Store</th>
                    <?php endif; ?>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Item #</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Amount Sold</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Qty</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Line Total</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Submitted By</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Date</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Status</th>
                    <?php if ($can_edit): ?>
                        <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center min-w-[100px]">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="submitted-tbody" class="text-sm">
                <?php if (empty($submitted_sales)): ?>
                <tr>
                    <td colspan="<?= $is_admin ? 10 : 8 ?>" class="px-5 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center gap-2 opacity-20">
                            <i class="fas fa-inbox text-4xl text-gray-500"></i>
                            <span class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">No records found</span>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php 
                    $total_qty = 0;
                    $total_line_total = 0;
                    foreach ($submitted_sales as $s): 
                        $total_qty += $s['quantity'];
                        $total_line_total += $s['line_total'];
                    ?>
                    <tr class="hover:bg-white/5 transition-colors border-b border-white/5 last:border-0 border-r border-transparent hover:border-r-green-500/50">
                        <td class="px-5 py-3.5 text-center" data-label="Select">
                            <input type="checkbox" name="sale_ids[]" value="<?= $s['id'] ?>" class="sale-checkbox rounded border-white/20 bg-slate-900 text-green-500 focus:ring-offset-slate-900">
                        </td>
                        <?php if ($is_admin): ?>
                            <td class="px-5 py-3.5 text-center" data-label="Store">
                                <div class="flex flex-col md:items-center items-end text-right md:text-center">
                                    <span class="font-bold text-gray-400 text-[11px]"><?= htmlspecialchars($s['store_code']) ?></span>
                                    <?php if (!empty($s['sname'])): ?>
                                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter truncate max-w-[120px] mx-auto md:mx-0"><?= htmlspecialchars($s['sname']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                        <td class="px-5 py-3.5 font-bold text-purple-300 tracking-wide text-center" data-label="Item #">
                            <?= htmlspecialchars($s['item_no']) ?>
                        </td>
                        <td class="px-5 py-3.5 text-emerald-400 font-black text-center" data-label="Amount Sold">₱<?= number_format($s['amount_sold'], 2) ?></td>
                        <td class="px-5 py-3.5 text-gray-300 font-bold text-center" data-label="Qty"><?= $s['quantity'] ?></td>
                        <td class="px-5 py-3.5 text-emerald-300 font-black text-center" data-label="Line Total">₱<?= number_format($s['line_total'], 2) ?></td>
                        <td class="px-5 py-3.5 text-center" data-label="Submitted By">
                            <span class="flex items-center justify-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-gradient-to-tr from-purple-600/20 to-pink-600/20 border border-white/10 flex items-center justify-center text-[10px] text-white font-bold"><?= strtoupper($s['username'][0]) ?></span>
                                <span class="text-gray-300 text-xs"><?= htmlspecialchars($s['username']) ?></span>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-center" data-label="Date" data-date="<?= date('Y-m-d', strtotime($s['created_at'])) ?>">
                            <div class="flex flex-col md:flex-row md:items-center justify-center gap-0.5 md:gap-1">
                                <span class="text-gray-300 text-[11px] font-medium whitespace-nowrap"><?= date('M d, Y', strtotime($s['created_at'])) ?></span>
                                <span class="hidden md:inline text-gray-500 font-bold">•</span>
                                <span class="text-gray-400 text-[9px] md:text-[11px] font-bold whitespace-nowrap"><?= date('h:i A', strtotime($s['created_at'])) ?></span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-center" data-label="Status">
                            <?php if ($s['is_exported']): ?>
                                <div class="w-6 h-6 rounded-lg bg-green-500/10 border border-green-500/20 flex items-center justify-center text-green-400 mx-auto" title="Already Exported">
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
                                    <button onclick="editSale(<?= $s['id'] ?>, '<?= htmlspecialchars($s['store_code']) ?>')" class="w-7 h-7 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 transition-all flex items-center justify-center" title="Edit Record"><i class="fas fa-edit text-[10px]"></i></button>
                                    <?php if ($can_delete): ?>
                                    <button onclick="deleteSale(<?= $s['id'] ?>)" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-all flex items-center justify-center" title="Delete Record"><i class="fas fa-trash-alt text-[10px]"></i></button>
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

    <!-- Pagination: Always Visible -->
    <div class="px-5 py-4 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-800/10">
        <div class="flex flex-col md:flex-row items-center gap-2 md:gap-4 text-center md:text-left">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Page <?= $page ?> of <?= $total_pages ?> <span class="mx-2 opacity-30">|</span> Result: <?= $total_rows ?> entries</span>
            
            <?php if (!empty($submitted_sales)): ?>
                <div class="h-4 w-px bg-white/10 hidden md:block"></div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-tighter">Total Qty:</span>
                        <span class="text-[11px] font-black text-white"><?= number_format($total_qty) ?></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-tighter">Total Amount:</span>
                        <span class="text-[11px] font-black text-emerald-400">₱<?= number_format($total_line_total, 2) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="flex items-center gap-1">
            <a href="#" data-page="1" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-[10px]" title="First Page"><i class="fas fa-angle-double-left"></i></a>
            
            <?php if ($page > 1): ?>
                <a href="#" data-page="<?= $page - 1 ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-xs"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++): 
            ?>
                <a href="#" data-page="<?= $i ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg text-xs font-black transition-all <?= $i == $page ? 'bg-green-600 text-white shadow-lg shadow-green-600/30' : 'bg-white/5 text-gray-500 hover:text-white' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="#" data-page="<?= $page + 1 ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-xs"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>

            <a href="#" data-page="<?= $total_pages ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-[10px]" title="Last Page"><i class="fas fa-angle-double-right"></i></a>
        </div>
    </div>
</div>
<script>
function runExportSales(type) {
    // Check if there are results in the table
    const tbody = document.getElementById('submitted-tbody');
    const hasData = tbody && !tbody.innerText.includes('No records found');
    const selectedIds = Array.from(document.querySelectorAll('.sale-checkbox:checked')).map(cb => cb.value);

    if (!hasData && selectedIds.length === 0) {
        if (typeof showStatusModal === 'function') {
            showStatusModal(false, 'No data available to export. Please adjust your filters.');
        } else {
            alert('No data available to export.');
        }
        return;
    }
    
    const search      = document.querySelector('[name="search"]')?.value || '';
    const startDate   = document.querySelector('[name="start_date"]')?.value || '';
    const endDate     = document.querySelector('[name="end_date"]')?.value || '';
    const limit       = document.querySelector('[name="limit"]')?.value || 100;
    const storeFilter = document.querySelector('[name="store_filter"]')?.value || '';
    
    const loader = document.getElementById('loading-overlay');
    let url = `api/export_submitted.php?type=${type}`;

    if (selectedIds.length > 0) {
        url += '&ids=' + selectedIds.join(',');
    } else {
        url += `&search=${encodeURIComponent(search)}`;
        url += `&start_date=${encodeURIComponent(startDate)}`;
        url += `&end_date=${encodeURIComponent(endDate)}`;
        url += `&limit=${limit}`;
        url += `&store_filter=${encodeURIComponent(storeFilter)}`;
    }
    
    if (typeof openGlobalFilenameModal === 'function') {
        openGlobalFilenameModal(type, 'submitted_sales', function(filename) {
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
                        showStatusModal(true, 'Sales data has been exported successfully!', 'Export Success');
                    }
                    if (typeof refreshTable === 'function') refreshTable();
                    else if (typeof window.refreshSaleTable === 'function') window.refreshSaleTable();
                }, 3000);
            }, 1000);
        });
    }
}
</script>
