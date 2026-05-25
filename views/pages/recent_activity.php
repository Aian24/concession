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

// Ensure table exists and is backpopulated (in case log_activity hasn't been called yet)
$table_check = $db->query("SHOW TABLES LIKE 'activity_log'");
if ($table_check && $table_check->num_rows === 0) {
    $db->query("CREATE TABLE activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        module ENUM('Sale', 'Return', 'Receiving', 'Pullout') DEFAULT NULL,
        store_code VARCHAR(50) DEFAULT NULL,
        reference VARCHAR(150) DEFAULT NULL,
        quantity INT NOT NULL DEFAULT 0,
        details TEXT DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_action_type (action_type),
        INDEX idx_module (module),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Backpopulate existing entries as 'create' actions
    $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
        SELECT username, 'create', 'Sale', store_code, item_no, quantity, CONCAT('Created sale entry for item #', item_no), created_at FROM sales");
    $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
        SELECT username, 'create', 'Return', store_code, COALESCE(return_item, exchange_item, 'Return Item'), quantity, CONCAT('Created return/exchange entry for item #', COALESCE(return_item, exchange_item, 'Return Item')), created_at FROM returns");
    $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
        SELECT username, 'create', 'Receiving', store_code, os_no, quantity, CONCAT('Created receiving entry for OS #', os_no), created_at FROM receiving");
    $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
        SELECT username, 'create', 'Pullout', store_code, item_no, quantity, CONCAT('Created pullout entry for item #', item_no), created_at FROM pullouts");
} else {
    // Table exists, let's verify and alter if columns are missing
    $cols = [];
    $res = $db->query("DESCRIBE activity_log");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = strtolower($row['Field']);
        }
    }
    if (!in_array('module', $cols)) {
        $db->query("ALTER TABLE activity_log ADD COLUMN module ENUM('Sale', 'Return', 'Receiving', 'Pullout') DEFAULT NULL AFTER action_type");
    }
    if (!in_array('reference', $cols)) {
        $db->query("ALTER TABLE activity_log ADD COLUMN reference VARCHAR(150) DEFAULT NULL AFTER store_code");
    }
    if (!in_array('quantity', $cols)) {
        $db->query("ALTER TABLE activity_log ADD COLUMN quantity INT NOT NULL DEFAULT 0 AFTER reference");
    }
    if (!in_array('details', $cols)) {
        $db->query("ALTER TABLE activity_log ADD COLUMN details TEXT DEFAULT NULL AFTER quantity");
    }
}

// Fetch activities from the last 7 days across all transaction tables
$query = "
    SELECT module AS type, action_type, username, store_code, created_at, reference, quantity, details 
    FROM activity_log 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
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
<style>
    @media (max-width: 768px) {
        #recent-activity-table thead { display: none; }
        #recent-activity-table, #recent-activity-table tbody { display: block; width: 100%; }
        #recent-activity-table tr { 
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
        #recent-activity-table tr:first-child { margin-top: 1.5rem; }
        #recent-activity-table td { 
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
        #recent-activity-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #recent-activity-table td span, 
        #recent-activity-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        #recent-activity-table td .flex-col { align-items: flex-start !important; text-align: left !important; }
        #recent-activity-table td[data-label="User"] .flex > div:first-child { display: none; }
    }
