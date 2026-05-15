<?php
if (!isset($can_delete)) {
    $role           = $_SESSION['role'] ?? 'user';
    $is_full_admin  = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view  = ($role === 'admin_view');
    $is_store_admin = ($role === 'store_admin');
    $is_admin       = ($is_full_admin || $is_admin_view || $is_store_admin);
    $can_edit       = ($is_full_admin || $is_admin_view);
    $can_delete     = ($is_full_admin);
}
?>
<style>
    @media (max-width: 768px) {
        #pullout-history-table thead { display: none; }
        #pullout-history-table, #pullout-history-table tbody { display: block; width: 100%; }
        #pullout-history-table tr { 
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
        #pullout-history-table tr:first-child { margin-top: 1.5rem; }
        #pullout-history-table td { 
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
        #pullout-history-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #pullout-history-table td[data-label="Select"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: auto;
            border: none;
            padding: 0;
            z-index: 10;
        }
        #pullout-history-table td[data-label="Select"]::before { display: none; }
        
        #pullout-history-table td span, 
        #pullout-history-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        #pullout-history-table td .flex-col { align-items: flex-start !important; text-align: left !important; }
        #pullout-history-table td .mx-auto { margin-left: 0 !important; }
        #pullout-history-table td[data-label="Submitted By"] .flex span:first-child { display: none; }
    }
