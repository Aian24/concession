<?php
if (!in_array('non_submission', $_SESSION['user_permissions'])) {
    echo "<div class='p-8 text-center text-red-400 font-bold'>Unauthorized Access</div>";
    exit;
}

require_once 'includes/db.php';
$db = db_connect();

// ── Search & Pagination Logic ──────────────────────────────
$is_filter_request = isset($_GET['ajax']) || isset($_GET['search']) || isset($_GET['start_date']) || isset($_GET['end_date']) || isset($_GET['store_filter']);

if ($is_filter_request) {
    $limit        = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
    $page         = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $search       = $_GET['search'] ?? '';
    $start_date   = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date     = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $store_filter = $_GET['store_filter'] ?? '';
    
    $_SESSION['ns_limit']        = $limit;
    $_SESSION['ns_page']         = $page;
    $_SESSION['ns_search']       = $search;
    $_SESSION['ns_start_date']   = $start_date;
    $_SESSION['ns_end_date']     = $end_date;
    $_SESSION['ns_store_filter'] = $store_filter;
} else {
    $limit        = $_SESSION['ns_limit'] ?? 50;
    $page         = $_SESSION['ns_page'] ?? 1;
    $search       = $_SESSION['ns_search'] ?? '';
    $start_date   = !empty($_SESSION['ns_start_date']) ? $_SESSION['ns_start_date'] : date('Y-m-01');
    $end_date     = !empty($_SESSION['ns_end_date']) ? $_SESSION['ns_end_date'] : date('Y-m-d');
    $store_filter = $_SESSION['ns_store_filter'] ?? '';
}
$offset = ($page - 1) * $limit;

// Generate all dates in the range
$dates = [];
$current = strtotime($start_date);
$end = strtotime($end_date);
// Limit date range to prevent memory exhaustion (e.g. max 365 days)
if (($end - $current) > (365 * 86400)) {
    $end = $current + (365 * 86400);
}
while ($current <= $end) {
    $dates[] = date('Y-m-d', $current);
    $current = strtotime('+1 day', $current);
}

// Build Query for stores
$where = "WHERE scode NOT IN ('HO', 'HEADOFFICE', 'HEAD OFFICE') AND (sname IS NULL OR (sname NOT LIKE '%Head Office%' AND sname NOT LIKE '%HO%'))";
$params = [];
$types = "";

