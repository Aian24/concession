<?php if (empty($boutique_rows)): ?>
    <div class="text-center py-10">
        <i class="fas fa-box-open text-3xl text-gray-600 mb-3 block"></i>
        <p class="text-gray-400 font-bold text-[10px] uppercase tracking-widest">No Boutique Data Available</p>
    </div>
<?php else: ?>
    <style>
        @media (max-width: 768px) {
            #boutique-data-table thead { display: none; }
            #boutique-data-table, #boutique-data-table tbody { display: block; width: 100%; }
            #boutique-data-table tr { 
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
            #boutique-data-table tr:first-child { margin-top: 1.5rem; }
            #boutique-data-table td { 
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
            #boutique-data-table td::before { 
                content: attr(data-label); 
                font-weight: 900; 
                text-transform: uppercase; 
                font-size: 7px; 
                color: #64748b; 
                letter-spacing: 0.1em;
                margin-bottom: 4px;
                opacity: 0.8;
            }
            
            #boutique-data-table td[data-label="Select"] {
                position: absolute;
                top: 1rem;
                left: 1rem;
                width: auto;
                border: none;
                padding: 0;
                z-index: 10;
            }
            #boutique-data-table td[data-label="Select"]::before { display: none; }

            #boutique-data-table td[data-label="Actions"] {
                position: absolute;
                top: 1rem;
                right: 1rem;
                width: auto;
                border: none;
                padding: 0;
                z-index: 10;
            }
            #boutique-data-table td[data-label="Actions"]::before { display: none; }
            #boutique-data-table td[data-label="Actions"] .flex { justify-content: flex-end; }
            
            #boutique-data-table td span, 
            #boutique-data-table td div { 
                font-size: 10px !important; 
                overflow: hidden;
                text-overflow: ellipsis;
            }
            #boutique-data-table td[data-label="Store Name"] { grid-column: span 2 !important; }
            
            /* Add some top padding to the first grid item to clear the absolute checkboxes */
            #boutique-data-table td[data-label="Date"] { margin-top: 1.5rem; }
            #boutique-data-table td[data-label="Store Code"] { margin-top: 1.5rem; }
        }
    </style>
    <div class="overflow-x-auto min-h-[400px]">
        <table id="boutique-data-table" class="w-full text-left text-[11px] text-gray-300">
            <thead class="text-[9px] text-gray-400 uppercase tracking-widest bg-white/5 border-b border-white/5">
                <tr>
                    <th class="px-4 py-3 font-bold w-10">
                        <input type="checkbox" id="selectAll" class="rounded bg-slate-900 border-white/10 text-yellow-500 focus:ring-yellow-500/20">
                    </th>
                    <th class="px-4 py-3 font-bold">Date</th>
                    <th class="px-4 py-3 font-bold">Store Code</th>
                    <th class="px-4 py-3 font-bold">Store Name</th>
                    <th class="px-4 py-3 font-bold text-right">Qty Sold</th>
                    <th class="px-4 py-3 font-bold text-right">Amount</th>
                    <th class="px-4 py-3 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php foreach ($boutique_rows as $row): ?>
                    <tr class="hover:bg-white/5 transition-colors group border-b border-white/5 last:border-0 border-r border-transparent hover:border-r-yellow-500/50">
                        <td class="px-4 py-2" data-label="Select">
                            <input type="checkbox" class="boutique-checkbox rounded bg-slate-900 border-white/10 text-yellow-500 focus:ring-yellow-500/20" value="<?= $row['id'] ?>">
                        </td>
                        <td class="px-4 py-2 font-bold text-white" data-label="Date"><?= htmlspecialchars($row['date']) ?></td>
                        <td class="px-4 py-2 font-medium text-yellow-400" data-label="Store Code"><?= htmlspecialchars($row['store_code']) ?></td>
                        <td class="px-4 py-2 text-gray-400" data-label="Store Name"><?= htmlspecialchars($row['store_name']) ?></td>
                        <td class="px-4 py-2 md:text-right text-gray-200 font-bold" data-label="Qty Sold"><?= number_format($row['qty_sold']) ?></td>
                        <td class="px-4 py-2 md:text-right text-emerald-400 font-black" data-label="Amount">₱<?= number_format($row['amount'], 2) ?></td>
                        <td class="px-4 py-2 text-right" data-label="Actions">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="editBoutique(<?= $row['id'] ?>, '<?= htmlspecialchars($row['date'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['store_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['store_name'], ENT_QUOTES) ?>', <?= $row['qty_sold'] ?>, <?= $row['amount'] ?>)" class="w-7 h-7 rounded-lg bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 flex items-center justify-center transition-colors" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteBoutique(<?= $row['id'] ?>)" class="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 flex items-center justify-center transition-colors" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 border-t border-white/5 bg-slate-800/30 flex items-center justify-between">
            <span class="text-[10px] text-gray-500 font-bold uppercase tracking-wider" id="total-boutique-count">
                Showing <?= count($boutique_rows) ?> of <?= $total_rows ?>
            </span>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="#" class="pagination-link w-7 h-7 flex items-center justify-center rounded bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-colors" data-page="<?= $page - 1 ?>"><i class="fas fa-chevron-left text-[10px]"></i></a>
                <?php endif; ?>
                
                <?php
                $start_p = max(1, $page - 2);
                $end_p = min($total_pages, $page + 2);
                for ($i = $start_p; $i <= $end_p; $i++):
                ?>
                    <a href="#" class="pagination-link w-7 h-7 flex items-center justify-center rounded <?= $i == $page ? 'bg-yellow-500/20 text-yellow-400 font-black' : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white' ?> text-[10px] transition-colors" data-page="<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="#" class="pagination-link w-7 h-7 flex items-center justify-center rounded bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-colors" data-page="<?= $page + 1 ?>"><i class="fas fa-chevron-right text-[10px]"></i></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
