<?php
// prism_data_table_partial.php
if (!isset($prism_rows)) {
    require_once 'includes/db.php';
    $db = db_connect();
    
    $limit  = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $page   = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';

    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    if ($search !== '') {
        $where .= " AND (item_no LIKE ?)";
        $lk = "%$search%";
        $params[] = $lk;
        $types .= "s";
    }

    $stmt = $db->prepare("SELECT * FROM prismdata $where ORDER BY id ASC LIMIT ? OFFSET ?");
    $p_with_limit = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($types . "ii", ...$p_with_limit);
    $stmt->execute();
    $prism_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $count_stmt = $db->prepare("SELECT COUNT(*) FROM prismdata $where");
    if (!empty($params)) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_row()[0];
    $total_pages = max(1, ceil($total_rows / $limit));
    $count_stmt->close();
}
?>
<style>
    @media (max-width: 768px) {
        #prism-management-table thead { display: none; }
        #prism-management-table, #prism-management-table tbody { display: block; width: 100%; }
        #prism-management-table tr { 
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
        #prism-management-table tr:first-child { margin-top: 1.5rem; }
        #prism-management-table td { 
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
        #prism-management-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #prism-management-table td[data-label="Select"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: auto;
            border: none;
            padding: 0;
            z-index: 10;
        }
        #prism-management-table td[data-label="Select"]::before { display: none; }
        
        #prism-management-table td span, 
        #prism-management-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        #prism-management-table td .flex-col { align-items: flex-start !important; text-align: left !important; }
    }
</style>

<div class="overflow-x-auto">
    <table class="w-full border-collapse" id="prism-management-table">
        <thead>
            <tr class="text-left border-b border-white/5 bg-slate-800/30">
                <th class="p-4 w-10">
                    <div class="flex items-center justify-center">
                        <input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-white/10 bg-slate-900 accent-blue-500">
                    </div>
                </th>
                <th class="p-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Item Number</th>
                <th class="p-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">SRP</th>
                <th class="p-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            <?php if (empty($prism_rows)): ?>
                <tr>
                    <td colspan="4" class="p-12 text-center text-gray-500 italic text-sm">No prism data found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($prism_rows as $row): ?>
                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="p-4" data-label="Select">
                        <div class="flex items-center justify-center">
                            <input type="checkbox" class="prism-checkbox w-4 h-4 rounded border-white/10 bg-slate-900 accent-blue-500" value="<?= $row['id'] ?>">
                        </div>
                    </td>
                    <td class="p-4" data-label="Item Number">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center border border-blue-500/20">
                                <i class="fas fa-barcode text-blue-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-bold text-white"><?= htmlspecialchars($row['item_no']) ?></span>
                        </div>
                    </td>
                    <td class="p-4 text-right" data-label="SRP">
                        <span class="text-xs font-black text-emerald-400">&#8369; <?= number_format($row['srp'], 2) ?></span>
                    </td>
                    <td class="p-4 text-right" data-label="Actions">
                        <div class="flex items-center justify-end md:justify-end gap-2 opacity-40 group-hover:opacity-100 transition-all">
                            <button onclick="editPrism(<?= $row['id'] ?>, '<?= addslashes($row['item_no']) ?>', <?= $row['srp'] ?>)" class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 border border-blue-500/20 transition-all flex items-center justify-center" title="Edit">
                                <i class="fas fa-edit text-[10px]"></i>
                            </button>
                            <button onclick="deletePrism(<?= $row['id'] ?>)" class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 border border-red-500/20 transition-all flex items-center justify-center" title="Delete">
                                <i class="fas fa-trash-alt text-[10px]"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="p-4 border-t border-white/5 bg-slate-800/20 flex flex-col md:flex-row items-center justify-between gap-4">
    <div id="total-prism-count" class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-center md:text-left">
        Showing <?= count($prism_rows) ?> of <?= number_format($total_rows) ?> entries
    </div>
    <div class="flex items-center gap-1 order-1 sm:order-2 overflow-x-auto max-w-full pb-2 sm:pb-0">
        <?php
        $window = 2; // Number of pages to show before and after current page
        $start_page = max(1, $page - $window);
        $end_page = min($total_pages, $page + $window);

        if ($page > 1): ?>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black bg-white/5 text-gray-400 hover:bg-white/10" data-page="1" title="First Page">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black bg-white/5 text-gray-400 hover:bg-white/10" data-page="<?= $page - 1 ?>" title="Previous">
                <i class="fas fa-angle-left"></i>
            </button>
        <?php endif; ?>

        <?php if ($start_page > 1): ?>
            <span class="text-gray-600 px-1">...</span>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black transition-all <?= $i == $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-white/5 text-gray-400 hover:bg-white/10' ?>" data-page="<?= $i ?>">
                <?= $i ?>
            </button>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages): ?>
            <span class="text-gray-600 px-1">...</span>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black bg-white/5 text-gray-400 hover:bg-white/10" data-page="<?= $page + 1 ?>" title="Next">
                <i class="fas fa-angle-right"></i>
            </button>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black bg-white/5 text-gray-400 hover:bg-white/10" data-page="<?= $total_pages ?>" title="Last Page">
                <i class="fas fa-angle-double-right"></i>
            </button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
