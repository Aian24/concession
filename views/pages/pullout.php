<?php
// Pull session data
$pull_username   = $_SESSION['user']       ?? '';
$pull_store_code = $_SESSION['store_code'] ?? '';
// Use global flags from index.php; re-derive if loaded standalone (AJAX)
if (!isset($is_admin)) {
    $role            = $_SESSION['role'] ?? 'user';
    $is_full_admin   = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
    $is_admin_view   = ($role === 'admin_view');
    $is_store_admin  = ($role === 'store_admin');
    $is_multi_store_admin = ($role === 'multi_store_admin');
    $is_admin        = ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);
    $can_submit      = ($role === 'user');
    $can_edit        = ($is_full_admin || $is_admin_view || $is_multi_store_admin);
    $can_delete      = ($is_full_admin);
}

require_once 'includes/db.php';
$db = db_connect();

// ── Search & Pagination Logic ──────────────────────────────
$is_filter_request = isset($_GET['ajax']) || isset($_GET['search']) || isset($_GET['start_date']) || isset($_GET['end_date']) || isset($_GET['store_filter']);
if ($is_filter_request) {
    $limit        = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 100;
    $page         = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $search       = $_GET['search'] ?? '';
    $start_date   = $_GET['start_date'] ?? '';
    $end_date     = $_GET['end_date']   ?? '';
    $store_filter = $_GET['store_filter'] ?? '';
    
    $_SESSION['pullout_limit'] = $limit;
    $_SESSION['pullout_page'] = $page;
    $_SESSION['pullout_search'] = $search;
    $_SESSION['pullout_start_date'] = $start_date;
    $_SESSION['pullout_end_date'] = $end_date;
    $_SESSION['pullout_store_filter'] = $store_filter;
} else {
    $limit        = $_SESSION['pullout_limit'] ?? 100;
    $page         = $_SESSION['pullout_page'] ?? 1;
    $search       = $_SESSION['pullout_search'] ?? '';
    $start_date   = $_SESSION['pullout_start_date'] ?? '';
    $end_date     = $_SESSION['pullout_end_date'] ?? '';
    $store_filter = $_SESSION['pullout_store_filter'] ?? '';
}
$offset       = ($page - 1) * $limit;

