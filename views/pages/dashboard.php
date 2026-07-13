<?php
$db = db_connect();

// Date Range Logic
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
    $_SESSION['dashboard_end_date'] = $end_date;
} else {
    $end_date = $_SESSION['dashboard_end_date'] ?? date('Y-m-d');
}

if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
    $_SESSION['dashboard_start_date'] = $start_date;
} elseif (isset($_SESSION['dashboard_start_date'])) {
    $start_date = $_SESSION['dashboard_start_date'];
} else {
    // Default: Earliest transaction of the current month, or 1st of the month
    $min_date_res = $db->query("SELECT MIN(created_at) as min_date FROM sales WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $min_date_row = $min_date_res ? $min_date_res->fetch_assoc() : null;
    if ($min_date_row && $min_date_row['min_date']) {
        $start_date = date('Y-m-d', strtotime($min_date_row['min_date']));
    } else {
        $start_date = date('Y-m-01'); // 1st of the month
    }
}

// Store Filtering Logic
$role = $_SESSION['role'] ?? 'user';
$is_store_admin = ($role === 'store_admin');
$is_multi_store_admin = ($role === 'multi_store_admin');
$is_full_admin = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
$is_admin_view = ($role === 'admin_view');
$is_admin = ($is_full_admin || $is_admin_view || $is_multi_store_admin);
$session_store_code = $_SESSION['store_code'] ?? '';

// Filter store code from GET
if (isset($_GET['store_code'])) {
    $filter_store_code = $_GET['store_code'];
    $_SESSION['dashboard_store_code'] = $filter_store_code;
} else {
    $filter_store_code = $_SESSION['dashboard_store_code'] ?? '';
}

$store_clause = "";
if ($is_store_admin) {
    $store_clause = " AND store_code = '$session_store_code'";
} elseif ($is_multi_store_admin) {
    $assigned = $_SESSION['assigned_stores'] ?? [];
    if ($filter_store_code !== '' && in_array($filter_store_code, $assigned)) {
        $store_clause = " AND store_code = '$filter_store_code'";
    } else {
        $store_clause = build_multi_store_clause('store_code', $assigned);
    }
} elseif ($is_full_admin || $is_admin_view) {
    if ($filter_store_code !== '') {
        $store_clause = " AND store_code = '$filter_store_code'";
    }
} else {
    // Regular user
    $store_clause = " AND store_code = '$session_store_code'";
}

// Filter toggles for Data Source
if (isset($_GET['action']) && $_GET['action'] == 'dashboard') {
    $has_source = isset($_GET['source_concession']) || isset($_GET['source_boutique']);
    if (!$has_source) {
        $show_concession = true;
        $show_boutique = false;
    } else {
        $show_concession = isset($_GET['source_concession']) && $_GET['source_concession'] == '1';
        $show_boutique = isset($_GET['source_boutique']) && $_GET['source_boutique'] == '1';
    }
} else {
    $show_concession = true;
    $show_boutique = false;
}

// Fetch stores for filter dropdown
$stores_list = [];
if ($is_full_admin || $is_admin_view) {
    $temp_stores = [];
    
    if ($show_concession) {
        $res = $db->query("SELECT scode, sname FROM storecode");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $temp_stores[$r['scode']] = $r;
            }
        }
    }
    
    if ($show_boutique) {
        $check_table = $db->query("SHOW TABLES LIKE 'boutique'");
        if ($check_table && $check_table->num_rows > 0) {
            $res = $db->query("SELECT DISTINCT store_code as scode, store_name as sname FROM boutique");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $temp_stores[$r['scode']] = $r;
                }
            }
        }
    }
    
    $stores_list = array_values($temp_stores);
    usort($stores_list, function($a, $b) { 
        return strcmp($a['sname'] ?? '', $b['sname'] ?? ''); 
    });
} elseif ($is_multi_store_admin) {
    $assigned_data = $_SESSION['assigned_stores_data'] ?? [];
    $stores_list = array_map(function($s) {
        return ['scode' => $s['store_code'], 'sname' => $s['sname']];
    }, $assigned_data);
}

// 1. Total Sales & Quantity
$total_sales = 0.00;
$total_sales_qty = 0;
$total_sales_count = 0;

if ($show_concession) {
    $sales_res = $db->query("SELECT SUM(line_total) as total_sales, SUM(quantity) as total_qty, COUNT(id) as total_count FROM sales WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' $store_clause");
    $sales_data = $sales_res ? $sales_res->fetch_assoc() : [];
    $total_sales += (float) ($sales_data['total_sales'] ?? 0.00);
    $total_sales_qty += (int) ($sales_data['total_qty'] ?? 0);
    $total_sales_count += (int) ($sales_data['total_count'] ?? 0);
}

if ($show_boutique) {
    $check_table = $db->query("SHOW TABLES LIKE 'boutique'");
    if ($check_table && $check_table->num_rows > 0) {
        $boutique_res = $db->query("SELECT SUM(amount) as total_sales, SUM(qty_sold) as total_qty, COUNT(id) as total_count FROM boutique WHERE date BETWEEN '$start_date' AND '$end_date' $store_clause");
        if ($boutique_res) {
            $boutique_data = $boutique_res->fetch_assoc();
            $total_sales += (float) ($boutique_data['total_sales'] ?? 0.00);
            $total_sales_qty += (int) ($boutique_data['total_qty'] ?? 0);
            $total_sales_count += (int) ($boutique_data['total_count'] ?? 0);
        }
    }
}

// 1.1 Top Sellers (Admin Only) - Concession
$top_store_name = 'N/A';
$top_store_qty = 0;
$top_store_amount = 0.00;
$top_stores_ranking = [];
if ($is_admin) {
    $top_store_res = $db->query("SELECT s.store_code, sc.sname, SUM(s.line_total) as total_sales, SUM(s.quantity) as total_qty FROM sales s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci WHERE s.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' $store_clause GROUP BY s.store_code, sc.sname ORDER BY total_sales DESC");
    if ($top_store_res) {
        $top_stores_ranking = $top_store_res->fetch_all(MYSQLI_ASSOC);
        if (!empty($top_stores_ranking)) {
            $top = $top_stores_ranking[0];
            $top_store_name = $top['store_code'] . ($top['sname'] ? " (" . $top['sname'] . ")" : "");
            $top_store_qty = array_sum(array_column($top_stores_ranking, 'total_qty'));
            $top_store_amount = array_sum(array_column($top_stores_ranking, 'total_sales'));
        }
    }
}

// 1.2 Top Sellers (Admin Only) - Boutique
$top_boutique_name = 'N/A';
$top_boutique_qty = 0;
$top_boutique_amount = 0.00;
$top_boutique_ranking = [];
if ($is_admin) {
    // Avoid crashing if table doesn't exist yet
    $check_table = $db->query("SHOW TABLES LIKE 'boutique'");
    if ($check_table && $check_table->num_rows > 0) {
        $top_boutique_res = $db->query("SELECT store_code, store_name as sname, SUM(amount) as total_sales, SUM(qty_sold) as total_qty FROM boutique WHERE date BETWEEN '$start_date' AND '$end_date' GROUP BY store_code, store_name ORDER BY total_sales DESC");
        if ($top_boutique_res) {
            $top_boutique_ranking = $top_boutique_res->fetch_all(MYSQLI_ASSOC);
            if (!empty($top_boutique_ranking)) {
                $top = $top_boutique_ranking[0];
                $top_boutique_name = $top['store_code'] . ($top['sname'] ? " (" . $top['sname'] . ")" : "");
                $top_boutique_qty = array_sum(array_column($top_boutique_ranking, 'total_qty'));
                $top_boutique_amount = array_sum(array_column($top_boutique_ranking, 'total_sales'));
            }
        }
    }
}