if ($store_filter !== '') {
    $where .= " AND scode = ?";
    $params[] = $store_filter;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (scode LIKE ? OR sname LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk; $params[] = $lk;
    $types .= "ss";
}

$store_sql = "SELECT scode, sname FROM storecode $where ORDER BY scode ASC";
$stmt = $db->prepare($store_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all distinct sales dates within the range
$sales_sql = "SELECT DISTINCT store_code, DATE(created_at) as sale_date FROM sales WHERE created_at >= ? AND created_at <= ?";
$stmt = $db->prepare($sales_sql);
$start_dt = $start_date . ' 00:00:00';
$end_dt = $end_date . ' 23:59:59';
$stmt->bind_param("ss", $start_dt, $end_dt);
$stmt->execute();
$sales_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sales_map = [];
foreach ($sales_res as $row) {
    $sales_map[$row['store_code']][] = $row['sale_date'];
}

// Determine missing entries
$all_missing_entries = [];
foreach ($stores as $st) {
    $scode = $st['scode'];
    $submitted_dates = $sales_map[$scode] ?? [];
    
    foreach ($dates as $d) {
        if (!in_array($d, $submitted_dates)) {
            $all_missing_entries[] = [
                'scode' => $scode,
                'sname' => $st['sname'],
                'missing_date' => $d
            ];
        }
    }
}

// Order by missing_date ASC, then scode ASC
usort($all_missing_entries, function($a, $b) {
    $dateCmp = strcmp($a['missing_date'], $b['missing_date']);
    if ($dateCmp === 0) {
        return strcmp($a['scode'], $b['scode']);
    }
    return $dateCmp; // ASC
});

$total_rows = count($all_missing_entries);
$total_pages = max(1, ceil($total_rows / $limit));
$missing_stores = array_slice($all_missing_entries, $offset, $limit);

if (isset($_GET['ajax'])) {
    include 'views/pages/non_submission_table_partial.php';
    exit;
}

// Fetch all stores for the store filter
$stores_stmt = $db->prepare("SELECT scode, sname FROM storecode WHERE scode NOT IN ('HO', 'HEADOFFICE', 'HEAD OFFICE') ORDER BY scode");
$stores_stmt->execute();
$all_stores = $stores_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stores_stmt->close();
?>

<div class="pb-12 animate-fade-in">
    <div class="glass-panel border border-white/5 shadow-xl overflow-hidden mt-6">
        <div class="p-5 border-b border-white/5 bg-slate-800/30 space-y-4 relative z-20">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <i class="fas fa-file-excel text-red-400 text-sm"></i>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Stores Without Submissions</h3>
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
                        <button onclick="exportToExcel('csv')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-green-500/10 hover:bg-green-500/20 text-green-400 border border-green-500/20 text-[10px] font-black tracking-widest transition-all">CSV</button>
                        <button onclick="exportToExcel('xls')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-[10px] font-black tracking-widest transition-all">XLS</button>
                        <button onclick="exportToExcel('txt')" class="h-8 flex items-center justify-center px-3 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 text-[10px] font-black tracking-widest transition-all">TXT</button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <style>
                input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
            </style>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-white/5 p-3 rounded-xl border border-white/5">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Search</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." 
                               class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-8 pr-4 py-1.5 h-8 text-[10px] text-white focus:outline-none focus:border-purple-500/50">
                    </div>
                </div>

                <div class="space-y-1 relative" id="store-filter-container">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Filter</label>
                    <?php
                    $current_label = "All Stores";
                    if ($store_filter) {
                        foreach($all_stores as $row) {
                            if ($row['scode'] === $store_filter) {
                                $current_label = $row['scode'] . ($row['sname'] ? " - " . $row['sname'] : "");
                                break;
                            }
                        }
                    }
                    ?>
                    <div id="store-filter-trigger" class="w-full bg-slate-900/80 border border-white/10 rounded-lg px-3 py-1.5 h-8 text-xs text-white flex items-center justify-between cursor-pointer focus:border-purple-500/50 transition-all hover:bg-white/5">
                        <span id="selected-store-label" class="truncate font-bold opacity-80"><?= htmlspecialchars($current_label) ?></span>
                        <i class="fas fa-chevron-down text-[9px] text-gray-500 ml-2"></i>
                    </div>
                    <!-- Hidden select for filtering logic -->
                    <select name="store_filter" class="hidden" id="hidden-store-filter">
                        <option value="" <?= $store_filter === '' ? 'selected' : '' ?>>All Stores</option>
                        <?php foreach($all_stores as $st): ?>
                            <option value="<?= htmlspecialchars($st['scode']) ?>" <?= $store_filter == $st['scode'] ? 'selected' : '' ?>><?= htmlspecialchars($st['scode']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Custom Menu -->
                    <div id="store-filter-menu" class="absolute top-[calc(100%+4px)] left-0 right-0 bg-[#0f172a] border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto overflow-x-hidden backdrop-blur-xl">
                        <div class="sticky top-0 bg-[#0f172a] p-2 border-b border-white/5 z-20">
                            <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-[10px] text-white focus:outline-none focus:border-purple-500/50" placeholder="Search store..." autocomplete="off">
                        </div>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $store_filter === '' ? 'bg-purple-500/10' : '' ?>" data-value="">
                            <span class="font-bold">All Stores</span>
                        </div>
                        <?php foreach($all_stores as $st): 
                            $sel = ($store_filter == $st['scode']);
                            $displayName = $st['scode'] . ($st['sname'] ? " - " . $st['sname'] : "");
                        ?>
                            <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex flex-col justify-center transition-all border-b border-white/5 last:border-0 <?= $sel ? 'bg-purple-500/10' : '' ?>" 
                                 data-value="<?= htmlspecialchars($st['scode']) ?>" 
                                 data-label="<?= htmlspecialchars($displayName) ?>">
                                <span class="font-bold truncate"><?= htmlspecialchars($st['scode']) ?></span>
                                <?php if ($st['sname']): ?>
                                    <span class="text-[9px] text-gray-500 truncate uppercase tracking-tighter"><?= htmlspecialchars($st['sname']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">From Date</label>
                    <div class="relative">
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" onclick="this.showPicker()" placeholder="mm/dd/yyyy"
                               class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-3 pr-8 py-1.5 h-8 text-xs text-white focus:outline-none focus:border-purple-500/50 cursor-pointer">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="fas fa-calendar-alt text-gray-500 text-[10px]"></i>
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">To Date</label>
                    <div class="relative">
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" onclick="this.showPicker()" placeholder="mm/dd/yyyy"
                               class="w-full bg-slate-900/80 border border-white/10 rounded-lg pl-3 pr-8 py-1.5 h-8 text-xs text-white focus:outline-none focus:border-purple-500/50 cursor-pointer">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="fas fa-calendar-alt text-gray-500 text-[10px]"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="non-submission-container">
            <?php include 'views/pages/non_submission_table_partial.php'; ?>
        </div>
    </div>
</div>

<script>
(function() {
    let filterTimer;
    function refreshTable(page = 1) {
        const container = document.getElementById('non-submission-container');
        const searchInputEl = document.querySelector('[name="search"]');
        const search = searchInputEl?.value || '';
        const limit  = document.querySelector('[name="limit"]')?.value || 50;
        const start_date = document.querySelector('[name="start_date"]')?.value || '';
        const end_date = document.querySelector('[name="end_date"]')?.value || '';
        const store = document.querySelector('#hidden-store-filter')?.value || '';
        
        const isSearchFocused = document.activeElement === searchInputEl;

        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';
        
        const url = `index.php?action=non_submission&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}&start_date=${start_date}&end_date=${end_date}&store_filter=${store}`;
        fetch(url).then(res => res.text()).then(html => { 
            container.innerHTML = html; 
            initRefreshableEvents(); 
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

    function initRefreshableEvents() {
        document.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                refreshTable(this.getAttribute('data-page'));
            });
        });
    }

    function initPersistentEvents() {
        const searchInput = document.querySelector('[name="search"]');
        const limitSelect = document.querySelector('[name="limit"]');
        const startDate = document.querySelector('[name="start_date"]');
        const endDate = document.querySelector('[name="end_date"]');
        const storeFilter = document.querySelector('#hidden-store-filter');

        searchInput?.addEventListener('input', () => {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => refreshTable(1), 300);
        });

        [limitSelect, startDate, endDate, storeFilter].forEach(el => {
            el?.addEventListener('change', () => refreshTable(1));
        });
    }

    window.exportToExcel = function(format = 'csv') {
        const tbody = document.getElementById('non-submission-tbody');
        const hasData = tbody && !tbody.innerText.includes('No stores are missing submissions');
        
        if (!hasData) {
            if (typeof showStatusModal === 'function') {
                showStatusModal(false, 'No data available to export. Please adjust your filters.');
            } else {
                alert('No data available to export.');
            }
            return;
        }

        const search = document.querySelector('[name="search"]')?.value || '';
        const start_date = document.querySelector('[name="start_date"]')?.value || '';
        const end_date = document.querySelector('[name="end_date"]')?.value || '';
        const store = document.querySelector('#hidden-store-filter')?.value || '';
        let url = `api/export_non_submission.php?format=${format}&search=${encodeURIComponent(search)}&start_date=${start_date}&end_date=${end_date}&store_filter=${store}`;

        if (typeof openGlobalFilenameModal === 'function') {
            openGlobalFilenameModal(format, 'non_submission_report', function(filename) {
                if (filename) url += '&filename=' + encodeURIComponent(filename);
                
                const loader = document.getElementById('loading-overlay');
                if (loader) {
                    const p = loader.querySelector('p');
                    if (p) p.textContent = 'Preparing ' + format.toUpperCase() + ' File...';
                    loader.classList.remove('opacity-0', 'pointer-events-none');
                }
                
                setTimeout(() => {
                    window.location.href = url;
                    setTimeout(() => {
                        if (loader) loader.classList.add('opacity-0', 'pointer-events-none');
                        if (typeof showStatusModal === 'function') {
                            showStatusModal(true, 'Report data has been exported successfully!', 'Export Success');
                        }
                    }, 3000);
                }, 1000);
            });
        } else {
            window.location.href = url;
        }
    };

    initPersistentEvents();
    initRefreshableEvents();
})();
</script>
