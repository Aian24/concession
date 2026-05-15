<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h2 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500 mb-1">WooCommerce Sales Data</h2>
        <p class="text-gray-400">Live feed of orders from RustyLopez.com</p>
    </div>
    
    <div class="flex items-center gap-3 backdrop-blur-md bg-slate-800/60 p-2 rounded-2xl border border-white/10 shadow-xl">
        <button onclick="exportTable('csv')" class="px-5 py-2.5 bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 border border-blue-500/20 hover:border-blue-500/50 group">
            <i class="fas fa-file-csv group-hover:scale-110 transition-transform"></i> CSV
        </button>
        <button onclick="exportTable('xls')" class="px-5 py-2.5 bg-green-500/10 hover:bg-green-500/20 text-green-400 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 border border-green-500/20 hover:border-green-500/50 group">
            <i class="fas fa-file-excel group-hover:scale-110 transition-transform"></i> XLS
        </button>
        <button onclick="exportTable('txt')" class="px-5 py-2.5 bg-purple-500/10 hover:bg-purple-500/20 text-purple-400 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 border border-purple-500/20 hover:border-purple-500/50 group">
            <i class="fas fa-file-alt group-hover:scale-110 transition-transform"></i> TXT
        </button>
    </div>
</div>

<div class="glass-panel overflow-hidden border border-white/5 shadow-2xl">
    <?php
    require_once 'includes/WooCommerceFetcher.php';
    $wp_domain = 'https://rustylopez.com'; 
    $consumer_key = 'ck_f71b92d918a8be2e8b012a6d6653d492229c42ea';
    $consumer_secret = 'cs_de7e60459ebf280d5bf41534bd43b2c5c3cc313d';
    $fetcher = new WooCommerceFetcher($wp_domain, $consumer_key, $consumer_secret);



    $current_status = $_SESSION['monitoring_status'] ?? 'any';
    $current_page   = intval($_SESSION['monitoring_page'] ?? 1);
    $limit          = intval($_SESSION['monitoring_limit'] ?? 50);

    // Fetch counts
    $totals = $fetcher->fetchOrderTotals();

    $filter_links = [
        'any'        => ['label' => 'All',        'count' => $totals['any']        ?? 0],
        'processing' => ['label' => 'Processing', 'count' => $totals['processing'] ?? 0],
        'on-hold'    => ['label' => 'On hold',    'count' => $totals['on-hold']    ?? 0],
        'completed'  => ['label' => 'Completed',  'count' => $totals['completed']  ?? 0],
        'cancelled'  => ['label' => 'Cancelled',  'count' => $totals['cancelled']  ?? 0],
        'failed'     => ['label' => 'Failed',     'count' => $totals['failed']     ?? 0],
    ];
    ?>
    <!-- Filter Form wrapper -->
    <form method="POST" action="monitoring" id="monitoring-filter-form">
        <input type="hidden" name="status" id="hidden-status" value="<?= $current_status ?>">
        <input type="hidden" name="page" id="hidden-page" value="<?= $current_page ?>">

        <div class="p-4 border-b border-white/5 bg-slate-800/40 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <!-- Badge Links -->
            <ul class="flex flex-wrap gap-4 text-sm font-medium">
                <?php foreach($filter_links as $val => $data): ?>
                    <li>
                        <button type="button" onclick="switchStatus('<?= $val ?>')" class="<?= $current_status === $val ? 'text-purple-400 border-b border-purple-400' : 'text-gray-400 hover:text-white' ?> pb-1 transition-colors">
                            <?= $data['label'] ?> <span class="text-xs opacity-60">(<?= $data['count'] ?>)</span>
                        </button>
                    </li>
                    <?php if($val !== 'failed'): ?>
                        <li class="text-white/20">|</li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <!-- Search -->
            <div class="relative w-full md:w-64 shrink-0">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" id="orderSearch" placeholder="Search orders..." value="<?= htmlspecialchars($_SESSION['monitoring_search'] ?? '') ?>" class="input-modern w-full pl-10 text-sm py-2">
            </div>
        </div>

        <div class="p-4 border-b border-white/5 bg-slate-800/40 flex flex-col sm:flex-row gap-4 justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-400">Show</span>
                <select name="limit" class="input-modern text-sm py-1 px-3 appearance-none bg-slate-900 border-white/10" onchange="submitForm()">
                    <?php foreach([10, 25, 50, 100] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-sm text-gray-400">entries</span>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-2 items-center text-sm relative">
                <span class="text-gray-400 font-medium mr-2">Filter by date:</span>
            <?php 
                $start_val  = htmlspecialchars($_SESSION['monitoring_start_date'] ?? '');
                $end_val    = htmlspecialchars($_SESSION['monitoring_end_date']   ?? '');
            ?>
            <input type="date" name="start_date" onclick="this.showPicker()" class="input-modern py-1.5 px-3 bg-slate-900 border-white/10 focus:border-purple-500" value="<?= $start_val ?>" onchange="submitForm()">
            <span class="text-gray-500 mx-1">to</span>
            <input type="date" name="end_date" onclick="this.showPicker()" class="input-modern py-1.5 px-3 bg-slate-900 border-white/10 focus:border-purple-500" value="<?= $end_val ?>" onchange="submitForm()">
        </div>
    </div>
    </form>

    <div class="overflow-x-auto">
        <table id="dataTable" class="w-full text-left border-collapse glass-table whitespace-nowrap">
            <thead>
                <tr>
                    <th class="p-4 w-12 text-center">
                        <input type="checkbox" class="custom-checkbox" onclick="toggleAllRows(this)">
                    </th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase">Order ID</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase">Customer</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase">Amount</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase">Date</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase">Store</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase">WC Status</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                $search_query = $_SESSION['monitoring_search']     ?? '';
                $start_query  = $_SESSION['monitoring_start_date'] ?? '';
                $end_query    = $_SESSION['monitoring_end_date']   ?? '';
                $api_response = $fetcher->fetchRecentSalesAPI($limit, $current_page, $current_status, $search_query, $start_query, $end_query); 
                
                $transactions       = [];
                $actual_total_items = 0;
                
                if ($api_response['success']) {
                    $transactions       = $api_response['data'];
                    $actual_total_items = $api_response['total_items'];
                } else {
                    echo "<tr><td colspan='7'><div class='text-red-400 p-4 border border-red-500 bg-red-900/20 m-4 rounded-xl text-sm text-center'>API Connection Error: {$api_response['error']}</div></td></tr>";
                }

                if (empty($transactions) && $api_response['success']) {
                    echo "<tr><td colspan='7'><div class='text-gray-400 p-8 text-center'>No orders found for this filter.</div></td></tr>";
                }

                foreach($transactions as $txn):
                    $statusColor = 'bg-blue-500/20 text-blue-400 border-blue-500/30';
                    $statusIcon  = 'fa-info-circle';
                    if ($txn['status'] === 'Completed') {
                        $statusColor = 'bg-green-500/20 text-green-400 border-green-500/30';
                        $statusIcon  = 'fa-check-circle';
                    } elseif ($txn['status'] === 'Processing' || $txn['status'] === 'On-hold') {
                        $statusColor = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                        $statusIcon  = 'fa-clock';
                    } elseif ($txn['status'] === 'Cancelled' || $txn['status'] === 'Failed' || $txn['status'] === 'Refunded') {
                        $statusColor = 'bg-red-500/20 text-red-400 border-red-500/30';
                        $statusIcon  = 'fa-times-circle';
                    }
                ?>
                <tr class="hover:bg-white/5 transition-colors group cursor-pointer" onclick="const cb = this.querySelector('.row-checkbox'); cb.checked = !cb.checked; event.stopPropagation();">
                    <td class="p-4 text-center" onclick="event.stopPropagation()">
                        <input type="checkbox" class="custom-checkbox row-checkbox">
                    </td>
                    <td class="p-4">
                        <div class="font-semibold text-purple-300 group-hover:text-purple-400 transition-colors"><?= $txn['id'] ?></div>
                    </td>
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-500/20 to-pink-500/20 flex items-center justify-center text-purple-400 font-bold text-xs shadow-inner">
                                <?= strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $txn['customer']), 0, 1) ?: '?') ?>
                            </div>
                            <span class="text-gray-200 font-medium"><?= $txn['customer'] ?></span>
                        </div>
                    </td>
                    <td class="p-4 font-semibold text-emerald-400"><?= $txn['amount'] ?></td>
                    <td class="p-4 text-gray-400 flex items-center gap-2">
                        <i class="far fa-calendar-alt text-slate-500"></i> <?= $txn['date'] ?>
                    </td>
                    <td class="p-4 text-gray-300 font-medium"><?= $txn['store'] ?></td>
                    <td class="p-4">
                        <span class="px-3 py-1.5 rounded-lg text-xs font-medium border flex items-center gap-1.5 w-max <?= $statusColor ?>">
                            <i class="fas <?= $statusIcon ?>"></i> <?= $txn['status'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php 
    $total_items  = (!empty($search_val) || !empty($start_val) || !empty($end_val)) ? $actual_total_items : ($filter_links[$current_status]['count'] ?? 0);
    $total_pages  = ceil($total_items / $limit);
    if ($total_pages === 0) $total_pages = 1;
    $start_index  = (($current_page - 1) * $limit) + 1;
    $end_index    = min($current_page * $limit, $total_items);
    if ($total_items === 0) $start_index = 0;

    $pg_url = "monitoring?status={$current_status}&limit={$limit}";
    if(!empty($search_val)) $pg_url .= "&search="     . urlencode($search_val);
    if(!empty($start_val))  $pg_url .= "&start_date=" . urlencode($start_val);
    if(!empty($end_val))    $pg_url .= "&end_date="   . urlencode($end_val);
    ?>
    <div class="p-4 border-t border-white/5 bg-slate-800/40 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-400 gap-4">
        <div>Showing <?= $start_index ?> to <?= $end_index ?> of <?= $total_items ?> entries</div>
        <div class="flex gap-1">
            <button onclick="goToPage(<?= max(1, $current_page - 1) ?>)" class="w-8 h-8 rounded-lg <?= $current_page <= 1 ? 'bg-white/5 opacity-50 cursor-not-allowed' : 'bg-white/10 hover:bg-white/20' ?> flex items-center justify-center transition-colors">
                <i class="fas fa-chevron-left"></i>
            </button>
            <?php for($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                <button onclick="goToPage(<?= $i ?>)" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors <?= $i === $current_page ? 'bg-purple-500 text-white shadow-[0_0_10px_rgba(168,85,247,0.4)]' : 'bg-white/5 hover:bg-white/10' ?>">
                    <?= $i ?>
                </button>
            <?php endfor; ?>
            <button onclick="goToPage(<?= min($total_pages, $current_page + 1) ?>)" class="w-8 h-8 rounded-lg <?= $current_page >= $total_pages ? 'bg-white/5 opacity-50 cursor-not-allowed' : 'bg-white/10 hover:bg-white/20' ?> flex items-center justify-center transition-colors">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
function switchStatus(status) {
    document.getElementById('hidden-status').value = status;
    document.getElementById('hidden-page').value = 1;
    document.getElementById('monitoring-filter-form').submit();
}

function submitForm() {
    showGlobalLoader('UPDATING FILTER...');
    document.getElementById('monitoring-filter-form').submit();
}

function goToPage(pageNo) {
    document.getElementById('hidden-page').value = pageNo;
    document.getElementById('monitoring-filter-form').submit();
}

document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('orderSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(() => {
                showGlobalLoader('SEARCHING DATABASE...');
                document.getElementById('monitoring-filter-form').submit();
            }, 800);
        });
    }
});
</script>
