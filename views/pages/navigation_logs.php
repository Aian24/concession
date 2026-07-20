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

// Fetch navigation logs grouped by user and date
$query = "
    SELECT 
        username,
        DATE(visit_time) as visit_date,
        GROUP_CONCAT(DISTINCT page_name ORDER BY visit_time ASC SEPARATOR ', ') as pages_visited,
        GROUP_CONCAT(DISTINCT ip_address ORDER BY visit_time ASC SEPARATOR ', ') as ip_addresses,
        MIN(visit_time) as first_visit,
        MAX(visit_time) as last_visit,
        COUNT(*) as total_visits
    FROM page_navigation_logs 
    GROUP BY username, DATE(visit_time)
    ORDER BY visit_date DESC, username ASC
";

$result = $db->query($query);
$logs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<style>
    @media (max-width: 768px) {
        #navigation-logs-table thead { display: none; }
        #navigation-logs-table, #navigation-logs-table tbody { display: block; width: 100%; }
        #navigation-logs-table tr { 
            display: grid;
            grid-template-columns: 1fr;
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
        #navigation-logs-table tr:first-child { margin-top: 1.5rem; }
        #navigation-logs-table td { 
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
        #navigation-logs-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #navigation-logs-table td span, 
        #navigation-logs-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
</style>

<div class="animate-fade-in pb-12">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center border border-blue-500/30">
                    <i class="fas fa-route text-blue-400"></i>
                </div>
                User Navigation Summary
            </h2>
            <p class="text-gray-400 text-sm mt-1">Daily summary of pages visited by each user. Data is automatically purged after 7 days.</p>
        </div>
        
        <div class="flex items-center gap-3 bg-slate-800/40 p-1.5 rounded-xl border border-white/5">
            <div class="px-4 py-2 bg-blue-500/10 border border-blue-500/20 rounded-lg text-[10px] font-black text-blue-400 uppercase tracking-widest">
                Admin Only
            </div>
        </div>
    </div>

    <div class="glass-panel border border-white/5 overflow-hidden shadow-2xl">
        <div class="overflow-x-auto min-h-[200px] relative">
            <div id="table-loader" class="absolute inset-0 flex items-center justify-center bg-slate-900/80 z-20 backdrop-blur-sm">
                <div class="flex flex-col items-center gap-3">
                    <i class="fas fa-spinner animate-spin text-blue-500 text-4xl"></i>
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-widest">Formatting Data...</span>
                </div>
            </div>
            <table class="w-full text-left border-collapse hidden" id="navigation-logs-table">
                <thead>
                    <tr class="bg-white/5">
                        <th class="px-6 py-4 text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] border-b border-white/5">Date</th>
                        <th class="px-6 py-4 text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] border-b border-white/5">User</th>
                        <th class="px-6 py-4 text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] border-b border-white/5">Pages Visited</th>
                        <th class="px-6 py-4 text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] border-b border-white/5">IP Address</th>
                        <th class="px-6 py-4 text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] border-b border-white/5">Time Range</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($logs as $log): 
                        $date = new DateTime($log['visit_date']);
                        $first_visit = new DateTime($log['first_visit']);
                        $last_visit = new DateTime($log['last_visit']);
                    ?>
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-6 py-4" data-label="Date">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-xs"><?= $date->format('M d, Y') ?></span>
                                    <span class="text-[10px] text-gray-500 font-medium tracking-tighter uppercase"><?= $date->format('l') ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4" data-label="User">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-slate-800 border border-white/10 flex items-center justify-center text-[10px] font-black text-blue-300">
                                        <?= strtoupper(substr($log['username'], 0, 1)) ?>
                                    </div>
                                    <span class="text-xs font-bold text-gray-300 group-hover:text-white transition-colors"><?= htmlspecialchars($log['username']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4" data-label="Pages Visited">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-white">(<?= htmlspecialchars($log['pages_visited']) ?>)</span>
                                    <span class="text-[10px] text-gray-500"><?= $log['total_visits'] ?> visits</span>
                                </div>
                            </td>
                            <td class="px-6 py-4" data-label="IP Address">
                                <span class="text-xs font-mono text-blue-300/80"><?= htmlspecialchars($log['ip_addresses']) ?></span>
                            </td>
                            <td class="px-6 py-4" data-label="Time Range">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-gray-400"><?= $first_visit->format('h:i A') ?></span>
                                    <span class="text-[10px] text-gray-500">to <?= $last_visit->format('h:i A') ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 bg-white/5 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-2">
            <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] text-center md:text-left">Total User-Day Records: <span class="text-white"><?= count($logs) ?></span></span>
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
    $('#navigation-logs-table').DataTable({
        pageLength: 50,
        ordering: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search user or pages...",
            lengthMenu: "Show _MENU_ entries",
            emptyTable: `<div class="flex flex-col items-center py-10">
                            <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mb-4 border border-white/5">
                                <i class="fas fa-route text-gray-600 text-xl"></i>
                            </div>
                            <p class="text-gray-500 font-bold uppercase tracking-widest text-xs">No navigation logs found</p>
                        </div>`
        },
        initComplete: function() {
            $('#navigation-logs-table').removeClass('hidden').hide().fadeIn(300);
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
    font-size: 0.75rem !important; 
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding: 0 1.5rem;
    font-weight: 700;
}
.dataTables_wrapper .dataTables_filter input {
    background-color: rgba(30, 41, 59, 0.5) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 0.5rem !important;
    color: #f3f4f6 !important;
    padding: 0.5rem 1rem !important;
    margin-left: 0.5rem !important;
}
.dataTables_wrapper .dataTables_length select {
    background-color: rgba(30, 41, 59, 0.5) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 0.5rem !important;
    color: #f3f4f6 !important;
    padding: 0.25rem 2rem 0.25rem 0.75rem !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: #9ca3af !important;
    border-radius: 0.375rem !important;
    margin: 0 0.125rem !important;
    padding: 0.375rem 0.75rem !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    background: rgba(30, 41, 59, 0.5) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    color: #fff !important;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    border: none !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    color: #fff !important;
    background: rgba(59, 130, 246, 0.3) !important;
    border: 1px solid rgba(59, 130, 246, 0.5) !important;
}
</style>