// 2. Total Returns
$returns_res = $db->query("SELECT SUM(return_amount) as total_returns, COUNT(id) as total_count FROM returns WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' $store_clause");
$returns_data = $returns_res ? $returns_res->fetch_assoc() : ['total_returns' => 0, 'total_count' => 0];
$total_returns = (float) ($returns_data['total_returns'] ?? 0.00);
$total_returns_count = (int) ($returns_data['total_count'] ?? 0);

// 3. Total Receiving
$receiving_res = $db->query("SELECT SUM(quantity) as total_received, COUNT(id) as total_count FROM receiving WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' $store_clause");
$receiving_data = $receiving_res ? $receiving_res->fetch_assoc() : ['total_received' => 0, 'total_count' => 0];
$total_received_qty = (int) ($receiving_data['total_received'] ?? 0);
$receiving_count = (int) ($receiving_data['total_count'] ?? 0);

// 4. Total Stores
if ($is_admin) {
    // Exclude HO if present in stores_list
    $total_stores = 0;
    foreach ($stores_list as $sl) {
        if ($sl['scode'] !== 'HO') $total_stores++;
    }
} else {
    $total_stores = 1;
}

// 4.1 Active Stores (with transactions in selected range)
$active_stores_array = [];
if ($show_concession) {
    $active_stores_res = $db->query("SELECT DISTINCT store_code FROM sales WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' $store_clause AND store_code != 'HO'");
    if ($active_stores_res) {
        while ($r = $active_stores_res->fetch_assoc()) {
            $active_stores_array[$r['store_code']] = true;
        }
    }
}
if ($show_boutique) {
    $check_table = $db->query("SHOW TABLES LIKE 'boutique'");
    if ($check_table && $check_table->num_rows > 0) {
        $active_stores_res = $db->query("SELECT DISTINCT store_code FROM boutique WHERE date BETWEEN '$start_date' AND '$end_date' $store_clause AND store_code != 'HO'");
        if ($active_stores_res) {
            while ($r = $active_stores_res->fetch_assoc()) {
                $active_stores_array[$r['store_code']] = true;
            }
        }
    }
}
$active_stores_count = count($active_stores_array);

// Chart Data Generation
$chart_labels = [];
$chart_sales_values = [];
$chart_qty_values = [];
$chart_returns_values = [];
$chart_received_values = [];
$chart_txn_values = [];

$current = new DateTime($start_date);
$end = new DateTime($end_date);
$end->modify('+1 day');
$interval = new DateInterval('P1D');
$period = new DatePeriod($current, $interval, $end);

foreach ($period as $date) {
    $d = $date->format('Y-m-d');
    $chart_labels[$d] = $date->format('M d');
    $chart_sales_values[$d] = 0;
    $chart_qty_values[$d] = 0;
    $chart_returns_values[$d] = 0;
    $chart_received_values[$d] = 0;
    $chart_txn_values[$d] = 0;
}

if ($show_concession) {
    $chart_res = $db->query("SELECT DATE(created_at) as d, SUM(line_total) as total, SUM(quantity) as qty, COUNT(id) as txn_count FROM sales WHERE (created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59') $store_clause GROUP BY DATE(created_at)");
    if ($chart_res) {
        while($row = $chart_res->fetch_assoc()) {
            if (isset($chart_sales_values[$row['d']])) {
                $chart_sales_values[$row['d']] += (float)$row['total'];
                $chart_qty_values[$row['d']] += (int)$row['qty'];
                $chart_txn_values[$row['d']] += (int)$row['txn_count'];
            }
        }
    }
}

if ($show_boutique) {
    $check_table = $db->query("SHOW TABLES LIKE 'boutique'");
    if ($check_table && $check_table->num_rows > 0) {
        $chart_res2 = $db->query("SELECT date as d, SUM(amount) as total, SUM(qty_sold) as qty, COUNT(id) as txn_count FROM boutique WHERE (date BETWEEN '$start_date' AND '$end_date') $store_clause GROUP BY date");
        if ($chart_res2) {
            while($row = $chart_res2->fetch_assoc()) {
                if (isset($chart_sales_values[$row['d']])) {
                    $chart_sales_values[$row['d']] += (float)$row['total'];
                    $chart_qty_values[$row['d']] += (int)$row['qty'];
                    $chart_txn_values[$row['d']] += (int)$row['txn_count'];
                }
            }
        }
    }
}

$ret_chart_res = $db->query("SELECT DATE(created_at) as d, SUM(return_amount) as total FROM returns WHERE (created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59') $store_clause GROUP BY DATE(created_at)");
if ($ret_chart_res) {
    while($row = $ret_chart_res->fetch_assoc()) {
        if (isset($chart_returns_values[$row['d']])) {
            $chart_returns_values[$row['d']] += (float)$row['total'];
        }
    }
}

$rec_chart_res = $db->query("SELECT DATE(created_at) as d, SUM(quantity) as total FROM receiving WHERE (created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59') $store_clause GROUP BY DATE(created_at)");
if ($rec_chart_res) {
    while($row = $rec_chart_res->fetch_assoc()) {
        if (isset($chart_received_values[$row['d']])) {
            $chart_received_values[$row['d']] += (int)$row['total'];
        }
    }
}

// 5. Dynamic Notifications
$notif_res = $db->query("
    (SELECT 'sale' as type, username, store_code, created_at, line_total as val FROM sales WHERE 1=1 $store_clause)
    UNION
    (SELECT 'return' as type, username, store_code, created_at, return_amount as val FROM returns WHERE 1=1 $store_clause)
    UNION
    (SELECT 'receiving' as type, username, store_code, created_at, quantity as val FROM receiving WHERE 1=1 $store_clause)
    ORDER BY created_at DESC LIMIT 6
");
$notifications = $notif_res ? $notif_res->fetch_all(MYSQLI_ASSOC) : [];

if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        $string = array('y' => 'year','m' => 'month','w' => 'week','d' => 'day','h' => 'hour','i' => 'minute','s' => 'second');
        foreach ($string as $k => &$v) {
            if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); } 
            else { unset($string[$k]); }
        }
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}
?>

<style>
    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
    .no-scrollbar::-webkit-scrollbar { display: none !important; }
    .no-scrollbar { -ms-overflow-style: none !important; scrollbar-width: none !important; }
    /* Dashboard mobile card title fix */
    @media (max-width: 639px) {
        .dash-card-title { font-size: 9px !important; letter-spacing: 0.05em !important; }
        .dash-card-value { font-size: 1.1rem !important; }
        .dash-card-icon { width: 36px !important; height: 36px !important; }
        .dash-card-icon i { font-size: 0.875rem !important; }
    }
</style>

