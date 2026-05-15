<div class="overflow-x-auto min-h-[300px]">
    <table class="w-full text-left border-collapse glass-table whitespace-nowrap">
        <thead>
            <tr>
                <th class="px-5 py-3 w-10 text-center">
                    <input type="checkbox" id="selectAll" class="rounded border-white/20 bg-slate-900 text-blue-500 focus:ring-offset-slate-900">
                </th>
                <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase">Store Code</th>
                <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase">Store Name</th>
                <th class="px-5 py-3 font-bold text-gray-500 text-[10px] tracking-widest uppercase text-center">Actions</th>
            </tr>
        </thead>
        <tbody id="stores-tbody" class="text-sm">
            <?php if (empty($stores)): ?>
            <tr>
                <td colspan="4" class="px-5 py-12 text-center text-gray-500">
                    <div class="flex flex-col items-center gap-2 opacity-20">
                        <i class="fas fa-store-slash text-4xl text-gray-500"></i>
                        <span class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">No stores found</span>
                    </div>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($stores as $s): ?>
                <tr class="hover:bg-white/5 transition-colors border-b border-white/5 last:border-0 border-r border-transparent hover:border-r-blue-500/50">
                    <td class="px-5 py-3.5 text-center">
                        <input type="checkbox" value="<?= htmlspecialchars($s['scode']) ?>" class="store-checkbox rounded border-white/20 bg-slate-900 text-blue-500 focus:ring-offset-slate-900">
                    </td>
                    <td class="px-5 py-3.5 font-bold text-blue-300 tracking-wide"><?= htmlspecialchars($s['scode']) ?></td>
                    <td class="px-5 py-3.5 text-gray-300 font-bold"><?= htmlspecialchars($s['sname']) ?></td>
                    <td class="px-5 py-3.5 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="editStore('<?= htmlspecialchars($s['scode'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['sname'], ENT_QUOTES) ?>')" class="w-7 h-7 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 transition-all flex items-center justify-center" title="Edit Store"><i class="fas fa-edit text-[10px]"></i></button>
                            <button onclick="deleteStore('<?= htmlspecialchars($s['scode'], ENT_QUOTES) ?>')" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-all flex items-center justify-center" title="Delete Store"><i class="fas fa-trash-alt text-[10px]"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="px-5 py-4 border-t border-white/5 flex items-center justify-between bg-slate-800/10">
    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Page <?= $page ?> of <?= $total_pages ?> <span class="mx-2 opacity-30">|</span> Result: <?= $total_rows ?> stores</span>
    
    <div class="flex items-center gap-1">
        <a href="#" data-page="1" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-[10px]" title="First Page"><i class="fas fa-angle-double-left"></i></a>
        
        <?php if ($page > 1): ?>
            <a href="#" data-page="<?= $page - 1 ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-xs"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        
        <?php 
        $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++): 
        ?>
            <a href="#" data-page="<?= $i ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg text-xs font-black transition-all <?= $i == $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30' : 'bg-white/5 text-gray-500 hover:text-white' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="#" data-page="<?= $page + 1 ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-xs"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>

        <a href="#" data-page="<?= $total_pages ?>" class="pagination-link w-7 h-7 flex items-center justify-center rounded-lg bg-white/5 text-gray-500 hover:text-white transition-all text-[10px]" title="Last Page"><i class="fas fa-angle-double-right"></i></a>
    </div>
</div>
