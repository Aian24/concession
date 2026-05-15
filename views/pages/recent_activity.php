<?php
// Ensure flags are available (handles standalone or inclusion contexts)
if (!isset($is_full_admin)) {
    $role = $_SESSION['role'] ?? 'user';
    $is_full_admin = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
}

if (!$is_full_admin) {
    header("Location: dashboard");
    exit;
}

require_once 'includes/db.php';
$db = db_connect();

// Fetch activities from the last 7 days across all transaction tables
$query = "
    (SELECT 'Sale' COLLATE utf8mb4_unicode_ci as type, 
            username COLLATE utf8mb4_unicode_ci as username, 
            store_code COLLATE utf8mb4_unicode_ci as store_code, 
            created_at, 
            item_no COLLATE utf8mb4_unicode_ci as reference, 
            quantity 
     FROM sales 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK))
    UNION ALL
    (SELECT 'Return' COLLATE utf8mb4_unicode_ci as type, 
            username COLLATE utf8mb4_unicode_ci as username, 
            store_code COLLATE utf8mb4_unicode_ci as store_code, 
            created_at, 
            return_item COLLATE utf8mb4_unicode_ci as reference, 
            quantity 
     FROM returns 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK))
    UNION ALL
    (SELECT 'Receiving' COLLATE utf8mb4_unicode_ci as type, 
            username COLLATE utf8mb4_unicode_ci as username, 
            store_code COLLATE utf8mb4_unicode_ci as store_code, 
            created_at, 
            os_no COLLATE utf8mb4_unicode_ci as reference, 
            quantity 
     FROM receiving 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK))
    UNION ALL
    (SELECT 'Pullout' COLLATE utf8mb4_unicode_ci as type, 
            username COLLATE utf8mb4_unicode_ci as username, 
            store_code COLLATE utf8mb4_unicode_ci as store_code, 
            created_at, 
            item_no COLLATE utf8mb4_unicode_ci as reference, 
            quantity 
     FROM pullouts 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK))
    ORDER BY created_at DESC
";

$result = $db->query($query);
$activities = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch store names for better display
$stores_res = $db->query("SELECT scode, sname FROM storecode");
$store_map = [];
while ($row = $stores_res->fetch_assoc()) {
    $store_map[$row['scode']] = $row['sname'];
}
?>

<div class="animate-fade-in pb-12">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center border border-purple-500/30">
                    <i class="fas fa-clock-rotate-left text-purple-400"></i>
                </div>
                Recent Activity
            </h2>
            <p class="text-gray-400 text-sm mt-1">Transaction logs from the past 7 days across all modules.</p>
        </div>
        
        <div class="flex items-center gap-3 bg-slate-800/40 p-1.5 rounded-xl border border-white/5">
            <div class="px-4 py-2 bg-purple-500/10 border border-purple-500/20 rounded-lg text-[10px] font-black text-purple-400 uppercase tracking-widest">
                Last 7 Days Only
            </div>
        </div>
    </div>

    <div class="glass-panel border border-white/5 overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5">
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Timestamp</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">User</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Store</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Transaction</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Reference</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5 text-right">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mb-4 border border-white/5">
                                        <i class="fas fa-history text-gray-600 text-xl"></i>
                                    </div>
                                    <p class="text-gray-500 font-bold uppercase tracking-widest text-xs">No activity found in the last 7 days</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $act): 
                            $type_color = [
                                'Sale'      => 'bg-green-500/10 text-green-400 border-green-500/20',
                                'Return'    => 'bg-red-500/10 text-red-400 border-red-500/20',
                                'Receiving' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                                'Pullout'   => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                'Supplies'  => 'bg-purple-500/10 text-purple-400 border-purple-500/20'
                            ][$act['type']] ?? 'bg-slate-500/10 text-slate-400 border-slate-500/20';

                            $date = new DateTime($act['created_at']);
                        ?>
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold text-xs"><?= $date->format('M d, Y') ?></span>
                                        <span class="text-[10px] text-gray-500 font-medium tracking-tighter uppercase"><?= $date->format('h:i A') ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-800 border border-white/10 flex items-center justify-center text-[10px] font-black text-purple-300">
                                            <?= strtoupper(substr($act['username'], 0, 1)) ?>
                                        </div>
                                        <span class="text-xs font-bold text-gray-300 group-hover:text-white transition-colors"><?= htmlspecialchars($act['username']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-white"><?= htmlspecialchars($store_map[$act['store_code']] ?? 'Unknown Store') ?></span>
                                        <span class="text-[9px] text-gray-500 font-black tracking-widest"><?= htmlspecialchars($act['store_code']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $type_color ?>">
                                        <?= $act['type'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-xs font-mono text-purple-300/80"><?= htmlspecialchars($act['reference']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-xs font-black text-white"><?= number_format($act['quantity']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 bg-white/5 border-t border-white/5 flex items-center justify-between">
            <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em]">Total Recent Actions: <span class="text-white"><?= count($activities) ?></span></span>
            <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em]">Data Purged Automatically After 7 Days</span>
        </div>
    </div>
</div>
