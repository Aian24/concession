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

// Fetch user logs
$query = "
    SELECT id, username, ip_address, login_time 
    FROM user_logs 
    ORDER BY login_time DESC
";

$result = $db->query($query);
$logs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<style>
    @media (max-width: 768px) {
        #user-logs-table thead { display: none; }
        #user-logs-table, #user-logs-table tbody { display: block; width: 100%; }
        #user-logs-table tr { 
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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
        #user-logs-table tr:first-child { margin-top: 1.5rem; }
        #user-logs-table td { 
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
        #user-logs-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #user-logs-table td span, 
        #user-logs-table td div { 
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
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center border border-purple-500/30">
                    <i class="fas fa-users-viewfinder text-purple-400"></i>
                </div>
                User Logs
            </h2>
            <p class="text-gray-400 text-sm mt-1">Login activity logs. Data is automatically purged after 7 days.</p>
        </div>
        
        <div class="flex items-center gap-3 bg-slate-800/40 p-1.5 rounded-xl border border-white/5">
            <div class="px-4 py-2 bg-purple-500/10 border border-purple-500/20 rounded-lg text-[10px] font-black text-purple-400 uppercase tracking-widest">
                Admin Only
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
            <table class="w-full text-left border-collapse hidden" id="user-logs-table">
                <thead>
                    <tr class="bg-white/5">
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">Timestamp</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">User</th>
                        <th class="px-6 py-4 text-[10px] font-black text-purple-400 uppercase tracking-[0.2em] border-b border-white/5">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mb-4 border border-white/5">
                                        <i class="fas fa-user-slash text-gray-600 text-xl"></i>
                                    </div>
                                    <p class="text-gray-500 font-bold uppercase tracking-widest text-xs">No user logs found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $date = new DateTime($log['login_time']);
                        ?>
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="px-6 py-4" data-label="Timestamp">
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold text-xs"><?= $date->format('M d, Y') ?></span>
                                        <span class="text-[10px] text-gray-500 font-medium tracking-tighter uppercase"><?= $date->format('h:i:s A') ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4" data-label="User">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-800 border border-white/10 flex items-center justify-center text-[10px] font-black text-purple-300">
                                            <?= strtoupper(substr($log['username'], 0, 1)) ?>
                                        </div>
                                        <span class="text-xs font-bold text-gray-300 group-hover:text-white transition-colors"><?= htmlspecialchars($log['username']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4" data-label="IP Address">
                                    <span class="text-xs font-mono text-purple-300/80"><?= htmlspecialchars($log['ip_address']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 bg-white/5 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-2">
            <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] text-center md:text-left">Total Login Records: <span class="text-white"><?= count($logs) ?></span></span>
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
    $('#user-logs-table').DataTable({
        pageLength: 15,
        ordering: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search logs...",
            lengthMenu: "Show _MENU_ entries"
        },
        initComplete: function() {
            $('#user-logs-table').removeClass('hidden').hide().fadeIn(300);
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
