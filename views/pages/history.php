<?php
if (!isset($_SESSION['user'])) exit;

$db = db_connect();
$username = $_SESSION['user'];



$tab    = $_SESSION['history_tab']    ?? 'sales';
$page   = intval($_SESSION['history_page'] ?? 1);
$limit  = intval($_SESSION['history_limit'] ?? 10);
$search = trim($_SESSION['history_search'] ?? '');

if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$allowed_tabs = ['sales', 'returns', 'receiving'];
if (!in_array($tab, $allowed_tabs)) $tab = 'sales';

$records = [];
$total_rows = 0;

// Check if admin
$is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');

// Filter by actual submission time (today) for non-admins
$today_clause = $is_admin ? "" : " AND DATE(system_timestamp) = CURRENT_DATE";
$order_by     = $is_admin ? "id DESC" : "system_timestamp DESC";

if ($tab === 'sales') {
    $sql = "SELECT id, item_no, amount_sold, quantity, line_total, created_at, system_timestamp " . ($is_admin ? ", username" : "") . "
            FROM sales 
            WHERE 1=1 " . ($is_admin ? "" : " AND username = ? ") . $today_clause;
    
    $count_sql = "SELECT COUNT(*) as total FROM sales WHERE 1=1 " . ($is_admin ? "" : " AND username = ? ") . $today_clause;
    
    if ($search !== '') {
        $sql .= " AND (item_no LIKE ? OR id LIKE ?)";
        $count_sql .= " AND (item_no LIKE ? OR id LIKE ?)";
    }
    
    $sql .= " ORDER BY $order_by LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $c_stmt = $db->prepare($count_sql);
    
    if ($is_admin) {
        if ($search !== '') {
            $lk = "%$search%";
            $stmt->bind_param("ssii", $lk, $lk, $limit, $offset);
            $c_stmt->bind_param("ss", $lk, $lk);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }
    } else {
        if ($search !== '') {
            $lk = "%$search%";
            $stmt->bind_param("ssiii", $username, $lk, $lk, $limit, $offset);
            $c_stmt->bind_param("sss", $username, $lk, $lk);
        } else {
            $stmt->bind_param("sii", $username, $limit, $offset);
            $c_stmt->bind_param("s", $username);
        }
    }
    
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $c_stmt->execute();
    $total_rows = $c_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
} elseif ($tab === 'returns') {
    $sql = "SELECT id, return_item, return_amount, reason, is_exchange, exchange_name, exchange_item, exchange_amount, created_at, system_timestamp " . ($is_admin ? ", username" : "") . "
            FROM returns 
            WHERE 1=1 " . ($is_admin ? "" : " AND username = ? ") . $today_clause;
    
    $count_sql = "SELECT COUNT(*) as total FROM returns WHERE 1=1 " . ($is_admin ? "" : " AND username = ? ") . $today_clause;
    
    if ($search !== '') {
        $sql .= " AND (return_item LIKE ? OR exchange_item LIKE ? OR reason LIKE ?)";
        $count_sql .= " AND (return_item LIKE ? OR exchange_item LIKE ? OR reason LIKE ?)";
    }
    
    $sql .= " ORDER BY $order_by LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $c_stmt = $db->prepare($count_sql);
    
    if ($is_admin) {
        if ($search !== '') {
            $lk = "%$search%";
            $stmt->bind_param("sssii", $lk, $lk, $lk, $limit, $offset);
            $c_stmt->bind_param("sss", $lk, $lk, $lk);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }
    } else {
        if ($search !== '') {
            $lk = "%$search%";
            $stmt->bind_param("ssssii", $username, $lk, $lk, $lk, $limit, $offset);
            $c_stmt->bind_param("ssss", $username, $lk, $lk, $lk);
        } else {
            $stmt->bind_param("sii", $username, $limit, $offset);
            $c_stmt->bind_param("s", $username);
        }
    }
    
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $c_stmt->execute();
    $total_rows = $c_stmt->get_result()->fetch_assoc()['total'] ?? 0;

} elseif ($tab === 'receiving') {
    $sql = "SELECT id, os_no, from_store, to_store, quantity, created_at, system_timestamp " . ($is_admin ? ", username" : "") . "
            FROM receiving 
            WHERE 1=1 " . ($is_admin ? "" : " AND username = ? ") . $today_clause;
            
    $count_sql = "SELECT COUNT(*) as total FROM receiving WHERE 1=1 " . ($is_admin ? "" : " AND username = ? ") . $today_clause;
    
    if ($search !== '') {
        $sql .= " AND (os_no LIKE ? OR from_store LIKE ? OR to_store LIKE ?)";
        $count_sql .= " AND (os_no LIKE ? OR from_store LIKE ? OR to_store LIKE ?)";
    }
    
    $sql .= " ORDER BY $order_by LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $c_stmt = $db->prepare($count_sql);
    
    if ($is_admin) {
        if ($search !== '') {
            $lk = "%$search%";
            $stmt->bind_param("sssii", $lk, $lk, $lk, $limit, $offset);
            $c_stmt->bind_param("sss", $lk, $lk, $lk);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }
    } else {
        if ($search !== '') {
            $lk = "%$search%";
            $stmt->bind_param("ssssii", $username, $lk, $lk, $lk, $limit, $offset);
            $c_stmt->bind_param("ssss", $username, $lk, $lk, $lk);
        } else {
            $stmt->bind_param("sii", $username, $limit, $offset);
            $c_stmt->bind_param("s", $username);
        }
    }
    
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $c_stmt->execute();
    $total_rows = $c_stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

$total_pages = max(1, ceil($total_rows / $limit));
if ($page > $total_pages) $page = $total_pages;
?>

<div class="pb-12 animate-fade-in">
    <!-- Tab Switcher -->
    <div class="flex items-center gap-2 border-b border-white/5 mb-6">
        <button onclick="switchTab('sales')" 
           class="px-4 py-3 text-xs font-bold tracking-wider uppercase border-b-2 transition-all <?= $tab === 'sales' ? 'border-purple-500 text-purple-400 bg-purple-500/5' : 'border-transparent text-gray-500 hover:text-gray-300' ?>">
            <i class="fas fa-shopping-cart mr-2"></i> Sales
        </button>
        <button onclick="switchTab('returns')" 
           class="px-4 py-3 text-xs font-bold tracking-wider uppercase border-b-2 transition-all <?= $tab === 'returns' ? 'border-orange-500 text-orange-400 bg-orange-500/5' : 'border-transparent text-gray-500 hover:text-gray-300' ?>">
            <i class="fas fa-undo mr-2"></i> Returns
        </button>
        <button onclick="switchTab('receiving')" 
           class="px-4 py-3 text-xs font-bold tracking-wider uppercase border-b-2 transition-all <?= $tab === 'receiving' ? 'border-cyan-500 text-cyan-400 bg-cyan-500/5' : 'border-transparent text-gray-500 hover:text-gray-300' ?>">
            <i class="fas fa-box-open mr-2"></i> Receiving
        </button>
    </div>

    <!-- Filters Grid -->
    <div class="glass-panel border border-white/5 shadow-xl p-4 mb-6">
        <form method="POST" action="history" id="history-filter-form">
            <input type="hidden" name="tab" id="hidden-tab" value="<?= $tab ?>">
            <input type="hidden" name="page" id="hidden-page" value="<?= $page ?>">
            
            <div class="flex flex-row gap-3 items-end w-full">
                <!-- Search -->
                <div class="relative flex-grow">
                    <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-purple-400 uppercase tracking-widest z-10">Search</span>
                    <div class="relative mt-1">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                        <input type="text" name="search" id="history-search" value="<?= htmlspecialchars($search) ?>" placeholder="Filter current view..." 
                               class="bg-slate-900/80 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-purple-500/50">
                    </div>
                </div>
                
                <!-- Limit -->
                <div class="relative w-24">
                    <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-purple-400 uppercase tracking-widest z-10">Limit</span>
                    <select name="limit" id="history-limit" class="bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2.5 mt-1 w-full text-xs text-white focus:outline-none focus:border-purple-500/50 cursor-pointer">
                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <script>
    function switchTab(tabName) {
        document.getElementById('hidden-tab').value = tabName;
        document.getElementById('hidden-page').value = 1;
        document.getElementById('history-filter-form').submit();
    }

    function goToPage(pageNo) {
        document.getElementById('hidden-page').value = pageNo;
        document.getElementById('history-filter-form').submit();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('history-filter-form');
        const searchInput = document.getElementById('history-search');
        const limitSelect = document.getElementById('history-limit');
        
        let timer;
        searchInput.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(() => {
                form.submit();
            }, 800);
        });
        
        limitSelect.addEventListener('change', function() {
            form.submit();
        });
    });
    </script>

    <!-- Data View -->
    <?php if (empty($records)): ?>
        <div class="glass-panel border border-white/5 shadow-xl p-16 text-center text-gray-500 rounded-2xl">
            <i class="fas fa-folder-open text-4xl mb-4 block opacity-10"></i>
            <p class="text-xs font-bold uppercase tracking-widest"><?= $is_admin ? "No transactions recorded yet" : "No transactions recorded today" ?></p>
        </div>
    <?php else: ?>
        <!-- Desktop Table Layout (hidden on small screens) -->
        <div class="hidden md:block glass-panel border border-white/5 shadow-xl overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-white/5 bg-slate-800/20 text-[10px] font-bold tracking-wider uppercase text-gray-400">
                        <?php if ($tab === 'sales'): ?>
                            <?php if($is_admin): ?><th class="px-6 py-3">User</th><?php endif; ?>
                            <th class="px-6 py-3">Item #</th>
                            <th class="px-6 py-3 text-center">Amount</th>
                            <th class="px-6 py-3 text-center">Quantity</th>
                            <th class="px-6 py-3 text-center">Line Total</th>
                            <th class="px-6 py-3 text-right">Date</th>
                        <?php elseif ($tab === 'returns'): ?>
                            <?php if($is_admin): ?><th class="px-6 py-3">User</th><?php endif; ?>
                            <th class="px-6 py-3">Returned Item</th>
                            <th class="px-6 py-3 text-center">Amount</th>
                            <th class="px-6 py-3">Reason</th>
                            <th class="px-6 py-3">Exchange Name</th>
                            <th class="px-6 py-3 text-center">Ex. Item #</th>
                            <th class="px-6 py-3 text-center">Ex. Amt</th>
                            <th class="px-6 py-3 text-center">Total</th>
                            <th class="px-6 py-3 text-right">Date</th>
                        <?php elseif ($tab === 'receiving'): ?>
                            <?php if($is_admin): ?><th class="px-6 py-3">User</th><?php endif; ?>
                            <th class="px-6 py-3">OS #</th>
                            <th class="px-6 py-3">From (Store)</th>
                            <th class="px-6 py-3">To (Store)</th>
                            <th class="px-6 py-3 text-center">Quantity</th>
                            <th class="px-6 py-3 text-right">Date</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-xs text-gray-300 font-medium">
                    <?php foreach ($records as $r): ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <?php if ($tab === 'sales'): ?>
                                <?php if($is_admin): ?><td class="px-6 py-4 font-bold text-purple-400"><?= htmlspecialchars($r['username']) ?></td><?php endif; ?>
                                <td class="px-6 py-4 text-white font-bold tracking-wide"><?= htmlspecialchars($r['item_no']) ?></td>
                                <td class="px-6 py-4 text-center font-bold text-emerald-400">₱<?= number_format($r['amount_sold'], 2) ?></td>
                                <td class="px-6 py-4 text-center text-purple-300 font-black"><?= $r['quantity'] ?></td>
                                <td class="px-6 py-4 text-center font-black text-emerald-300">₱<?= number_format($r['line_total'], 2) ?></td>
                                <td class="px-6 py-4 text-right text-gray-500 text-[10px]">
                                    <div><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                    <?php if (date('Y-m-d', strtotime($r['created_at'])) !== date('Y-m-d', strtotime($r['system_timestamp']))): ?>
                                        <div class="mt-1"><span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 whitespace-nowrap">BACKDATED</span></div>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($tab === 'returns'): ?>
                                <?php if($is_admin): ?><td class="px-6 py-4 font-bold text-purple-400"><?= htmlspecialchars($r['username']) ?></td><?php endif; ?>
                                <td class="px-6 py-4 text-white font-bold"><?= htmlspecialchars($r['return_item'] ?: 'Exchange Only') ?></td>
                                <td class="px-6 py-4 text-center font-bold text-red-400"><?= $r['return_amount'] != 0 ? '₱'.number_format(abs($r['return_amount']), 2) : '—' ?></td>
                                <td class="px-6 py-4 text-xs max-w-[120px] truncate" title="<?= htmlspecialchars($r['reason'] ?: '—') ?>"><?= htmlspecialchars($r['reason'] ?: '—') ?></td>
                                <td class="px-6 py-4 text-blue-300"><?= htmlspecialchars($r['exchange_name'] ?: '—') ?></td>
                                <td class="px-6 py-4 text-center font-mono text-blue-400"><?= $r['exchange_item'] ? '#'.$r['exchange_item'] : '—' ?></td>
                                <td class="px-6 py-4 text-center font-bold text-emerald-400"><?= $r['exchange_amount'] > 0 ? '₱'.number_format($r['exchange_amount'], 2) : '—' ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php 
                                    $net = ($r['exchange_amount'] ?? 0) + ($r['return_amount'] ?? 0);
                                    if ($net < 0): ?>
                                        <span class="text-red-400 font-black">-₱<?= number_format(abs($net), 2) ?></span>
                                    <?php elseif ($net > 0): ?>
                                        <span class="text-emerald-400 font-black">₱<?= number_format($net, 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-500 font-bold">₱0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right text-gray-500 text-[10px]">
                                    <div><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                    <?php if (date('Y-m-d', strtotime($r['created_at'])) !== date('Y-m-d', strtotime($r['system_timestamp']))): ?>
                                        <div class="mt-1"><span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 whitespace-nowrap">BACKDATED</span></div>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($tab === 'receiving'): ?>
                                <?php if($is_admin): ?><td class="px-6 py-4 font-bold text-purple-400"><?= htmlspecialchars($r['username']) ?></td><?php endif; ?>
                                <td class="px-6 py-4 text-cyan-400 font-bold"><?= htmlspecialchars($r['os_no']) ?></td>
                                <td class="px-6 py-4 font-medium text-gray-400"><?= htmlspecialchars($r['from_store']) ?></td>
                                <td class="px-6 py-4 font-medium text-gray-300"><?= htmlspecialchars($r['to_store']) ?></td>
                                <td class="px-6 py-4 text-center font-black text-white"><?= $r['quantity'] ?></td>
                                <td class="px-6 py-4 text-right text-gray-500 text-[10px]">
                                    <div><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                    <?php if (date('Y-m-d', strtotime($r['created_at'])) !== date('Y-m-d', strtotime($r['system_timestamp']))): ?>
                                        <div class="mt-1"><span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 whitespace-nowrap">BACKDATED</span></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards Layout (hidden on larger screens) -->
        <div class="md:hidden space-y-4">
            <?php foreach ($records as $r): ?>
                <div class="glass-panel border border-white/5 shadow-md p-4 bg-[#0d1527]/40 rounded-xl">
                    <div class="flex items-center justify-between border-b border-white/5 pb-2 mb-3">
                        <div class="flex flex-col gap-1">
                            <span class="text-[8px] font-black tracking-widest uppercase text-gray-500">
                                <?= date('M d, Y', strtotime($r['created_at'])) ?>
                                <?php if($is_admin): ?>
                                    <span class="ml-2 px-2 py-0.5 rounded bg-purple-500/10 text-purple-400 border border-purple-500/20"><?= htmlspecialchars($r['username']) ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if (date('Y-m-d', strtotime($r['created_at'])) !== date('Y-m-d', strtotime($r['system_timestamp']))): ?>
                                <span class="w-max px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">BACKDATED</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-xs">
                        <?php if ($tab === 'sales'): ?>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Item #</span>
                                <span class="text-white font-bold tracking-wide"><?= htmlspecialchars($r['item_no']) ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Line Total</span>
                                <span class="font-black text-emerald-400">₱<?= number_format($r['line_total'], 2) ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Amount</span>
                                <span class="font-bold text-gray-300">₱<?= number_format($r['amount_sold'], 2) ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Quantity</span>
                                <span class="font-black text-purple-300"><?= $r['quantity'] ?></span>
                            </div>
                        <?php elseif ($tab === 'returns'): ?>
                            <div class="col-span-2">
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Returned Item</span>
                                <span class="text-white font-bold"><?= htmlspecialchars($r['return_item'] ?: 'Exchange Only') ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Return Amt</span>
                                <span class="font-bold text-red-400"><?= $r['return_amount'] != 0 ? '₱'.number_format(abs($r['return_amount']), 2) : '—' ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Reason</span>
                                <span class="text-gray-400"><?= htmlspecialchars($r['reason'] ?: '—') ?></span>
                            </div>
                            
                            <?php if ($r['is_exchange']): ?>
                                <div class="col-span-2 border-t border-white/5 pt-2 mt-1">
                                    <span class="text-[8px] font-black text-blue-400 uppercase tracking-wider flex items-center gap-1 mb-1"><i class="fas fa-sync-alt"></i> Exchange Details</span>
                                </div>
                                <div>
                                    <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Exchange Name</span>
                                    <span class="text-blue-300 font-medium"><?= htmlspecialchars($r['exchange_name']) ?></span>
                                </div>
                                <div>
                                    <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Ex. Item #</span>
                                    <span class="font-mono text-blue-400">#<?= htmlspecialchars($r['exchange_item']) ?></span>
                                </div>
                                <div>
                                    <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Exchange Amt</span>
                                    <span class="font-black text-emerald-400">₱<?= number_format($r['exchange_amount'], 2) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-span-2 border-t border-white/5 pt-2 mt-1 flex items-center justify-between">
                                <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black">Net Transaction Total</span>
                                <?php 
                                $net = ($r['exchange_amount'] ?? 0) + ($r['return_amount'] ?? 0);
                                if ($net < 0): ?>
                                    <div class="text-red-400 font-black">-₱<?= number_format(abs($net), 2) ?></div>
                                <?php elseif ($net > 0): ?>
                                    <div class="text-emerald-400 font-black">₱<?= number_format($net, 2) ?></div>
                                <?php else: ?>
                                    <span class="text-gray-500 font-bold">₱0.00</span>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($tab === 'receiving'): ?>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">OS #</span>
                                <span class="text-cyan-400 font-bold"><?= htmlspecialchars($r['os_no']) ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">Quantity</span>
                                <span class="font-black text-white"><?= $r['quantity'] ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">From Store</span>
                                <span class="font-bold text-gray-400"><?= htmlspecialchars($r['from_store']) ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 uppercase tracking-wider block">To Store</span>
                                <span class="font-bold text-gray-300"><?= htmlspecialchars($r['to_store']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <div class="mt-6 flex items-center justify-between bg-slate-900/20 px-4 py-3 rounded-xl border border-white/5">
            <span class="text-[9px] font-bold text-gray-500 uppercase tracking-wider">Page <?= $page ?> of <?= $total_pages ?></span>
            <div class="flex items-center gap-1">
                <button onclick="goToPage(1)" 
                   class="w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-[10px]" title="First Page"><i class="fas fa-angle-double-left"></i></button>
                
                <?php if ($page > 1): ?>
                    <button onclick="goToPage(<?= $page - 1 ?>)" 
                       class="w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-xs"><i class="fas fa-chevron-left"></i></button>
                <?php endif; ?>

                <?php 
                for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): 
                    // Hide outer numbers on mobile to keep only 3 visible
                    $mobile_hide = ($i === $page-2 || $i === $page+2) ? 'hidden md:flex' : 'flex';
                ?>
                    <button onclick="goToPage(<?= $i ?>)" 
                       class="w-7 h-7 items-center justify-center rounded-lg text-xs font-black transition-all <?= $mobile_hide ?> <?= $i == $page ? 'bg-purple-600 text-white shadow-lg' : 'bg-white/5 text-gray-500 hover:text-white' ?>">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <button onclick="goToPage(<?= $page + 1 ?>)" 
                       class="w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-xs"><i class="fas fa-chevron-right"></i></button>
                <?php endif; ?>

                <button onclick="goToPage(<?= $total_pages ?>)" 
                   class="w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-[10px]" title="Last Page"><i class="fas fa-angle-double-right"></i></button>
            </div>
        </div>
    <?php endif; ?>
</div>
