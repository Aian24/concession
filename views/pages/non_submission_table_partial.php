<?php if (!isset($missing_stores)) exit; ?>

<style>
    @media (max-width: 768px) {
        #non-submission-table thead { display: none; }
        #non-submission-table, #non-submission-table tbody { display: block; width: 100%; }
        #non-submission-table tr { 
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
        #non-submission-table tr:first-child { margin-top: 1.5rem; }
        #non-submission-table td { 
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
        #non-submission-table td::before { 
            content: attr(data-label); 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 7px; 
            color: #64748b; 
            letter-spacing: 0.1em;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        #non-submission-table td[data-label="Missing Date"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: auto;
            border: none;
            padding: 0;
            z-index: 10;
        }
        #non-submission-table td[data-label="Missing Date"]::before { display: none; }
        
        #non-submission-table td span, 
        #non-submission-table td div { 
            font-size: 10px !important; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #non-submission-table td[data-label="Store Name"] { grid-column: span 2 !important; }
    }
</style>
<div class="overflow-x-auto min-h-[300px]">
    <table id="non-submission-table" class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-900/50 text-[10px] uppercase tracking-widest text-gray-500 border-y border-white/10">
                <th class="p-4 font-black w-1/4">Store Code</th>
                <th class="p-4 font-black w-1/2">Store Name</th>
                <th class="p-4 font-black text-right w-1/4">Missing Date</th>
            </tr>
        </thead>
        <tbody class="text-xs divide-y divide-white/5 bg-slate-800/10" id="non-submission-tbody">
            <?php if (empty($missing_stores)): ?>
                <tr>
                    <td colspan="3" class="p-8 text-center text-gray-500 italic">No stores are missing submissions for the selected date range.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($missing_stores as $s): ?>
                    <tr class="hover:bg-white/5 transition-colors group border-b border-white/5 last:border-0 border-r border-transparent hover:border-r-red-500/50">
                        <td class="p-4 font-bold text-white" data-label="Store Code"><?= htmlspecialchars($s['scode']) ?></td>
                        <td class="p-4 text-gray-300 font-medium" data-label="Store Name"><?= htmlspecialchars($s['sname'] ?: 'N/A') ?></td>
                        <td class="p-4 md:text-right" data-label="Missing Date">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-400 text-[10px] font-black uppercase tracking-widest">
                                <i class="fas fa-calendar-times"></i> <?= date('M d, Y', strtotime($s['missing_date'])) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<div class="p-4 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-900/30">
    <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
        Showing <?= ($offset + 1) ?> to <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> entries
    </div>
    <div class="flex items-center gap-1">
        <?php if ($page > 1): ?>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center bg-slate-800 hover:bg-slate-700 text-white transition-colors border border-white/5" data-page="<?= $page - 1 ?>"><i class="fas fa-chevron-left text-[10px]"></i></button>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-bold transition-all <?= $i === $page ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-500/20 border-none' : 'bg-slate-800 hover:bg-slate-700 text-gray-400 border border-white/5' ?>" data-page="<?= $i ?>">
                <?= $i ?>
            </button>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <button class="pagination-link w-8 h-8 rounded-lg flex items-center justify-center bg-slate-800 hover:bg-slate-700 text-white transition-colors border border-white/5" data-page="<?= $page + 1 ?>"><i class="fas fa-chevron-right text-[10px]"></i></button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
