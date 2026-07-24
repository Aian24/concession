<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    echo "<div class='glass-panel p-8 text-center text-red-500'>Access Denied. You do not have permission to view this page.</div>";
    return;
}

require_once 'includes/db.php';
$settings = get_system_settings();
?>

<div class="glass-panel border border-white/10 p-6 md:p-8 rounded-2xl max-w-4xl mx-auto shadow-2xl relative overflow-hidden">
    <!-- Animated background glowing orb -->
    <div class="absolute top-[-10%] right-[-10%] w-64 h-64 bg-purple-600 rounded-full mix-blend-multiply filter blur-[80px] opacity-20 pointer-events-none"></div>

    <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-pink-500/20 border border-purple-500/30 flex items-center justify-center shadow-lg shadow-purple-500/10">
            <i class="fas fa-cogs text-2xl text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-400"></i>
        </div>
        <div>
            <h2 class="text-2xl font-black text-white uppercase tracking-tight">System Settings</h2>
            <p class="text-gray-400 text-xs mt-1">Configure global application preferences</p>
        </div>
    </div>

    <form id="system-settings-form" enctype="multipart/form-data" class="space-y-8 relative z-10">
        <!-- Text Settings -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Company Name</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500 group-focus-within:text-purple-400 transition-colors">
                        <i class="fas fa-building text-sm"></i>
                    </div>
                    <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name']) ?>" required
                           class="w-full bg-slate-900/80 border border-white/10 rounded-xl pl-11 pr-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50 transition-all placeholder-gray-600 font-medium shadow-inner">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Time Format</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500 group-focus-within:text-purple-400 transition-colors">
                        <i class="fas fa-clock text-sm"></i>
                    </div>
                    <select name="time_format" class="w-full bg-slate-900/80 border border-white/10 rounded-xl pl-11 pr-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50 transition-all font-medium appearance-none shadow-inner cursor-pointer">
                        <option value="12h" <?= $settings['time_format'] === '12h' ? 'selected' : '' ?>>12-Hour (e.g. 02:30 PM)</option>
                        <option value="24h" <?= $settings['time_format'] === '24h' ? 'selected' : '' ?>>24-Hour (e.g. 14:30)</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-white/10 pt-8">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-6 flex items-center gap-2">
                <i class="fas fa-image text-purple-400"></i> Appearance & Branding
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Visuals Upload -->
                <div class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Main Logo Upload</label>
                        <div class="flex items-center gap-4">
                            <div class="relative w-16 h-16 rounded-xl border border-white/10 bg-slate-900/50 overflow-hidden flex items-center justify-center shrink-0" style="border-radius: <?= $settings['logo_radius'] ?>%;">
                                <img id="logo-preview-img" src="<?= htmlspecialchars($settings['logo_path']) ?>?v=<?= time() ?>" class="max-w-full max-h-full object-contain" alt="Logo Preview">
                            </div>
                            <div class="flex-1">
                                <input type="file" name="logo_path" id="logo-input" accept="image/*" class="w-full text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-wider file:bg-purple-500/20 file:text-purple-300 hover:file:bg-purple-500/30 transition-all cursor-pointer">
                                <p class="text-[10px] text-gray-500 mt-2">Recommended: Transparent PNG, max 2MB.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Favicon Upload</label>
                        <div class="flex items-center gap-4">
                            <div class="relative w-10 h-10 rounded-lg border border-white/10 bg-slate-900/50 overflow-hidden flex items-center justify-center shrink-0">
                                <img id="favicon-preview-img" src="<?= htmlspecialchars($settings['favicon_path']) ?>?v=<?= time() ?>" class="max-w-full max-h-full object-contain" alt="Favicon Preview">
                            </div>
                            <div class="flex-1">
                                <input type="file" name="favicon_path" id="favicon-input" accept="image/*" class="w-full text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-wider file:bg-pink-500/20 file:text-pink-300 hover:file:bg-pink-500/30 transition-all cursor-pointer">
                                <p class="text-[10px] text-gray-500 mt-2">Square aspect ratio (e.g. 192x192). WEBP or PNG preferred.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customization Sliders -->
                <div class="space-y-6 bg-slate-900/30 border border-white/5 rounded-2xl p-5">
                    <div class="space-y-4">
                        <div class="flex justify-between items-end">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Logo Size (Height)</label>
                            <span id="size-val" class="text-xs font-mono text-purple-400 font-bold"><?= $settings['logo_size'] ?>px</span>
                        </div>
                        <input type="range" name="logo_size" id="logo-size" min="30" max="200" value="<?= $settings['logo_size'] ?>" 
                               class="w-full h-1 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-purple-500">
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between items-end">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Logo Border Radius</label>
                            <span id="radius-val" class="text-xs font-mono text-pink-400 font-bold"><?= $settings['logo_radius'] ?>%</span>
                        </div>
                        <input type="range" name="logo_radius" id="logo-radius" min="0" max="50" value="<?= $settings['logo_radius'] ?>" 
                               class="w-full h-1 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-pink-500">
                    </div>
                    
                    <div class="mt-4 p-4 border border-white/10 rounded-xl bg-slate-900 flex items-center justify-center h-40">
                        <div class="text-center">
                            <p class="text-[10px] text-gray-500 uppercase tracking-widest mb-3">Live Preview</p>
                            <img id="live-preview" src="<?= htmlspecialchars($settings['logo_path']) ?>?v=<?= time() ?>" 
                                 class="object-contain transition-all duration-200 shadow-xl" 
                                 style="height: <?= $settings['logo_size'] ?>px; border-radius: <?= $settings['logo_radius'] ?>%; background-color: rgba(255,255,255,0.05);">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-6 border-t border-white/10 flex justify-end">
            <button type="submit" class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-purple-600 to-pink-600 text-white font-black text-xs uppercase tracking-[0.2em] shadow-lg shadow-purple-500/20 hover:brightness-110 active:scale-[0.98] transition-all flex items-center gap-2">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sizeSlider = document.getElementById('logo-size');
    const radiusSlider = document.getElementById('logo-radius');
    const sizeVal = document.getElementById('size-val');
    const radiusVal = document.getElementById('radius-val');
    const livePreview = document.getElementById('live-preview');
    const logoPreviewImg = document.getElementById('logo-preview-img');
    const logoInput = document.getElementById('logo-input');
    
    const faviconPreviewImg = document.getElementById('favicon-preview-img');
    const faviconInput = document.getElementById('favicon-input');

    // Live update sliders
    sizeSlider.addEventListener('input', (e) => {
        sizeVal.textContent = e.target.value + 'px';
        livePreview.style.height = e.target.value + 'px';
    });

    radiusSlider.addEventListener('input', (e) => {
        radiusVal.textContent = e.target.value + '%';
        livePreview.style.borderRadius = e.target.value + '%';
        // Also update the mini preview box
        logoPreviewImg.parentElement.style.borderRadius = e.target.value + '%';
    });

    // Image previews
    logoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreviewImg.src = e.target.result;
                livePreview.src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    faviconInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                faviconPreviewImg.src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Form submission
    const form = document.getElementById('system-settings-form');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (typeof showGlobalLoader === 'function') showGlobalLoader("Saving Settings...");
        
        try {
            const formData = new FormData(form);
            const response = await fetch('api/save_system_settings.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
            
            if (result.success) {
                if (typeof showStatusModal === 'function') {
                    showStatusModal('Success', result.message, 'success');
                    // Refresh the page after a brief moment to apply settings globally
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alert(result.message);
                    window.location.reload();
                }
            } else {
                if (typeof showStatusModal === 'function') {
                    showStatusModal('Error', result.message, 'error');
                } else {
                    alert(result.message);
                }
            }
        } catch (error) {
            if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
            console.error('Error:', error);
            if (typeof showStatusModal === 'function') {
                showStatusModal('Error', 'A network error occurred while saving settings.', 'error');
            } else {
                alert('A network error occurred.');
            }
        }
    });
});
</script>