// Build Query
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($is_store_admin || (!$is_admin && !$is_multi_store_admin)) {
    $where .= " AND s.store_code = ?";
    $params[] = $pull_store_code;
    $types .= "s";
} elseif ($is_multi_store_admin) {
    $assigned = $_SESSION['assigned_stores'] ?? [];
    if ($store_filter !== '' && in_array($store_filter, $assigned)) {
        $where .= " AND s.store_code = ?";
        $params[] = $store_filter;
        $types .= "s";
    } else {
        $where .= build_multi_store_clause('s.store_code', $assigned);
    }
} elseif ($is_admin && $store_filter !== '') {
    $where .= " AND s.store_code = ?";
    $params[] = $store_filter;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (s.item_no LIKE ? OR s.username LIKE ? OR s.id LIKE ? OR s.store_code LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk;
    $types .= "ssss";
}

if ($start_date !== '') {
    $where .= " AND s.created_at >= ?";
    $params[] = $start_date . ' 00:00:00';
    $types .= "s";
}

if ($end_date !== '') {
    $where .= " AND s.created_at <= ?";
    $params[] = $end_date . ' 23:59:59';
    $types .= "s";
}

// Get Total for Pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM pullouts s $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $limit));
$count_stmt->close();

// Fetch Rows
$recent_stmt = $db->prepare("SELECT s.*, sc.sname FROM pullouts s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
$p_with_limit = array_merge($params, [$limit, $offset]);
$recent_stmt->bind_param($types . "ii", ...$p_with_limit);
$recent_stmt->execute();
$submitted_pullouts = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

// If this is an AJAX request for the table, only return the table part
if (isset($_GET['ajax'])) {
    include 'views/pages/pullout_table_partial.php';
    exit;
}
?>

<div class="pb-12 animate-fade-in">
    <?php if ($can_submit): ?>
    <!-- New Pullout Form -->
    <div class="glass-panel border border-white/5 shadow-xl mb-10 min-h-[70vh] flex flex-col">
        <div class="px-5 py-2.5 border-b border-white/5 bg-slate-800/25 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-arrow-right-from-bracket text-amber-400 text-xs"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">New Pullout Entry</h3>
            </div>
            <span id="entry-count-badge" class="text-[9px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 px-1.5 py-0.5 rounded">1 item</span>
        </div>

        <div class="p-4 flex-grow flex flex-col justify-start gap-4">
            <!-- Transaction Date Selector -->
            <div class="glass-panel border border-white/5 p-3 mb-2 bg-white/2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center border border-amber-500/20">
                            <i class="fas fa-calendar-day text-amber-400 text-[10px]"></i>
                        </div>
                        <div>
                            <h4 class="text-[9px] font-black text-white uppercase tracking-widest leading-none mb-0.5">Transaction Date</h4>
                            <p class="text-[7px] text-gray-500 font-bold uppercase tracking-tighter">Current or previous date</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <div class="grid grid-cols-2 gap-2 w-full sm:w-64">
                            <!-- Current Date Option -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="page_date_type" value="current" checked class="peer sr-only" onchange="handleDateTypeChange(this.value)">
                                <div class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg bg-slate-900 border border-white/5 peer-checked:border-amber-500/50 peer-checked:bg-amber-500/10 transition-all">
                                    <i class="fas fa-clock text-[9px] text-gray-500 peer-checked:text-amber-400"></i>
                                    <span class="text-[9px] font-bold text-gray-500 peer-checked:text-amber-400 uppercase tracking-tighter">Current</span>
                                </div>
                            </label>

                            <!-- Backdate Option -->
                            <label class="relative cursor-pointer group" onclick="document.getElementById('page_custom_date').showPicker()">
                                <input type="radio" name="page_date_type" value="backdate" class="peer sr-only" onchange="handleDateTypeChange(this.value)">
                                <div id="backdate-btn-content" class="py-1.5 px-3 flex items-center justify-center gap-2 rounded-lg bg-slate-900 border border-white/5 peer-checked:border-amber-500/50 peer-checked:bg-amber-500/10 transition-all overflow-hidden relative">
                                    <i class="fas fa-history text-[9px] text-gray-500 peer-checked:text-amber-400"></i>
                                    <span id="backdate-text" class="text-[9px] font-bold text-gray-500 peer-checked:text-amber-400 uppercase tracking-tighter">Backdate</span>
                                    <input type="date" id="page_custom_date" 
                                           max="<?= date('Y-m-d') ?>" 
                                           class="absolute inset-0 opacity-0 cursor-pointer pointer-events-none" 
                                           value="<?= date('Y-m-d') ?>"
                                           onchange="updateBackdateText(this.value)">
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function handleDateTypeChange(val) {
                    const backdateText = document.getElementById('backdate-text');
                    if (val !== 'backdate') {
                        backdateText.innerText = 'Backdate';
                    }
                }

                function updateBackdateText(val) {
                    if (!val) return;
                    const [year, month, day] = val.split('-');
                    const date = new Date(year, month - 1, day);
                    const formatted = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    document.getElementById('backdate-text').innerText = formatted;
                }
            </script>

            <div id="entry-rows" class="space-y-4">
                <!-- Row Template -->
                <div class="entry-row glass-panel border border-white/5 shadow-lg overflow-hidden bg-[#0d1527]/30 animate-slide-in">
                    <div class="px-4 py-2 bg-white/5 border-b border-white/5 flex items-center justify-between">
                        <span class="entry-title text-[10px] font-black text-gray-500 uppercase tracking-widest">Entry #1</span>
                        <button type="button" onclick="removeRow(this)" class="remove-btn hidden text-red-500/50 hover:text-red-500 transition-colors flex items-center gap-1.5 group">
                            <span class="text-[9px] font-bold uppercase tracking-wider opacity-0 group-hover:opacity-100 transition-opacity">Remove</span>
                            <i class="fas fa-trash-alt text-[10px]"></i>
                        </button>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="relative">
                            <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-amber-400/80 uppercase tracking-widest z-10">Item #</span>
                            <div class="relative">
                                <input type="number" name="item_no[]" required oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-amber-500/50 font-medium" placeholder="100123">
                            </div>
                        </div>
                        <div class="relative">
                            <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-amber-400/80 uppercase tracking-widest z-10">Qty</span>
                            <input type="number" name="quantity[]" min="1" class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-white focus:outline-none focus:border-amber-500/50 font-medium" placeholder="0">
                        </div>
                        <div class="relative">
                            <span class="absolute top-0 -translate-y-1/2 left-3 px-1 bg-[#0d1527] text-[8px] font-black text-amber-400/80 uppercase tracking-widest z-10">Image Proof</span>
                            <div class="flex items-center gap-2">
                                <label class="flex-grow">
                                    <input type="file" name="image[]" accept="image/*" class="hidden" onchange="previewEntryImage(this)">
                                    <div class="bg-slate-900/50 border border-white/10 rounded-xl px-4 py-2.5 w-full text-xs text-gray-500 hover:text-amber-400 hover:border-amber-500/30 transition-all cursor-pointer flex items-center gap-2 overflow-hidden">
                                        <i class="fas fa-image text-[10px]"></i>
                                        <span class="file-name truncate">Upload Image</span>
                                    </div>
                                </label>
                                <div class="image-preview-thumbnail hidden w-10 h-10 rounded-lg border border-white/10 overflow-hidden bg-slate-950 flex-shrink-0">
                                    <img src="#" class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-white/5 pt-4">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="button" onclick="addRow()" class="flex-1 sm:flex-initial h-[42px] px-4 rounded-lg bg-slate-700/40 hover:bg-slate-700/60 text-amber-400 border border-white/10 text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg hover:-translate-y-0.5">
                        <i class="fas fa-plus-circle"></i> Add Another Entry
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    <div class="flex flex-wrap items-center justify-between sm:justify-start gap-3 bg-slate-800/40 px-3 py-2 rounded-lg border border-white/5 min-h-[38px]">
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Entries:</span>
                            <span class="text-xs font-black text-white" id="summary-entries">1</span>
                        </div>
                        <div class="w-px h-3 bg-white/10"></div>
                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                            <span class="text-[9px] text-gray-500 font-bold uppercase tracking-tight">Total Qty:</span>
                            <span class="text-xs font-black text-white" id="summary-qty">0</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 w-full sm:w-auto h-[38px]">
                        <button type="button" onclick="clearForm()" class="px-3 py-0 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 text-[10px] font-bold border border-white/5 uppercase transition-all flex items-center justify-center">Clear</button>
                        <button type="button" id="submit-btn" onclick="submitPullout()" class="px-4 py-0 rounded-lg bg-gradient-to-r from-amber-600 to-orange-600 text-white text-[10px] font-black uppercase tracking-wider shadow-lg shadow-amber-500/10 hover:-translate-y-0.5 transition-all flex items-center justify-center">Submit Pullout</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- History Container (Only for Admins) -->
    <?php if ($is_admin): ?>
    <div id="loading-overlay" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[100] flex flex-col items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="relative w-20 h-20 mb-6">
            <div class="absolute inset-0 border-4 border-amber-500/20 border-t-amber-500 rounded-full animate-spin"></div>
            <div class="absolute top-4 left-4 w-12 h-12 border-4 border-orange-500/20 border-b-orange-500 rounded-full animate-spin-reverse"></div>
        </div>
        <p class="text-amber-400 font-semibold tracking-widest animate-pulse uppercase text-sm">Preparing File...</p>
    </div>
    <div id="pullout-history-container">
        <?php include 'views/pages/pullout_table_partial.php'; ?>
    </div>
    <?php endif; ?>

    <!-- Edit Pullout Modal (Admin) -->
    <div id="edit-pullout-modal" class="fixed inset-0 z-[102] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 scale-95">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeEditPulloutModal()"></div>
        <div class="relative glass-panel border border-white/10 shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="bg-gradient-to-tr from-amber-600/20 to-orange-600/20 p-5 border-b border-white/5 relative">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                        <i class="fas fa-edit text-amber-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white tracking-wide uppercase">Edit Pullout Record</h3>
                        <p id="edit-id-label" class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-0.5"></p>
                    </div>
                </div>
                <button onclick="closeEditPulloutModal()" class="absolute top-5 right-5 text-gray-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="p-6">
                <form id="edit-pullout-form" class="space-y-4">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Item #</label>
                        <input type="number" name="item_no" id="edit-item-no" required oninput="if(this.value.length > 6) this.value = this.value.slice(0, 6);" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500/50 transition-all font-medium" placeholder="100123">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Quantity</label>
                        <input type="number" name="quantity" id="edit-qty" required min="1" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500/50 transition-all font-medium">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1">Transaction Date</label>
                        <input type="date" name="created_at" id="edit-date" required class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500/50 transition-all font-medium cursor-pointer" onclick="this.showPicker()">
                    </div>
                    
                    <button type="submit" class="w-full py-4 mt-8 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-amber-500/20 hover:brightness-110 active:scale-[0.98] transition-all">
                        Update Record
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function updateBadge() {
        const rows = document.querySelectorAll('.entry-row');
        const badge = document.getElementById('entry-count-badge');
        if (badge) badge.textContent = rows.length + (rows.length === 1 ? ' item' : ' items');
        
        rows.forEach((r, i) => {
            r.querySelector('.entry-title').textContent = `Entry #${i + 1}`;
            const rem = r.querySelector('.remove-btn');
            if (rem) {
                if (i === 0) {
                    rem.classList.add('hidden');
                } else {
                    rem.classList.remove('hidden');
                }
            }
        });
    }

    function updateSummary() {
        let entries = 0, qty = 0;
        document.querySelectorAll('.entry-row').forEach(row => {
            const itm = row.querySelector('[name="item_no[]"]').value.trim();
            const q   = parseInt(row.querySelector('[name="quantity[]"]').value) || 0;
            if (itm) entries++;
            qty += q;
        });
        const summaryEntries = document.getElementById('summary-entries');
        const summaryQty = document.getElementById('summary-qty');
        if (summaryEntries) summaryEntries.textContent = entries;
        if (summaryQty) summaryQty.textContent = qty;
    }

    window.addRow = function () {
        // Try to find an existing row to clone, or use a cached template
        let tpl = document.querySelector('.entry-row');
        if (!tpl && window._entryRowTemplate) {
            tpl = window._entryRowTemplate;
        }
        
        if (!tpl) return;
        
        // Cache the template for future use if we haven't yet
        if (!window._entryRowTemplate) window._entryRowTemplate = tpl.cloneNode(true);
        
        const row = tpl.cloneNode(true);
        
        // Reset values
        row.querySelectorAll('input').forEach(i => i.value = '');
        row.querySelector('.file-name').textContent = 'Upload Image';
        row.querySelector('.image-preview-thumbnail').classList.add('hidden');
        row.querySelector('.image-preview-thumbnail img').src = '#';
        
        // Add events
        row.querySelectorAll('input').forEach(i => i.addEventListener('input', updateSummary));
        
        document.getElementById('entry-rows').appendChild(row);
        updateBadge();
        row.querySelector('input').focus();
    };

    window.removeRow = function (btn) {
        showConfirmModal('Remove this entry row?', () => {
            btn.closest('.entry-row').remove();
            updateBadge();
            updateSummary();
        }, 'Remove Entry');
    };

    window.resetForm = function() {
        const rows = document.querySelectorAll('.entry-row');
        rows.forEach((r, i) => { if (i > 0) r.remove(); });
        if (rows[0]) {
            rows[0].querySelectorAll('input').forEach(i => i.value = '');
            const fileName = rows[0].querySelector('.file-name');
            if (fileName) fileName.textContent = 'Upload Image';
            const thumb = rows[0].querySelector('.image-preview-thumbnail');
            if (thumb) thumb.classList.add('hidden');
        }
        updateSummary();
        updateBadge();
    };

    window.clearForm = function () {
        showConfirmModal('Clear all current entry data?', () => {
            resetForm();
        }, 'Clear Form');
    };

    window.previewEntryImage = function(input) {
        const row = input.closest('.entry-row');
        const fileNameSpan = row.querySelector('.file-name');
        const previewContainer = row.querySelector('.image-preview-thumbnail');
        const previewImg = previewContainer.querySelector('img');
        
        if (input.files && input.files[0]) {
            fileNameSpan.textContent = input.files[0].name;
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewContainer.classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            fileNameSpan.textContent = 'Upload Image';
            previewContainer.classList.add('hidden');
        }
    };

    window.startBarcodeScanForRow = function(btn) {
        const row = btn.closest('.entry-row');
        const input = row.querySelector('[name="item_no[]"]');
        
        // We can reuse the global scanner and just target this specific input
        // Note: startBarcodeScan in layout.php takes inputName, but it logic looks for LAST input
        // Let's modify it to be more flexible or use a temp identifier
        const tempId = 'temp-scan-' + Date.now();
        input.setAttribute('data-scan-target', tempId);
        
        // Trigger global scanner
        if (typeof startBarcodeScan === 'function') {
            // Overriding the applyScannedCode for this instance might be needed 
            // but let's see if we can just target the attribute
            const originalApply = window.applyScannedCode;
            window.applyScannedCode = function(code, name) {
                const target = document.querySelector(`[data-scan-target="${tempId}"]`);
                if (target) {
                    target.value = code;
                    target.removeAttribute('data-scan-target');
                    target.dispatchEvent(new Event('input'));
                }
                window.applyScannedCode = originalApply; // Restore
                closeScanner();
            };
            startBarcodeScan('item_no');
        }
    };

    window.submitPullout = async function() {
        const formData = new FormData();
        const rows = document.querySelectorAll('.entry-row');
        let validEntries = 0;

        const btn = document.getElementById('submit-btn');
        const origText = btn.innerText;
        
        // Show Global Loader for Compression
        if (typeof showGlobalLoader === 'function') {
            showGlobalLoader("COMPRESSING IMAGES...");
        } else {
            btn.disabled = true;
            btn.innerText = "COMPRESSING...";
        }

        // Get transaction date
        const dateType = document.querySelector('input[name="page_date_type"]:checked').value;
        const customDate = document.getElementById('page_custom_date').value;
        const finalDate = (dateType === 'backdate') ? customDate : '<?= date('Y-m-d') ?>';
        formData.append('transaction_date', finalDate);

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const item = row.querySelector('[name="item_no[]"]').value.trim();
            const qty  = row.querySelector('[name="quantity[]"]').value;
            const file = row.querySelector('[name="image[]"]').files[0];

            if (item && qty > 0) {
                formData.append(`entries[${validEntries}][item_no]`, item);
                formData.append(`entries[${validEntries}][quantity]`, qty);
                
                if (file) {
                    try {
                        const compressedBlob = await compressImage(file, 1200, 0.7);
                        formData.append(`entries[${validEntries}][image]`, compressedBlob, file.name);
                    } catch (err) {
                        console.error('Compression error:', err);
                        formData.append(`entries[${validEntries}][image]`, file);
                    }
                }
                validEntries++;
            }
        }

        if (validEntries === 0) {
            if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
            showStatusModal(false, 'Please add at least one valid entry.', 'Empty List');
            btn.disabled = false;
            btn.innerText = origText;
            return;
        }
        
        // Update Loader for Submission
        if (typeof showGlobalLoader === 'function') {
            showGlobalLoader("SUBMITTING PULLOUT...");
        } else {
            btn.innerText = "SUBMITTING...";
        }

        fetch('api/save_pullout.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
            showStatusModal(res.success, res.message, res.success ? 'Success' : 'Failed');
            if (res.success) {
                resetForm();
            }
        })
        .catch(err => {
            if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
            showStatusModal(false, 'A network error occurred.', 'Error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = origText;
        });
    };

    // Admin Functions
    const refreshTable = function(page = 1) {
        const container = document.getElementById('pullout-history-container');
        if (!container) return;
        
        const search    = document.querySelector('[name="search"]')?.value || '';
        const limit     = document.querySelector('[name="limit"]')?.value || 10;
        const start     = document.querySelector('[name="start_date"]')?.value || '';
        const end       = document.querySelector('[name="end_date"]')?.value || '';
        const store     = document.querySelector('[name="store_filter"]')?.value || '';
        
        if (container.querySelector('table')) container.querySelector('table').style.opacity = '0.5';

        const url = `index.php?action=pullout&ajax=1&p=${page}&search=${encodeURIComponent(search)}&limit=${limit}&start_date=${start}&end_date=${end}&store_filter=${store}`;
        fetch(url).then(res => res.text()).then(html => {
            container.innerHTML = html;
            if (typeof initTableEvents === 'function') initTableEvents();
        });
    };
    window.refreshPulloutTable = refreshTable;

    window.editPullout = function(id) {
        const row = document.querySelector(`.pullout-checkbox[value="${id}"]`)?.closest('tr');
        if (!row) return;

        const offset = <?= $is_admin ? 1 : 0 ?>;
        const itemNo = row.cells[1 + offset].innerText.trim();
        const qty    = row.cells[2 + offset].innerText.trim();

        document.getElementById('edit-id').value = id;
        document.getElementById('edit-id-label').innerText = `Record ID: ${id}`;
        document.getElementById('edit-item-no').value = itemNo;
        document.getElementById('edit-qty').value = qty;

        const dateCell = row.cells[5 + offset];
        const rawDate = dateCell.getAttribute('data-date') || '';
        document.getElementById('edit-date').value = rawDate;

        const modal = document.getElementById('edit-pullout-modal');
        // Move modal to body to escape transform container and fix scroll visibility
        document.body.appendChild(modal);
        modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.add('scale-100', 'flex');
        modal.classList.remove('hidden');
    };

    window.closeEditPulloutModal = function() {
        const modal = document.getElementById('edit-pullout-modal');
        modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        modal.classList.remove('scale-100');
    };

    document.getElementById('edit-pullout-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            id: this.id.value,
            item_no: this.item_no.value,
            quantity: this.quantity.value,
            created_at: this.created_at.value
        };

        const btn = this.querySelector('button[type="submit"]');
        btn.innerText = "SAVING...";
        btn.disabled = true;

        fetch('api/update_pullout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            closeEditPulloutModal();
            showStatusModal(res.success, res.message, res.success ? 'Success' : 'Failed');
            if (res.success) refreshTable();
        })
        .finally(() => {
            btn.innerText = "Update Record";
            btn.disabled = false;
        });
    });

    window.deletePullout = function(id) {
        showConfirmModal(`Delete Pullout Record #${id}?`, () => {
            fetch(`api/delete_pullout.php?id=${id}`)
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Deleted' : 'Failed');
                if (res.success) refreshTable();
            });
        }, 'Delete Record');
    };

    window.bulkDeletePullouts = function() {
        const selectedIds = Array.from(document.querySelectorAll('.pullout-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) return;

        showConfirmModal(`Delete ${selectedIds.length} selected records?`, () => {
            fetch('api/bulk_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ table: 'pullouts', ids: selectedIds })
            })
            .then(r => r.json())
            .then(res => {
                showStatusModal(res.success, res.message, res.success ? 'Success' : 'Failed');
                if (res.success) refreshTable();
            });
        }, 'Bulk Delete');
    };

    // Image Compression Helper
    function compressImage(file, maxDim, quality) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = event => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > maxDim) {
                            height *= maxDim / width;
                            width = maxDim;
                        }
                    } else {
                        if (height > maxDim) {
                            width *= maxDim / height;
                            height = maxDim;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(blob => {
                        resolve(blob);
                    }, 'image/jpeg', quality);
                };
                img.onerror = reject;
            };
            reader.onerror = reject;
        });
    }

    // Initial setup
    document.querySelectorAll('.entry-row input').forEach(i => i.addEventListener('input', updateSummary));
    updateSummary();
    updateBadge();

})();
</script>