<!-- Date Range Filter -->
<div class="glass-panel px-5 pt-4 pb-5 border border-white/5 mb-8 relative z-50">

    <!-- Row 1: Title only -->
    <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-600/20 to-pink-600/20 flex items-center justify-center text-white border border-white/10 flex-shrink-0">
            <i class="fas fa-calendar-alt text-sm"></i>
        </div>
        <div>
            <h3 class="text-sm font-black text-white uppercase tracking-widest leading-none mb-0.5">Date Range Analytics</h3>
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter">Live filtering applied to graphs and stats</p>
        </div>
    </div>

    <!-- Row 2: All controls aligned full-width -->
    <!-- Mobile: stacked rows | Desktop: single horizontal row -->
    <div class="flex flex-col lg:flex-row lg:items-end gap-3 w-full">

        <!-- Mobile row A: Data Source + Quick Year stacked on mobile -->
        <div class="flex flex-col lg:flex-row lg:items-end gap-3 w-full lg:contents">

            <!-- Data Source Pills -->
            <div class="flex flex-col gap-1 flex-shrink-0">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Data Source</label>
                <div class="flex items-center gap-1.5">
                    <form method="GET" id="source-concession-form">
                        <input type="hidden" name="action" value="dashboard">
                        <input type="hidden" name="start_date" value="<?= $start_date ?>">
                        <input type="hidden" name="end_date" value="<?= $end_date ?>">
                        <input type="hidden" name="store_code" value="<?= htmlspecialchars($filter_store_code) ?>">
                        <?php if ($show_boutique): ?><input type="hidden" name="source_boutique" value="1"><?php endif; ?>
                        <?php if (!$show_concession): ?><input type="hidden" name="source_concession" value="1"><?php endif; ?>
                        <button type="submit" class="h-9 px-3 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all border flex items-center gap-1.5 <?= $show_concession ? 'bg-blue-500/20 text-blue-300 border-blue-500/40' : 'bg-white/5 text-gray-500 border-white/10 hover:text-blue-400 hover:bg-blue-500/10' ?>">
                            <i class="fas fa-store text-[9px]"></i> Concession
                        </button>
                    </form>
                    <form method="GET" id="source-boutique-form">
                        <input type="hidden" name="action" value="dashboard">
                        <input type="hidden" name="start_date" value="<?= $start_date ?>">
                        <input type="hidden" name="end_date" value="<?= $end_date ?>">
                        <input type="hidden" name="store_code" value="<?= htmlspecialchars($filter_store_code) ?>">
                        <?php if ($show_concession): ?><input type="hidden" name="source_concession" value="1"><?php endif; ?>
                        <?php if (!$show_boutique): ?><input type="hidden" name="source_boutique" value="1"><?php endif; ?>
                        <button type="submit" class="h-9 px-3 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all border flex items-center gap-1.5 <?= $show_boutique ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-white/5 text-gray-500 border-white/10 hover:text-amber-400 hover:bg-amber-500/10' ?>">
                            <i class="fas fa-tag text-[9px]"></i> Boutique
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Year multi-select -->
            <div class="flex flex-col gap-1 flex-1 min-w-0">
                <div class="relative w-full">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Quick Year <span class="text-purple-400/60" id="yr-multi-hint"></span></label>
                    <button type="button" onclick="resetDashboardQuickYear()" class="absolute right-1 top-0 mt-0 text-gray-500 hover:text-red-400 transition-colors" title="Reset Year"><i class="fas fa-sync-alt text-[9px]"></i></button>
                </div>
                <div class="flex items-center bg-slate-900/80 border border-white/10 rounded-xl h-9 relative w-full">
                    <button type="button" onclick="scrollQuickYears(-1)" class="absolute left-0 z-10 h-full px-2 bg-gradient-to-r from-slate-900 via-slate-900/80 to-transparent text-gray-400 hover:text-white transition-all flex-shrink-0"><i class="fas fa-chevron-left text-[9px]"></i></button>
                    <div id="years-container" class="no-scrollbar flex items-center gap-1 overflow-x-auto scroll-smooth w-full px-6" style="scrollbar-width:none;-ms-overflow-style:none;">
                        <?php
                        $currYr = date('Y');
                        for($y = $currYr - 4; $y <= $currYr + 2; $y++):
                        ?>
                        <button type="button" data-year="<?= $y ?>" onclick="toggleYear(this, <?= $y ?>)" class="year-btn flex-shrink-0 w-[46px] py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider transition-all text-center select-none border text-gray-500 border-transparent hover:text-purple-400 hover:bg-white/5">
                            <?= $y ?>
                        </button>
                        <?php endfor; ?>
                    </div>
                    <button type="button" onclick="scrollQuickYears(1)" class="absolute right-0 z-10 h-full px-2 bg-gradient-to-l from-slate-900 via-slate-900/80 to-transparent text-gray-400 hover:text-white transition-all flex-shrink-0"><i class="fas fa-chevron-right text-[9px]"></i></button>
                </div>
            </div>

        </div><!-- end mobile row A -->

        <!-- Quick Month multi-select (full width on mobile, flex-1 on desktop) -->
        <div class="flex flex-col gap-1 flex-1 min-w-0">
            <div class="relative w-full">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Quick Month <span class="text-purple-400/60" id="mo-multi-hint"></span></label>
                <button type="button" onclick="resetDashboardQuickMonth()" class="absolute right-1 top-0 mt-0 text-gray-500 hover:text-red-400 transition-colors" title="Reset Month"><i class="fas fa-sync-alt text-[9px]"></i></button>
            </div>
            <div class="flex items-center bg-slate-900/80 border border-white/10 rounded-xl h-9 relative w-full">
                <button type="button" onclick="scrollQuickMonths(-1)" class="absolute left-0 z-10 h-full px-2 bg-gradient-to-r from-slate-900 via-slate-900/80 to-transparent text-gray-400 hover:text-white transition-all flex-shrink-0"><i class="fas fa-chevron-left text-[9px]"></i></button>
                <div id="months-container" class="no-scrollbar flex items-center gap-1 overflow-x-auto scroll-smooth w-full px-6" style="scrollbar-width:none;-ms-overflow-style:none;">
                    <?php
                    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    foreach($months as $idx => $m):
                        $m_num = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                    ?>
                    <button type="button" data-month="<?= $m_num ?>" onclick="toggleMonth(this, '<?= $m_num ?>')" class="month-btn flex-shrink-0 w-[43px] py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider transition-all text-center select-none border text-gray-500 border-transparent hover:text-purple-400 hover:bg-white/5">
                        <?= $m ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="scrollQuickMonths(1)" class="absolute right-0 z-10 h-full px-2 bg-gradient-to-l from-slate-900 via-slate-900/80 to-transparent text-gray-400 hover:text-white transition-all flex-shrink-0"><i class="fas fa-chevron-right text-[9px]"></i></button>
            </div>
        </div>

        <!-- Mobile row B: Start Date + End Date + Store Filter -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3 w-full lg:contents">

            <!-- Manual Date Inputs -->
            <form id="dashboard-filter-form" method="GET" class="grid grid-cols-2 gap-3 items-end w-full sm:flex sm:flex-1 lg:flex-[2]">
                <input type="hidden" name="action" value="dashboard">
                <?php if ($show_concession): ?><input type="hidden" name="source_concession" value="1"><?php endif; ?>
                <?php if ($show_boutique): ?><input type="hidden" name="source_boutique" value="1"><?php endif; ?>
                <?php if ($filter_store_code): ?><input type="hidden" name="store_code" value="<?= htmlspecialchars($filter_store_code) ?>"><?php endif; ?>
                <div class="flex flex-col gap-1 min-w-0 sm:flex-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Start Date</label>
                    <div class="relative">
                        <i class="fas fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-600 pointer-events-none"></i>
                        <input type="date" name="start_date" id="manual-start" value="<?= $start_date ?>" onchange="this.form.submit()" onclick="this.showPicker()" class="w-full bg-slate-900/80 border border-white/10 rounded-xl pl-8 pr-2 py-2 h-9 text-[11px] sm:text-xs text-white focus:outline-none focus:border-purple-500/50 cursor-pointer transition-all" style="min-width: 0;">
                    </div>
                </div>
                <div class="flex flex-col gap-1 min-w-0 sm:flex-1">
                    <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">End Date</label>
                    <div class="relative">
                        <i class="fas fa-calendar-check absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-600 pointer-events-none"></i>
                        <input type="date" name="end_date" id="manual-end" value="<?= $end_date ?>" onchange="this.form.submit()" onclick="this.showPicker()" class="w-full bg-slate-900/80 border border-white/10 rounded-xl pl-8 pr-2 py-2 h-9 text-[11px] sm:text-xs text-white focus:outline-none focus:border-pink-500/50 cursor-pointer transition-all" style="min-width: 0;">
                    </div>
                </div>
            </form>

            <!-- Store Filter -->
            <?php if ($is_admin): ?>
            <div class="flex flex-col gap-1 w-full sm:w-auto sm:flex-shrink-0">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Store Filter</label>
                <div class="relative" id="store-filter-container">
                    <i class="fas fa-store absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-600 pointer-events-none z-10"></i>
                    <?php
                    $current_store_label = "All Stores";
                    if ($filter_store_code) {
                        foreach ($stores_list as $sl) {
                            if ($sl['scode'] === $filter_store_code) {
                                $current_store_label = $sl['scode'] . " - " . $sl['sname'];
                                break;
                            }
                        }
                    }
                    ?>
                    <div id="store-filter-trigger" class="w-full sm:w-48 bg-slate-900/80 border border-white/10 rounded-xl pl-9 pr-4 py-2 h-9 text-xs text-white flex items-center justify-between cursor-pointer hover:bg-white/5 transition-all">
                        <span id="selected-store-label" class="truncate font-bold opacity-80 uppercase tracking-tight text-[10px]"><?= htmlspecialchars($current_store_label) ?></span>
                        <i class="fas fa-chevron-down text-[9px] text-gray-500 ml-2"></i>
                    </div>
                    <div id="store-filter-menu" class="absolute top-[calc(100%+4px)] right-0 min-w-[260px] bg-[#0f172a] border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto backdrop-blur-xl">
                        <div class="sticky top-0 bg-[#0f172a] p-2 border-b border-white/5 z-20">
                            <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-[10px] text-white focus:outline-none focus:border-amber-500/50" placeholder="Search store..." autocomplete="off">
                        </div>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center border-b border-white/5 <?= $filter_store_code === '' ? 'bg-amber-500/10' : '' ?>" data-value=""><span class="font-bold">All Stores</span></div>
                        <?php foreach($stores_list as $st):
                            $sel = ($filter_store_code == $st['scode']);
                            $displayName = $st['scode'] . " - " . $st['sname'];
                        ?>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center border-b border-white/5 last:border-0 <?= $sel ? 'bg-amber-500/10' : '' ?>" data-value="<?= htmlspecialchars($st['scode']) ?>" data-label="<?= htmlspecialchars($displayName) ?>">
                            <div class="flex flex-col">
                                <span class="font-bold"><?= htmlspecialchars($st['scode']) ?></span>
                                <span class="text-[9px] text-gray-500 uppercase tracking-tighter"><?= htmlspecialchars($st['sname']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <form id="store-filter-form" method="GET">
                        <input type="hidden" name="action" value="dashboard">
                        <input type="hidden" name="start_date" value="<?= $start_date ?>">
                        <input type="hidden" name="end_date" value="<?= $end_date ?>">
                        <?php if ($show_concession): ?><input type="hidden" name="source_concession" value="1"><?php endif; ?>
                        <?php if ($show_boutique): ?><input type="hidden" name="source_boutique" value="1"><?php endif; ?>
                        <input type="hidden" name="store_code" id="store-filter-value" value="<?= htmlspecialchars($filter_store_code) ?>">
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- end mobile row B -->

    </div>
</div>


<script>
const ACTIVE_CLS   = ['!bg-purple-500/20','!text-purple-400','shadow-sm','!border-purple-500/50'];
const INACTIVE_CLS = ['text-gray-500','border-transparent'];
let selectedYears  = new Set();
let selectedMonths = new Set();

function applyQuickFilter() {
    if (selectedYears.size === 0 || selectedMonths.size === 0) return;
    const minY = Math.min(...selectedYears), maxY = Math.max(...selectedYears);
    const minM = Math.min(...[...selectedMonths].map(Number));
    const maxM = Math.max(...[...selectedMonths].map(Number));
    const pad = v => String(v).padStart(2,'0');
    const startDate = `${minY}-${pad(minM)}-01`;
    const lastDay   = new Date(maxY, maxM, 0).getDate();
    const endDate   = `${maxY}-${pad(maxM)}-${lastDay}`;
    document.getElementById('manual-start').value = startDate;
    document.getElementById('manual-end').value   = endDate;
    document.getElementById('dashboard-filter-form').submit();
}
function toggleYear(btn, year) {
    if (selectedYears.has(year)) { selectedYears.delete(year); btn.classList.remove(...ACTIVE_CLS); btn.classList.add(...INACTIVE_CLS); }
    else { selectedYears.add(year); btn.classList.add(...ACTIVE_CLS); btn.classList.remove(...INACTIVE_CLS); }
    document.getElementById('yr-multi-hint').textContent = selectedYears.size > 1 ? `(${selectedYears.size} selected)` : '';
    applyQuickFilter();
}
function toggleMonth(btn, monthNum) {
    const m = parseInt(monthNum);
    if (selectedMonths.has(m)) { selectedMonths.delete(m); btn.classList.remove(...ACTIVE_CLS); btn.classList.add(...INACTIVE_CLS); }
    else { selectedMonths.add(m); btn.classList.add(...ACTIVE_CLS); btn.classList.remove(...INACTIVE_CLS); }
    document.getElementById('mo-multi-hint').textContent = selectedMonths.size > 1 ? `(${selectedMonths.size} selected)` : '';
    applyQuickFilter();
}
function scrollQuickYears(dir) { 
    const c = document.getElementById('years-container'); 
    if (c) c.scrollBy({ left: dir * (c.clientWidth / 2), behavior: 'smooth' }); 
}
function scrollQuickMonths(dir) { 
    const c = document.getElementById('months-container'); 
    if (c) c.scrollBy({ left: dir * 150, behavior: 'smooth' }); 
}

function resetDashboardQuickYear() {
    selectedYears.clear();
    document.querySelectorAll('.year-btn').forEach(btn => {
        btn.classList.remove(...ACTIVE_CLS);
        btn.classList.add(...INACTIVE_CLS);
    });
    const cy = new Date().getFullYear();
    selectedYears.add(cy);
    const yBtn = document.querySelector(`.year-btn[data-year="${cy}"]`);
    if (yBtn) { yBtn.classList.add(...ACTIVE_CLS); yBtn.classList.remove(...INACTIVE_CLS); }
    document.getElementById('yr-multi-hint').textContent = '';
    applyQuickFilter();
}

function resetDashboardQuickMonth() {
    selectedMonths.clear();
    document.querySelectorAll('.month-btn').forEach(btn => {
        btn.classList.remove(...ACTIVE_CLS);
        btn.classList.add(...INACTIVE_CLS);
    });
    const cm = new Date().getMonth() + 1;
    selectedMonths.add(cm);
    const mBtn = document.querySelector(`.month-btn[data-month="${String(cm).padStart(2, '0')}"]`);
    if (mBtn) { mBtn.classList.add(...ACTIVE_CLS); mBtn.classList.remove(...INACTIVE_CLS); }
    document.getElementById('mo-multi-hint').textContent = '';
    applyQuickFilter();
}

document.addEventListener('DOMContentLoaded', () => {
    const sDate = '<?= $start_date ?>';
    const eDate = '<?= $end_date ?>';
    if (sDate && eDate) {
        const s = new Date(sDate + 'T00:00:00');
        const e = new Date(eDate + 'T00:00:00');
        for (let y = s.getFullYear(); y <= e.getFullYear(); y++) {
            selectedYears.add(y);
            const btn = document.querySelector(`.year-btn[data-year="${y}"]`);
            if (btn) { btn.classList.add(...ACTIVE_CLS); btn.classList.remove(...INACTIVE_CLS); }
        }
        let cur = new Date(s.getFullYear(), s.getMonth(), 1);
        const end = new Date(e.getFullYear(), e.getMonth(), 1);
        while (cur <= end) {
            const m = cur.getMonth() + 1;
            selectedMonths.add(m);
            const pad = v => String(v).padStart(2,'0');
            const btn = document.querySelector(`.month-btn[data-month="${pad(m)}"]`);
            if (btn) { btn.classList.add(...ACTIVE_CLS); btn.classList.remove(...INACTIVE_CLS); }
            cur.setMonth(cur.getMonth() + 1);
        }
        if (selectedYears.size > 1)  document.getElementById('yr-multi-hint').textContent = `(${selectedYears.size} selected)`;
        if (selectedMonths.size > 1) document.getElementById('mo-multi-hint').textContent = `(${selectedMonths.size} selected)`;
        setTimeout(() => {
            // Center the selected year(s)
            const yc = document.getElementById('years-container');
            const activeYBtns = yc ? Array.from(yc.querySelectorAll('.year-btn.shadow-sm')) : [];
            if (activeYBtns.length > 0 && yc) {
                const firstBtn = activeYBtns[0];
                const lastBtn = activeYBtns[activeYBtns.length - 1];
                const centerOffset = firstBtn.offsetLeft + ((lastBtn.offsetLeft + lastBtn.clientWidth) - firstBtn.offsetLeft) / 2;
                yc.scrollTo({ left: centerOffset - yc.clientWidth/2, behavior: 'smooth' });
            }
            
            // Center the selected month(s)
            const mc = document.getElementById('months-container');
            const activeMBtns = mc ? Array.from(mc.querySelectorAll('.month-btn.shadow-sm')) : [];
            if (activeMBtns.length > 0 && mc) {
                const firstBtn = activeMBtns[0];
                const lastBtn = activeMBtns[activeMBtns.length - 1];
                const centerOffset = firstBtn.offsetLeft + ((lastBtn.offsetLeft + lastBtn.clientWidth) - firstBtn.offsetLeft) / 2;
                mc.scrollTo({ left: centerOffset - mc.clientWidth/2, behavior: 'smooth' });
            }
        }, 300);
    }
    // Submit store filter form when a store is selected
    const storeValInput = document.getElementById('store-filter-value');
    if (storeValInput) {
        storeValInput.addEventListener('change', () => {
            document.getElementById('store-filter-form')?.submit();
        });
    }
});
</script>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4 lg:gap-6 mb-8">
    <!-- Stats Cards -->
    <?php if ($is_admin): ?>
    <div onclick="openTopSellersModal()" class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-[#3b82f6]/30 transition-all duration-500 cursor-pointer z-50 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-[#3b82f6]/10 rounded-full blur-2xl group-hover:bg-[#3b82f6]/20 transition-all"></div>
        <div class="flex items-center justify-between gap-2 mb-3 sm:mb-4 relative z-10">
            <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-[#3b82f6]/10 flex items-center justify-center text-[#3b82f6] border border-[#3b82f6]/20 shadow-lg shadow-[#3b82f6]/5 shrink-0">
                    <i class="fas fa-trophy text-lg sm:text-xl"></i>
                </div>
                <h3 class="dash-card-title text-[9px] sm:text-xs font-black text-gray-500 uppercase tracking-wider sm:tracking-[0.2em]" title="Top Sellers Concession">Top Sellers Con.</h3>
            </div>
            <div class="opacity-0 group-hover:opacity-100 transition-all duration-300 translate-x-2 group-hover:translate-x-0 flex items-center gap-1.5 bg-[#3b82f6]/10 border border-[#3b82f6]/20 px-2 sm:px-2.5 py-1 rounded-lg shrink-0 hidden sm:flex">
                <span class="text-[8px] font-bold text-[#3b82f6] uppercase tracking-widest whitespace-nowrap">View Details</span>
                <i class="fas fa-external-link-alt text-[#3b82f6] text-[9px]"></i>
            </div>
        </div>
        <p class="dash-card-value text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= htmlspecialchars($top_store_name) ?>"><?= htmlspecialchars($top_store_name) ?></p>
        
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="topConcessionChart"></canvas>
        </div>

        <div class="mt-auto relative z-10">
            <?php if ($top_store_name !== 'N/A'): ?>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1 mt-2">Overall Total</p>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-sm sm:text-base lg:text-lg font-bold text-white bg-white/10 px-2.5 py-0.5 rounded-full uppercase truncate">Qty: <?= number_format($top_store_qty) ?></span>
                <span class="text-sm sm:text-base lg:text-lg font-bold text-emerald-400 bg-emerald-500/10 px-2.5 py-0.5 rounded-full uppercase truncate">₱<?= number_format($top_store_amount, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div onclick="openTopBoutiqueModal()" class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-yellow-500/30 transition-all duration-500 cursor-pointer z-50 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-yellow-500/10 rounded-full blur-2xl group-hover:bg-yellow-500/20 transition-all"></div>
        <div class="flex items-center justify-between gap-2 mb-3 sm:mb-4 relative z-10">
            <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-400 border border-yellow-500/20 shadow-lg shadow-yellow-500/5 shrink-0">
                    <i class="fas fa-crown text-lg sm:text-xl"></i>
                </div>
                <h3 class="dash-card-title text-[9px] sm:text-xs font-black text-gray-500 uppercase tracking-wider sm:tracking-[0.2em]" title="Top Sellers Boutique">Top Sellers Bout.</h3>
            </div>
            <div class="opacity-0 group-hover:opacity-100 transition-all duration-300 translate-x-2 group-hover:translate-x-0 flex items-center gap-1.5 bg-yellow-500/10 border border-yellow-500/20 px-2 sm:px-2.5 py-1 rounded-lg shrink-0 hidden sm:flex">
                <span class="text-[8px] font-bold text-yellow-400 uppercase tracking-widest whitespace-nowrap">View Details</span>
                <i class="fas fa-external-link-alt text-yellow-400 text-[9px]"></i>
            </div>
        </div>
        <p class="dash-card-value text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= htmlspecialchars($top_boutique_name) ?>"><?= htmlspecialchars($top_boutique_name) ?></p>
        
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="topBoutiqueChart"></canvas>
        </div>

        <div class="mt-auto relative z-10">
            <?php if ($top_boutique_name !== 'N/A'): ?>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1 mt-2">Overall Total</p>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-sm sm:text-base lg:text-lg font-bold text-white bg-white/10 px-2.5 py-0.5 rounded-full uppercase truncate">Qty: <?= number_format($top_boutique_qty) ?></span>
                <span class="text-sm sm:text-base lg:text-lg font-bold text-emerald-400 bg-emerald-500/10 px-2.5 py-0.5 rounded-full uppercase truncate">₱<?= number_format($top_boutique_amount, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-purple-500/30 transition-all duration-500 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all"></div>
        <div class="flex items-center gap-2 sm:gap-4 mb-3 sm:mb-4">
            <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 border border-purple-500/20 shadow-lg shadow-purple-500/5">
                <i class="fas fa-shopping-bag text-lg sm:text-xl"></i>
            </div>
            <h3 class="dash-card-title text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em]" title="Sales">Sales</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="₱<?= number_format($total_sales, 2) ?>">₱<?= number_format($total_sales, 2) ?></p>
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="monthlyActivityChart"></canvas>
        </div>

        <div class="flex items-center gap-2 mt-auto pt-2 relative z-10">
            <span class="text-[10px] font-bold text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded-full uppercase truncate">Total Amount</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-pink-500/30 transition-all duration-500 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-500/10 rounded-full blur-2xl group-hover:bg-pink-500/20 transition-all"></div>
        <div class="flex items-center gap-2 sm:gap-4 mb-3 sm:mb-4">
            <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-pink-500/10 flex items-center justify-center text-pink-400 border border-pink-500/20 shadow-lg shadow-pink-500/5">
                <i class="fas fa-box text-lg sm:text-xl"></i>
            </div>
            <h3 class="dash-card-title text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em]" title="Total Qty">Total Qty</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($total_sales_qty) ?>"><?= number_format($total_sales_qty) ?></p>
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="totalQtyChart"></canvas>
        </div>
        <div class="flex items-center gap-2 mt-auto pt-2 relative z-10">
            <span class="text-[10px] font-bold text-pink-400 bg-pink-500/10 px-2 py-0.5 rounded-full uppercase truncate">Quantity Sold</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-blue-500/30 transition-all duration-500 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all"></div>
        <div class="flex items-center gap-2 sm:gap-4 mb-3 sm:mb-4">
            <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20 shadow-lg shadow-blue-500/5">
                <i class="fas fa-undo-alt text-lg sm:text-xl"></i>
            </div>
            <h3 class="dash-card-title text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em]" title="Returns">Returns</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="₱<?= number_format($total_returns, 2) ?>">₱<?= number_format($total_returns, 2) ?></p>
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="returnsChart"></canvas>
        </div>
        <div class="flex items-center gap-2 mt-auto pt-2 relative z-10">
            <span class="text-[10px] font-bold text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded-full uppercase truncate"><?= number_format($total_returns_count) ?> Total Returns</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-emerald-500/30 transition-all duration-500 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
        <div class="flex items-center gap-2 sm:gap-4 mb-3 sm:mb-4">
            <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 shadow-lg shadow-emerald-500/5">
                <i class="fas fa-truck-loading text-lg sm:text-xl"></i>
            </div>
            <h3 class="dash-card-title text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em]" title="Received">Received</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($total_received_qty) ?>"><?= number_format($total_received_qty) ?></p>
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="receivedChart"></canvas>
        </div>
        <div class="flex items-center gap-2 mt-auto pt-2 relative z-10">
            <span class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full uppercase truncate"><?= number_format($receiving_count) ?> Batch</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-amber-500/30 transition-all duration-500 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all"></div>
        <div class="flex items-center gap-2 sm:gap-4 mb-3 sm:mb-4">
            <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-400 border border-amber-500/20 shadow-lg shadow-amber-500/5">
                <i class="fas fa-store text-lg sm:text-xl"></i>
            </div>
            <h3 class="dash-card-title text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em]" title="Stores">Stores</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($total_stores) ?>"><?= number_format($total_stores) ?></p>
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="storesChart"></canvas>
        </div>
        <div class="flex items-center gap-2 mt-auto pt-2 relative z-10">
            <span class="text-[10px] font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full uppercase truncate">Total</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-orange-500/30 transition-all duration-500 flex flex-col h-[420px]">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-orange-500/10 rounded-full blur-2xl group-hover:bg-orange-500/20 transition-all"></div>
        <div class="flex items-center gap-2 sm:gap-4 mb-3 sm:mb-4">
            <div class="dash-card-icon w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-orange-500/10 flex items-center justify-center text-orange-400 border border-orange-500/20 shadow-lg shadow-orange-500/5">
                <i class="fas fa-signal text-lg sm:text-xl"></i>
            </div>
            <h3 class="dash-card-title text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em]" title="Activity">Activity</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($active_stores_count) ?>/<?= number_format($total_stores) ?>"><?= number_format($active_stores_count) ?><span class="text-sm text-gray-500 font-medium">/<?= number_format($total_stores) ?></span></p>
        <div class="w-full relative flex-1 min-h-[150px] my-2 z-10">
            <canvas id="activityChart"></canvas>
        </div>
        <div class="flex items-center gap-2 mt-auto pt-2 relative z-10">
            <span class="text-[10px] font-bold text-orange-400 bg-orange-500/10 px-2 py-0.5 rounded-full uppercase truncate">Active vs Total</span>
        </div>
    </div>
</div>



<?php if ($is_admin): ?>
<!-- Top Sellers Charts Grid -->
<?php
$tc_labels = [];
$tc_data = [];
if (!empty($top_stores_ranking)) {
    $i = 0;
    foreach($top_stores_ranking as $st) {
        if ($i >= 5) break;
        $name = !empty($st['sname']) ? $st['sname'] : $st['store_code'];
        $tc_labels[] = $st['store_code'] . ' - ' . $name;
        $tc_data[] = (float)$st['total_sales'];
        $i++;
    }
}
$tb_labels = [];
$tb_data = [];
if (!empty($top_boutique_ranking)) {
    $i = 0;
    foreach($top_boutique_ranking as $st) {
        if ($i >= 5) break;
        $name = !empty($st['sname']) ? $st['sname'] : $st['store_code'];
        $tb_labels[] = $st['store_code'] . ' - ' . $name;
        $tb_data[] = (float)$st['total_sales'];
        $i++;
    }
}
?>


<!-- Top Sellers Modal -->
<div id="top-sellers-modal" class="fixed inset-0 z-[105] hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTopSellersModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100%-2rem)] max-w-2xl glass-panel overflow-hidden">
        <?php 
        $tot_c_qty = !empty($top_stores_ranking) ? array_sum(array_column($top_stores_ranking, 'total_qty')) : 0;
        $tot_c_amt = !empty($top_stores_ranking) ? array_sum(array_column($top_stores_ranking, 'total_sales')) : 0;
        ?>
        <div class="p-5 border-b border-white/5 flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white/5 gap-4">
            <h3 class="text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-trophy text-[#3b82f6]"></i> Top Selling Stores (Concession)
            </h3>
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <div class="flex flex-1 sm:flex-none items-center gap-2 bg-gradient-to-r from-blue-500/10 to-transparent border border-blue-500/20 px-3 py-1.5 rounded-lg shadow-inner">
                    <i class="fas fa-box text-blue-400 text-[10px]"></i>
                    <div class="flex flex-col leading-tight">
                        <span class="text-[8px] text-blue-400/80 font-bold uppercase tracking-wider">Total Qty</span>
                        <span class="text-xs font-black text-blue-300"><?= number_format($tot_c_qty) ?></span>
                    </div>
                </div>
                <div class="flex flex-1 sm:flex-none items-center gap-2 bg-gradient-to-r from-emerald-500/10 to-transparent border border-emerald-500/20 px-3 py-1.5 rounded-lg shadow-inner">
                    <i class="fas fa-coins text-emerald-400 text-[10px]"></i>
                    <div class="flex flex-col leading-tight">
                        <span class="text-[8px] text-emerald-400/80 font-bold uppercase tracking-wider">Total Amount</span>
                        <span class="text-xs font-black text-emerald-300">₱<?= number_format($tot_c_amt, 2) ?></span>
                    </div>
                </div>
                <button onclick="closeTopSellersModal()" class="text-gray-400 hover:text-white transition-colors ml-auto sm:ml-2 bg-white/5 hover:bg-white/10 w-8 h-8 flex items-center justify-center rounded-lg">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-4 border-b border-white/5 grid grid-cols-12 gap-4 text-xs font-bold text-gray-500 uppercase tracking-wider px-6">
            <div class="col-span-1 text-center">Rank</div>
            <div class="col-span-5">Store</div>
            <div class="col-span-3 text-right">Quantity</div>
            <div class="col-span-3 text-right">Amount</div>
        </div>
        <div class="p-2 max-h-[60vh] overflow-y-auto scrollbar-thin scrollbar-thumb-white/10">
            <div class="space-y-2 p-2">
                <?php if (empty($top_stores_ranking)): ?>
                    <div class="text-xs text-gray-500 italic text-center py-4">No data available</div>
                <?php else: ?>
                    <?php $rank = 1; foreach($top_stores_ranking as $st): ?>
                    <div class="grid grid-cols-12 gap-4 items-center text-[11px] bg-white/5 px-4 py-3 rounded-xl border border-white/10 hover:bg-white/10 transition-colors group">
                        <div class="col-span-1 flex justify-center items-center">
                            <?php if ($rank == 1): ?>
                                <i class="fas fa-crown text-yellow-400 text-lg drop-shadow-[0_0_8px_rgba(250,204,21,0.5)]" title="1st Place"></i>
                            <?php elseif ($rank == 2): ?>
                                <i class="fas fa-medal text-gray-300 text-base" title="2nd Place"></i>
                            <?php elseif ($rank == 3): ?>
                                <i class="fas fa-award text-amber-600 text-base" title="3rd Place"></i>
                            <?php else: ?>
                                <span class="font-black text-[#3b82f6] group-hover:scale-110 transition-transform"><?= $rank ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-5 flex flex-col min-w-0">
                            <span class="font-bold text-white text-xs truncate"><?= htmlspecialchars($st['store_code']) ?></span>
                            <?php if ($st['sname']): ?>
                                <span class="text-[10px] text-gray-400 font-normal truncate"><?= htmlspecialchars($st['sname']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-3 text-right flex items-center justify-end gap-2">
                            <span class="text-gray-400 text-[10px] uppercase">Qty</span>
                            <span class="font-black text-gray-200 text-xs"><?= number_format($st['total_qty']) ?></span>
                        </div>
                        <div class="col-span-3 text-right">
                            <span class="font-black text-emerald-400 text-sm">₱<?= number_format($st['total_sales'], 0) ?></span>
                        </div>
                    </div>
                    <?php $rank++; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Boutique Modal -->
<div id="top-boutique-modal" class="fixed inset-0 z-[105] hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTopBoutiqueModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100%-2rem)] max-w-2xl glass-panel overflow-hidden">
        <?php 
        $tot_b_qty = !empty($top_boutique_ranking) ? array_sum(array_column($top_boutique_ranking, 'total_qty')) : 0;
        $tot_b_amt = !empty($top_boutique_ranking) ? array_sum(array_column($top_boutique_ranking, 'total_sales')) : 0;
        ?>
        <div class="p-5 border-b border-white/5 flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white/5 gap-4">
            <h3 class="text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-crown text-yellow-400"></i> Top Selling Stores (Boutique)
            </h3>
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <div class="flex flex-1 sm:flex-none items-center gap-2 bg-gradient-to-r from-yellow-500/10 to-transparent border border-yellow-500/20 px-3 py-1.5 rounded-lg shadow-inner">
                    <i class="fas fa-box text-yellow-400 text-[10px]"></i>
                    <div class="flex flex-col leading-tight">
                        <span class="text-[8px] text-yellow-400/80 font-bold uppercase tracking-wider">Total Qty</span>
                        <span class="text-xs font-black text-yellow-300"><?= number_format($tot_b_qty) ?></span>
                    </div>
                </div>
                <div class="flex flex-1 sm:flex-none items-center gap-2 bg-gradient-to-r from-emerald-500/10 to-transparent border border-emerald-500/20 px-3 py-1.5 rounded-lg shadow-inner">
                    <i class="fas fa-coins text-emerald-400 text-[10px]"></i>
                    <div class="flex flex-col leading-tight">
                        <span class="text-[8px] text-emerald-400/80 font-bold uppercase tracking-wider">Total Amount</span>
                        <span class="text-xs font-black text-emerald-300">₱<?= number_format($tot_b_amt, 2) ?></span>
                    </div>
                </div>
                <button onclick="closeTopBoutiqueModal()" class="text-gray-400 hover:text-white transition-colors ml-auto sm:ml-2 bg-white/5 hover:bg-white/10 w-8 h-8 flex items-center justify-center rounded-lg">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-4 border-b border-white/5 grid grid-cols-12 gap-4 text-xs font-bold text-gray-500 uppercase tracking-wider px-6">
            <div class="col-span-1 text-center">Rank</div>
            <div class="col-span-5">Store</div>
            <div class="col-span-3 text-right">Quantity</div>
            <div class="col-span-3 text-right">Amount</div>
        </div>
        <div class="p-2 max-h-[60vh] overflow-y-auto scrollbar-thin scrollbar-thumb-white/10">
            <div class="space-y-2 p-2">
                <?php if (empty($top_boutique_ranking)): ?>
                    <div class="text-xs text-gray-500 italic text-center py-4">No data available</div>
                <?php else: ?>
                    <?php $rank = 1; foreach($top_boutique_ranking as $st): ?>
                    <div class="grid grid-cols-12 gap-4 items-center text-[11px] bg-white/5 px-4 py-3 rounded-xl border border-white/10 hover:bg-white/10 transition-colors group">
                        <div class="col-span-1 flex justify-center items-center">
                            <?php if ($rank == 1): ?>
                                <i class="fas fa-crown text-yellow-400 text-lg drop-shadow-[0_0_8px_rgba(250,204,21,0.5)]" title="1st Place"></i>
                            <?php elseif ($rank == 2): ?>
                                <i class="fas fa-medal text-gray-300 text-base" title="2nd Place"></i>
                            <?php elseif ($rank == 3): ?>
                                <i class="fas fa-award text-amber-600 text-base" title="3rd Place"></i>
                            <?php else: ?>
                                <span class="font-black text-yellow-500 group-hover:scale-110 transition-transform"><?= $rank ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-5 flex flex-col min-w-0">
                            <span class="font-bold text-white text-xs truncate"><?= htmlspecialchars($st['store_code']) ?></span>
                            <?php if ($st['sname']): ?>
                                <span class="text-[10px] text-gray-400 font-normal truncate"><?= htmlspecialchars($st['sname']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-3 text-right flex items-center justify-end gap-2">
                            <span class="text-gray-400 text-[10px] uppercase">Qty</span>
                            <span class="font-black text-gray-200 text-xs"><?= number_format($st['total_qty']) ?></span>
                        </div>
                        <div class="col-span-3 text-right">
                            <span class="font-black text-emerald-400 text-sm">₱<?= number_format($st['total_sales'], 0) ?></span>
                        </div>
                    </div>
                    <?php $rank++; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function openTopSellersModal() {
    const modal = document.getElementById('top-sellers-modal');
    if (modal) {
        document.body.appendChild(modal);
        modal.classList.remove('hidden');
        history.pushState({ modal: 'top-sellers' }, '', location.href);
    }
}

function closeTopSellersModal(fromPopState = false) {
    const modal = document.getElementById('top-sellers-modal');
    if (modal && !modal.classList.contains('hidden')) {
        modal.classList.add('hidden');
        if (fromPopState !== true && history.state && history.state.modal === 'top-sellers') {
            history.back();
        }
    }
}

window.addEventListener('popstate', function(event) {
    if (typeof closeTopSellersModal === 'function') closeTopSellersModal(true);
    if (typeof closeTopBoutiqueModal === 'function') closeTopBoutiqueModal(true);
});

document.addEventListener('DOMContentLoaded', function() {


    const ctx = document.getElementById('monthlyActivityChart');
    if (ctx) {
        const ctx2d = ctx.getContext('2d');
        // Gradient Background
        const gradient = ctx2d.createLinearGradient(0, 0, 0, 350);
        gradient.addColorStop(0, 'rgba(168, 85, 247, 0.4)');
        gradient.addColorStop(1, 'rgba(168, 85, 247, 0.0)');

        const labels = <?= json_encode(array_values($chart_labels ?? [])) ?>;
        const salesData = <?= json_encode(array_values($chart_sales_values ?? [])) ?>;
        const qtyData = <?= json_encode(array_values($chart_qty_values ?? [])) ?>;

        new Chart(ctx2d, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Daily Sales (₱)',
                        data: salesData,
                        borderColor: '#a855f7',
                        borderWidth: 4,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.45,
                        yAxisID: 'y',
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#a855f7',
                        pointBorderWidth: 2,
                        pointRadius: (labels.length > 31) ? 0 : 3,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#a855f7',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Daily Quantity',
                        data: qtyData,
                        borderColor: '#ec4899',
                        borderWidth: 3,
                        borderDash: [5, 5],
                        borderDashOffset: 0,
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.45,
                        yAxisID: 'y1',
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#ec4899',
                        pointBorderWidth: 2,
                        pointRadius: (labels.length > 31) ? 0 : 3,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#ec4899',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                animations: {
                    borderDashOffset: {
                        animation: true,
                        duration: 2000,
                        easing: 'linear',
                        from: 20,
                        to: 0,
                        loop: true
                    }
                },
                animation: {
                    delay: (context) => {
                        let delay = 0;
                        if (context.type === 'data' && context.mode === 'default' && !context.dropped) {
                            delay = context.dataIndex * 40;
                            context.dropped = true;
                        }
                        return delay;
                    },
                    duration: 1500,
                    easing: 'easeOutQuart'
                },
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { 
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 10, family: 'Outfit', weight: 'bold' },
                        bodyFont: { size: 12, family: 'Outfit', weight: 'bold' },
                        padding: 12,
                        cornerRadius: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.datasetIndex === 0) {
                                    label += '₱ ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                                } else {
                                    label += context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: { color: 'rgba(255, 255, 255, 0.03)', drawBorder: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 9, family: 'Outfit' },
                            callback: function(value) {
                                if (value >= 1000) return '₱' + (value/1000) + 'k';
                                return '₱' + value;
                            }
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { display: false },
                        ticks: {
                            color: '#ec4899',
                            font: { size: 9, family: 'Outfit' },
                            callback: function(value) {
                                return value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 8, family: 'Outfit', weight: 'bold' },
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 8
                        }
                    }
                }
            }
        });
    }

    // New Data Arrays from PHP
    const returnsData = <?= json_encode(array_values($chart_returns_values ?? [])) ?: '[]' ?>;
    const receivedData = <?= json_encode(array_values($chart_received_values ?? [])) ?: '[]' ?>;
    const txnData = <?= json_encode(array_values($chart_txn_values ?? [])) ?: '[]' ?>;
    const activeStoresCount = <?= (int)$active_stores_count ?>;
    const totalStoresCount = <?= (int)$total_stores ?>;

    // Reusable function for Mini Line Charts
    function createMiniLineChart(canvasId, labels, data, colorHex, yAxisLabel) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const ctx2d = ctx.getContext('2d');
        
        const hex2rgb = (hex) => {
            let result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '168, 85, 247';
        };
        const rgb = hex2rgb(colorHex);
        
        const gradient = ctx2d.createLinearGradient(0, 0, 0, 150);
        gradient.addColorStop(0, `rgba(${rgb}, 0.4)`);
        gradient.addColorStop(1, `rgba(${rgb}, 0.0)`);

        new Chart(ctx2d, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: yAxisLabel,
                    data: data,
                    borderColor: colorHex,
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.45,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: colorHex,
                    pointBorderWidth: 2,
                    pointRadius: (labels.length > 31) ? 0 : 2,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: colorHex,
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 9, family: 'Outfit', weight: 'bold' },
                        bodyFont: { size: 11, family: 'Outfit', weight: 'bold' },
                        padding: 8,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                let val = context.parsed.y;
                                let isNegative = yAxisLabel === 'Returns (₱)' && val !== 0;
                                let sign = isNegative ? '-' : '';
                                if (yAxisLabel.includes('₱')) {
                                    return sign + '₱ ' + val.toLocaleString(undefined, {minimumFractionDigits: 2});
                                }
                                return sign + val.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: { color: 'rgba(255, 255, 255, 0.03)', drawBorder: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 8, family: 'Outfit' },
                            callback: function(value) {
                                let isNegative = yAxisLabel === 'Returns (₱)' && value !== 0;
                                let sign = isNegative ? '-' : '';
                                if (value >= 1000) return sign + (yAxisLabel.includes('₱') ? '₱' : '') + (value/1000) + 'k';
                                return sign + (yAxisLabel.includes('₱') ? '₱' : '') + value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 8, family: 'Outfit', weight: 'bold' },
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 6
                        }
                    }
                }
            }
        });
    }

    // Reusable function for Mini Doughnut Charts
    function createMiniDoughnutChart(canvasId, labels, data, colors) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    hoverBorderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        bodyFont: { size: 11, family: 'Outfit', weight: 'bold' },
                        padding: 8,
                        cornerRadius: 8,
                        displayColors: true
                    }
                }
            }
        });
    }

    const chartLabels = <?= json_encode(array_values($chart_labels ?? [])) ?: '[]' ?>;
    const chartQtyData = <?= json_encode(array_values($chart_qty_values ?? [])) ?: '[]' ?>;

    createMiniLineChart('totalQtyChart', chartLabels, chartQtyData, '#ec4899', 'Quantity Sold');
    createMiniLineChart('returnsChart', chartLabels, returnsData.map(v => Math.abs(v)), '#3b82f6', 'Returns (₱)');
    createMiniLineChart('receivedChart', chartLabels, receivedData, '#10b981', 'Received');
    createMiniLineChart('storesChart', chartLabels, txnData, '#f59e0b', 'Daily Transactions');
    
    createMiniDoughnutChart('activityChart', ['Active', 'Inactive'], [activeStoresCount, Math.max(0, totalStoresCount - activeStoresCount)], ['#f97316', 'rgba(255,255,255,0.1)']);

    window.openTopBoutiqueModal = function() {
        const m = document.getElementById('top-boutique-modal');
        if(m) {
            document.body.appendChild(m);
            m.classList.remove('hidden');
            m.classList.add('flex');
            history.pushState({ modal: 'top-boutique' }, '', location.href);
        }
    }
    window.closeTopBoutiqueModal = function(fromPopState = false) {
        const m = document.getElementById('top-boutique-modal');
        if(m && !m.classList.contains('hidden')) {
            m.classList.add('hidden');
            m.classList.remove('flex');
            if (fromPopState !== true && history.state && history.state.modal === 'top-boutique') {
                history.back();
            }
        }
    }

    // Top Sellers Doughnut Charts configuration
    const doughnutOptions = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        animation: {
            animateScale: true,
            animateRotate: true,
            duration: 2500,
            easing: 'easeOutQuart'
        },
        hover: {
            mode: 'index',
            intersect: true,
            animationDuration: 400
        },
        plugins: {
            legend: { 
                position: 'right', 
                labels: { 
                    color: '#94a3b8', 
                    font: { size: 10, family: 'Outfit', weight: 'bold' }, 
                    usePointStyle: true, 
                    padding: 15,
                    boxWidth: 8
                } 
            },
            tooltip: { 
                backgroundColor: '#0f172a', 
                titleFont: { size: 11, family: 'Outfit', weight: 'bold' }, 
                bodyFont: { size: 13, family: 'Outfit', weight: 'bold' }, 
                padding: 14, 
                cornerRadius: 12, 
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                displayColors: true,
                boxPadding: 6,
                callbacks: { 
                    label: function(c) { return ' ₱ ' + c.parsed.toLocaleString(undefined, {minimumFractionDigits: 2}); } 
                } 
            }
        }
    };

    const tcLabels = <?= json_encode($tc_labels ?? []) ?>;
    const tcData = <?= json_encode($tc_data ?? []) ?>;
    if (tcLabels.length > 0 && document.getElementById('topConcessionChart')) {
        new Chart(document.getElementById('topConcessionChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: tcLabels,
                datasets: [{
                    data: tcData,
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'],
                    borderWidth: 0,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#ffffff',
                    hoverOffset: 12
                }]
            },
            options: doughnutOptions
        });
    }

    const tbLabels = <?= json_encode($tb_labels ?? []) ?>;
    const tbData = <?= json_encode($tb_data ?? []) ?>;
    if (tbLabels.length > 0 && document.getElementById('topBoutiqueChart')) {
        new Chart(document.getElementById('topBoutiqueChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: tbLabels,
                datasets: [{
                    data: tbData,
                    backgroundColor: ['#facc15', '#f97316', '#ef4444', '#14b8a6', '#0ea5e9'],
                    borderWidth: 0,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#ffffff',
                    hoverOffset: 12
                }]
            },
            options: doughnutOptions
        });
    }
});
</script>