</style>
<div class="glass-panel border border-white/5 shadow-xl overflow-hidden mt-6" id="pullout-section">
    <div class="p-5 border-b border-white/5 bg-slate-800/30 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-history text-amber-400 text-sm"></i>
                <h3 class="text-sm font-bold text-white tracking-wide uppercase">Pullout History</h3>
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
                    <?php if ($can_edit): ?>
                    <button id="bulk-delete-pullouts" onclick="bulkDeletePullouts()" class="hidden h-8 flex items-center justify-center px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-[10px] font-black tracking-widest transition-all mr-2">
                        <i class="fas fa-trash-alt mr-2"></i> DELETE SELECTED
                    </button>
                    <?php endif; ?>
                    <button onclick="runExportPullouts('csv')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 text-[10px] font-black tracking-widest transition-all">CSV</button>
                    <button onclick="runExportPullouts('xls')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-[10px] font-black tracking-widest transition-all">XLS</button>
                    <button onclick="runExportPullouts('txt')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 text-[10px] font-black tracking-widest transition-all">TXT</button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <style>
            input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
        </style>
        <div class="grid grid-cols-2 <?= $is_admin ? 'lg:grid-cols-4' : 'lg:grid-cols-3' ?> gap-3 bg-white/5 p-3 rounded-xl border border-white/5">
            <div class="space-y-1">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Search</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Item # or User..." 
                           class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-xs text-white focus:outline-none focus:border-amber-500/50">
                </div>
            </div>

            <?php if ($is_full_admin || $is_admin_view): ?>
            <div class="space-y-1 relative" id="store-filter-container">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Filter</label>
                
                <?php
                $q_params = [];
                $q_types = "";
                $q_where = "WHERE 1=1";

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
                               FROM pullouts s 
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
                <div id="store-filter-trigger" class="w-full bg-slate-900/80 border border-white/10 rounded-lg px-3 py-1.5 h-8 text-xs text-white focus:outline-none focus:border-amber-500/50 transition-all cursor-pointer flex items-center justify-between hover:bg-white/5">
                    <span id="selected-store-label" class="truncate font-bold opacity-80"><?= htmlspecialchars($current_label) ?></span>
                    <i class="fas fa-chevron-down text-[9px] text-gray-500 ml-2"></i>
                </div>

                <!-- Custom Menu -->
                <div id="store-filter-menu" class="absolute top-[calc(100%+4px)] left-0 right-0 bg-[#0f172a] border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto overflow-x-hidden backdrop-blur-xl">
                    <div class="sticky top-0 bg-[#0f172a] p-2 border-b border-white/5 z-20">
                        <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-[10px] text-white focus:outline-none focus:border-amber-500/50" placeholder="Search store..." autocomplete="off">
                    </div>
                    <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $store_filter === '' ? 'bg-amber-500/10' : '' ?>" data-value="">
                        <span class="font-bold">All Stores</span>
                    </div>
                    <?php foreach($stores_data as $st): 
                        $sel = ($store_filter == $st['store_code']);
                        $displayName = $st['store_code'] . ($st['sname'] ? " - " . $st['sname'] : "");
                        $qty = number_format($st['total_qty'] ?? 0);
                    ?>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $sel ? 'bg-amber-500/10' : '' ?>" 
                             data-value="<?= htmlspecialchars($st['store_code']) ?>" 
                             data-label="<?= htmlspecialchars($displayName) ?>">
                            <div class="flex flex-col truncate mr-4">
                                <span class="font-bold truncate"><?= htmlspecialchars($st['store_code']) ?></span>
                                <?php if ($st['sname']): ?>
                                    <span class="text-[9px] text-gray-500 truncate uppercase tracking-tighter"><?= htmlspecialchars($st['sname']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Total Qty: <span class="text-amber-400 font-black ml-1"><?= $qty ?></span></span>
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
        <table class="w-full text-left border-collapse glass-table whitespace-nowrap" id="pullout-history-table">
            <thead>
                <tr>
                    <th class="px-5 py-3 w-10 text-center">
                        <input type="checkbox" id="selectAllPullouts" class="rounded border-white/20 bg-slate-900 text-amber-500 focus:ring-offset-slate-900">
                    </th>
                    <?php if ($is_admin): ?>
                        <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase">Store</th>
                    <?php endif; ?>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase">Item #</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Qty</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Image</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase">Submitted By</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase">Date</th>
                    <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Status</th>
                    <?php if ($can_edit): ?>
                        <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="pullout-tbody" class="text-sm">
                <?php if (empty($submitted_pullouts)): ?>
                <tr>
                    <td colspan="<?= $is_admin ? 9 : 7 ?>" class="px-5 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center gap-2 opacity-20">
                            <i class="fas fa-inbox text-4xl text-gray-500"></i>
                            <span class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">No records found</span>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php 
                    $total_qty = 0;
                    foreach ($submitted_pullouts as $p): 
                        $total_qty += $p['quantity'];
                    ?>
                    <tr class="hover:bg-white/5 transition-colors border-b border-white/5 last:border-0">
                        <td class="px-5 py-3.5 text-center" data-label="Select">
                            <input type="checkbox" value="<?= $p['id'] ?>" class="pullout-checkbox rounded border-white/20 bg-slate-900 text-amber-500">
                        </td>
                        <?php if ($is_admin): ?>
                            <td class="px-5 py-3.5" data-label="Store">
                                <div class="flex flex-col md:items-start items-end text-right md:text-left">
                                    <span class="font-bold text-gray-400 text-[11px]"><?= htmlspecialchars($p['store_code']) ?></span>
                                    <?php if (!empty($p['sname'])): ?>
                                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter truncate max-w-[120px]"><?= htmlspecialchars($p['sname']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                        <td class="px-5 py-3.5 font-bold text-amber-300 tracking-wide" data-label="Item #"><?= htmlspecialchars($p['item_no']) ?></td>
                        <td class="px-5 py-3.5 text-gray-300 font-bold text-center" data-label="Qty"><?= $p['quantity'] ?></td>
                        <td class="px-5 py-3.5 text-center" data-label="Image">
                            <?php if ($p['image_path']): ?>
                                <button onclick="viewPulloutImage('<?= $p['image_path'] ?>')" class="w-10 h-10 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all border border-white/10 overflow-hidden group mx-auto md:mx-0">
                                    <img src="<?= $p['image_path'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform" alt="Proof">
                                </button>
                            <?php else: ?>
                                <span class="text-gray-600 text-[10px]">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5" data-label="Submitted By">
                            <span class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-gradient-to-tr from-amber-600/20 to-orange-600/20 border border-white/10 flex items-center justify-center text-[10px] text-white font-bold"><?= strtoupper($p['username'][0]) ?></span>
                                <span class="text-gray-300 text-xs"><?= htmlspecialchars($p['username']) ?></span>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-300 text-[11px] font-medium tracking-tight whitespace-nowrap" data-label="Date"><?= date('M d, Y • h:i A', strtotime($p['created_at'])) ?></td>
                        <td class="px-5 py-3.5 text-center" data-label="Status">
                            <?php if ($p['is_exported']): ?>
                                <div class="w-6 h-6 rounded-lg bg-green-500/10 border border-green-500/20 flex items-center justify-center text-green-400 mx-auto md:mx-0" title="Already Exported">
                                    <i class="fas fa-check-double text-[9px]"></i>
                                </div>
                            <?php else: ?>
                                <div class="w-6 h-6 rounded-lg bg-slate-500/10 border border-white/5 flex items-center justify-center text-gray-600 mx-auto md:mx-0" title="Pending Export">
                                    <i class="fas fa-clock text-[9px]"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php if ($can_edit): ?>
                            <td class="px-5 py-3.5 text-center" data-label="Actions">
                                <div class="flex items-center justify-center md:justify-center gap-2">
                                    <button onclick="editPullout(<?= $p['id'] ?>)" class="w-7 h-7 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 transition-all flex items-center justify-center"><i class="fas fa-edit text-[10px]"></i></button>
                                    <button onclick="deletePullout(<?= $p['id'] ?>)" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-all flex items-center justify-center"><i class="fas fa-trash-alt text-[10px]"></i></button>
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
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Page <?= $page ?> of <?= $total_pages ?> <span class="mx-2 opacity-20">|</span> Result: <?= $total_rows ?> entries</span>
            
            <?php if (!empty($submitted_pullouts)): ?>
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
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <button onclick="refreshTable(<?= $i ?>)" class="w-7 h-7 flex items-center justify-center rounded-lg text-xs font-black transition-all <?= $i == $page ? 'bg-amber-600 text-white shadow-lg' : 'bg-white/5 text-gray-500 hover:text-white' ?>"><?= $i ?></button>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div id="image-viewer-modal" class="fixed inset-0 z-[200] flex items-center justify-center hidden opacity-0 transition-all duration-300">
    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-xl" onclick="closeImageViewer()"></div>
    <div class="relative max-w-[90vw] max-h-[90vh] flex flex-col items-center">
        <button onclick="closeImageViewer()" class="absolute -top-12 right-0 w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-all">
            <i class="fas fa-times"></i>
        </button>
        <img id="modal-view-image" src="#" class="max-w-full max-h-[80vh] object-contain rounded-2xl shadow-2xl border border-white/10" alt="Full Image">
        <div class="mt-4 px-6 py-2 rounded-full bg-white/5 border border-white/5 text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Proof of Pullout</div>
    </div>
</div>

<script>
function initTableEvents() {
    const selectAll = document.getElementById('selectAllPullouts');
    const updateBulkVisibility = () => {
        const btn = document.getElementById('bulk-delete-pullouts');
        if (!btn) return;
        const checkedCount = document.querySelectorAll('.pullout-checkbox:checked').length;
        btn.classList.toggle('hidden', checkedCount === 0);
    };

    selectAll?.addEventListener('change', function() {
        document.querySelectorAll('.pullout-checkbox').forEach(cb => cb.checked = this.checked);
        updateBulkVisibility();
    });

    document.getElementById('pullout-tbody')?.addEventListener('change', (e) => {
        if (e.target.classList.contains('pullout-checkbox')) {
            updateBulkVisibility();
        }
    });

    // Auto-refresh on filters change
    const filters = ['search', 'limit', 'start_date', 'end_date', 'store_filter'];
    filters.forEach(name => {
        document.querySelector(`[name="${name}"]`)?.addEventListener('change', () => refreshTable(1));
        if (name === 'search') {
            let timer;
            document.querySelector(`[name="${name}"]`)?.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => refreshTable(1), 500);
            });
        }
    });
}

