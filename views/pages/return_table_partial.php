<?php
if (!isset($can_delete)) {
    $role       = $_SESSION['role'] ?? 'user';
    $is_full_admin  = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view  = ($role === 'admin_view');
    $is_store_admin = ($role === 'store_admin');
    $is_multi_store_admin = ($role === 'multi_store_admin');
    $is_admin       = ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);
    $can_edit   = ($is_full_admin || $is_admin_view || $is_multi_store_admin);
    $can_delete = ($is_full_admin);
}
?>
<style>
    @media (max-width: 768px) {
        #return-history-table thead { display: none; }
        #return-history-table, #return-history-table tbody { display: block; width: 100%; }
        #return-history-table tr { 
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
        #return-history-table tr:first-child { margin-top: 1.5rem; }
        #return-history-table td { 
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
        #return-history-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #return-history-table td[data-label="Select"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: auto;
            border: none;
            padding: 0;
            z-index: 10;
        }
        #return-history-table td[data-label="Select"]::before { display: none; }
        
        #return-history-table td span, 
        #return-history-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        #return-history-table td .flex-col { align-items: flex-start !important; text-align: left !important; }
        #return-history-table td .mx-auto { margin-left: 0 !important; }
        #return-history-table td[data-label="User"] .flex span:first-child { display: none; }
    }
</style>