</style>

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
        <div class="overflow-x-auto min-h-[200px] relative">
            <div id="table-loader" class="absolute inset-0 flex items-center justify-center bg-slate-900/80 z-20 backdrop-blur-sm">
                <div class="flex flex-col items-center gap-3">
                    <i class="fas fa-spinner animate-spin text-purple-500 text-4xl"></i>
                    <span class="text-xs font-bold text-purple-400 uppercase tracking-widest">Formatting Data...</span>
                </div>
            </div>
            <table class="w-full text-left border-collapse hidden" id="recent-activity-table">
                <thead>
                    <tr class="bg-white/5">
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Timestamp</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">User</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Store</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Action / Module</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Reference</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Details</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5 text-right">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-20 text-center">
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
                                'Pullout'   => 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                            ][$act['type']] ?? 'bg-slate-500/10 text-slate-400 border-slate-500/20';

                            $action_badge = [
                                'create' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                'edit'   => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                'delete' => 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                            ][$act['action_type']] ?? 'bg-slate-500/10 text-slate-400 border-slate-500/20';

                            $date = new DateTime($act['created_at']);
                        ?>
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="px-6 py-4" data-label="Timestamp">
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold text-xs"><?= $date->format('M d, Y') ?></span>
                                        <span class="text-[10px] text-gray-500 font-medium tracking-tighter uppercase"><?= $date->format('h:i A') ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4" data-label="User">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-800 border border-white/10 flex items-center justify-center text-[10px] font-black text-purple-300">
                                            <?= strtoupper(substr($act['username'], 0, 1)) ?>
                                        </div>
                                        <span class="text-xs font-bold text-gray-300 group-hover:text-white transition-colors"><?= htmlspecialchars($act['username']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4" data-label="Store">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-white"><?= htmlspecialchars($store_map[$act['store_code']] ?? 'Unknown Store') ?></span>
                                        <span class="text-[9px] text-gray-500 font-black tracking-widest"><?= htmlspecialchars($act['store_code']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4" data-label="Action / Module">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest border <?= $type_color ?>">
                                            <?= $act['type'] ?>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest border <?= $action_badge ?>">
                                            <?= $act['action_type'] ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4" data-label="Reference">
                                    <span class="text-xs font-mono text-purple-300/80"><?= htmlspecialchars($act['reference']) ?></span>
                                </td>
                                <td class="px-6 py-4" data-label="Details">
                                    <span class="text-xs text-gray-300 font-medium whitespace-normal break-words inline-block max-w-xs"><?= htmlspecialchars($act['details'] ?: 'Transaction recorded') ?></span>
                                </td>
                                <td class="px-6 py-4 text-right" data-label="Qty">
                                    <span class="text-xs font-black text-white"><?= number_format($act['quantity']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 bg-white/5 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-2">
            <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] text-center md:text-left">Total Recent Actions: <span class="text-white"><?= count($activities) ?></span></span>
            <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] text-center md:text-right">Data Purged Automatically After 7 Days</span>
        </div>
    </div>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<!-- jQuery & DataTables JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#recent-activity-table').DataTable({
        pageLength: 10,
        ordering: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search activities...",
            lengthMenu: "Show _MENU_ entries"
        },
        initComplete: function() {
            $('#recent-activity-table').removeClass('hidden').hide().fadeIn(300);
            $('#table-loader').fadeOut(300, function() {
                $(this).remove();
            });
        }
    });
});
</script>
<style>
/* Dark mode DataTables styling overrides */
.dataTables_wrapper .dataTables_length, 
.dataTables_wrapper .dataTables_filter, 
.dataTables_wrapper .dataTables_info, 
.dataTables_wrapper .dataTables_processing, 
.dataTables_wrapper .dataTables_paginate {
    color: #9ca3af !important; 
    font-size: 0.875rem; 
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding: 0 1.5rem;
    font-weight: 600;
}
.dataTables_wrapper .dataTables_length select, 
.dataTables_wrapper .dataTables_filter input {
    background-color: rgba(15, 23, 42, 0.5) !important; 
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    border-radius: 0.5rem;
    padding: 0.25rem 0.5rem;
    margin-left: 0.5rem;
    outline: none;
}
.dataTables_wrapper .dataTables_filter input:focus {
    border-color: #a855f7 !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: #9ca3af !important;
    border: 1px solid transparent !important;
    border-radius: 0.5rem;
    margin: 0 0.25rem;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current, 
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: #a855f7 !important; 
    color: white !important;
    border: 1px solid #a855f7 !important;
}
table.dataTable.no-footer {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
}
/* Ensure table header border matches theme */
table.dataTable thead th, table.dataTable thead td {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
}
/* Fix for search input width and spacing */
.dataTables_wrapper .dataTables_filter {
    text-align: right;
}
@media (max-width: 768px) {
    .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length {
        text-align: left;
        padding: 0 1rem;
    }
}
</style>
