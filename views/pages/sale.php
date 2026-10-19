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
    $is_admin        = $_SESSION['is_admin'] ?? ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);
    $can_submit      = $_SESSION['can_submit'] ?? ($role === 'user');
    $can_edit        = $_SESSION['can_edit'] ?? false;
    $can_delete      = $_SESSION['can_delete'] ?? false;
}

require_once 'includes/db.php';
$db = db_connect();

$system_settings       = get_system_settings($db);
$promo_master_enabled  = !empty($system_settings['promo_enabled']);
$promo_stores_raw      = $system_settings['promo_stores'] ?? 'ALL';

// Check if promo applies to the current store
$promo_enabled = false;
if ($promo_master_enabled) {
    if ($promo_stores_raw === 'ALL' || empty($promo_stores_raw)) {
        $promo_enabled = true;
    } else {
        $allowed_stores = json_decode($promo_stores_raw, true);
        if (!is_array($allowed_stores)) {
            $allowed_stores = array_map('trim', explode(',', $promo_stores_raw));
        }
        if ($sale_store_code === 'MULTI' || $sale_store_code === 'ALL' || in_array($sale_store_code, $allowed_stores)) {
            $promo_enabled = true;
        }
    }
}

$promo_name      = $system_settings['promo_name'] ?? 'RL Shoe Bag Promo';
$promo_min_spend = floatval($system_settings['promo_min_spend'] ?? 1999.00);
$promo_item_name = $system_settings['promo_item_name'] ?? 'RL Shoe Bag';
$promo_item_no   = $system_settings['promo_item_no'] ?? '475552';
$promo_style_code = $system_settings['promo_style_code'] ?? 'RACT95002T26';
$promo_start_date = $system_settings['promo_start_date'] ?? '2026-10-01';
$promo_end_date   = $system_settings['promo_end_date'] ?? '';

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
$recent_stmt = $db->prepare("SELECT s.*, sc.sname FROM sales s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci $where ORDER BY s.created_at DESC, s.id ASC LIMIT ? OFFSET ?");
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
    <div class="glass-panel border border-white/5 shadow-xl mb-10 min-h-[70vh] flex flex-col overflow-hidden">
        <div class="px-5 py-2.5 border-b border-white/5 bg-slate-800/25 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-plus-circle text-green-400 text-xs"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Sale Entry</h3>
            </div>
            <span id="entry-count-badge" class="text-[9px] font-bold bg-green-500/10 text-green-400 border border-green-500/20 px-1.5 py-0.5 rounded">1 item</span>
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
                                <div class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg border transition-all bg-slate-900 border-white/5 text-gray-500 peer-checked:!border-purple-500/50 peer-checked:!bg-purple-500/10 peer-checked:!text-purple-400">
                                    <i class="fas fa-clock text-[9px]"></i>
                                    <span class="text-[9px] font-bold uppercase tracking-tighter">Current</span>
                                </div>
                            </label>

                            <!-- Backdate Option -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="page_date_type" value="backdate" class="peer sr-only" onchange="handleDateTypeChange(this.value)">
                                <div id="backdate-btn-content" class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg border transition-all overflow-hidden relative bg-slate-900 border-white/5 text-gray-500 peer-checked:!border-purple-500/50 peer-checked:!bg-purple-500/10 peer-checked:!text-purple-400">
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
                    (function initBackdatePicker() {
                        if (typeof flatpickr === 'undefined') { setTimeout(initBackdatePicker, 250); return; }
                        const dateEl = document.getElementById('page_custom_date');
                        if (!dateEl) return;
                        if (dateEl._flatpickr) dateEl._flatpickr.destroy();
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
                                    <button type="button" class="px-3 py-1.5 rounded bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-bold uppercase transition-all fp-set">Today</button>
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
                    })();
                })();
            </script>

            <div>
                <?php if ($promo_enabled): ?>
                <!-- Promo / Gift With Purchase (GWP) Banner -->
                <div id="promo-banner" class="glass-panel border border-pink-500/30 bg-gradient-to-r from-purple-950/40 via-slate-900/60 to-pink-950/40 rounded-xl p-2.5 sm:p-3 mb-3 shadow-lg relative overflow-hidden animate-fade-in">
                    <div class="flex items-center justify-between gap-3 relative z-10 flex-wrap">
                        
                        <!-- Left: Promo Title -->
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-pink-500/20 border border-pink-500/30 flex items-center justify-center text-pink-400 shrink-0 shadow-sm">
                                <i class="fas fa-gift text-sm animate-bounce"></i>
                            </div>
                            <h4 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider truncate"><?= htmlspecialchars($promo_name) ?></h4>
                        </div>

                        <!-- Right: Status Badge & Freebie Toggle with Stepper -->
                        <div class="flex items-center gap-2 sm:gap-2.5 shrink-0 flex-wrap">
                            <!-- Qualified Count Badge -->
                            <div id="promo-earned-badge" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-pink-500/10 border border-pink-500/20 text-[9px] sm:text-[10px] font-black text-pink-300 uppercase tracking-wider shadow-sm">
                                <i class="fas fa-gift text-[9px] text-pink-400"></i>
                                <span id="promo-earned-count">0 Gifts</span>
                            </div>

                            <!-- Manual Promo Gift Toggle with Reduced Label -->
                            <div class="flex items-center gap-1.5">
                                <label class="flex items-center gap-2 cursor-pointer select-none bg-slate-900/80 hover:bg-slate-900 border border-pink-500/30 hover:border-pink-500/60 rounded-xl px-2.5 py-1.5 transition-all shadow-sm group" title="Toggle freebie for multiple/combined transactions">
                                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-pink-300 group-hover:text-pink-200 transition-colors flex items-center gap-1">
                                        <i class="fas fa-gift text-pink-400 text-[9px]"></i> Freebie
                                    </span>
                                    <div class="relative inline-flex items-center">
                                        <input type="checkbox" id="manual-promo-toggle" onchange="onManualPromoToggleChange(this.checked)" class="sr-only peer">
                                        <div class="w-7 h-4 bg-slate-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-pink-500 peer-checked:to-purple-500 border border-slate-600"></div>
                                    </div>
                                </label>

                                <!-- Stepper Input (shown when toggle is ON) -->
                                <div id="banner-manual-controls" class="hidden flex items-center gap-1.5 animate-fade-in">
                                    <div class="flex items-center bg-slate-900/90 border border-pink-500/50 rounded-lg px-1.5 py-0.5 shadow-inner">
                                        <span class="text-[8px] sm:text-[8.5px] font-black uppercase text-pink-300/80 px-1 tracking-wider">Qty:</span>
                                        <button type="button" onclick="adjustManualGiftQty(-1)" class="w-5 h-5 rounded bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-[10px] font-bold transition-all hover:scale-105 active:scale-95" title="Decrease gift quantity">
                                            <i class="fas fa-minus text-[8px]"></i>
                                        </button>
                                        <input type="number" id="manual-gift-qty-input" min="1" max="99" value="1" oninput="onManualGiftQtyChange(this)" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-8 bg-transparent text-center text-xs font-black text-white focus:outline-none select-all" title="Number of free gifts">
                                        <button type="button" onclick="adjustManualGiftQty(1)" class="w-5 h-5 rounded bg-white/5 hover:bg-white/10 text-white flex items-center justify-center text-[10px] font-bold transition-all hover:scale-105 active:scale-95" title="Increase gift quantity">
                                            <i class="fas fa-plus text-[8px]"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endif; ?>

                <div id="entry-rows" class="space-y-4">
                    <div class="entry-row glass-panel border border-white/5 shadow-lg overflow-hidden bg-[#0d1527]/30 animate-slide-in">
                        <div class="px-4 py-2 bg-white/5 border-b border-white/5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="entry-title text-[10px] font-black text-gray-400 uppercase tracking-widest">Entry #1</span>
                                <span class="entry-gift-indicator hidden items-center gap-1 px-2 py-0.5 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-[8.5px] font-black uppercase tracking-wider shadow-sm animate-pulse" title="Qualified for FREE <?= htmlspecialchars($promo_item_name) ?>">
                                    <i class="fas fa-gift text-pink-400 text-[8px]"></i> Free Gift Qualified
                                </span>
                            </div>
                            <button type="button" onclick="removeRow(this)" class="remove-btn hidden text-red-500/50 hover:text-red-500 transition-colors flex items-center gap-1.5 group">
                                <span class="text-[9px] font-bold uppercase tracking-wider opacity-0 group-hover:opacity-100 transition-opacity">Remove</span>
                                <i class="fas fa-trash-alt text-[10px]"></i>
                            </button>
                        </div>
                        <div class="p-3 md:p-4 grid grid-cols-1 md:grid-cols-6 gap-2.5 md:gap-4">
                            <div class="relative flex items-stretch md:col-span-1">
                                <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-green-400/80 uppercase tracking-widest z-10">Item #</span>
                                <input type="number" name="item_no" min="0" oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-l-xl px-3 py-2 flex-1 text-xs text-white focus:outline-none focus:border-green-500/50 font-medium focus:text-base md:focus:text-xs focus:tracking-widest md:focus:tracking-normal transition-all" placeholder="100123">
                                <button type="button" onclick="startBarcodeScanForRow(this)" class="bg-purple-600/20 border border-l-0 border-white/10 px-3 rounded-r-xl text-purple-400 hover:bg-purple-600/30 transition-all flex items-center justify-center">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-2 md:col-span-2">
                                <div class="relative">
                                    <span class="absolute top-0 -translate-y-1/2 left-2 px-1 bg-[#0d1527] text-[7.5px] font-black text-green-400/80 uppercase tracking-widest z-10">Price (₱)</span>
                                    <input type="text" name="base_price" oninput="formatCurrencyInput(this); if(typeof calculateFinalPrice === 'function') calculateFinalPrice(this.closest('.entry-row'));" class="bg-slate-900/50 border border-white/10 rounded-xl px-2 py-2 w-full text-xs text-white focus:outline-none focus:border-green-500/50 font-medium" placeholder="0.00">
                                </div>
                                <div class="relative">
                                    <span class="absolute top-0 -translate-y-1/2 left-2 px-1 bg-[#0d1527] text-[7.5px] font-black text-green-400/80 uppercase tracking-widest z-10">Disc (%)</span>
                                    <select name="discount" onchange="if(typeof calculateFinalPrice === 'function') calculateFinalPrice(this.closest('.entry-row'));" class="bg-slate-900/50 border border-white/10 rounded-xl px-2 py-2 w-full text-xs text-white focus:outline-none focus:border-green-500/50 font-medium appearance-none cursor-pointer">
                                        <option value="0">0%</option>
                                        <option value="10">10%</option>
                                        <option value="20">20%</option>
                                        <option value="30">30%</option>
                                    </select>
                                    <i class="fas fa-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 text-[9px] pointer-events-none"></i>
                                </div>
                                <div class="relative">
                                    <span class="absolute top-0 -translate-y-1/2 left-2 px-1 bg-[#0d1527] text-[7.5px] font-black text-green-400/80 uppercase tracking-widest z-10">Final (₱)</span>
                                    <input type="text" name="amount_sold" oninput="formatCurrencyInput(this); if(typeof calculateFinalPrice === 'function') calculateFinalPrice(this.closest('.entry-row'), true);" class="bg-slate-900/50 border border-white/10 rounded-xl px-2 py-2 w-full text-xs text-green-400 focus:outline-none focus:border-green-500/50 font-bold" placeholder="0.00">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 md:contents">
                                <div class="relative col-span-1 md:col-span-1">
                                    <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-green-400/80 uppercase tracking-widest z-10">Qty</span>
                                    <input type="number" name="quantity" min="0" max="1" maxlength="1" oninput="this.value = this.value.replace(/[^01]/g, '').substring(0, 1);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-xl px-3 py-2 w-full text-xs text-white focus:outline-none focus:border-green-500/50 font-medium text-center md:text-left" placeholder="0">
                                </div>
                                <div class="relative col-span-2 md:col-span-2">
                                    <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-green-400/80 uppercase tracking-widest z-10">Details</span>
                                    <input type="text" name="item_details" readonly class="bg-slate-900/50 border border-white/10 rounded-xl px-3 py-2 w-full text-xs text-gray-400 focus:outline-none font-medium cursor-not-allowed truncate" placeholder="Code | Style | Color | Size">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 sm:mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 border-t border-white/5 pt-3 sm:pt-4">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="button" onclick="addRow()" class="flex-1 sm:flex-initial h-[38px] sm:h-[42px] px-3.5 sm:px-4 rounded-lg bg-slate-700/40 hover:bg-slate-700/60 text-green-400 border border-white/10 text-[9px] sm:text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg hover:-translate-y-0.5">
                        <i class="fas fa-plus-circle"></i> Add Another Entry
                    </button>
                    
                    <button type="button" onclick="startBarcodeScan('item_no')" class="sm:hidden h-[38px] px-3.5 rounded-lg bg-purple-600/40 hover:bg-purple-600/60 text-purple-400 border border-white/10 text-[9px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg hover:-translate-y-0.5">
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
                        <div id="summary-gift-pill" class="hidden flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-[8.5px] font-black uppercase tracking-wider animate-pulse whitespace-nowrap">
                            <i class="fas fa-gift text-pink-400 text-[8.5px]"></i>
                            <span id="summary-gift-count">0 Gifts</span>
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
                                   class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold focus:text-lg md:focus:text-sm focus:tracking-widest md:focus:tracking-normal" 
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
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">SRP (₱)</label>
                            <div class="relative group">
                                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 font-bold group-focus-within:text-blue-400 transition-colors">₱</span>
                                <input type="text" name="base_price" id="edit-base" required oninput="formatCurrencyInput(this); calculateEditFinal();"
                                       class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-10 pr-5 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold" 
                                       placeholder="0.00">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Disc (%)</label>
                            <div class="relative group">
                                <i class="fas fa-percent absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors pointer-events-none"></i>
                                <select name="discount" id="edit-discount" onchange="calculateEditFinal();"
                                       class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-12 pr-10 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold appearance-none cursor-pointer">
                                    <option value="0">0%</option>
                                    <option value="10">10%</option>
                                    <option value="20">20%</option>
                                    <option value="30">30%</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[10px]"></i>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Final Price (₱)</label>
                            <div class="relative group">
                                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 font-bold group-focus-within:text-blue-400 transition-colors">₱</span>
                                <input type="text" name="amount_sold" id="edit-amount" required oninput="formatCurrencyInput(this);"
                                       class="w-full bg-slate-950/50 border border-white/10 rounded-2xl pl-10 pr-5 py-4 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold" 
                                       placeholder="0.00">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Quantity</label>
                            <div class="relative group">
                                <i class="fas fa-layer-group absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                                <input type="number" name="quantity" id="edit-qty" required min="0" max="1" maxlength="1" oninput="this.value = this.value.replace(/[^01]/g, '').substring(0, 1);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();"
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
    <?php if ($show_history_table): ?>
    <div id="submitted-sales-container" class="mt-4">
        <?php include 'views/pages/sale_table_partial.php'; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    // ── Number Formatting Logic ──────────────────────────────
    window.formatCurrencyInput = function(input) {
        if (!input.value) return;
        let val = input.value.replace(/[^0-9.]/g, '');
        let parts = val.split('.');
        if (parts.length > 2) {
            parts = [parts[0], parts.slice(1).join('')];
        }
        if (parts[0]) {
            parts[0] = parseInt(parts[0], 10).toLocaleString('en-US');
        }
        input.value = parts.join('.');
    };

    window.calculateEditFinal = function() {
        const base = parseFloat(document.getElementById('edit-base').value.replace(/,/g, '')) || 0;
        const disc = parseFloat(document.getElementById('edit-discount').value) || 0;
        const final = base - (base * (disc / 100));
        document.getElementById('edit-amount').value = final.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };

    // ── GWP Promo Configuration & Helpers ─────────────────────
    window.promoConfig = {
        enabled: <?= $promo_enabled ? 'true' : 'false' ?>,
        minSpend: <?= (float)$promo_min_spend ?>,
        promoName: <?= json_encode($promo_name) ?>,
        giftName: <?= json_encode($promo_item_name) ?>,
        defaultItemNo: <?= json_encode($promo_item_no) ?>,
        styleCode: <?= json_encode($promo_style_code) ?>
    };

    window.manualPromoGiftClaimed = false;

    window.onManualPromoToggleChange = function(isChecked) {
        const promoConfig = window.promoConfig || {};
        if (!promoConfig.enabled) {
            const toggle = document.getElementById('manual-promo-toggle');
            if (toggle) toggle.checked = false;
            if (typeof showStatusModal === 'function') {
                showStatusModal(false, 'Promo gift is currently disabled in Promo Settings.', 'Promo Inactive');
            }
            return;
        }

        if (isChecked) {
            window.manualPromoGiftClaimed = true;
            const controls = document.getElementById('banner-manual-controls');
            if (controls) controls.classList.remove('hidden');

            const qtyInput = document.getElementById('manual-gift-qty-input');
            if (qtyInput && (!qtyInput.value || parseInt(qtyInput.value) < 1)) {
                qtyInput.value = '1';
            }

            window.updateSummary();
            setTimeout(() => {
                if (qtyInput) {
                    qtyInput.focus();
                    qtyInput.select();
                }
            }, 50);

            if (typeof showStatusModal === 'function') {
                showStatusModal(true, 'Freebie enabled. You can adjust the quantity for multiple/combined transactions.', 'Freebie Enabled');
            }
        } else {
            window.removeManualPromoGift();
        }
    };

    window.toggleManualPromoGift = function() {
        const toggle = document.getElementById('manual-promo-toggle');
        if (toggle) {
            toggle.checked = !toggle.checked;
            window.onManualPromoToggleChange(toggle.checked);
        }
    };

    window.adjustManualGiftQty = function(delta) {
        const input = document.getElementById('manual-gift-qty-input');
        if (!input) return;
        let current = parseInt(input.value) || 1;
        let next = current + delta;
        if (next < 1) next = 1;
        if (next > 99) next = 99;
        input.value = next;
        window.updateSummary();
    };

    window.onManualGiftQtyChange = function(input) {
        let val = parseInt(input.value) || 1;
        if (val < 1) val = 1;
        if (val > 99) val = 99;
        input.value = val;
        window.updateSummary();
    };

    window.removeManualPromoGift = function(silent = false) {
        window.manualPromoGiftClaimed = false;
        const toggle = document.getElementById('manual-promo-toggle');
        if (toggle) toggle.checked = false;

        const controls = document.getElementById('banner-manual-controls');
        if (controls) controls.classList.add('hidden');

        const qtyInput = document.getElementById('manual-gift-qty-input');
        if (qtyInput) qtyInput.value = '1';

        window.updateSummary();
        if (!silent && typeof showStatusModal === 'function') {
            showStatusModal(false, 'Promo Freebie removed from this sale.', 'Freebie Removed');
        }
    };

    // ── Form Logic ───────────────────────────────────────────
    function updateBadge() {
        const rows = document.querySelectorAll('.entry-row');
        const countBadge = document.getElementById('entry-count-badge');
        if (countBadge) {
            countBadge.textContent = rows.length + (rows.length === 1 ? ' item' : ' items');
        }
        rows.forEach((r, i) => {
            const titleEl = r.querySelector('.entry-title');
            if (titleEl) titleEl.textContent = `Entry #${i + 1}`;
            const rem = r.querySelector('.remove-btn');
            if (rem) {
                rem.style.opacity = rows.length > 1 ? '1' : '0';
                rem.style.pointerEvents = rows.length > 1 ? 'all' : 'none';
                if (rows.length > 1) {
                    rem.classList.remove('hidden');
                } else {
                    rem.classList.add('hidden');
                }
            }
        });
    }

    window.updateSummary = function() {
        let items = 0, qty = 0, total = 0, giftsCount = 0;
        const promoConfig = window.promoConfig || {};
        const promoEnabled = !!promoConfig.enabled;
        const minSpend = parseFloat(promoConfig.minSpend) || 1999;

        document.querySelectorAll('.entry-row').forEach(row => {
            const itmInput = row.querySelector('[name="item_no"]');
            const itm = itmInput ? itmInput.value.trim() : '';
            const amtInput = row.querySelector('[name="amount_sold"]');
            const amt = parseFloat(amtInput ? amtInput.value.replace(/,/g, '') : 0) || 0;
            const qtyInput = row.querySelector('[name="quantity"]');
            let qVal = qtyInput ? qtyInput.value.trim() : '';

            // Auto-default qty to 1 if item has valid price/item# and qty is empty or 0
            if ((qVal === '' || qVal === '0') && (amt > 0 || itm.length === 6) && itm !== '0') {
                qVal = '1';
                if (qtyInput) qtyInput.value = '1';
            }

            const q = parseInt(qVal) || 0;
            if (itm) items++;
            qty += q;
            const rowTotal = amt * q;
            total += rowTotal;

            // Check if this specific entry qualifies for Promo Gift (Current 1,999 rule)
            // (When manual promo gift is claimed, manual quantity takes precedence)
            const indicator = row.querySelector('.entry-gift-indicator');
            if (promoEnabled && indicator) {
                const isQualified = (!window.manualPromoGiftClaimed && rowTotal >= minSpend && minSpend > 0 && itm !== '0' && itm !== '');
                if (isQualified) {
                    indicator.classList.remove('hidden');
                    indicator.classList.add('inline-flex');
                    giftsCount++;
                    items++;
                    qty++;
                } else {
                    indicator.classList.add('hidden');
                    indicator.classList.remove('inline-flex');
                }
            } else if (indicator) {
                indicator.classList.add('hidden');
                indicator.classList.remove('inline-flex');
            }
        });

        // If manual promo gift is enabled, add the cashier's specified gifts quantity
        if (promoEnabled && window.manualPromoGiftClaimed) {
            const qtyInput = document.getElementById('manual-gift-qty-input');
            let manualQty = parseInt(qtyInput ? qtyInput.value : 1) || 1;
            if (manualQty < 1) manualQty = 1;

            giftsCount = manualQty;
            items += manualQty;
            qty += manualQty;
        }

        // Update earned count badge in banner
        const promoCountEl = document.getElementById('promo-earned-count');
        if (promoCountEl) {
            promoCountEl.textContent = giftsCount === 1 ? '1 Gift' : `${giftsCount} Gifts`;
        }

        // Update summary box numbers
        const summaryItemsEl = document.getElementById('summary-items');
        const summaryQtyEl = document.getElementById('summary-qty');
        const summaryTotalEl = document.getElementById('summary-total');
        if (summaryItemsEl) summaryItemsEl.textContent = items;
        if (summaryQtyEl) summaryQtyEl.textContent = qty;
        if (summaryTotalEl) summaryTotalEl.textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits:2});

        // Summary Gift Pill
        const giftPill = document.getElementById('summary-gift-pill');
        const giftCountSpan = document.getElementById('summary-gift-count');
        if (giftPill && giftCountSpan) {
            if (giftsCount > 0) {
                giftCountSpan.textContent = giftsCount === 1 ? '1 Free Gift' : `${giftsCount} Free Gifts`;
                giftPill.classList.remove('hidden');
                giftPill.classList.add('flex');
            } else {
                giftPill.classList.add('hidden');
                giftPill.classList.remove('flex');
            }
        }
    };

    window.addRow = function () {
        const tpl = document.querySelector('.entry-row');
        if (!tpl) return;
        const row = tpl.cloneNode(true);
        
        // Reset regular inputs
        row.querySelectorAll('input').forEach(i => {
            i.value = '';
            i.classList.remove('border-red-500/50', 'focus:border-red-500/50', 'ring-2', 'ring-green-500/50', 'border-green-500/50');
            i.classList.add('border-white/10', 'focus:border-green-500/50');
        });
        row.querySelectorAll('select').forEach(s => s.value = '0');
        row.querySelectorAll('.item-error-msg').forEach(e => e.remove());

        // Hide gift indicator on newly added row
        const indicator = row.querySelector('.entry-gift-indicator');
        if (indicator) {
            indicator.classList.add('hidden');
            indicator.classList.remove('inline-flex');
        }
        
        const removeBtn = row.querySelector('.remove-btn');
        if (removeBtn) {
            removeBtn.classList.remove('hidden');
            removeBtn.style.opacity = '1';
            removeBtn.style.pointerEvents = 'all';
        }
        
        document.getElementById('entry-rows').appendChild(row);
        updateBadge();

        const itemInput = row.querySelector('[name="item_no"]');
        if (itemInput) {
            itemInput.addEventListener('input', function() { 
                if (typeof window.lookupPrismPrice === 'function') {
                    window.lookupPrismPrice(this); 
                }
            });
            itemInput.focus();
        }
        window.updateSummary();
    };

    window.removeRow = function (btn) {
        if (typeof showConfirmModal === 'function') {
            showConfirmModal('Are you sure you want to remove this entry row?', () => {
                btn.closest('.entry-row').remove();
                updateBadge();
                window.updateSummary();
            }, 'Remove Entry');
        } else {
            btn.closest('.entry-row').remove();
            updateBadge();
            window.updateSummary();
        }
    };

    window.clearForm = function (skipConfirm = false) {
        const doClear = () => {
            const rows = document.querySelectorAll('.entry-row');
            rows.forEach((r, i) => { if (i > 0) r.remove(); });
            const firstRow = rows[0];
            if (firstRow) {
                firstRow.querySelectorAll('input').forEach(i => {
                    i.value = '';
                    i.classList.remove('border-red-500/50', 'focus:border-red-500/50', 'ring-2', 'ring-green-500/50', 'border-green-500/50');
                    i.classList.add('border-white/10', 'focus:border-green-500/50');
                });
                firstRow.querySelectorAll('select').forEach(s => s.value = '0');
                firstRow.querySelectorAll('.item-error-msg').forEach(e => e.remove());
                
                const indicator = firstRow.querySelector('.entry-gift-indicator');
                if (indicator) {
                    indicator.classList.add('hidden');
                    indicator.classList.remove('inline-flex');
                }
            }

            if (typeof window.removeManualPromoGift === 'function') {
                window.removeManualPromoGift(true);
            }

            window.updateSummary();
            updateBadge();
        };

        if (skipConfirm) {
            doClear();
        } else if (typeof showConfirmModal === 'function') {
            showConfirmModal('Are you sure you want to clear all current entry data?', doClear, 'Clear Form');
        } else {
            doClear();
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
        let valid = true;
        let hasErrors = false;
        let autoGiftsCount = 0;
        const promoConfig = window.promoConfig || {};
        const promoEnabled = !!promoConfig.enabled;
        const minSpend = parseFloat(promoConfig.minSpend) || 1999;
        const defaultGiftItemNo = promoConfig.defaultItemNo || '475552';
        
        document.querySelectorAll('.entry-row').forEach((row) => {
            const itemInput = row.querySelector('[name="item_no"]');
            const item = itemInput ? itemInput.value.trim() : '';
            const baseInput = row.querySelector('[name="base_price"]');
            const base = baseInput ? baseInput.value.replace(/,/g, '') : '0';
            const discInput = row.querySelector('[name="discount"]');
            const disc = discInput ? discInput.value : '0';
            const amtInput  = row.querySelector('[name="amount_sold"]');
            const amt  = amtInput ? amtInput.value.replace(/,/g, '') : '';
            const qtyInput  = row.querySelector('[name="quantity"]');
            const qty  = qtyInput ? qtyInput.value : '';
            
            if (row.querySelector('.item-error-msg')) {
                hasErrors = true;
            }
            
            if (item || amt !== '' || qty) {
                if (!item || amt === '' || !qty) {
                    valid = false;
                } else {
                    const numAmt = parseFloat(amt) || 0;
                    const numQty = parseInt(qty) || 0;
                    const rowTotal = numAmt * numQty;

                    // Main item entry
                    entries.push({ item_no: item, base_price: base, discount: disc, amount_sold: amt, quantity: qty });

                    // Directly append free gift row for this qualifying entry (existing 1,999 rule)
                    // (Only if cashier did not manually set promo gifts)
                    if (!window.manualPromoGiftClaimed && promoEnabled && rowTotal >= minSpend && minSpend > 0 && item !== '0') {
                        entries.push({
                            item_no: defaultGiftItemNo,
                            base_price: 0,
                            discount: 100,
                            amount_sold: 0,
                            quantity: 1,
                            is_gwp: 1
                        });
                        autoGiftsCount++;
                    }
                }
            }
        });

        // If manual promo gift is enabled, append separate gift entries (1 qty each)
        if (promoEnabled && window.manualPromoGiftClaimed) {
            const qtyInput = document.getElementById('manual-gift-qty-input');
            let manualQty = parseInt(qtyInput ? qtyInput.value : 1) || 1;
            if (manualQty < 1) manualQty = 1;

            for (let g = 0; g < manualQty; g++) {
                entries.push({
                    item_no: defaultGiftItemNo,
                    base_price: 0,
                    discount: 100,
                    amount_sold: 0,
                    quantity: 1,
                    is_gwp: 1
                });
            }
        }

        if (hasErrors) {
            showStatusModal(false, 'Please correct the invalid items before submitting.', 'Validation Error');
            return;
        }

        if (!valid) {
            showStatusModal(false, 'Please complete all fields (Item #, Price, and Qty) for each entry.', 'Incomplete Entry');
            return;
        }

        if (entries.length === 0) {
            showStatusModal(false, 'Please add at least one sale entry.', 'Empty List');
            return;
        }
        
        // Show Loader
        const loader = document.getElementById('loading-overlay');
        const p = loader?.querySelector('p');
        if (p) p.textContent = 'Processing Transaction...';
        loader?.classList.remove('opacity-0', 'pointer-events-none');

        const dateType = document.querySelector('input[name="page_date_type"]:checked')?.value || 'current';
        const customDate = document.getElementById('page_custom_date')?.value;
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
                loader?.classList.add('opacity-0', 'pointer-events-none');
                showStatusModal(res.success, res.message, res.success ? 'Sale Successful' : 'Sale Failed');
                if (res.success) { 
                    window.clearForm(true);
                    
                    const searchInput = document.querySelector('[name="search"]');
                    const startInput  = document.querySelector('[name="start_date"]');
                    const endInput    = document.querySelector('[name="end_date"]');
                    if (searchInput) searchInput.value = '';
                    if (startInput)  startInput.value = '';
                    if (endInput)    endInput.value = '';
                    refreshTable(1); 
                }
            }, 600);
        })
        .catch(() => {
            loader?.classList.add('opacity-0', 'pointer-events-none');
            showStatusModal(false, 'A network error occurred while submitting the sale.', 'Submission Error');
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

        const itemNo = row.querySelector('[data-label="Item #"]')?.innerText.trim() || '';
        const srp    = row.querySelector('[data-label="SRP"]')?.innerText.replace('₱', '').replace(/,/g, '').trim() || '';
        const disc   = row.querySelector('[data-label="Disc %"]')?.innerText.replace('%', '').trim() || '0';
        const price  = row.querySelector('[data-label="Final Price"]')?.innerText.replace('₱', '').replace(/,/g, '').trim() || '';
        const qty    = row.querySelector('[data-label="Qty"]')?.innerText.trim() || '';
        const rawDate = row.querySelector('[data-label="Date"]')?.getAttribute('data-date') || '';

        document.getElementById('edit-id').value = id;
        document.getElementById('edit-id-label').innerText = `Record ID: ${id}`;
        document.getElementById('edit-item-no').value = itemNo;
        document.getElementById('edit-base').value = srp ? parseFloat(srp).toLocaleString('en-US', {minimumFractionDigits: 2}) : '';
        document.getElementById('edit-discount').value = parseFloat(disc) || 0;
        document.getElementById('edit-amount').value = price ? parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2}) : '';
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
            base_price: this.base_price.value.replace(/,/g, ''),
            discount: this.discount.value,
            amount_sold: this.amount_sold.value.replace(/,/g, ''),
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
        if (!container) return;
        
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
            if (typeof window.initQuickFiltersState === 'function') window.initQuickFiltersState();
            if (typeof window.centerTableQuickFilters === 'function') window.centerTableQuickFilters();
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
        const searchInput    = document.querySelector('[name="search"]');
        const limitSelect    = document.querySelector('[name="limit"]');
        const startInput     = document.querySelector('[name="start_date"]');
        const endInput       = document.querySelector('[name="end_date"]');
        const storeFilter    = document.querySelector('[name="store_filter"]');
        const selectAll      = document.getElementById('selectAll');
        
        searchInput?.addEventListener('input', () => {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => refreshTable(1), 800);
        });
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
    
    // Call init on page load
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof window.initQuickFiltersState === 'function') window.initQuickFiltersState();
        if (typeof window.centerTableQuickFilters === 'function') window.centerTableQuickFilters();
    });

    window.showItemError = function(input, message) {
        window.removeItemError(input);
        const err = document.createElement('span');
        err.className = 'item-error-msg absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 px-2 py-0.5 bg-red-600 text-[8px] font-black text-white rounded uppercase tracking-widest z-20 shadow-md whitespace-nowrap animate-fade-in';
        err.textContent = message;
        input.closest('.relative').appendChild(err);
        input.classList.remove('border-white/10', 'focus:border-green-500/50', 'border-green-500/50');
        input.classList.add('border-red-500/50', 'focus:border-red-500/50');
    };

    window.removeItemError = function(input) {
        const wrapper = input.closest('.relative');
        const err = wrapper.querySelector('.item-error-msg');
        if (err) err.remove();
        input.classList.remove('border-red-500/50', 'focus:border-red-500/50');
        input.classList.add('border-white/10', 'focus:border-green-500/50');
    };

    window.itemValidationTimers = window.itemValidationTimers || new WeakMap();

    window.calculateFinalPrice = function(row, isManualFinal = false) {
        if (isManualFinal) {
            window.updateSummary();
            return;
        }
        const basePrice = parseFloat(row.querySelector('[name="base_price"]').value.replace(/,/g, '')) || 0;
        const discountPct = parseFloat(row.querySelector('[name="discount"]').value) || 0;
        let finalPrice = basePrice - (basePrice * (discountPct / 100));
        if (finalPrice < 0) finalPrice = 0;
        
        const finalInput = row.querySelector('[name="amount_sold"]');
        if (row.querySelector('[name="base_price"]').value !== '') {
            finalInput.value = finalPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        const qtyInput = row.querySelector('[name="quantity"]');
        if (qtyInput && (qtyInput.value === '' || qtyInput.value === '0') && basePrice > 0) {
            qtyInput.value = '1';
        }

        window.updateSummary();
    };

    window.lookupPrismPrice = function(input) {
        const item_no = input.value.trim();
        
        window.removeItemError(input);
        input.classList.remove('border-green-500/50');

        if (window.itemValidationTimers.has(input)) {
            clearTimeout(window.itemValidationTimers.get(input));
        }

        const row = input.closest('.entry-row');
        const amountInput = row ? row.querySelector('[name="amount_sold"]') : null;
        const baseInput = row ? row.querySelector('[name="base_price"]') : null;
        const detailsInput = row ? row.querySelector('[name="item_details"]') : null;

        if (item_no.length === 0) {
            if (baseInput) baseInput.value = '';
            if (amountInput) { amountInput.value = ''; window.updateSummary(); }
            if (detailsInput) detailsInput.value = '';
            return;
        }

        if (item_no === '0') {
            window.removeItemError(input);
            input.classList.add('border-green-500/50');
            if (baseInput) baseInput.value = '0';
            if (amountInput) {
                amountInput.value = '0';
                window.updateSummary();
            }
            if (detailsInput) {
                detailsInput.value = 'NO SALE';
            }
            const qtyInput = row ? row.querySelector('[name="quantity"]') : null;
            if (qtyInput) {
                qtyInput.value = '0';
            }
            return;
        }

        if (item_no.length === 6) {
            fetch(`api/get_prism_price.php?item_no=${encodeURIComponent(item_no)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    window.removeItemError(input);
                    input.classList.add('border-green-500/50');
                    if (baseInput) baseInput.value = res.srp ? parseFloat(res.srp).toLocaleString('en-US', {minimumFractionDigits: 2}) : '';
                    if (amountInput) {
                        amountInput.value = res.srp ? parseFloat(res.srp).toLocaleString('en-US', {minimumFractionDigits: 2}) : '';
                        amountInput.classList.add('ring-2', 'ring-green-500/50');
                        setTimeout(() => amountInput.classList.remove('ring-2', 'ring-green-500/50'), 1000);
                    }
                    const qtyInput = row ? row.querySelector('[name="quantity"]') : null;
                    if (qtyInput && (qtyInput.value === '' || qtyInput.value === '0')) {
                        qtyInput.value = '1';
                    }
                    if (detailsInput) {
                        const itemcode = res.itemcode || '';
                        const style = res.stylename || '';
                        const color = res.color || '';
                        const size = res.size || '';
                        const parts = [itemcode, style, color, size].filter(Boolean);
                        detailsInput.value = parts.join(' | ');
                    }
                    window.calculateFinalPrice(row);
                } else {
                    window.showItemError(input, 'Item # not found');
                    if (baseInput) baseInput.value = '';
                    if (amountInput) {
                        amountInput.value = '';
                        window.updateSummary();
                    }
                    if (detailsInput) detailsInput.value = '';
                }
            });
            return;
        }

        if (baseInput) baseInput.value = '';
        if (amountInput) { amountInput.value = ''; window.updateSummary(); }
        if (detailsInput) detailsInput.value = '';
        
        const timer = setTimeout(() => {
            if (input.value.trim().length > 0 && input.value.trim().length < 6) {
                window.showItemError(input, 'Invalid Item#');
            }
        }, 1000);
        window.itemValidationTimers.set(input, timer);
    };

    // Attach listener to initial rows
    document.querySelectorAll('[name="item_no"]').forEach(input => {
        input.addEventListener('input', function() {
            window.lookupPrismPrice(this);
        });
    });

    // Global listener for any input change in the entry rows
    document.getElementById('entry-rows')?.addEventListener('input', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') window.updateSummary();
    });
    document.getElementById('entry-rows')?.addEventListener('change', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') window.updateSummary();
    });

    initTableEvents();
    window.refreshSaleTable = refreshTable;
})();
</script>
