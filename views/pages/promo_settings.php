<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']) || (($_SESSION['role'] ?? '') !== 'admin' && empty($_SESSION['is_admin']))) {
    echo "<div class='glass-panel p-8 text-center text-red-400 font-bold'>Access Denied. You do not have permission to view Promo Settings.</div>";
    return;
}

require_once 'includes/db.php';
$settings = get_system_settings();
?>

<div class="glass-panel border border-white/10 p-6 md:p-8 rounded-2xl w-full shadow-2xl relative overflow-hidden">
    <!-- Ambient glowing backgrounds -->
    <div class="absolute -top-12 -right-12 w-72 h-72 bg-pink-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-12 -left-12 w-72 h-72 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-6 border-b border-white/10 relative z-10">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-pink-500/30 to-purple-600/30 border border-pink-500/40 flex items-center justify-center shadow-lg shadow-pink-500/10">
                <i class="fas fa-gift text-2xl text-transparent bg-clip-text bg-gradient-to-r from-pink-400 to-purple-300"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-black text-white uppercase tracking-tight">Promo Settings</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-pink-500/20 text-pink-300 border border-pink-500/30">Promo Engine</span>
                </div>
                <p class="text-gray-300 text-xs mt-1">Configure promotional gift items, minimum spend tiers, and item codes</p>
            </div>
        </div>

        <!-- Quick Fill Preset Button -->
        <div class="flex items-center gap-2">
            <button type="button" onclick="loadOct2026Preset()" class="px-3.5 py-2 rounded-xl bg-slate-800/90 hover:bg-slate-700/90 text-pink-300 border border-pink-500/30 hover:border-pink-500/60 transition-all text-xs font-bold flex items-center gap-2 shadow-md group">
                <i class="fas fa-wand-magic-sparkles text-pink-400 group-hover:rotate-12 transition-transform"></i>
                <span>Load Oct 2026 Promo Preset</span>
            </button>
        </div>
    </div>

    <form id="promo-settings-form" class="space-y-8 relative z-10">
        
        <!-- Master Status Toggle Card -->
        <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/90 border-2 border-slate-700/80 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-start sm:items-center gap-4">
                <div id="status-icon-box" class="w-12 h-12 rounded-xl <?= (!empty($settings['promo_enabled'])) ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-400' : 'bg-slate-800 border-slate-700 text-gray-500' ?> border flex items-center justify-center shrink-0 transition-all">
                    <i id="status-icon" class="fas <?= (!empty($settings['promo_enabled'])) ? 'fa-toggle-on text-2xl' : 'fa-toggle-off text-2xl' ?>"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-white uppercase tracking-wide">Promotion Status</h3>
                        <span id="promo-status-badge" class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider <?= (!empty($settings['promo_enabled'])) ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-gray-800 text-gray-400 border border-gray-700' ?>">
                            <?= (!empty($settings['promo_enabled'])) ? 'Active & Running' : 'Disabled' ?>
                        </span>
                    </div>
                    <p class="text-xs text-gray-300 mt-1">When enabled, cashiers can claim the Promo Gift in the Create Sale screen when minimum spend is reached.</p>
                </div>
            </div>

            <div class="flex items-center gap-4 self-end md:self-center">
                <label class="relative inline-flex items-center cursor-pointer select-none">
                    <input type="checkbox" name="promo_enabled" id="promo-enabled-toggle" value="1" <?= (!empty($settings['promo_enabled'])) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-14 h-7 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-pink-600 peer-checked:to-purple-600 border-2 border-slate-600"></div>
                </label>
            </div>
        </div>

        <!-- High-Visibility Input Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Promo Campaign Name -->
            <div class="space-y-2">
                <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider ml-1">
                    <span class="flex items-center gap-1.5"><i class="fas fa-tag text-pink-400"></i> Promo Campaign Name</span>
                    <span class="text-[10px] text-pink-400 font-bold bg-pink-500/10 px-2 py-0.5 rounded border border-pink-500/20">Required</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-pink-400">
                        <i class="fas fa-bullhorn text-sm"></i>
                    </div>
                    <input type="text" name="promo_name" id="promo-name-input" 
                           value="<?= htmlspecialchars($settings['promo_name'] ?? 'RL Shoe Bag Promo') ?>" required
                           class="w-full bg-slate-900 border-2 border-slate-600 focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 rounded-xl pl-10 pr-4 py-3 text-sm text-white font-bold placeholder-gray-500 shadow-inner transition-all outline-none" 
                           placeholder="e.g. RL Shoe Bag Promo">
                </div>
                <p class="text-[11px] text-gray-400 ml-1">Displayed as the promo title on POS banner and transaction logs.</p>
            </div>

            <!-- Minimum Spend Requirement -->
            <div class="space-y-2">
                <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider ml-1">
                    <span class="flex items-center gap-1.5"><i class="fas fa-coins text-emerald-400"></i> Minimum Spend Requirement</span>
                    <span class="text-[10px] text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">Philippine Peso (PHP)</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-400 font-bold text-base">
                        ₱
                    </div>
                    <input type="number" step="0.01" min="0" name="promo_min_spend" id="promo-min-spend-input" 
                           value="<?= htmlspecialchars($settings['promo_min_spend'] ?? '1999.00') ?>" required
                           class="w-full bg-slate-900 border-2 border-slate-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl pl-9 pr-4 py-3 text-sm text-emerald-400 font-black placeholder-gray-500 shadow-inner transition-all outline-none" 
                           placeholder="1999.00">
                </div>
                <p class="text-[11px] text-gray-400 ml-1">Cashier is only allowed to claim gift once sale subtotal meets or exceeds this amount.</p>
            </div>

            <!-- Gift Item Name / Description -->
            <div class="space-y-2">
                <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider ml-1">
                    <span class="flex items-center gap-1.5"><i class="fas fa-shopping-bag text-purple-400"></i> Gift Item Description</span>
                    <span class="text-[10px] text-purple-400 font-bold bg-purple-500/10 px-2 py-0.5 rounded border border-purple-500/20">Promo Item</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-purple-400">
                        <i class="fas fa-gift text-sm"></i>
                    </div>
                    <input type="text" name="promo_item_name" id="promo-item-name-input" 
                           value="<?= htmlspecialchars($settings['promo_item_name'] ?? 'RL Shoe Bag') ?>" required
                           class="w-full bg-slate-900 border-2 border-slate-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 rounded-xl pl-10 pr-4 py-3 text-sm text-white font-bold placeholder-gray-500 shadow-inner transition-all outline-none" 
                           placeholder="e.g. RL Shoe Bag">
                </div>
                <p class="text-[11px] text-gray-400 ml-1">Name of free promotional item provided to customer.</p>
            </div>

            <!-- Default Gift Item # (Barcode / SKU) -->
            <div class="space-y-2">
                <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider ml-1">
                    <span class="flex items-center gap-1.5"><i class="fas fa-barcode text-cyan-400"></i> Default Gift Item #</span>
                    <span class="text-[10px] text-cyan-400 font-bold bg-cyan-500/10 px-2 py-0.5 rounded border border-cyan-500/20">Default: 475552</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-cyan-400">
                        <i class="fas fa-barcode text-sm"></i>
                    </div>
                    <input type="text" name="promo_item_no" id="promo-item-no-input" 
                           value="<?= htmlspecialchars($settings['promo_item_no'] ?? '475552') ?>"
                           class="w-full bg-slate-900 border-2 border-slate-600 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 rounded-xl pl-10 pr-4 py-3 text-sm text-white font-bold placeholder-gray-500 shadow-inner transition-all outline-none font-mono" 
                           placeholder="475552">
                </div>
                <p class="text-[11px] text-gray-400 ml-1">Default POS item number for the promotional gift.</p>
            </div>

            <!-- Default Gift Style Code -->
            <div class="space-y-2">
                <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider ml-1">
                    <span class="flex items-center gap-1.5"><i class="fas fa-shapes text-pink-400"></i> Gift Style Code</span>
                    <span class="text-[10px] text-pink-400 font-bold bg-pink-500/10 px-2 py-0.5 rounded border border-pink-500/20">Default: RACT95002T26</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-pink-400">
                        <i class="fas fa-hashtag text-sm"></i>
                    </div>
                    <input type="text" name="promo_style_code" id="promo-style-code-input" 
                           value="<?= htmlspecialchars($settings['promo_style_code'] ?? 'RACT95002T26') ?>"
                           class="w-full bg-slate-900 border-2 border-slate-600 focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 rounded-xl pl-10 pr-4 py-3 text-sm text-white font-bold placeholder-gray-500 shadow-inner transition-all outline-none font-mono uppercase" 
                           placeholder="RACT95002T26">
                </div>
                <p class="text-[11px] text-gray-400 ml-1">Style code displayed in POS item details when claimed.</p>
            </div>

            <!-- Promo Start Date -->
            <div class="space-y-2">
                <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider ml-1">
                    <span class="flex items-center gap-1.5"><i class="fas fa-calendar-day text-amber-400"></i> Promo Start Date</span>
                    <span class="text-[10px] text-amber-400 font-bold bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">Active Date</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-amber-400">
                        <i class="fas fa-calendar-alt text-sm"></i>
                    </div>
                    <input type="date" name="promo_start_date" id="promo-start-date-input" 
                           value="<?= htmlspecialchars($settings['promo_start_date'] ?? '2026-10-01') ?>"
                           style="color-scheme: dark;"
                           class="w-full bg-slate-900 border-2 border-slate-600 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl pl-10 pr-4 py-3 text-sm text-white font-bold shadow-inner transition-all outline-none cursor-pointer">
                </div>
                <p class="text-[11px] text-gray-400 ml-1">First day when this promotion becomes valid.</p>
            </div>

            <!-- Promo End Date -->
            <div class="space-y-2 md:col-span-2">
                <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider ml-1">
                    <span class="flex items-center gap-1.5"><i class="fas fa-calendar-check text-blue-400"></i> Promo End Date</span>
                    <span class="text-[10px] text-blue-400 font-bold bg-blue-500/10 px-2 py-0.5 rounded border border-blue-500/20">Optional</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-blue-400">
                        <i class="fas fa-calendar-check text-sm"></i>
                    </div>
                    <input type="date" name="promo_end_date" id="promo-end-date-input" 
                           value="<?= htmlspecialchars($settings['promo_end_date'] ?? '') ?>"
                           style="color-scheme: dark;"
                           class="w-full bg-slate-900 border-2 border-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 rounded-xl pl-10 pr-4 py-3 text-sm text-white font-bold shadow-inner transition-all outline-none cursor-pointer">
                </div>
                <p class="text-[11px] text-gray-400 ml-1">Leave blank if the promotion runs indefinitely until manually disabled.</p>
            </div>

        </div>

        <!-- Live Cashier Preview Section -->
        <div class="p-6 rounded-2xl bg-slate-900/90 border-2 border-slate-700/80 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-desktop text-pink-400"></i>
                    <h4 class="text-xs font-black text-white uppercase tracking-wider">Live Cashier Banner Preview (Create Sale Screen)</h4>
                </div>
                <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-widest">Real-time Reactive Preview</span>
            </div>

            <div id="preview-container" class="transition-all duration-300">
                <div class="p-4 rounded-xl bg-gradient-to-r from-purple-950/70 via-slate-900/90 to-pink-950/70 border-2 border-pink-500/40 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-pink-500/30 shrink-0">
                            <i class="fas fa-gift text-xl animate-bounce"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-black uppercase tracking-wider bg-pink-500/30 text-pink-200 px-2 py-0.5 rounded border border-pink-500/40">PROMO ELIGIBLE</span>
                                <h4 id="preview-promo-title" class="text-sm font-black text-white uppercase tracking-wider"><?= htmlspecialchars($settings['promo_name'] ?? 'RL Shoe Bag Promo') ?></h4>
                            </div>
                            <p class="text-xs text-gray-200 mt-1">
                                Spend <span id="preview-min-spend" class="text-emerald-400 font-black">₱<?= number_format((float)($settings['promo_min_spend'] ?? 1999), 2) ?></span>+ to get a <span id="preview-gift-name" class="text-pink-300 font-black">FREE <?= htmlspecialchars($settings['promo_item_name'] ?? 'RL Shoe Bag') ?></span> <span id="preview-gift-codes" class="text-xs text-gray-400 font-mono">(#<?= htmlspecialchars($settings['promo_item_no'] ?? '475552') ?> • <?= htmlspecialchars($settings['promo_style_code'] ?? 'RACT95002T26') ?>)</span>!
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 self-end sm:self-center">
                        <div class="px-4 py-2 rounded-xl bg-pink-500/20 border border-pink-500/40 text-pink-300 text-xs font-bold flex items-center gap-2">
                            <i class="fas fa-toggle-on text-pink-400"></i>
                            <span>Claim Promo Gift (₱0.00)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Submission Button -->
        <div class="pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-gray-400">
                <i class="fas fa-info-circle text-pink-400 mr-1"></i> Changes take effect immediately across all concession stores.
            </div>
            <button type="submit" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-gradient-to-r from-pink-600 to-purple-600 text-white font-black text-xs uppercase tracking-[0.2em] shadow-lg shadow-pink-500/25 hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer">
                <i class="fas fa-save text-sm"></i> Save Promo Settings
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const promoToggle = document.getElementById('promo-enabled-toggle');
    const statusIcon = document.getElementById('status-icon');
    const statusIconBox = document.getElementById('status-icon-box');
    const statusBadge = document.getElementById('promo-status-badge');

    const promoNameInput = document.getElementById('promo-name-input');
    const promoMinSpendInput = document.getElementById('promo-min-spend-input');
    const promoItemNameInput = document.getElementById('promo-item-name-input');
    const promoItemNoInput = document.getElementById('promo-item-no-input');
    const promoStyleCodeInput = document.getElementById('promo-style-code-input');

    const previewTitle = document.getElementById('preview-promo-title');
    const previewMinSpend = document.getElementById('preview-min-spend');
    const previewGiftName = document.getElementById('preview-gift-name');
    const previewGiftCodes = document.getElementById('preview-gift-codes');
    const previewContainer = document.getElementById('preview-container');

    function updatePreviewCodes() {
        if (!previewGiftCodes) return;
        const itemNo = promoItemNoInput?.value.trim() || '475552';
        const styleCode = promoStyleCodeInput?.value.trim() || 'RACT95002T26';
        previewGiftCodes.textContent = `(#${itemNo} • ${styleCode})`;
    }

    // Toggle switch handler
    if (promoToggle) {
        promoToggle.addEventListener('change', () => {
            if (promoToggle.checked) {
                statusBadge.textContent = 'Active & Running';
                statusBadge.className = 'px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                
                statusIconBox.className = 'w-12 h-12 rounded-xl bg-emerald-500/20 border-emerald-500/40 text-emerald-400 border flex items-center justify-center shrink-0 transition-all';
                statusIcon.className = 'fas fa-toggle-on text-2xl';

                previewContainer.style.opacity = '1';
                previewContainer.style.filter = 'none';
            } else {
                statusBadge.textContent = 'Disabled';
                statusBadge.className = 'px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-gray-800 text-gray-400 border border-gray-700';
                
                statusIconBox.className = 'w-12 h-12 rounded-xl bg-slate-800 border-slate-700 text-gray-500 border flex items-center justify-center shrink-0 transition-all';
                statusIcon.className = 'fas fa-toggle-off text-2xl';

                previewContainer.style.opacity = '0.5';
                previewContainer.style.filter = 'grayscale(0.6)';
            }
        });
    }

    // Live preview update
    if (promoNameInput && previewTitle) {
        promoNameInput.addEventListener('input', (e) => {
            previewTitle.textContent = e.target.value.trim() || 'RL Shoe Bag Promo';
        });
    }

    if (promoMinSpendInput && previewMinSpend) {
        promoMinSpendInput.addEventListener('input', (e) => {
            const val = parseFloat(e.target.value) || 0;
            previewMinSpend.textContent = '₱' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        });
    }

    if (promoItemNameInput && previewGiftName) {
        promoItemNameInput.addEventListener('input', (e) => {
            previewGiftName.textContent = 'FREE ' + (e.target.value.trim() || 'RL Shoe Bag');
        });
    }

    if (promoItemNoInput) {
        promoItemNoInput.addEventListener('input', updatePreviewCodes);
    }
    if (promoStyleCodeInput) {
        promoStyleCodeInput.addEventListener('input', updatePreviewCodes);
    }

    // Save form handler
    const form = document.getElementById('promo-settings-form');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (typeof showGlobalLoader === 'function') showGlobalLoader("Saving Promo Settings...");
        
        try {
            const formData = new FormData(form);
            const response = await fetch('api/save_promo_settings.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
            
            if (result.success) {
                if (typeof showStatusModal === 'function') {
                    showStatusModal(true, result.message, 'Success');
                } else {
                    alert(result.message);
                }
            } else {
                if (typeof showStatusModal === 'function') {
                    showStatusModal(false, result.message, 'Error');
                } else {
                    alert(result.message);
                }
            }
        } catch (error) {
            if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
            console.error('Error saving promo settings:', error);
            if (typeof showStatusModal === 'function') {
                showStatusModal(false, 'A network error occurred while saving promo settings.', 'Error');
            } else {
                alert('A network error occurred.');
            }
        }
    });
});

// Quick Preset function
function loadOct2026Preset() {
    document.getElementById('promo-name-input').value = 'RL Shoe Bag Promo';
    document.getElementById('promo-min-spend-input').value = '1999.00';
    document.getElementById('promo-item-name-input').value = 'RL Shoe Bag';
    document.getElementById('promo-item-no-input').value = '475552';
    document.getElementById('promo-style-code-input').value = 'RACT95002T26';
    document.getElementById('promo-start-date-input').value = '2026-10-01';
    document.getElementById('promo-end-date-input').value = '';
    
    const promoToggle = document.getElementById('promo-enabled-toggle');
    if (promoToggle && !promoToggle.checked) {
        promoToggle.checked = true;
        promoToggle.dispatchEvent(new Event('change'));
    }

    // Trigger input events for live preview
    document.getElementById('promo-name-input').dispatchEvent(new Event('input'));
    document.getElementById('promo-min-spend-input').dispatchEvent(new Event('input'));
    document.getElementById('promo-item-name-input').dispatchEvent(new Event('input'));
    document.getElementById('promo-item-no-input').dispatchEvent(new Event('input'));
    document.getElementById('promo-style-code-input').dispatchEvent(new Event('input'));
}
</script>
