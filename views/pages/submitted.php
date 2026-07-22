<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h2 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500 mb-1">Submitted Transactions</h2>
        <p class="text-gray-400">View and export your submitted data</p>
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
    <!-- Filters area -->
    <div class="p-4 border-b border-white/5 bg-slate-800/40 flex flex-col sm:flex-row gap-4 justify-between items-center">
        <div class="relative w-full sm:w-64">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input type="text" placeholder="Search transactions..." class="input-modern w-full pl-10 text-sm py-2">
        </div>
        <div class="flex gap-2">
            <button class="btn-secondary px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2 text-gray-300">
                <i class="fas fa-filter text-purple-400"></i> Filter
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table id="dataTable" class="w-full text-left border-collapse glass-table whitespace-nowrap">
            <thead>
                <tr style="border-bottom: 2px solid rgba(255, 255, 255, 0.2); background-color: rgba(255, 255, 255, 0.05);">
                    <th class="p-4 w-12 text-center border-b-2 border-white/20">
                        <input type="checkbox" class="custom-checkbox" onclick="toggleAllRows(this)">
                    </th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase border-b-2 border-white/20">Transaction ID</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase border-b-2 border-white/20">Type</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase border-b-2 border-white/20">Amount</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase border-b-2 border-white/20">Date</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase border-b-2 border-white/20">Store Code</th>
                    <th class="p-4 font-semibold text-gray-400 text-sm tracking-wider uppercase border-b-2 border-white/20">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                require_once 'includes/WooCommerceFetcher.php';

                // REPLACE THIS WITH YOUR BLUEHOST DOMAIN
                $wp_domain = 'https://rustylopez.com'; 
                $consumer_key = 'ck_f71b92d918a8be2e8b012a6d6653d492229c42ea';
                $consumer_secret = 'cs_de7e60459ebf280d5bf41534bd43b2c5c3cc313d';

                $fetcher = new WooCommerceFetcher($wp_domain, $consumer_key, $consumer_secret);
                $api_response = $fetcher->fetchRecentSalesAPI(20); 
                
                $transactions = [];
                if ($api_response['success']) {
                    $transactions = $api_response['data'];
                } else {
                    echo "<div class='text-red-400 p-4 border border-red-500 bg-red-900/20 mb-4 rounded-xl text-sm'>API Connection Error: {$api_response['error']}</div>";
                }

                foreach($transactions as $txn):
                    $statusColor = 'bg-blue-500/20 text-blue-400 border-blue-500/30';
                    $statusIcon = 'fa-info-circle';
                    if ($txn['status'] === 'Completed') {
                        $statusColor = 'bg-green-500/20 text-green-400 border-green-500/30';
                        $statusIcon = 'fa-check-circle';
                    } elseif ($txn['status'] === 'Pending') {
                        $statusColor = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                        $statusIcon = 'fa-clock';
                    } elseif ($txn['status'] === 'Reviewed') {
                        $statusColor = 'bg-purple-500/20 text-purple-400 border-purple-500/30';
                        $statusIcon = 'fa-eye';
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
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-gray-400 shadow-inner">
                                <?php
                                    $icon = 'fa-receipt';
                                    if($txn['type'] == 'Return') $icon = 'fa-undo';
                                    if($txn['type'] == 'Receiving') $icon = 'fa-box-open';
                                    if($txn['type'] == 'ROS Supplies') $icon = 'fa-boxes-stacked';
                                    if($txn['type'] == 'Memo') $icon = 'fa-file-lines';
                                    if($txn['type'] == 'HR') $icon = 'fa-users';
                                ?>
                                <i class="fas <?= $icon ?> text-xs"></i>
                            </span>
                            <span class="text-gray-200 font-medium"><?= $txn['type'] ?></span>
                        </div>
                    </td>
                    <td class="p-4 font-semibold <?= strpos($txn['amount'], '-') !== false ? 'text-red-400' : 'text-emerald-400' ?>">
                        <?= $txn['amount'] ?>
                    </td>
                    <td class="p-4 text-gray-400 flex items-center gap-2">
                        <i class="far fa-calendar-alt text-slate-500"></i> <?= $txn['date'] ?>
                    </td>
                    <td class="p-4 text-gray-300 font-medium">
                        <?= $txn['store'] ?>
                    </td>
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
    
    <!-- Pagination -->
    <div class="p-4 border-t border-white/5 bg-slate-800/40 flex justify-between items-center text-sm text-gray-400">
        <div>Showing 1 to 7 of 7 entries</div>
        <div class="flex gap-1">
            <button class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center transition-colors disabled:opacity-50" disabled><i class="fas fa-chevron-left"></i></button>
            <button class="w-8 h-8 rounded-lg bg-purple-500 text-white flex items-center justify-center shadow-[0_0_10px_rgba(168,85,247,0.4)]">1</button>
            <button class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center transition-colors disabled:opacity-50" disabled><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>
