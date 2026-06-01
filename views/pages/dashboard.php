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

// Fetch stores for filter dropdown
$stores_list = [];
if ($is_full_admin || $is_admin_view) {
    $stores_res = $db->query("SELECT scode, sname FROM storecode ORDER BY sname ASC");
    if ($stores_res) {
        $stores_list = $stores_res->fetch_all(MYSQLI_ASSOC);
    }
} elseif ($is_multi_store_admin) {
    $assigned_data = $_SESSION['assigned_stores_data'] ?? [];
    $stores_list = array_map(function($s) {
        return ['scode' => $s['store_code'], 'sname' => $s['sname']];
    }, $assigned_data);
}

// 1. Total Sales
$sales_res = $db->query("SELECT SUM(line_total) as total_sales, SUM(quantity) as total_qty, COUNT(id) as total_count FROM sales WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' $store_clause");
$sales_data = $sales_res ? $sales_res->fetch_assoc() : ['total_sales' => 0, 'total_qty' => 0, 'total_count' => 0];
$total_sales = (float) ($sales_data['total_sales'] ?? 0.00);
$total_sales_qty = (int) ($sales_data['total_qty'] ?? 0);
$total_sales_count = (int) ($sales_data['total_count'] ?? 0);

// 1.1 Top Sellers (Admin Only) - Concession
$top_store_name = 'N/A';
$top_stores_ranking = [];
if ($is_admin) {
    $top_store_res = $db->query("SELECT s.store_code, sc.sname, SUM(s.line_total) as total_sales, SUM(s.quantity) as total_qty FROM sales s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci WHERE DATE(s.created_at) BETWEEN '$start_date' AND '$end_date' $store_clause GROUP BY s.store_code, sc.sname ORDER BY total_sales DESC");
    if ($top_store_res) {
        $top_stores_ranking = $top_store_res->fetch_all(MYSQLI_ASSOC);
        if (!empty($top_stores_ranking)) {
            $top = $top_stores_ranking[0];
            $top_store_name = $top['store_code'] . ($top['sname'] ? " (" . $top['sname'] . ")" : "");
        }
    }
}

// 1.2 Top Sellers (Admin Only) - Boutique
$top_boutique_name = 'N/A';
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
            }
        }
    }
}

// 2. Total Returns
$returns_res = $db->query("SELECT SUM(return_amount) as total_returns, COUNT(id) as total_count FROM returns WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' $store_clause");
$returns_data = $returns_res ? $returns_res->fetch_assoc() : ['total_returns' => 0, 'total_count' => 0];
$total_returns = (float) ($returns_data['total_returns'] ?? 0.00);
$total_returns_count = (int) ($returns_data['total_count'] ?? 0);

// 3. Total Receiving
$receiving_res = $db->query("SELECT SUM(quantity) as total_received, COUNT(id) as total_count FROM receiving WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' $store_clause");
$receiving_data = $receiving_res ? $receiving_res->fetch_assoc() : ['total_received' => 0, 'total_count' => 0];
$total_received_qty = (int) ($receiving_data['total_received'] ?? 0);
$receiving_count = (int) ($receiving_data['total_count'] ?? 0);

// 4. Total Stores
if ($is_store_admin) {
    $store_count_res = $db->query("SELECT COUNT(*) as total FROM storecode WHERE scode = '$session_store_code' AND scode != 'HO'");
} elseif ($is_multi_store_admin) {
    $store_count_res = $db->query("SELECT COUNT(*) as total FROM user_store_assignments WHERE user_id = " . intval($_SESSION['user_id'] ?? 0) . " AND store_code != 'HO'");
} else {
    $store_count_res = $db->query("SELECT COUNT(*) as total FROM storecode WHERE scode != 'HO'");
}
$total_stores = $store_count_res ? (int)$store_count_res->fetch_assoc()['total'] : 0;