window.viewPulloutImage = function(path) {
    const modal = document.getElementById('image-viewer-modal');
    const img = document.getElementById('modal-view-image');
    img.src = path;
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.remove('opacity-0'), 10);
};

window.closeImageViewer = function() {
    const modal = document.getElementById('image-viewer-modal');
    modal.classList.add('opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 300);
};

function runExportPulloutsFn(type) {
    const tbody = document.getElementById('pullout-tbody');
    const hasData = tbody && !tbody.innerText.includes('No records found');
    const selectedIds = Array.from(document.querySelectorAll('.pullout-checkbox:checked')).map(cb => cb.value);

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
    const storeFilter = document.querySelector('[name="store_filter"]')?.value || '';

    const loader = document.getElementById('loading-overlay');
    
    let url = `api/export_pullouts.php?type=${type}&search=${encodeURIComponent(search)}&start_date=${startDate}&end_date=${endDate}&store_filter=${storeFilter}`;
    if (selectedIds.length > 0) url += '&ids=' + selectedIds.join(',');

    if (typeof openGlobalFilenameModal === 'function') {
        openGlobalFilenameModal(type, 'pullouts_data', function(filename) {
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
                        showStatusModal(true, 'Pullout data has been exported successfully!', 'Export Success');
                    }
                    if (typeof refreshTable === 'function') refreshTable();
                    else if (typeof window.refreshPulloutTable === 'function') window.refreshPulloutTable();
                }, 3000);
            }, 800);
        });
    }
}
window.runExportPullouts = runExportPulloutsFn;

initTableEvents();
</script>