<div class="glass-panel border border-white/5 shadow-xl overflow-hidden mt-6" id="history-section">
    <div class="p-5 border-b border-white/5 bg-slate-800/30 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-history text-orange-400 text-sm"></i>
                <h3 class="text-sm font-bold text-white tracking-wide uppercase">Return History</h3>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 mr-2">
                    <span class="text-[10px] text-gray-500 font-bold uppercase mr-1">Show</span>
                    <select name="limit" class="bg-slate-900 border border-white/10 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none">
                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 || empty($limit) ? 'selected' : '' ?>>100</option>
                    </select>
                </div>

                <div class="flex items-center gap-1.5">
                    <button id="bulk-delete-returns" onclick="bulkDeleteReturns()" class="hidden h-8 flex items-center justify-center px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all mr-2">
                        <i class="fas fa-trash-alt mr-2"></i> DELETE SELECTED
                    </button>
                    <button onclick="runExportReturns('csv')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-orange-500/10 hover:bg-orange-500/20 text-orange-400 border border-orange-500/20 text-[10px] font-black tracking-widest transition-all">CSV</button>
                    <button onclick="runExportReturns('xls')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-[10px] font-black tracking-widest transition-all">XLS</button>
                    <button onclick="runExportReturns('txt')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 text-[10px] font-black tracking-widest transition-all">TXT</button>
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
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Filter by Item or User..." 
                           class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-[10px] text-white focus:outline-none focus:border-orange-500/50">
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

                $stores_sql = "SELECT s.store_code, sc.sname, SUM(s.quantity) as total_qty, SUM(COALESCE(s.return_amount,0) + COALESCE(s.exchange_amount,0)) as total_amount 
                               FROM returns s 
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
                <div id="store-filter-trigger" class="w-full bg-slate-900/80 border border-white/10 rounded-lg px-3 py-1.5 h-8 text-xs text-white flex items-center justify-between cursor-pointer focus:border-orange-500/50 transition-all hover:bg-white/5">
                    <span id="selected-store-label" class="truncate font-bold opacity-80"><?= htmlspecialchars($current_label) ?></span>
                    <i class="fas fa-chevron-down text-[9px] text-gray-500 ml-2"></i>
                </div>

                <!-- Custom Menu -->
                <div id="store-filter-menu" class="absolute top-[calc(100%+4px)] right-0 min-w-[280px] w-full bg-[#0f172a] border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto overflow-x-hidden backdrop-blur-xl">
                    <div class="sticky top-0 bg-[#0f172a] p-2 border-b border-white/5 z-20">
                        <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-[10px] text-white focus:outline-none focus:border-orange-500/50" placeholder="Search store..." autocomplete="off">
                    </div>
                    <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $store_filter === '' ? 'bg-orange-500/10' : '' ?>" data-value="">
                        <span class="font-bold">All Stores</span>
                    </div>
                    <?php foreach($stores_data as $st): 
                        $sel = ($store_filter == $st['store_code']);
                        $displayName = $st['store_code'] . ($st['sname'] ? " - " . $st['sname'] : "");
                        $qty = number_format($st['total_qty'] ?? 0);
                        $amt = number_format($st['total_amount'] ?? 0, 2);
                    ?>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $sel ? 'bg-orange-500/10' : '' ?>" 
                             data-value="<?= htmlspecialchars($st['store_code']) ?>" 
                             data-label="<?= htmlspecialchars($displayName) ?>">
                            <div class="flex flex-col min-w-0 flex-1 mr-4">
                                <span class="font-bold truncate"><?= htmlspecialchars($st['store_code']) ?></span>
                                <?php if ($st['sname']): ?>
                                    <span class="text-[9px] text-gray-500 truncate uppercase tracking-tighter"><?= htmlspecialchars($st['sname']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Total Qty: <span class="text-orange-400 font-black ml-1"><?= $qty ?></span></span>
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight mt-0.5">Total Amount: <span class="text-orange-400 font-black ml-1">₱<?= $amt ?></span></span>
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
    
    <div class="overflow-x-auto min-h-[300px] w-full">
        <table class="w-full text-left border-collapse glass-table" id="return-history-table">
            <thead>
                <tr>
                    <th class="px-2 py-3 w-10 text-center">
                        <input type="checkbox" id="selectAll" class="rounded border-white/20 bg-slate-900 text-orange-500">
                    </th>
                    <?php if ($is_admin): ?>
                        <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Store</th>
                    <?php endif; ?>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Returned Item</th>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Qty</th>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Amt</th>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center max-w-[150px]">Reason</th>
                    <th class="px-2 py-3 font-bold text-blue-400/80 text-[10px] tracking-widest uppercase italic text-center">Exchange</th>
                    <th class="px-2 py-3 font-bold text-blue-400/80 text-[10px] tracking-widest uppercase italic text-center">Ex. Item #</th>
                    <th class="px-2 py-3 font-bold text-emerald-400/80 text-[10px] tracking-widest uppercase italic text-center">Ex. Amt</th>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Total</th>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">User</th>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Date</th>
                    <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Status</th>
                    <?php if ($can_edit): ?>
                        <th class="px-2 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center min-w-[70px]">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="history-tbody" class="text-sm">
                <?php if (empty($submitted_records)): ?>
                <tr>
                    <td colspan="<?= $is_admin ? 14 : 12 ?>" class="px-5 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center gap-2 opacity-20">
                            <i class="fas fa-inbox text-4xl text-gray-500"></i>
                            <span class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">No records found</span>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php 
                    $total_qty = 0;
                    $total_amount = 0;
                    foreach ($submitted_records as $r): 
                        $row_qty = ($r['quantity'] ?? 0) ?: ($r['exchange_quantity'] ?? 0);
                        $total_qty += $row_qty;
                        $net = ($r['exchange_amount'] ?? 0) + ($r['return_amount'] ?? 0);
                        $total_amount += $net;
                    ?>
                    <tr class="hover:bg-white/5 transition-colors border-b border-white/5 last:border-0">
                        <td class="px-2 py-3 text-center" data-label="Select">
                            <input type="checkbox" value="<?= $r['id'] ?>" class="record-checkbox rounded border-white/20 bg-slate-900 text-orange-500">
                        </td>
                        <?php if ($is_admin): ?>
                            <td class="px-2 py-3 text-center" data-label="Store">
                                <div class="flex flex-col md:items-center items-end text-right md:text-center">
                                    <span class="font-bold text-gray-400 text-[11px]"><?= htmlspecialchars($r['store_code']) ?></span>
                                    <?php if (!empty($r['sname'])): ?>
                                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter truncate max-w-[120px] mx-auto"><?= htmlspecialchars($r['sname']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                        <td class="px-2 py-3 font-bold text-center" data-label="Returned Item">
                            <?php if ($r['return_item']): ?>
                                <span class="text-orange-300"><?= htmlspecialchars($r['return_item']) ?></span>
                            <?php else: ?>
                                <span class="text-blue-400 text-[10px] uppercase font-black tracking-widest italic">Exchange Only</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-3 text-gray-300 font-bold text-center" data-label="Qty"><?= $row_qty ?></td>
                        <td class="px-2 py-3 text-center" data-label="Amt">
                            <?php if ($r['return_amount'] != 0): ?>
                                <span class="text-red-400 font-black">₱<?= number_format(abs($r['return_amount']), 2) ?></span>
                            <?php else: ?>
                                <span class="text-gray-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="Reason">
                            <span class="text-gray-300 text-xs md:mx-auto whitespace-normal break-words break-all w-full min-w-[80px] max-w-[150px] inline-block" title="<?= htmlspecialchars($r['reason'] ?: '—') ?>">
                                <?= htmlspecialchars($r['reason'] ?: '—') ?>
                            </span>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="Exchange">
                            <?php if ($r['is_exchange']): ?>
                                <span class="text-blue-300 font-bold text-xs"><?= htmlspecialchars($r['exchange_name'] ?: '—') ?></span>
                            <?php else: ?>
                                <span class="text-gray-600 text-[10px]">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="Ex. Item #">
                            <?php if ($r['is_exchange']): ?>
                                <span class="text-blue-400 font-bold">#<?= htmlspecialchars($r['exchange_item'] ?: 'N/A') ?></span>
                            <?php else: ?>
                                <span class="text-gray-600 text-[10px]">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="Ex. Amt">
                            <?php if ($r['is_exchange'] && $r['exchange_amount'] > 0): ?>
                                <span class="text-emerald-400 font-black">₱<?= number_format($r['exchange_amount'], 2) ?></span>
                            <?php else: ?>
                                <span class="text-gray-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="Total">
                            <?php if ($net < 0): ?>
                                <span class="text-red-400 font-black">-₱<?= number_format(abs($net), 2) ?></span>
                            <?php elseif ($net > 0): ?>
                                <span class="text-emerald-400 font-black">₱<?= number_format($net, 2) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400 font-black">₱0.00</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="User">
                            <span class="flex items-center gap-2 justify-center">
                                <span class="hidden md:flex w-6 h-6 rounded-full bg-gradient-to-tr from-orange-600/20 to-amber-600/20 border border-white/10 items-center justify-center text-[10px] text-white font-bold"><?= strtoupper($r['username'][0]) ?></span>
                                <span class="text-gray-300 text-xs"><?= htmlspecialchars($r['username']) ?></span>
                            </span>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="Date" data-date="<?= date('Y-m-d', strtotime($r['created_at'])) ?>">
                            <div class="flex flex-col md:flex-row md:items-center justify-center gap-0.5 md:gap-1">
                                <span class="text-gray-300 text-[11px] font-medium whitespace-nowrap"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                <span class="hidden md:inline text-gray-500 font-bold">•</span>
                                <span class="text-gray-400 text-[9px] md:text-[11px] font-bold whitespace-nowrap"><?= date('h:i A', strtotime($r['created_at'])) ?></span>
                            </div>
                        </td>
                        <td class="px-2 py-3 text-center" data-label="Status">
                            <?php if ($r['is_exported']): ?>
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
                            <td class="px-2 py-3 text-center" data-label="Actions">
                                <div class="flex items-center justify-end md:justify-center gap-2">
                                    <button onclick="editReturn(<?= $r['id'] ?>)" class="w-7 h-7 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 transition-all flex items-center justify-center" title="Edit Record"><i class="fas fa-edit text-[10px]"></i></button>
                                    <?php if ($can_delete): ?>
                                    <button onclick="deleteReturn(<?= $r['id'] ?>)" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-all flex items-center justify-center" title="Delete Record"><i class="fas fa-trash-alt text-[10px]"></i></button>
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
    <div class="px-5 py-4 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-800/10">
        <div class="flex flex-col md:flex-row items-center gap-2 md:gap-4 text-center md:text-left">
            <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Page <?= $page ?> of <?= $total_pages ?> <span class="mx-2 opacity-20">|</span> Result: <?= $total_rows ?> entries</span>
            
            <?php if (!empty($submitted_records)): ?>
                <div class="h-4 w-px bg-white/10 hidden md:block"></div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-tighter">Total Qty:</span>
                        <span class="text-[11px] font-black text-white"><?= number_format($total_qty) ?></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-tighter">Total Amount:</span>
                        <span class="text-[11px] font-black <?= $total_amount < 0 ? 'text-red-400' : 'text-emerald-400' ?>">
                            <?= ($total_amount < 0 ? '-' : '') ?>₱<?= number_format(abs($total_amount), 2) ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="flex items-center gap-1">
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="#" data-page="<?= $i ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg text-xs font-black transition-all <?= $i == $page ? 'bg-orange-600 text-white shadow-lg' : 'bg-white/5 text-gray-500 hover:text-white' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
<script>
function runExportReturn(type) {
    const tbody = document.getElementById('history-tbody');
    const hasData = tbody && !tbody.innerText.includes('No records found');
    const selectedIds = Array.from(document.querySelectorAll('.record-checkbox:checked')).map(cb => cb.value);

    if (!hasData && selectedIds.length === 0) {
        if (typeof showStatusModal === 'function') {
            showStatusModal(false, 'No data available to export. Please adjust your filters.');
        } else {
            alert('No data available to export.');
        }
        return;
    }

    const loader = document.getElementById('loading-overlay');
    let url = `api/export_returns.php?type=${type}`;
    
    if (selectedIds.length > 0) {
        url += '&ids=' + selectedIds.join(',');
    } else {
        const search = document.querySelector('[name="search"]')?.value || '';
        const startDate = document.querySelector('[name="start_date"]')?.value || '';
        const endDate = document.querySelector('[name="end_date"]')?.value || '';
        const storeFilter = document.querySelector('[name="store_filter"]')?.value || '';
        
        url += `&search=${encodeURIComponent(search)}`;
        url += `&start_date=${encodeURIComponent(startDate)}`;
        url += `&end_date=${encodeURIComponent(endDate)}`;
        url += `&store_filter=${encodeURIComponent(storeFilter)}`;
    }

    if (typeof openGlobalFilenameModal === 'function') {
        openGlobalFilenameModal(type, 'returns_data', function(filename) {
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
                        showStatusModal(true, 'Returns data has been exported successfully!', 'Export Success');
                    }
                    if (typeof window.refreshReturnTable === 'function') window.refreshReturnTable();
                }, 3000);
            }, 1000);
        });
    }
}
window.runExportReturns = runExportReturn;
</script>