// 4.1 Active Stores (with transactions in selected range)
$active_stores_res = $db->query("SELECT COUNT(DISTINCT store_code) as active FROM sales WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' $store_clause AND store_code != 'HO'");
$active_stores_count = $active_stores_res ? (int)$active_stores_res->fetch_assoc()['active'] : 0;

// Chart Data Generation
$chart_labels = [];
$chart_sales_values = [];
$chart_qty_values = [];

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
}

$chart_res = $db->query("SELECT DATE(created_at) as d, SUM(line_total) as total, SUM(quantity) as qty FROM sales WHERE (DATE(created_at) BETWEEN '$start_date' AND '$end_date') $store_clause GROUP BY DATE(created_at)");
if ($chart_res) {
    while($row = $chart_res->fetch_assoc()) {
        if (isset($chart_sales_values[$row['d']])) {
            $chart_sales_values[$row['d']] = (float)$row['total'];
            $chart_qty_values[$row['d']] = (int)$row['qty'];
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
</style>

<!-- Date Range Filter -->
<div class="glass-panel p-5 border border-white/5 mb-8 relative z-50">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
        
        <!-- Left Section: Title & Quick Filters -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-6 w-full xl:w-auto">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600/20 to-pink-600/20 flex items-center justify-center text-white border border-white/10 shadow-xl flex-shrink-0">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="flex flex-col">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest leading-none mb-1">Date Range Analytics</h3>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter">Live filtering applied to graphs and stats</p>
                </div>
            </div>
            
            <!-- Compact Quick Month Filter -->
            <div class="flex items-center gap-2 bg-[#0f172a] border border-white/10 rounded-lg p-1 shadow-inner w-full sm:w-max relative z-20">
                <!-- Year Dropdown -->
                <div class="relative group/year">
                    <select id="quick-year-select" onchange="changeQuickYearDropdown(this.value)" class="appearance-none bg-slate-800/80 border border-white/5 rounded pl-2 pr-6 py-1 text-[10px] font-bold text-white focus:outline-none focus:border-purple-500/50 cursor-pointer hover:bg-slate-700 transition-colors shadow-sm">
                        <?php 
                        $currYr = date('Y');
                        $selectedYr = isset($_GET['start_date']) ? date('Y', strtotime($_GET['start_date'])) : $currYr;
                        for($y = $currYr - 5; $y <= $currYr + 1; $y++) {
                            echo "<option value=\"$y\" " . ($y == $selectedYr ? "selected" : "") . ">$y</option>";
                        }
                        ?>
                    </select>
                    <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-[8px] text-gray-400 pointer-events-none group-hover/year:text-white transition-colors"></i>
                </div>
                
                <div class="w-[1px] h-3 bg-white/10"></div>
                
                <!-- Month Carousel -->
                <div class="flex items-center relative flex-1 sm:flex-none sm:w-[240px] overflow-hidden group/months">
                    <button type="button" onclick="scrollQuickMonths(-1)" class="absolute left-0 z-10 h-full px-1.5 bg-gradient-to-r from-[#0f172a] via-[#0f172a]/90 to-transparent flex items-center justify-start text-gray-400 hover:text-white transition-all"><i class="fas fa-chevron-left text-[8px]"></i></button>
                    
                    <div id="months-container" class="flex items-center gap-1 overflow-x-auto hide-scrollbar scroll-smooth w-full px-4">
                        <?php 
                        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        foreach($months as $idx => $m): 
                            $m_num = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                        ?>
                        <button type="button" onclick="selectQuickMonth(this, '<?= $m_num ?>')" class="flex-shrink-0 w-[calc(16.666%-3.33px)] py-1 rounded text-[9px] font-bold uppercase tracking-wider text-gray-500 hover:text-purple-400 hover:bg-white/5 transition-all month-btn text-center select-none border border-transparent">
                            <?= $m ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" onclick="scrollQuickMonths(1)" class="absolute right-0 z-10 h-full px-1.5 bg-gradient-to-l from-[#0f172a] via-[#0f172a]/90 to-transparent flex items-center justify-end text-gray-400 hover:text-white transition-all"><i class="fas fa-chevron-right text-[8px]"></i></button>
                </div>
            </div>
        </div>
        
        <form id="dashboard-filter-form" class="grid grid-cols-2 sm:grid-cols-3 gap-4 w-full xl:w-auto xl:flex-1 max-w-3xl mt-4 xl:mt-0" method="GET">
            <input type="hidden" name="action" value="dashboard">
            <div class="space-y-1 group">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1 group-hover:text-purple-400 transition-colors">Start Date</label>
                <div class="relative">
                    <i class="fas fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-600 group-hover:text-purple-400 transition-colors pointer-events-none"></i>
                    <input type="date" name="start_date" value="<?= $start_date ?>" onchange="this.form.submit()" onclick="this.showPicker()"
                           class="w-full bg-slate-900/80 border border-white/10 rounded-xl pl-9 pr-4 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 cursor-pointer transition-all">
                </div>
            </div>
            <div class="space-y-1 group">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1 group-hover:text-pink-400 transition-colors">End Date</label>
                <div class="relative">
                    <i class="fas fa-calendar-check absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-600 group-hover:text-pink-400 transition-colors pointer-events-none"></i>
                    <input type="date" name="end_date" value="<?= $end_date ?>" onchange="this.form.submit()" onclick="this.showPicker()"
                           class="w-full bg-slate-900/80 border border-white/10 rounded-xl pl-9 pr-4 py-2 text-xs text-white focus:outline-none focus:border-pink-500/50 cursor-pointer transition-all">
                </div>
            </div>
            <?php if ($is_admin): ?>
            <div class="space-y-1 group relative col-span-2 sm:col-span-1">
                <label class="text-[9px] font-bold text-gray-500 uppercase ml-1 group-hover:text-amber-400 transition-colors">Store Filter</label>
                <div class="relative" id="store-filter-container">
                    <i class="fas fa-store absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-600 group-hover:text-amber-400 transition-colors pointer-events-none z-10"></i>
                    
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
                    
                    <!-- Custom Trigger -->
                    <div id="store-filter-trigger" class="w-full bg-slate-900/80 border border-white/10 rounded-xl pl-9 pr-4 py-2 h-9 text-xs text-white flex items-center justify-between cursor-pointer focus:border-amber-500/50 transition-all hover:bg-white/5">
                        <span id="selected-store-label" class="truncate font-bold opacity-80 uppercase tracking-tight"><?= htmlspecialchars($current_store_label) ?></span>
                        <i class="fas fa-chevron-down text-[9px] text-gray-500 ml-2"></i>
                    </div>

                    <!-- Custom Menu -->
                    <div id="store-filter-menu" class="absolute top-[calc(100%+4px)] right-0 min-w-[280px] w-full bg-[#0f172a] border border-white/10 rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-[100] hidden max-h-64 overflow-y-auto overflow-x-hidden backdrop-blur-xl">
                        <div class="sticky top-0 bg-[#0f172a] p-2 border-b border-white/5 z-20">
                            <input type="text" id="store-search-filter" class="w-full bg-slate-900 border border-white/10 rounded-lg px-3 py-1.5 text-[10px] text-white focus:outline-none focus:border-amber-500/50" placeholder="Search store..." autocomplete="off">
                        </div>
                        <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $filter_store_code === '' ? 'bg-amber-500/10' : '' ?>" data-value="">
                            <span class="font-bold">All Stores</span>
                        </div>
                        <?php foreach($stores_list as $st): 
                            $sel = ($filter_store_code == $st['scode']);
                            $displayName = $st['scode'] . " - " . $st['sname'];
                        ?>
                            <div class="store-option px-3 py-2.5 text-[11px] text-white hover:bg-white/5 cursor-pointer flex justify-between items-center transition-all border-b border-white/5 last:border-0 <?= $sel ? 'bg-amber-500/10' : '' ?>" 
                                 data-value="<?= htmlspecialchars($st['scode']) ?>" 
                                 data-label="<?= htmlspecialchars($displayName) ?>">
                                <div class="flex flex-col min-w-0 flex-1">
                                    <span class="font-bold truncate"><?= htmlspecialchars($st['scode']) ?></span>
                                    <span class="text-[9px] text-gray-500 truncate uppercase tracking-tighter"><?= htmlspecialchars($st['sname']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Hidden input for form submission -->
                    <input type="hidden" name="store_code" id="store-filter-value" value="<?= htmlspecialchars($filter_store_code) ?>">
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<style>
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<script>
let currentQuickYear = parseInt(document.getElementById('quick-year-select').value);

function changeQuickYearDropdown(val) {
    currentQuickYear = parseInt(val);
    
    const form = document.getElementById('dashboard-filter-form');
    const sDate = form.querySelector('[name="start_date"]')?.value;
    let monthNum = '01';
    if (sDate) {
        const s = new Date(sDate);
        monthNum = String(s.getMonth() + 1).padStart(2, '0');
    } else {
        monthNum = String(new Date().getMonth() + 1).padStart(2, '0');
    }
    
    const btns = document.querySelectorAll('.month-btn');
    let targetBtn = btns[parseInt(monthNum) - 1];
    if (targetBtn) {
        selectQuickMonth(targetBtn, monthNum);
    }
}

function scrollQuickMonths(direction) {
    const container = document.getElementById('months-container');
    const scrollAmount = container.clientWidth;
    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

function selectQuickMonth(btn, monthNum) {
    const year = currentQuickYear;
    const startDate = `${year}-${monthNum}-01`;
    const endDateObj = new Date(year, parseInt(monthNum), 0); 
    const endDate = `${year}-${monthNum}-${String(endDateObj.getDate()).padStart(2, '0')}`;
    
    const form = document.getElementById('dashboard-filter-form');
    form.querySelector('[name="start_date"]').value = startDate;
    form.querySelector('[name="end_date"]').value = endDate;
    
    document.querySelectorAll('.month-btn').forEach(b => {
        b.classList.remove('!bg-purple-500/20', '!text-purple-400', 'shadow-sm', '!border-purple-500/50');
        b.classList.add('text-gray-500', 'border-transparent');
    });
    btn.classList.remove('text-gray-500', 'border-transparent');
    btn.classList.add('!bg-purple-500/20', '!text-purple-400', 'shadow-sm', '!border-purple-500/50');
    
    form.submit();
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dashboard-filter-form');
    const sDate = form.querySelector('[name="start_date"]')?.value;
    const eDate = form.querySelector('[name="end_date"]')?.value;
    
    let targetMonthIdx = new Date().getMonth(); // Default to current month
    let exactMatchFound = false;
    
    if (sDate && eDate) {
        const s = new Date(sDate);
        const e = new Date(eDate);
        const eLastDay = new Date(e.getFullYear(), e.getMonth() + 1, 0).getDate();
        
        // If exact month is selected
        if (s.getDate() === 1 && e.getDate() === eLastDay && s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear()) {
            currentQuickYear = s.getFullYear();
            const select = document.getElementById('quick-year-select');
            if (select) select.value = currentQuickYear;
            targetMonthIdx = s.getMonth();
            exactMatchFound = true;
        } else if (s.getFullYear() === currentQuickYear) {
            targetMonthIdx = s.getMonth();
            exactMatchFound = true;
        }
    }
    
    // Auto-scroll and highlight
    const btns = document.querySelectorAll('.month-btn');
    if (btns[targetMonthIdx]) {
        if (exactMatchFound) {
            // Apply strong active colors if specifically filtered
            btns[targetMonthIdx].classList.remove('text-gray-500', 'border-transparent');
            btns[targetMonthIdx].classList.add('!bg-purple-500/20', '!text-purple-400', 'shadow-sm', '!border-purple-500/50');
        } else {
            // Just subtly highlight current month if no strict filter
            btns[targetMonthIdx].classList.remove('text-gray-500');
            btns[targetMonthIdx].classList.add('!text-white', '!font-black');
        }
        
        // Scroll container to center the month
        setTimeout(() => {
            const container = document.getElementById('months-container');
            const btn = btns[targetMonthIdx];
            container.scrollTo({
                left: btn.offsetLeft - container.clientWidth/2 + btn.clientWidth/2,
                behavior: 'smooth'
            });
        }, 300);
    }
});
</script>

<div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
    <!-- Stats Cards -->
    <?php if ($is_admin): ?>
    <div onclick="openTopSellersModal()" class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-[#3b82f6]/30 transition-all duration-500 cursor-pointer z-50">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-[#3b82f6]/10 rounded-full blur-2xl group-hover:bg-[#3b82f6]/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-[#3b82f6]/10 flex items-center justify-center text-[#3b82f6] border border-[#3b82f6]/20 shadow-lg shadow-[#3b82f6]/5">
                <i class="fas fa-trophy text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Top Sellers Concession">Top Sellers Concession</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= htmlspecialchars($top_store_name) ?>"><?= htmlspecialchars($top_store_name) ?></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-[#3b82f6] bg-[#3b82f6]/10 px-2 py-0.5 rounded-full uppercase truncate">Click for Details</span>
        </div>
    </div>
    <div onclick="openTopBoutiqueModal()" class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-yellow-500/30 transition-all duration-500 cursor-pointer z-50">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-yellow-500/10 rounded-full blur-2xl group-hover:bg-yellow-500/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-400 border border-yellow-500/20 shadow-lg shadow-yellow-500/5">
                <i class="fas fa-crown text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Top Sellers Boutique">Top Sellers Boutique</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= htmlspecialchars($top_boutique_name) ?>"><?= htmlspecialchars($top_boutique_name) ?></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-yellow-400 bg-yellow-500/10 px-2 py-0.5 rounded-full uppercase truncate">Click for Details</span>
        </div>
    </div>
    <?php endif; ?>
    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-purple-500/30 transition-all duration-500">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 border border-purple-500/20 shadow-lg shadow-purple-500/5">
                <i class="fas fa-shopping-bag text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Sales">Sales</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="₱<?= number_format($total_sales, 2) ?>">₱<?= number_format($total_sales, 2) ?></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded-full uppercase truncate"><?= number_format($total_sales_count) ?> Trans</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-pink-500/30 transition-all duration-500">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-500/10 rounded-full blur-2xl group-hover:bg-pink-500/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-pink-500/10 flex items-center justify-center text-pink-400 border border-pink-500/20 shadow-lg shadow-pink-500/5">
                <i class="fas fa-box text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Total Qty">Total Qty</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($total_sales_qty) ?>"><?= number_format($total_sales_qty) ?></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-pink-400 bg-pink-500/10 px-2 py-0.5 rounded-full uppercase truncate">Quantity Sold</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-blue-500/30 transition-all duration-500">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20 shadow-lg shadow-blue-500/5">
                <i class="fas fa-undo-alt text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Returns">Returns</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($total_returns_count) ?>"><?= number_format($total_returns_count) ?></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded-full uppercase truncate">₱<?= number_format($total_returns, 0) ?></span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-emerald-500/30 transition-all duration-500">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 shadow-lg shadow-emerald-500/5">
                <i class="fas fa-truck-loading text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Received">Received</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($total_received_qty) ?>"><?= number_format($total_received_qty) ?></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full uppercase truncate"><?= number_format($receiving_count) ?> Batch</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-amber-500/30 transition-all duration-500">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-400 border border-amber-500/20 shadow-lg shadow-amber-500/5">
                <i class="fas fa-store text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Stores">Stores</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($total_stores) ?>"><?= number_format($total_stores) ?></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full uppercase truncate">Total</span>
        </div>
    </div>

    <div class="glass-panel p-4 sm:p-5 xl:p-6 border border-white/5 relative overflow-hidden group hover:border-orange-500/30 transition-all duration-500">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-orange-500/10 rounded-full blur-2xl group-hover:bg-orange-500/20 transition-all"></div>
        <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-orange-500/10 flex items-center justify-center text-orange-400 border border-orange-500/20 shadow-lg shadow-orange-500/5">
                <i class="fas fa-signal text-lg sm:text-xl"></i>
            </div>
            <h3 class="text-[10px] sm:text-xs font-black text-gray-500 uppercase tracking-widest sm:tracking-[0.2em] truncate" title="Activity">Activity</h3>
        </div>
        <p class="text-lg sm:text-xl min-[1400px]:text-lg xl:text-xl 2xl:text-2xl font-bold text-white mb-1 truncate" title="<?= number_format($active_stores_count) ?>/<?= number_format($total_stores) ?>"><?= number_format($active_stores_count) ?><span class="text-sm text-gray-500 font-medium">/<?= number_format($total_stores) ?></span></p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-orange-400 bg-orange-500/10 px-2 py-0.5 rounded-full uppercase truncate">Active vs Total</span>
        </div>
    </div>
</div>

<div class="w-full">
    <!-- Chart Section -->
    <div class="glass-panel p-8 border border-white/5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-8">
            <div class="flex-1">
                <h3 class="text-lg font-bold text-white tracking-wide uppercase flex items-center gap-2">
                    <i class="fas fa-chart-line text-purple-400"></i> Performance Activity
                </h3>
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1">Daily sales line graph overview</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 px-3 py-1 bg-purple-500/10 rounded-lg border border-purple-500/20">
                    <div class="w-2 h-2 rounded-full bg-purple-500 animate-pulse"></div>
                    <span class="text-[9px] font-black uppercase text-purple-400 tracking-tighter">Live Data View</span>
                </div>
            </div>
        </div>
        
        <div class="w-full relative h-[400px]">
            <canvas id="monthlyActivityChart"></canvas>
        </div>
    </div>
</div>

<?php if ($is_admin): ?>
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
    }
}

function closeTopSellersModal() {
    const modal = document.getElementById('top-sellers-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyActivityChart').getContext('2d');
    
    // Gradient Background
    const gradient = ctx.createLinearGradient(0, 0, 0, 350);
    gradient.addColorStop(0, 'rgba(168, 85, 247, 0.4)');
    gradient.addColorStop(1, 'rgba(168, 85, 247, 0.0)');

    const labels = <?= json_encode(array_values($chart_labels)) ?>;
    const salesData = <?= json_encode(array_values($chart_sales_values)) ?>;
    const qtyData = <?= json_encode(array_values($chart_qty_values)) ?>;

    new Chart(ctx, {
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
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: { 
                    display: true,
                    labels: {
                        color: '#64748b',
                        font: { size: 10, family: 'Outfit', weight: 'bold' },
                        usePointStyle: true,
                        padding: 20
                    }
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
                        font: { size: 10, family: 'Outfit' },
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
                        font: { size: 10, family: 'Outfit' },
                        callback: function(value) {
                            return value + ' qty';
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: {
                        color: '#64748b',
                        font: { size: 9, family: 'Outfit', weight: 'bold' },
                        maxRotation: 45,
                        minRotation: 45,
                        autoSkip: true,
                        maxTicksLimit: 15
                    }
                }
            }
        }
    });

    window.openTopBoutiqueModal = function() {
        const m = document.getElementById('top-boutique-modal');
        if(m) {
            document.body.appendChild(m);
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
    }
    window.closeTopBoutiqueModal = function() {
        const m = document.getElementById('top-boutique-modal');
        if(m) {
            m.classList.add('hidden');
            m.classList.remove('flex');
        }
    }
});
</script>
