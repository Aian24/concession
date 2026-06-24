<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Dashboard - Concession System</title>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="images/icon-192.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&family=Space+Grotesk:wght@300;400;500;600;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/webp" href="assets/images/concessiontab.webp">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SheetJS for Excel export -->
    <?php if (in_array($action ?? '', ['sale', 'return', 'receiving', 'pullout', 'ros_supplies', 'non_submission', 'prism_data', 'boutique_data', 'history', 'recent_activity'])): ?>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js" defer></script>
    <?php endif; ?>
    
    <!-- Barcode Scanner -->
    <?php if (strpos($action ?? '', 'create_') !== false || in_array($action ?? '', ['sale', 'return', 'receiving', 'pullout', 'ros_supplies'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2/dist/quagga.min.js" defer></script>
    <?php endif; ?>
    
    <!-- Chart.js -->
    <?php if (in_array($action ?? '', ['dashboard', 'monitoring'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <?php endif; ?>
    
    <!-- Flatpickr Datepicker -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css"></noscript>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
    <script>
    (function() {
        // Load theme from localStorage early to prevent white flashes
        const saved = localStorage.getItem('concession_theme');
        if (saved) {
            try {
                const theme = JSON.parse(saved);
                Object.entries(theme).forEach(([prop, val]) => {
                    document.documentElement.style.setProperty(prop, val);
                });
            } catch(e) {}
        }
        
        // Inject theme CSS overrides early to prevent flashing of default Tailwind colors
        if (!document.getElementById('theme-css-overrides')) {
            const style = document.createElement('style');
            style.id = 'theme-css-overrides';
            style.innerHTML = `
                /* Backgrounds */
                body, .bg-slate-900, div[class*="bg-slate-900"] { background-color: var(--bg-primary) !important; }
                .sidebar-glass, #sidebar, aside, [class*="bg-slate-950"] { background-color: var(--bg-sidebar) !important; }
                .glass-panel, [class*="bg-slate-800"], div[class*="bg-slate-800"] { background-color: var(--bg-secondary) !important; }
                
                /* Text Colors */
                body, h1, h2, h3, h4, h5, h6, p, span, a, div, li, td, th, label, .text-white, .text-slate-100 { color: var(--text-primary) !important; }
                .text-gray-400, .text-slate-400, .text-gray-500, .text-slate-500, label { color: var(--text-secondary) !important; }
                
                /* Nav Links */
                #sidebar nav a span, #sidebar nav a { color: var(--text-secondary) !important; }
                #sidebar nav a:hover span, #sidebar nav a:hover { color: var(--text-primary) !important; }
                
                /* Tables */
                table thead tr, table thead th, .glass-table th { background-color: var(--bg-secondary) !important; color: var(--text-muted) !important; border-color: var(--border-color) !important; }
                table tbody tr { border-color: var(--border-color) !important; }
                
                /* Inputs */
                input:not([type="color"]):not([type="range"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]), select, textarea, #store-filter-trigger {
                    background-color: var(--bg-input) !important;
                    color: var(--text-primary) !important;
                    border-color: transparent !important;
                }
                input:focus, select:focus, textarea:focus, #store-filter-trigger:focus { border-color: var(--accent-primary) !important; box-shadow: 0 0 0 1px var(--accent-primary) !important; }
                
                /* Custom Menus */
                #store-filter-menu, .flatpickr-calendar {
                    background: var(--bg-secondary) !important;
                    border: 1px solid var(--border-color) !important;
                    backdrop-filter: blur(20px) !important;
                    -webkit-backdrop-filter: blur(20px) !important;
                }
                #store-filter-menu .sticky { background: transparent !important; }
                .store-option:hover { background: var(--bg-card) !important; }
    
                /* Flatpickr Modern Design overrides */
                .flatpickr-calendar { font-family: var(--font-family) !important; box-shadow: 0 20px 50px rgba(0,0,0,0.5) !important; border-radius: 1rem !important; }
                .flatpickr-calendar::before, .flatpickr-calendar::after { display: none !important; }
                .flatpickr-month, .flatpickr-weekday { color: var(--text-secondary) !important; fill: var(--text-secondary) !important; }
                .flatpickr-day { color: var(--text-primary) !important; border-radius: 0.5rem !important; }
                .flatpickr-day:hover, .flatpickr-day:focus { background: var(--bg-card) !important; border-color: transparent !important; }
                .flatpickr-day.selected { background: var(--accent-primary) !important; border-color: var(--accent-primary) !important; color: white !important; font-weight: bold; }
                .flatpickr-day.flatpickr-disabled { color: var(--text-muted) !important; }
                .flatpickr-current-month .flatpickr-monthDropdown-months, .flatpickr-current-month input.cur-year { background: transparent !important; color: var(--text-primary) !important; }
                .flatpickr-current-month .flatpickr-monthDropdown-months option { background: var(--bg-primary) !important; color: var(--text-primary) !important; }
                
                /* Borders */
                [class*="border-white/5"], [class*="border-white/10"], [class*="border-slate-"] { border-color: var(--border-color) !important; }
                
                /* Fonts */
                html, body, .font-outfit, body *:not(.fas):not(.far):not(.fab):not(.fa):not(i[class*="fa-"]):not(svg):not(path) {
                    font-family: var(--font-family) !important;
                }
                html { font-size: var(--font-size-base) !important; }
                /* Exclude Panel itself from font overrides, but NOT FontAwesome icons */
                #theme-panel, #theme-panel *:not(.fas):not(.far):not(.fab):not(.fa):not(i[class*="fa-"]) { font-family: 'Outfit', sans-serif !important; }
                
                /* Exclude Button gradients from being overwritten by div text-white rule */
                button[class*="bg-gradient"], a[class*="bg-gradient"], 
                .btn-primary, [class*="from-purple"], [class*="from-red"] {
                    color: white !important;
                }
            `;
            document.head.appendChild(style);
        }
    })();
    </script>
</head>
<body class="text-white font-outfit min-h-screen flex overflow-hidden" style="background-color: var(--bg-primary);">
    


    <!-- Logout Confirmation Modal -->
    <div id="logout-modal" class="fixed inset-0 z-[200] flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeLogoutModal()"></div>
        <div class="relative w-full max-w-sm glass-panel border border-white/10 shadow-2xl p-8 mx-4 transform transition-transform duration-300 scale-95" id="logout-modal-content">
            <div class="w-20 h-20 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center mx-auto mb-6 shadow-[0_0_20px_rgba(239,68,68,0.2)]">
                <i class="fas fa-sign-out-alt text-red-500 text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold text-white text-center mb-2">Ready to Leave?</h3>
            <p class="text-gray-400 text-center text-sm mb-8">Are you sure you want to log out of your session? Any unsaved entries will be lost.</p>
            
            <div class="flex flex-col gap-3">
                <a href="?logout=true" class="w-full py-4 rounded-xl bg-gradient-to-r from-red-600 to-red-700 text-white font-bold text-xs uppercase tracking-widest text-center shadow-lg shadow-red-600/20 hover:brightness-110 transition-all">
                    Confirm Logout
                </a>
                <button onclick="closeLogoutModal()" class="w-full py-3.5 rounded-xl bg-white/5 text-gray-300 font-bold text-xs uppercase tracking-widest hover:bg-white/10 transition-all border border-white/5 outline-none">
                    Stay Signed In
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Settings Modal -->
    <div id="profile-modal" class="fixed inset-0 z-[200] flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closeProfileModal()"></div>
        <div class="relative w-full max-w-md glass-panel border border-white/10 shadow-2xl p-0 mx-4 transform transition-transform duration-300 scale-95 overflow-hidden" id="profile-modal-content">
            <div class="bg-gradient-to-tr from-purple-600/20 to-pink-600/20 p-6 border-b border-white/5 relative">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-purple-500 to-pink-500 flex items-center justify-center text-xl font-bold border-2 border-white/10 shadow-xl text-white">
                        <?= strtoupper(substr($_SESSION['user'], 0, 1)) ?>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white leading-none mb-1"><?= htmlspecialchars($_SESSION['user']) ?></h3>
                        <?php if (($_SESSION['store_code'] ?? '') === 'MULTI' && !empty($_SESSION['assigned_stores'])): ?>
                            <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest"><?= $_SESSION['role'] ?> • <?= count($_SESSION['assigned_stores']) ?> STORES ASSIGNED</p>
                        <?php else: ?>
                            <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest"><?= $_SESSION['role'] ?> • STORE: <?= htmlspecialchars($_SESSION['store_code']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <button onclick="closeProfileModal()" class="absolute top-4 right-4 w-7 h-7 rounded-sm hover:bg-white/5 flex items-center justify-center text-gray-500 hover:text-white transition-colors">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="profile-settings-form" class="space-y-4" enctype="multipart/form-data">
                    <!-- Avatar Upload -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative group cursor-pointer" onclick="document.getElementById('avatar-input').click()">
                            <div class="w-20 h-20 rounded-full bg-slate-800 border-2 border-white/10 overflow-hidden flex items-center justify-center shadow-xl">
                                <?php if (!empty($_SESSION['avatar'])): ?>
                                    <img id="avatar-preview" src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div id="avatar-letter" class="text-3xl font-bold text-gray-500"><?= strtoupper(substr($_SESSION['user'], 0, 1)) ?></div>
                                    <img id="avatar-preview" class="w-full h-full object-cover hidden">
                                <?php endif; ?>
                            </div>
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-full">
                                <i class="fas fa-camera text-white"></i>
                            </div>
                        </div>
                        <input type="file" id="avatar-input" name="avatar" class="hidden" accept="image/*" onchange="previewAvatar(this)">
                        <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-2">Click to change avatar</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1 tracking-wider">Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($_SESSION['user']) ?>" required class="w-full bg-slate-900/80 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500/50 transition-all font-medium">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-gray-500 uppercase ml-1 tracking-wider">Current Password <span class="text-[8px] opacity-50">(To save changes)</span></label>
                        <input type="password" name="current_password" required class="w-full bg-slate-900/80 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500/50 transition-all font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-2 border-t border-white/5">
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-500 uppercase ml-1 tracking-wider">New Password</label>
                            <input type="password" name="new_password" class="w-full bg-slate-900/80 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500/50 transition-all font-medium" placeholder="Leave blank to keep">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-500 uppercase ml-1 tracking-wider">Confirm New</label>
                            <input type="password" name="confirm_password" class="w-full bg-slate-900/80 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500/50 transition-all font-medium">
                        </div>
                    </div>
                    
                    <div id="profile-msg" class="hidden text-[10px] font-bold text-center py-2 rounded-lg mt-2"></div>
                    
                    <button type="submit" class="w-full py-3.5 mt-2 rounded-xl bg-gradient-to-r from-purple-600 to-pink-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-purple-500/20 hover:brightness-110 active:scale-[0.98] transition-all">
                        Save Profile Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Global Status Modal -->
    <div id="status-modal" class="fixed inset-0 z-[500] flex items-center justify-center hidden opacity-0 transition-all duration-300 scale-95">
        <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" onclick="closeStatusModal()"></div>
        <div class="relative glass-panel border border-white/10 shadow-2xl p-8 max-w-sm w-full mx-4 text-center">
            <div id="modal-icon-container" class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center border-4">
                <i id="modal-icon" class="fas fa-3xl"></i>
                <div id="success-svg" class="hidden">
                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                        <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                    </svg>
                </div>
            </div>
            <h3 id="modal-title" class="text-xl font-black text-white mb-2 uppercase tracking-wide"></h3>
            <p id="modal-message" class="text-gray-400 text-sm mb-6"></p>
            <button id="modal-close-btn" onclick="closeStatusModal()" class="w-full py-3 rounded-xl bg-gradient-to-r from-slate-700 to-slate-800 text-white font-black text-[10px] uppercase tracking-widest hover:brightness-125 transition-all outline-none border border-white/5">Close</button>
        </div>
    </div>

    <!-- Global Confirmation Modal -->
    <div id="confirm-modal" class="fixed inset-0 z-[501] flex items-center justify-center hidden opacity-0 transition-all duration-300 scale-95">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md"></div>
        <div class="relative glass-panel border border-white/10 shadow-2xl p-8 max-w-sm w-full mx-4 text-center overflow-hidden">
            <div class="w-16 h-16 rounded-full mx-auto mb-6 flex items-center justify-center border-2 border-amber-500/20 bg-amber-500/10 shadow-lg shadow-amber-500/20">
                <i class="fas fa-question text-amber-500 text-2xl"></i>
            </div>
            <h3 id="confirm-title" class="text-lg font-black text-white mb-2 uppercase tracking-wide">Are you sure?</h3>
            <p id="confirm-message" class="text-gray-400 text-xs mb-8 font-medium leading-relaxed"></p>
            <div class="flex gap-3">
                <button id="confirm-cancel" class="flex-1 py-3.5 rounded-xl bg-white/5 text-gray-400 font-black text-[10px] uppercase tracking-widest hover:bg-white/10 transition-all border border-white/5 outline-none">Cancel</button>
                <button id="confirm-proceed" class="flex-1 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white font-black text-[10px] uppercase tracking-widest shadow-lg shadow-amber-500/20 hover:brightness-110 active:scale-[0.98] transition-all outline-none">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Global Barcode Scanner Modal -->
    <div id="scanner-modal" class="fixed inset-0 bg-slate-950/95 z-[600] flex flex-col hidden opacity-0 transition-all duration-300">
        <div class="p-4 border-b border-white/5 flex items-center justify-between bg-slate-900/80 backdrop-blur-md">
            <div class="flex items-center gap-2">
                <i class="fas fa-barcode text-purple-400"></i>
                <h3 class="text-white text-xs font-black uppercase tracking-wider">Scan Product Code</h3>
            </div>
            <button onclick="closeScanner()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="flex-grow flex flex-col items-center justify-center p-6 relative">
            <div class="w-full max-w-[360px] relative">
                <!-- Instruction text inside a glass pill -->
                <div class="absolute -top-12 left-1/2 -translate-x-1/2 whitespace-nowrap px-4 py-1.5 rounded-full bg-slate-900/60 backdrop-blur-md border border-white/5 flex items-center gap-2 z-20">
                    <div class="w-1.5 h-1.5 rounded-full bg-purple-500 animate-pulse"></div>
                    <span class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">Align Laser with Barcode</span>
                </div>

                <div class="w-full aspect-square bg-slate-950 border border-white/10 rounded-[2.5rem] overflow-hidden relative shadow-2xl shadow-purple-500/10">
                    <div id="scanner-viewport" class="w-full h-full"></div>
                    
                    <!-- New High-Tech Overlay -->
                    <div class="absolute inset-0 pointer-events-none" style="z-index:10;">
                        <!-- Darkened background outside target -->
                        <div class="absolute inset-0 bg-slate-950/40"></div>
                        
                        <!-- Target cutout (visual only) -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[85%] h-[20%] border border-white/10 rounded-2xl bg-transparent shadow-[0_0_0_1000px_rgba(2,6,23,0.6)]"></div>
                        
                        <!-- Precision Corners -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[85%] h-[20%]">
                            <!-- Top Left -->
                            <div class="absolute -top-1 -left-1 w-6 h-6 border-t-4 border-l-4 border-purple-500 rounded-tl-xl shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                            <!-- Top Right -->
                            <div class="absolute -top-1 -right-1 w-6 h-6 border-t-4 border-r-4 border-purple-500 rounded-tr-xl shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                            <!-- Bottom Left -->
                            <div class="absolute -bottom-1 -left-1 w-6 h-6 border-b-4 border-l-4 border-purple-500 rounded-bl-xl shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                            <!-- Bottom Right -->
                            <div class="absolute -bottom-1 -right-1 w-6 h-6 border-b-4 border-r-4 border-purple-500 rounded-br-xl shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                            
                            <!-- Laser Line -->
                            <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-[2px] bg-gradient-to-r from-transparent via-purple-400 to-transparent shadow-[0_0_20px_rgba(168,85,247,0.8)] z-10 animate-pulse"></div>
                            <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-10 bg-gradient-to-b from-transparent via-purple-500/10 to-transparent pointer-events-none opacity-50"></div>
                        </div>

                        <!-- Modern Progress Tracker -->
                        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 w-[60%] h-1 bg-white/5 rounded-full overflow-hidden">
                            <div id="scan-progress-bar" class="h-full bg-gradient-to-r from-purple-500 to-pink-500 w-0 transition-all duration-100 ease-linear shadow-[0_0_10px_rgba(168,85,247,0.5)]"></div>
                        </div>
                    </div>

                    <!-- Redesigned Success Panel (Centered Overlay) -->
                    <div id="scanner-confirm-panel" class="absolute inset-0 bg-slate-950/80 backdrop-blur-md flex flex-col items-center justify-center p-6 hidden transition-all duration-300 z-50">
                        <div class="bg-slate-900 border border-white/10 p-8 rounded-[2rem] shadow-2xl flex flex-col items-center scale-90 animate-fade-in-up">
                            <div class="w-16 h-16 rounded-full bg-green-500/20 border border-green-500/30 flex items-center justify-center mb-4">
                                <i class="fas fa-check text-green-500 text-2xl"></i>
                            </div>
                            <h4 class="text-white text-[10px] font-black uppercase tracking-[0.3em] mb-2 opacity-60">Scanned Code</h4>
                            <p id="scanned-code-display" class="text-3xl font-black text-white tracking-tight font-mono mb-6"></p>
                            <div class="w-12 h-1 bg-green-500 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                </div>

                <!-- Status indicator -->
                <div id="scanner-status" class="mt-8 text-center px-4">
                    <div id="scanner-status-text" class="text-gray-400 text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-3">
                        <span class="w-8 h-[1px] bg-white/10"></span>
                        <span class="flex items-center gap-2">
                            <i class="fas fa-satellite-dish animate-pulse text-purple-500"></i> System Ready
                        </span>
                        <span class="w-8 h-[1px] bg-white/10"></span>
                    </div>
                    <div id="scanner-detect-text" class="text-purple-400 text-[11px] font-mono mt-2 h-5 tracking-widest font-black opacity-80"></div>
                    <div id="scanner-error-text" class="text-red-500 text-[10px] font-bold mt-2 hidden bg-red-500/10 py-1 px-3 rounded-full border border-red-500/20 inline-block"></div>
                </div>
            </div>

        </div>
    </div>

    <!-- Animated background glowing orbs -->
    <div class="fixed top-[-10%] left-[-10%] w-[40rem] h-[40rem] rounded-full mix-blend-multiply filter blur-[120px] opacity-20 animate-blob pointer-events-none" style="background-color: var(--orb-1)"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[40rem] h-[40rem] rounded-full mix-blend-multiply filter blur-[120px] opacity-20 animate-blob animation-delay-2000 pointer-events-none" style="background-color: var(--orb-2)"></div>

    <!-- Mobile Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar-glass w-64 h-[100dvh] lg:h-screen fixed lg:relative z-50 flex flex-col transition-transform duration-300 transform -translate-x-full lg:translate-x-0 overflow-hidden">
        <div class="p-6 flex flex-col items-center justify-center relative">
            <img src="assets/images/concession.webp" alt="Concession Logo" class="h-16 lg:h-24 w-auto object-contain transition-all">
            <button class="lg:hidden text-gray-400 hover:text-white transition-colors absolute right-6 top-1/2 -translate-y-1/2" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="px-6 pb-4">
            <?php
            // Fallback for current sessions that don't have store_name yet
            if (!isset($_SESSION['store_name']) && isset($_SESSION['store_code'])) {
                $sc = $_SESSION['store_code'];
                if ($sc === 'MULTI') {
                    $_SESSION['store_name'] = 'Multiple Stores';
                } elseif ($sc === 'ALL') {
                    $_SESSION['store_name'] = 'All Stores';
                } else {
                    $db_store = db_connect();
                    $s_stmt = $db_store->prepare("SELECT sname FROM storecode WHERE scode = ? LIMIT 1");
                    $s_stmt->bind_param("s", $sc);
                    $s_stmt->execute();
                    $s_data = $s_stmt->get_result()->fetch_assoc();
                    $_SESSION['store_name'] = $s_data['sname'] ?? 'N/A';
                    $s_stmt->close();
                }
            }
            ?>
            <div class="bg-slate-800/80 backdrop-blur-md rounded-xl px-4 py-3 border border-white/5 shadow-lg flex items-center gap-3 overflow-hidden">
                <div class="w-1.5 h-1.5 rounded-full bg-green-400 shadow-[0_0_8px_rgba(74,222,128,0.6)] animate-pulse shrink-0"></div>
                <div class="flex items-center gap-1.5 min-w-0 overflow-hidden">
                    <span class="text-[11px] font-black text-white truncate"><?= htmlspecialchars($_SESSION['store_name'] ?? 'N/A') ?></span>
                    <?php if (($_SESSION['store_code'] ?? '') === 'MULTI' && !empty($_SESSION['assigned_stores'])): ?>
                        <span class="text-[10px] font-bold text-purple-400 shrink-0 tracking-tighter uppercase">(<?= count($_SESSION['assigned_stores']) ?> Stores)</span>
                    <?php else: ?>
                        <span class="text-[10px] font-bold text-gray-500 shrink-0 tracking-tighter uppercase">(<?= htmlspecialchars($_SESSION['store_code'] ?? '') ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <nav class="flex-1 overflow-y-auto px-4 space-y-[2px]">
            <?php
            $all_nav = [
                'dashboard'       => ['icon' => 'fa-home', 'title' => 'Dashboard'],
                'history'         => ['icon' => 'fa-history', 'title' => "Today's Transact"],
                'monitoring'      => ['icon' => 'fa-chart-line', 'title' => 'Monitoring'],
                
                'create_sale'     => ['icon' => 'fa-shopping-cart', 'title' => 'Create Sale'],
                'sale'            => ['icon' => 'fa-shopping-cart', 'title' => 'Sales Data'],
                
                'create_return'   => ['icon' => 'fa-undo', 'title' => 'Create Return'],
                'return'          => ['icon' => 'fa-undo', 'title' => 'Returns Data'],
                
                'create_receiving'=> ['icon' => 'fa-box-open', 'title' => 'Create Receiving'],
                'receiving'       => ['icon' => 'fa-box-open', 'title' => 'Receiving Data'],
                
                'create_pullout'  => ['icon' => 'fa-truck-loading', 'title' => 'Create Pullout'],
                'pullout'         => ['icon' => 'fa-truck-loading', 'title' => 'Pullout Data'],
                
                'create_ros_supplies' => ['icon' => 'fa-boxes-stacked', 'title' => 'Create ROS Supply'],
                'ros_supplies'    => ['icon' => 'fa-boxes-stacked', 'title' => 'ROS Data'],

                'non_submission'  => ['icon' => 'fa-file-excel', 'title' => 'Non-Submission'],
                'admin'           => ['icon' => 'fa-users-cog', 'title' => 'Manage Users'],
                'roles'           => ['icon' => 'fa-user-shield', 'title' => 'Manage Roles'],
                'stores'          => ['icon' => 'fa-store', 'title' => 'Manage Stores'],
                'prism_data'      => ['icon' => 'fa-gem', 'title' => 'Manage Prism Data'],
                'boutique_data'   => ['icon' => 'fa-store', 'title' => 'Manage Boutique Data'],
                'recent_activity' => ['icon' => 'fa-clock-rotate-left', 'title' => 'Recent Activity'],
                'server_health'   => ['icon' => 'fa-server', 'title' => 'Server Health'],
            ];

            $nav_items = [];
            foreach ($all_nav as $key => $item) {
                // history is a special case: we only want to show it in the sidebar if they are a standard user
                // or if it's explicitly in their permissions and we want it. Actually, everyone has history.
                // But admin uses dashboard usually. Let's just follow the permissions array.
                if (in_array($key, $user_permissions)) {
                    $nav_items[$key] = $item;
                }
            }

            foreach ($nav_items as $key => $item):
                $isActive = $action === $key;
                $activeClass = $isActive 
                    ? 'bg-gradient-to-r from-purple-600/30 to-pink-600/30 text-white shadow-md' 
                    : 'text-gray-400 hover:bg-white/5 hover:text-white';
            ?>
            <a href="<?= $key ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all relative overflow-hidden group <?= $activeClass ?>">
                <?php if ($isActive): ?>
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-purple-400 to-pink-500 rounded-full"></div>
                <?php endif; ?>
                <i class="fas <?= $item['icon'] ?> w-6 text-center <?= $isActive ? 'text-purple-400' : 'group-hover:text-purple-300' ?> transition-colors"></i>
                <span class="font-medium"><?= $item['title'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 mt-auto border-t border-white/5 bg-slate-900/50 pb-12 lg:pb-4">
            <button onclick="showLogoutModal()" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-all text-xs font-bold uppercase tracking-widest border border-red-500/20 hover:border-red-500/40 shadow-lg shadow-red-500/5">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen relative z-10 w-full lg:w-[calc(100%-16rem)]">
        <!-- Header -->
        <header class="h-20 flex items-center justify-between px-4 sm:px-8 border-b border-white/5 bg-slate-900/50 backdrop-blur-xl sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <button class="lg:hidden text-gray-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition-colors" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <?php
                $module_subs = [
                    'dashboard'  => 'Quick overview of store performance',
                    'monitoring' => 'Real-time sales and inventory tracking',
                    'sale'       => 'Record and manage concession sales transactions',
                    'return'     => 'Manage product returns and optional item exchanges',
                    'receiving'  => 'Update inventory stock levels',
                    'pullout'    => 'Manage store pullouts with proof upload',
                    'ros_supplies' => 'Manage store supplies and replenishment',
                    'history'    => 'Track your transactions recorded today',
                    'stores'     => 'Manage store codes and store names',
                    'non_submission' => 'Track stores with missing sales submissions',
                    'server_health'  => 'Monitor server performance and processes',
                ];
                $current_title = $nav_items[$action]['title'] ?? str_replace('_', ' ', $action);
                $current_sub   = $module_subs[$action] ?? '';
                ?>
                <div class="flex flex-col pl-5">
                    <h1 class="text-xl font-bold capitalize bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400 leading-none"><?= $current_title ?></h1>
                    <?php if ($current_sub): ?>
                        <p class="text-[10px] text-gray-500 font-medium uppercase tracking-wider mt-1"><?= $current_sub ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3 cursor-pointer group px-3 py-1.5 rounded-xl hover:bg-white/5 transition-all" onclick="showProfileSettings()">
                        <div class="text-right hidden sm:block whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 mb-0.5">
                                <span class="text-[9px] text-gray-500 font-bold uppercase tracking-widest group-hover:text-purple-400 transition-colors">Profile Settings</span>
                                <i class="fas fa-cog text-[9px] text-gray-600 group-hover:rotate-90 transition-all duration-500"></i>
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <span class="text-xs font-bold text-white"><?= htmlspecialchars($_SESSION['user']) ?></span>
                                <span class="w-1 h-1 rounded-full bg-purple-500/50"></span>
                                <span class="text-[9px] text-gray-500 font-black uppercase tracking-wider"><?= $_SESSION['role'] ?></span>
                            </div>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-purple-500 to-pink-500 flex items-center justify-center text-lg font-bold shadow-lg shadow-purple-500/40 text-white group-hover:ring-2 group-hover:ring-purple-500/50 group-hover:scale-105 transition-all duration-300 relative overflow-hidden">
                            <?php if (!empty($_SESSION['avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($_SESSION['user'], 0, 1)) ?>
                            <?php endif; ?>
                            <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-slate-900 rounded-full flex items-center justify-center border border-white/10 group-hover:border-purple-500/50 transition-colors z-10">
                                <i class="fas fa-pen text-[7px] text-gray-500 group-hover:text-purple-400"></i>
                            </div>
                        </div>
                    </div>
            </div>
        </header>

        <!-- Dynamic Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pt-6 sm:pt-8 pb-24">
            <div class="w-full mx-auto h-full animate-fade-in-up">
                <?php
                // Securely handle page inclusion
                $page_file = "views/pages/" . basename($target_file) . ".php";
                if (file_exists($page_file)) {
                    require $page_file;
                } else {
                    echo "<div class='glass-panel p-12 text-center flex flex-col items-center justify-center min-h-[400px] border border-white/5'>
                            <div class='w-24 h-24 rounded-full bg-purple-500/20 flex items-center justify-center mb-6'>
                                <i class='fas fa-tools text-5xl text-purple-400'></i>
                            </div>
                            <h2 class='text-3xl font-bold text-white mb-3'>Under Construction</h2>
                            <p class='text-gray-400 max-w-md text-lg'>The <span class='text-purple-300 font-semibold'>".str_replace('_', ' ', $action)."</span> module is currently being built. Check back soon for updates.</p>
                          </div>";
                }
                ?>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        function showGlobalLoader(msg = "PROCESSING...") {
            const loader = document.getElementById('global-loader');
            const text = document.getElementById('loader-text');
            if (loader && text) {
                text.innerText = msg;
                loader.classList.remove('hidden');
                setTimeout(() => loader.classList.remove('opacity-0'), 10);
            }
        }

        function hideGlobalLoader() {
            const loader = document.getElementById('global-loader');
            if (loader) {
                loader.classList.add('opacity-0');
                setTimeout(() => loader.classList.add('hidden'), 300);
            }
        }

        function showLogoutModal() {
            const modal = document.getElementById('logout-modal');
            const content = document.getElementById('logout-modal-content');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.replace('scale-95', 'scale-100');
            }, 10);
        }

        function closeLogoutModal() {
            const modal = document.getElementById('logout-modal');
            const content = document.getElementById('logout-modal-content');
            modal.classList.add('opacity-0');
            content.classList.replace('scale-100', 'scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        function showProfileSettings() {
            const modal = document.getElementById('profile-modal');
            const content = document.getElementById('profile-modal-content');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.replace('scale-95', 'scale-100');
            }, 10);
        }

        function closeProfileModal() {
            const modal = document.getElementById('profile-modal');
            const content = document.getElementById('profile-modal-content');
            modal.classList.add('opacity-0');
            content.classList.replace('scale-100', 'scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
            
            // Clear message
            const msg = document.getElementById('profile-msg');
            msg.classList.add('hidden');
            document.getElementById('profile-settings-form').reset();
        }

        // Global Custom Dropdown Logic
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('#store-filter-trigger');
            const option = e.target.closest('.store-option');
            const menu = e.target.closest('#store-filter-menu') ? e.target.closest('#store-filter-menu') : document.getElementById('store-filter-menu');
            const searchInput = e.target.closest('#store-search-filter');

            if (trigger) {
                // Find the menu relative to the trigger if there are multiple, or fallback to global ID
                const container = trigger.closest('#store-filter-container') || trigger.parentElement;
                const targetMenu = container.querySelector('#store-filter-menu') || document.getElementById('store-filter-menu');
                
                if (targetMenu) {
                    targetMenu.classList.toggle('hidden');
                    if (!targetMenu.classList.contains('hidden')) {
                        const si = targetMenu.querySelector('#store-search-filter');
                        if (si) {
                            si.value = '';
                            targetMenu.querySelectorAll('.store-option').forEach(opt => opt.classList.remove('hidden'));
                            setTimeout(() => si.focus(), 50);
                        }
                    }
                }
            } else if (option) {
                const val = option.getAttribute('data-value');
                const label = option.getAttribute('data-label') || 'All Stores';
                const container = option.closest('#store-filter-container') || option.closest('.group') || document;
                
                // Try different common names for the hidden input
                const hiddenInput = container.querySelector('select[name="store_filter"], input[name="store_code"], select[name="store_code"], input[id="store-filter-value"]');
                
                if (hiddenInput) {
                    hiddenInput.value = val;
                    const labelEl = container.querySelector('#selected-store-label');
                    if (labelEl) labelEl.textContent = label;
                    
                    const targetMenu = option.closest('#store-filter-menu');
                    if (targetMenu) targetMenu.classList.add('hidden');
                    
                    // Trigger change or submit
                    if (hiddenInput.tagName === 'SELECT') {
                        hiddenInput.dispatchEvent(new Event('change'));
                    } else if (hiddenInput.type === 'hidden') {
                        // If it's a hidden input in a form, maybe submit the form
                        const form = hiddenInput.closest('form');
                        if (form && form.id === 'dashboard-filter-form') {
                            form.submit();
                        } else {
                            // Trigger change for any other listeners
                            hiddenInput.dispatchEvent(new Event('change'));
                        }
                    }
                }
            } else if (searchInput) {
                // Do nothing
            } else {
                // Close all open menus
                document.querySelectorAll('#store-filter-menu').forEach(m => m.classList.add('hidden'));
            }
        });

        document.addEventListener('input', function(e) {
            if (e.target.id === 'store-search-filter') {
                const query = e.target.value.toLowerCase().trim();
                const menu = e.target.closest('#store-filter-menu');
                if (!menu) return;

                const options = menu.querySelectorAll('.store-option');
                options.forEach(opt => {
                    const val = (opt.getAttribute('data-value') || '').toLowerCase();
                    const label = (opt.getAttribute('data-label') || '').toLowerCase();
                    const text = opt.innerText.toLowerCase();
                    
                    if (val === '' || val.includes(query) || label.includes(query) || text.includes(query)) {
                        opt.classList.remove('hidden');
                    } else {
                        opt.classList.add('hidden');
                    }
                });
            }
        });

        // Global Modals Logic
        function showStatusModal(success, message, customTitle = '') {
            const modal = document.getElementById('status-modal');
            const icon  = document.getElementById('modal-icon');
            const iconC = document.getElementById('modal-icon-container');
            const title = document.getElementById('modal-title');
            const msg   = document.getElementById('modal-message');
            const successSvg = document.getElementById('success-svg');
            const closeBtn = document.getElementById('modal-close-btn');
            
            modal.classList.remove('hidden');
            
            if (success) {
                icon.classList.add('hidden');
                successSvg.classList.remove('hidden');
                // Restart SVG animation by re-injecting HTML
                const svgContent = successSvg.innerHTML;
                successSvg.innerHTML = '';
                successSvg.innerHTML = svgContent;

                iconC.className = 'w-24 h-24 mx-auto mb-6 flex items-center justify-center border-none bg-transparent';
                title.textContent = customTitle || 'Transaction Successful';
                title.className = 'text-xl font-black text-emerald-400 mb-2 uppercase tracking-wide';
                if (closeBtn) closeBtn.classList.add('hidden');
            } else {
                icon.classList.remove('hidden');
                successSvg.classList.add('hidden');
                icon.className = 'fas fa-exclamation-triangle text-red-500 fa-3xl';
                iconC.className = 'w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center border-4 border-red-500/20 bg-red-500/10 shadow-lg shadow-red-500/20';
                title.textContent = customTitle || 'Action Failed';
                title.className = 'text-xl font-black text-red-400 mb-2 uppercase tracking-wide';
                if (closeBtn) closeBtn.classList.remove('hidden');
            }
            msg.textContent = message;

            setTimeout(() => {
                modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95', 'hidden');
                modal.classList.add('scale-100');
            }, 50);

            // Auto-close if successful
            if (success) {
                setTimeout(() => {
                    closeStatusModal();
                }, 2200); // Slightly longer to appreciate the animation
            }
        }

        function closeStatusModal() {
            const modal = document.getElementById('status-modal');
            modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
            modal.classList.remove('scale-100');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        function showConfirmModal(message, onProceed, title = 'Confirm Action') {
            const modal = document.getElementById('confirm-modal');
            const msg = document.getElementById('confirm-message');
            const ttl = document.getElementById('confirm-title');
            const btnProceed = document.getElementById('confirm-proceed');
            const btnCancel = document.getElementById('confirm-cancel');

            msg.innerHTML = message;
            ttl.textContent = title;
            modal.classList.remove('hidden');

            const proceedHandler = () => {
                closeConfirmModal();
                onProceed();
                btnProceed.removeEventListener('click', proceedHandler);
                btnCancel.removeEventListener('click', cancelHandler);
            };

            const cancelHandler = () => {
                closeConfirmModal();
                btnProceed.removeEventListener('click', proceedHandler);
                btnCancel.removeEventListener('click', cancelHandler);
            };

            btnProceed.addEventListener('click', proceedHandler);
            btnCancel.addEventListener('click', cancelHandler);

            setTimeout(() => {
                modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
                modal.classList.add('scale-100');
            }, 10);
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
            modal.classList.remove('scale-100');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        let isScanningLocked = false;
        let lastDetectedCode = null;
        let stabilityCount = 0;
        const STABILITY_THRESHOLD = 5;
        let currentScannerTarget = null;
        
        function applyScannedCode(decodedText, inputName) {
            let targetInput = currentScannerTarget;
            
            if (!targetInput) {
                let inputs = document.querySelectorAll('#entry-rows [name="item_no"], .entry-row [name="item_no"]');
                if (inputs.length === 0) inputs = document.querySelectorAll(`[name="${inputName}"]`);
                if (inputName === 'exchange_item') inputs = document.querySelectorAll('.entry-ex-item');
                else if (inputName === 'return_item') inputs = document.querySelectorAll('.entry-item');

                if (inputs.length > 0) {
                    targetInput = inputs[inputs.length - 1];
                    // If last input is already filled with a different code, add a new row (auto-scan behavior)
                    if (targetInput.value.trim() !== '' && targetInput.value.trim() !== decodedText && typeof window.addRow === 'function') {
                        window.addRow();
                        inputs = document.querySelectorAll('#entry-rows [name="item_no"], .entry-row [name="item_no"]');
                        if (inputs.length === 0) inputs = document.querySelectorAll(`[name="${inputName}"]`);
                        targetInput = inputs[inputs.length - 1];
                    }
                }
            }

            if (targetInput) {
                targetInput.value = decodedText;
                targetInput.dispatchEvent(new Event('input'));
            }
        }

        window.startBarcodeScan = function(inputName = 'item_no', targetEl = null) {
            isScanningLocked = false;
            lastDetectedCode = null;
            stabilityCount = 0;
            currentScannerTarget = targetEl;
            
            const modal = document.getElementById('scanner-modal');
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.remove('opacity-0'), 10);
            
            const pb = document.getElementById('scan-progress-bar');
            if (pb) pb.style.width = '0%';
            
            const statusText = document.getElementById('scanner-status-text');
            const detectText = document.getElementById('scanner-detect-text');
            const errorText = document.getElementById('scanner-error-text');
            
            // Reset status
            if (statusText) statusText.innerHTML = '<i class="fas fa-spinner animate-spin text-purple-400"></i> Initializing camera...';
            if (detectText) detectText.textContent = '';
            if (errorText) { errorText.textContent = ''; errorText.classList.add('hidden'); }

            // Check if Quagga is available
            if (typeof Quagga === 'undefined') {
                const msg = 'ERROR: Quagga2 library failed to load. Check internet connection.';
                console.error(msg);
                if (errorText) { errorText.textContent = msg; errorText.classList.remove('hidden'); }
                if (statusText) statusText.innerHTML = '<i class="fas fa-exclamation-triangle text-red-400"></i> Library not loaded';
                return;
            }

            // Try with environment (rear) camera first — works on mobile.
            // On desktop where there's no "environment" camera, the error handler retries
            // without any facingMode so it picks whatever camera is available.
            function _initQuagga(constraints, isRetry) {
                // Clear the viewport before re-init
                const vp = document.getElementById('scanner-viewport');
                if (vp && isRetry) vp.innerHTML = '';

                try {
                Quagga.init({
                    inputStream: {
                        name: "Live",
                        type: "LiveStream",
                        target: document.getElementById('scanner-viewport'),
                        constraints: constraints,
                        area: { top: "40%", right: "0%", left: "0%", bottom: "40%" }
                    },
                    locator:  { patchSize: "small", halfSample: true },
                    numOfWorkers: 0,
                    frequency: 10,
                    decoder: {
                        readers: [
                            "code_128_reader", "ean_reader", "ean_8_reader",
                            "upc_reader", "upc_e_reader", "code_39_reader", "codabar_reader"
                        ]
                    },
                    locate: false
                }, function(err) {
                    if (err) {
                        console.error("Quagga init error:", err);
                        // If environment facing mode not found, retry with any camera
                        if (!isRetry && (err.name === 'NotFoundError' || err.name === 'OverconstrainedError' || err.name === 'TypeError')) {
                            console.warn("Retrying without facingMode...");
                            if (statusText) statusText.innerHTML = '<i class="fas fa-spinner animate-spin text-yellow-400"></i> Retrying camera...';
                            _initQuagga({ width: { ideal: 1280 }, height: { ideal: 720 } }, true);
                            return;
                        }
                        const errMsg = err.message || err.name || JSON.stringify(err);
                        if (errorText) { errorText.textContent = 'Camera Error: ' + errMsg; errorText.classList.remove('hidden'); }
                        if (statusText) statusText.innerHTML = '<i class="fas fa-exclamation-triangle text-red-400"></i> Camera failed';
                        return;
                    }

                    try { Quagga.start(); } catch(startErr) {
                        console.error("Quagga start error:", startErr);
                        if (errorText) { errorText.textContent = 'Start Error: ' + startErr.message; errorText.classList.remove('hidden'); }
                        return;
                    }

                    if (statusText) statusText.innerHTML = '<i class="fas fa-satellite-dish text-purple-500"></i> Signal Acquired — Scanning';

                    // Make the video (desktop) or canvas (mobile) feed visible
                    function _applyVideoStyles() {
                        const viewport = document.getElementById('scanner-viewport');
                        if (!viewport) return;

                        // Show <video> element if present (desktop/most browsers)
                        const video = viewport.querySelector('video');
                        if (video) {
                            // Ensure mobile-required attributes are set
                            video.setAttribute('playsinline', '');
                            video.setAttribute('muted', '');
                            video.muted = true;
                            video.style.width      = '100%';
                            video.style.height     = '100%';
                            video.style.objectFit  = 'cover';
                            video.style.display    = 'block';
                            video.style.background = 'transparent';
                            // Force play if paused
                            if (video.paused) { video.play().catch(() => {}); }
                            if (statusText) statusText.innerHTML = '<i class="fas fa-search text-green-400 animate-pulse"></i> Scanning — point at barcode';
                        }

                        // Handle canvases — Quagga injects two types:
                        //   1. canvas (no class)        → the live video feed on mobile
                        //   2. canvas.drawingBuffer     → debug detection overlay (hide it)
                        viewport.querySelectorAll('canvas').forEach(c => {
                            if (c.classList.contains('drawingBuffer')) {
                                c.style.display = 'none';
                            } else {
                                // Video-feed canvas used by mobile browsers
                                c.style.width   = '100%';
                                c.style.height  = '100%';
                                c.style.display = 'block';
                                if (statusText) statusText.innerHTML = '<i class="fas fa-search text-green-400 animate-pulse"></i> Scanning — point at barcode';
                            }
                        });
                    }

                    _applyVideoStyles();
                    setTimeout(_applyVideoStyles, 300);
                    setTimeout(_applyVideoStyles, 800);

                    // Watch for Quagga injecting elements asynchronously
                    if (vp) {
                        const obs = new MutationObserver(() => { _applyVideoStyles(); });
                        obs.observe(vp, { childList: true, subtree: true });
                        setTimeout(() => obs.disconnect(), 5000);
                    }
                });
                } catch(initErr) {
                    console.error("Quagga init exception:", initErr);
                    if (errorText) { errorText.textContent = 'Init Exception: ' + initErr.message; errorText.classList.remove('hidden'); }
                    if (statusText) statusText.innerHTML = '<i class="fas fa-exclamation-triangle text-red-400"></i> Scanner crashed';
                }
            }

            // Kick off: try rear/environment camera first
            _initQuagga({ facingMode: "environment", width: { ideal: 1280 }, height: { ideal: 720 } }, false);

            Quagga.onDetected(function(result) {
                if (isScanningLocked) return;
                const code = result.codeResult.code;
                if (!code) return;

                // Show detection indicator
                if (detectText) detectText.textContent = '⚡ Reading: ' + code;
                
                if (code !== lastDetectedCode) {
                    lastDetectedCode = code;
                    stabilityCount = 1;
                    if (pb) pb.style.width = '0%';
                    if (statusText) statusText.innerHTML = '<i class="fas fa-barcode text-purple-400"></i> Reading: ' + code;
                    return;
                }

                stabilityCount++;
                const progress = Math.min(100, (stabilityCount / STABILITY_THRESHOLD) * 100);
                if (pb) pb.style.width = progress + '%';

                if (stabilityCount < STABILITY_THRESHOLD) {
                    if (statusText) statusText.innerHTML = '<i class="fas fa-crosshairs text-pink-500 animate-pulse"></i> Verifying: ' + code;
                    return;
                }

                // Stable scan achieved
                isScanningLocked = true;
                if (pb) pb.style.width = '100%';
                if (statusText) statusText.innerHTML = '<i class="fas fa-check-double text-green-400"></i> Code Decrypted';
                try { Quagga.stop(); } catch(e) { console.warn('Quagga stop error:', e); }

                const confirmPanel = document.getElementById('scanner-confirm-panel');
                const codeDisplay = document.getElementById('scanned-code-display');
                const btnGroup = confirmPanel.querySelector('.flex.gap-3');
                
                // Show the modal but hide the buttons
                if (btnGroup) btnGroup.classList.add('hidden');
                codeDisplay.textContent = code;
                confirmPanel.classList.remove('hidden');

                // Give a brief moment for the user to see the green success state, then apply automatically
                setTimeout(() => {
                    confirmPanel.classList.add('hidden');
                    applyScannedCode(code, inputName);
                    closeScanner();
                }, 600);
            });

            window.cancelScannedCode = function() {
                const confirmPanel = document.getElementById('scanner-confirm-panel');
                confirmPanel.classList.add('hidden');
                isScanningLocked = false;
                lastDetectedCode = null;
                stabilityCount = 0;
                if (pb) pb.style.width = '0%';
                // Re-init scanner after cancel since we fully stopped it
                closeScanner();
                setTimeout(() => { window.startBarcodeScan(inputName); }, 300);
            };
        }
        
        window.handleBarcodeFile = function(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (typeof window.showGlobalLoader === 'function') {
                    window.showGlobalLoader("DECODING BARCODE...");
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    Quagga.decodeSingle({
                        src: e.target.result,
                        numOfWorkers: 0,
                        decoder: {
                            readers: ["code_128_reader","ean_reader","ean_8_reader","upc_reader","upc_e_reader","code_39_reader","i2of5_reader","codabar_reader"]
                        },
                        locate: true,
                        locator: { patchSize: "medium", halfSample: true }
                    }, function(result) {
                        if (typeof window.hideGlobalLoader === 'function') window.hideGlobalLoader();
                        if (result && result.codeResult) {
                            const decodedText = result.codeResult.code;
                            let lastInputName = 'item_no';
                            if (document.querySelector('.entry-ex-item')) lastInputName = 'exchange_item';
                            else if (document.querySelector('.entry-item')) lastInputName = 'return_item';
                            applyScannedCode(decodedText, lastInputName);
                        } else {
                            alert("Could not read barcode from image. Try taking a clearer photo.");
                        }
                    });
                };
                reader.readAsDataURL(file);
            }
        }
        
        window.closeScanner = function() {
            const modal = document.getElementById('scanner-modal');
            modal.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 300);
            
            lastDetectedCode = null;
            stabilityCount = 0;

            try { Quagga.stop(); } catch(e) {}
            Quagga.offDetected();
            // Clear viewport
            const viewport = document.getElementById('scanner-viewport');
            if (viewport) viewport.innerHTML = '';
        }

        window.previewAvatar = function(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatar-preview');
                    const letter = document.getElementById('avatar-letter');
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    if (letter) letter.classList.add('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            // Intercept internal link clicks (Pagination, Filters, Sidebar)
            document.querySelectorAll('a').forEach(a => {
                a.addEventListener('click', (e) => {
                    if (a.href && !a.href.includes('javascript:') && !a.href.includes('#') && a.target !== '_blank' && !a.hasAttribute('download') && !a.href.includes('logout=')) {
                        showGlobalLoader("LOADING DATA...");
                    }
                });
            });

            // Profile Settings Submission
            const profileForm = document.getElementById('profile-settings-form');
            profileForm?.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const msg = document.getElementById('profile-msg');
                const origText = btn.innerText;
                
                const formData = new FormData(this);

                btn.disabled = true;
                btn.innerText = "SAVING...";
                msg.classList.add('hidden');

                fetch('api/update_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    msg.innerText = res.message;
                    msg.classList.remove('hidden');
                    msg.className = `text-[10px] font-bold text-center py-2 rounded-lg mt-2 ${res.success ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'}`;
                    
                    if (res.success) {
                        setTimeout(() => {
                            location.reload(); // Reload to apply all changes (username, avatar, etc)
                        }, 1000);
                    }
                })
                .catch(() => {
                    msg.innerText = "A network error occurred.";
                    msg.classList.remove('hidden', 'bg-green-500/10', 'text-green-400');
                    msg.classList.add('bg-red-500/10', 'text-red-400');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerText = origText;
                });
            });
        });
    </script>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }
        @keyframes spinReverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        .animate-spin-reverse {
            animation: spinReverse 1s linear infinite;
        }
        /* Quagga scanner — precise CSS overrides to beat the global theme rules */
        /* Modal itself: solid dark background so page content is hidden */
        #scanner-modal { background-color: rgba(2,6,23,0.97) !important; }
        
        /* Reset opaque theme overrides for slate backgrounds inside the modal */
        #scanner-modal [class*="bg-slate-900"],
        #scanner-modal [class*="bg-slate-950"] { background-color: transparent !important; }
        
        /* Restore necessary backgrounds manually with higher specificity */
        #scanner-modal > div:first-child { background-color: rgba(15,23,42,0.85) !important; }
        #scanner-modal .w-full.aspect-square { background-color: #000 !important; }
        #scanner-modal #scanner-confirm-panel { background-color: rgba(2,6,23,0.8) !important; }
        #scanner-modal #scanner-confirm-panel > div { background-color: rgba(15,23,42,1) !important; }
        #scanner-modal .rounded-full.bg-slate-900\/60 { background-color: rgba(15,23,42,0.6) !important; }

        /* ONLY the viewport + its feed elements need transparent bg
           to override the theme's [class*="bg-slate-950"] opaque rule */
        #scanner-viewport { position: relative; z-index: 0; background: transparent !important; }
        #scanner-viewport video {
            width: 100% !important; height: 100% !important;
            object-fit: cover !important; display: block !important;
            background: transparent !important;
        }
        #scanner-viewport canvas:not(.drawingBuffer) {
            width: 100% !important; height: 100% !important;
            display: block !important; background: transparent !important;
        }
        #scanner-viewport canvas.drawingBuffer { display: none !important; }
    </style>
    
    <!-- Global Export Filename Modal -->
    <div id="global-filename-modal" class="fixed inset-0 z-[105] flex items-center justify-center opacity-0 pointer-events-none transition-all duration-500 scale-95">
        <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" onclick="closeGlobalFilenameModal()"></div>
        <div class="relative glass-panel border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.5)] w-full max-w-sm mx-4 overflow-hidden transform transition-all duration-500">
            <div class="absolute -top-20 -left-20 w-40 h-40 bg-purple-500/20 rounded-full blur-[80px] pointer-events-none"></div>
            <div class="absolute -bottom-20 -right-20 w-40 h-40 bg-pink-500/20 rounded-full blur-[80px] pointer-events-none"></div>
            
            <div class="bg-gradient-to-br from-purple-600/30 via-fuchsia-600/20 to-pink-600/30 p-6 border-b border-white/10 relative">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-lg shadow-purple-500/20">
                        <i class="fas fa-file-export text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 id="global-filename-title" class="text-base font-black text-white tracking-wider uppercase">Export File</h3>
                        <p class="text-[10px] text-purple-300/70 font-bold uppercase tracking-[0.1em] mt-0.5">Choose your file identity</p>
                    </div>
                </div>
                <button onclick="closeGlobalFilenameModal()" class="absolute top-6 right-6 w-8 h-8 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="p-8">
                <div class="space-y-6">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between px-1">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Filename</label>
                            <span class="text-[9px] font-bold text-purple-400/50 uppercase">Required</span>
                        </div>
                        <div class="relative group">
                            <input type="text" id="global-export-filename-input" 
                                   class="w-full bg-slate-950/50 border border-white/10 rounded-2xl px-5 py-4 text-sm text-white focus:outline-none focus:border-purple-500/50 focus:ring-4 focus:ring-purple-500/10 transition-all font-bold placeholder:text-gray-600" 
                                   placeholder="export_data">
                            <div class="absolute right-5 top-1/2 -translate-y-1/2 flex items-center gap-3">
                                <div class="w-px h-4 bg-white/10"></div>
                                <span id="global-filename-ext" class="text-[10px] font-black text-purple-400 uppercase tracking-tighter">.csv</span>
                            </div>
                        </div>
                    </div>
                    
                    <button onclick="confirmGlobalExportFilename()" class="relative w-full group overflow-hidden rounded-2xl p-px transition-all hover:scale-[1.02] active:scale-[0.98]">
                        <div class="absolute inset-0 bg-gradient-to-r from-purple-600 via-fuchsia-600 to-pink-600 transition-all"></div>
                        <div class="relative py-4 rounded-[15px] bg-slate-900/10 hover:bg-transparent transition-all flex items-center justify-center gap-3">
                            <span class="text-white font-black text-[11px] uppercase tracking-[0.2em]">Download Now</span>
                            <i class="fas fa-download text-[10px] text-white/50 group-hover:translate-y-1 transition-transform"></i>
                        </div>
                    </button>
                    
                    <p class="text-center text-[9px] text-gray-600 font-bold uppercase tracking-widest">The file will be saved to your downloads</p>
                </div>
            </div>
        </div>
    </div>
    <div id="global-loader" class="fixed inset-0 w-screen h-[100dvh] bg-slate-900/80 backdrop-blur-xl z-[100] flex items-center justify-center hidden opacity-0 transition-all duration-300">
        <div class="flex flex-col items-center justify-center p-6 text-center">
            <div class="relative w-20 h-20 mb-6">
                <div class="absolute inset-0 border-4 border-purple-500/20 border-t-purple-500 rounded-full animate-spin shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                <div class="absolute top-4 left-4 w-12 h-12 border-4 border-pink-500/20 border-b-pink-500 rounded-full animate-spin-reverse"></div>
            </div>
            <p id="loader-text" class="text-purple-400 font-semibold tracking-widest animate-pulse uppercase text-sm">Processing...</p>
        </div>
    </div>

    <!-- Theme Customizer Toggle Button -->
    <button id="theme-toggle-btn" onclick="toggleThemePanel()" 
        class="fixed bottom-6 right-6 z-[90] w-12 h-12 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 text-white flex items-center justify-center shadow-lg shadow-purple-500/30 hover:scale-110 hover:shadow-xl hover:shadow-purple-500/40 active:scale-95 transition-all duration-300 group"
        title="Customize Theme">
        <i class="fas fa-palette text-lg group-hover:rotate-12 transition-transform"></i>
    </button>

    <!-- Theme Customizer Panel -->
    <div id="theme-panel" class="fixed top-0 right-0 w-80 h-full z-[95] translate-x-full transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]">
        <div class="absolute inset-0 bg-[#0c1322]/95 backdrop-blur-2xl border-l border-white/10 shadow-[-20px_0_60px_rgba(0,0,0,0.5)]"></div>
        <div class="relative h-full flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-br from-purple-600/30 via-fuchsia-600/20 to-pink-600/30 px-6 py-5 border-b border-white/10 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-lg shadow-purple-500/20">
                        <i class="fas fa-palette text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-wider">Customize</h3>
                        <p class="text-[8px] text-purple-300/60 font-bold uppercase tracking-[0.15em]">Theme Editor</p>
                    </div>
                </div>
                <button onclick="toggleThemePanel()" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center text-gray-400 hover:text-white transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-5 space-y-5">

                <!-- Preset Themes -->
                <div class="space-y-2.5">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-swatchbook text-purple-400 text-[8px]"></i> Preset Themes
                    </label>
                    <div class="grid grid-cols-2 gap-2" id="theme-presets">
                        <button onclick="applyPreset('default')" class="preset-btn group flex items-center gap-2 px-3 py-2.5 rounded-xl bg-white/5 border border-white/5 hover:border-purple-500/30 transition-all text-left">
                            <div class="flex gap-0.5 shrink-0">
                                <div class="w-3 h-3 rounded-full bg-purple-500"></div>
                                <div class="w-3 h-3 rounded-full bg-pink-500"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-300 group-hover:text-white">Default</span>
                        </button>
                        <button onclick="applyPreset('ocean')" class="preset-btn group flex items-center gap-2 px-3 py-2.5 rounded-xl bg-white/5 border border-white/5 hover:border-cyan-500/30 transition-all text-left">
                            <div class="flex gap-0.5 shrink-0">
                                <div class="w-3 h-3 rounded-full bg-cyan-500"></div>
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-300 group-hover:text-white">Ocean</span>
                        </button>
                        <button onclick="applyPreset('emerald')" class="preset-btn group flex items-center gap-2 px-3 py-2.5 rounded-xl bg-white/5 border border-white/5 hover:border-emerald-500/30 transition-all text-left">
                            <div class="flex gap-0.5 shrink-0">
                                <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                                <div class="w-3 h-3 rounded-full bg-teal-500"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-300 group-hover:text-white">Emerald</span>
                        </button>
                        <button onclick="applyPreset('sunset')" class="preset-btn group flex items-center gap-2 px-3 py-2.5 rounded-xl bg-white/5 border border-white/5 hover:border-orange-500/30 transition-all text-left">
                            <div class="flex gap-0.5 shrink-0">
                                <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-300 group-hover:text-white">Sunset</span>
                        </button>
                        <button onclick="applyPreset('midnight')" class="preset-btn group flex items-center gap-2 px-3 py-2.5 rounded-xl bg-white/5 border border-white/5 hover:border-indigo-500/30 transition-all text-left">
                            <div class="flex gap-0.5 shrink-0">
                                <div class="w-3 h-3 rounded-full bg-indigo-500"></div>
                                <div class="w-3 h-3 rounded-full bg-violet-500"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-300 group-hover:text-white">Midnight</span>
                        </button>
                        <button onclick="applyPreset('rose')" class="preset-btn group flex items-center gap-2 px-3 py-2.5 rounded-xl bg-white/5 border border-white/5 hover:border-rose-500/30 transition-all text-left">
                            <div class="flex gap-0.5 shrink-0">
                                <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                                <div class="w-3 h-3 rounded-full bg-pink-400"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-300 group-hover:text-white">Rose</span>
                        </button>
                    </div>
                </div>

                <div class="h-px bg-white/5"></div>

                <!-- Font Family -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-font text-purple-400 text-[8px]"></i> Font Family
                    </label>
                    <select id="theme-font" onchange="updateThemeVar('--font-family', this.value + ', sans-serif')"
                        class="w-full bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500/50 cursor-pointer">
                        <option value="Outfit" selected>Outfit</option>
                        <option value="Inter">Inter</option>
                        <option value="Roboto">Roboto</option>
                        <option value="Poppins">Poppins</option>
                        <option value="Space Grotesk">Space Grotesk</option>
                        <option value="DM Sans">DM Sans</option>
                    </select>
                </div>

                <!-- Font Size -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                            <i class="fas fa-text-height text-purple-400 text-[8px]"></i> Font Size
                        </label>
                        <span id="font-size-display" class="text-[10px] font-bold text-purple-400">14px</span>
                    </div>
                    <input type="range" id="theme-font-size" min="11" max="18" value="14" 
                        oninput="updateThemeVar('--font-size-base', this.value + 'px'); document.getElementById('font-size-display').textContent = this.value + 'px'"
                        class="w-full h-1.5 rounded-full appearance-none cursor-pointer theme-range">
                </div>

                <div class="h-px bg-white/5"></div>

                <!-- Accent Primary Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-droplet text-purple-400 text-[8px]"></i> Accent Color
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-accent" value="#a855f7" 
                            onchange="updateThemeVar('--accent-primary', this.value); updateThemeVar('--text-accent', this.value); updateThemeVar('--sidebar-icon-active', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-accent-text" value="#a855f7" 
                            onchange="const c = this.value; document.getElementById('theme-accent').value = c; updateThemeVar('--accent-primary', c); updateThemeVar('--text-accent', c); updateThemeVar('--sidebar-icon-active', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <!-- Accent Secondary Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-droplet text-pink-400 text-[8px]"></i> Secondary Accent
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-accent2" value="#ec4899" 
                            onchange="updateThemeVar('--accent-secondary', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-accent2-text" value="#ec4899" 
                            onchange="const c = this.value; document.getElementById('theme-accent2').value = c; updateThemeVar('--accent-secondary', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <div class="h-px bg-white/5"></div>

                <!-- Background Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-fill-drip text-blue-400 text-[8px]"></i> Background Color
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-bg" value="#0f172a" 
                            onchange="updateThemeVar('--bg-primary', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-bg-text" value="#0f172a" 
                            onchange="const c = this.value; document.getElementById('theme-bg').value = c; updateThemeVar('--bg-primary', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <!-- Sidebar Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-bars text-indigo-400 text-[8px]"></i> Sidebar Color
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-sidebar" value="#0f172a" 
                            onchange="updateThemeVar('--bg-sidebar', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-sidebar-text" value="#0f172a" 
                            onchange="const c = this.value; document.getElementById('theme-sidebar').value = c; updateThemeVar('--bg-sidebar', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <!-- Card / Panel Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-square text-teal-400 text-[8px]"></i> Card / Panel Color
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-card" value="#1e293b" 
                            onchange="updateThemeVar('--bg-secondary', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-card-text" value="#1e293b" 
                            onchange="const c = this.value; document.getElementById('theme-card').value = c; updateThemeVar('--bg-secondary', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <!-- Border Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-border-all text-slate-400 text-[8px]"></i> Border Color
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-border" value="#334155" 
                            onchange="updateThemeVar('--border-color', this.value); updateThemeVar('--border-hover', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-border-text" value="#334155" 
                            onchange="const c = this.value; document.getElementById('theme-border').value = c; updateThemeVar('--border-color', c); updateThemeVar('--border-hover', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <!-- Input / Filter Background -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-keyboard text-indigo-400 text-[8px]"></i> Input & Filter BG
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-input" value="#0f172a" 
                            onchange="updateThemeVar('--bg-input', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-input-text" value="#0f172a" 
                            onchange="const c = this.value; document.getElementById('theme-input').value = c; updateThemeVar('--bg-input', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <div class="h-px bg-white/5"></div>

                <!-- Text Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-a text-gray-300 text-[8px]"></i> Text Color
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-text" value="#f8fafc" 
                            onchange="updateThemeVar('--text-primary', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-text-text" value="#f8fafc"
                            onchange="const c = this.value; document.getElementById('theme-text').value = c; updateThemeVar('--text-primary', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>

                <!-- Secondary Text Color -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-[0.15em] flex items-center gap-2">
                        <i class="fas fa-a text-gray-500 text-[8px]"></i> Label / Muted Text
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme-text2" value="#94a3b8" 
                            onchange="updateThemeVar('--text-secondary', this.value); updateThemeVar('--text-muted', this.value)"
                            class="w-10 h-10 rounded-xl border border-white/10 cursor-pointer bg-transparent p-0.5">
                        <input type="text" id="theme-text2-text" value="#94a3b8"
                            onchange="const c = this.value; document.getElementById('theme-text2').value = c; updateThemeVar('--text-secondary', c); updateThemeVar('--text-muted', c)"
                            class="flex-1 bg-slate-900/80 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500/50 font-mono">
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="p-5 border-t border-white/10 bg-slate-950/50 space-y-2.5 shrink-0">
                <button onclick="saveThemeToDB()" class="w-full py-3 rounded-xl bg-gradient-to-r from-purple-600 to-pink-600 text-white font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-purple-500/20 hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-cloud-arrow-up text-xs"></i> Save Theme
                </button>
                <button onclick="resetTheme()" class="w-full py-2.5 rounded-xl bg-white/5 text-gray-400 font-bold text-[10px] uppercase tracking-widest hover:bg-white/10 hover:text-white transition-all border border-white/5 flex items-center justify-center gap-2">
                    <i class="fas fa-rotate-left text-[9px]"></i> Reset to Default
                </button>
            </div>
        </div>
    </div>
    <!-- Theme Panel Overlay -->
    <div id="theme-panel-overlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[94] hidden opacity-0 transition-opacity duration-300" onclick="toggleThemePanel()"></div>

    <script>
    // ── Theme Engine ────────────────────────────────────────
    function injectThemeOverrides() {
        if (document.getElementById('theme-css-overrides')) return;
        const style = document.createElement('style');
        style.id = 'theme-css-overrides';
        style.innerHTML = `
            /* Backgrounds */
            body, .bg-slate-900, div[class*="bg-slate-900"] { background-color: var(--bg-primary) !important; }
            .sidebar-glass, #sidebar, aside, [class*="bg-slate-950"] { background-color: var(--bg-sidebar) !important; }
            .glass-panel, [class*="bg-slate-800"], div[class*="bg-slate-800"] { background-color: var(--bg-secondary) !important; }
            
            /* Text Colors */
            body, h1, h2, h3, h4, h5, h6, p, span, a, div, li, td, th, label, .text-white, .text-slate-100 { color: var(--text-primary) !important; }
            .text-gray-400, .text-slate-400, .text-gray-500, .text-slate-500, label { color: var(--text-secondary) !important; }
            
            /* Nav Links */
            #sidebar nav a span, #sidebar nav a { color: var(--text-secondary) !important; }
            #sidebar nav a:hover span, #sidebar nav a:hover { color: var(--text-primary) !important; }
            
            /* Tables */
            table thead tr, table thead th, .glass-table th { background-color: var(--bg-secondary) !important; color: var(--text-muted) !important; border-color: var(--border-color) !important; }
            table tbody tr { border-color: var(--border-color) !important; }
            
            /* Inputs */
            input:not([type="color"]):not([type="range"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]), select, textarea, #store-filter-trigger {
                background-color: var(--bg-input) !important;
                color: var(--text-primary) !important;
                border-color: transparent !important;
            }
            input:focus, select:focus, textarea:focus, #store-filter-trigger:focus { border-color: var(--accent-primary) !important; box-shadow: 0 0 0 1px var(--accent-primary) !important; }
            
            /* Custom Menus */
            #store-filter-menu, .flatpickr-calendar {
                background: var(--bg-secondary) !important;
                border: 1px solid var(--border-color) !important;
                backdrop-filter: blur(20px) !important;
                -webkit-backdrop-filter: blur(20px) !important;
            }
            #store-filter-menu .sticky { background: transparent !important; }
            .store-option:hover { background: var(--bg-card) !important; }

            /* Flatpickr Modern Design overrides */
            .flatpickr-calendar { font-family: var(--font-family) !important; box-shadow: 0 20px 50px rgba(0,0,0,0.5) !important; border-radius: 1rem !important; }
            .flatpickr-calendar::before, .flatpickr-calendar::after { display: none !important; }
            .flatpickr-month, .flatpickr-weekday { color: var(--text-secondary) !important; fill: var(--text-secondary) !important; }
            .flatpickr-day { color: var(--text-primary) !important; border-radius: 0.5rem !important; }
            .flatpickr-day:hover, .flatpickr-day:focus { background: var(--bg-card) !important; border-color: transparent !important; }
            .flatpickr-day.selected { background: var(--accent-primary) !important; border-color: var(--accent-primary) !important; color: white !important; font-weight: bold; }
            .flatpickr-day.flatpickr-disabled { color: var(--text-muted) !important; }
            .flatpickr-current-month .flatpickr-monthDropdown-months, .flatpickr-current-month input.cur-year { background: transparent !important; color: var(--text-primary) !important; }
            .flatpickr-current-month .flatpickr-monthDropdown-months option { background: var(--bg-primary) !important; color: var(--text-primary) !important; }
            
            /* Borders */
            [class*="border-white/5"], [class*="border-white/10"], [class*="border-slate-"] { border-color: var(--border-color) !important; }
            
            /* Fonts */
            html, body, .font-outfit, body *:not(.fas):not(.far):not(.fab):not(.fa):not(i[class*="fa-"]):not(svg):not(path):not(input[type="date"]) {
                font-family: var(--font-family) !important;
            }
            html { font-size: var(--font-size-base) !important; }
            /* Exclude Panel itself from font overrides, but NOT FontAwesome icons */
            #theme-panel, #theme-panel *:not(.fas):not(.far):not(.fab):not(.fa):not(i[class*="fa-"]) { font-family: 'Outfit', sans-serif !important; }
            
            /* Exclude Button gradients from being overwritten by div text-white rule */
            button[class*="bg-gradient"], a[class*="bg-gradient"], 
            .btn-primary, [class*="from-purple"], [class*="from-red"] {
                color: white !important;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Inject overrides immediately to map Tailwind to Variables
    injectThemeOverrides();

    const THEME_PRESETS = {
        default: {
            '--font-family': "'Outfit', sans-serif",
            '--font-size-base': '14px',
            '--bg-primary': '#0f172a',
            '--bg-sidebar': '#0f172a',
            '--bg-secondary': '#1e293b',
            '--bg-input': '#0f172a',
            '--border-color': '#334155',
            '--text-primary': '#f8fafc',
            '--text-secondary': '#94a3b8',
            '--text-muted': '#64748b',
            '--accent-primary': '#a855f7',
            '--accent-secondary': '#ec4899',
            '--text-accent': '#a855f7',
            '--sidebar-icon-active': '#c084fc',
            '--orb-1': '#9333ea',
            '--orb-2': '#2563eb'
        },
        ocean: {
            '--font-family': "'Inter', sans-serif",
            '--font-size-base': '14px',
            '--bg-primary': '#0a192f',
            '--bg-sidebar': '#071323',
            '--bg-secondary': '#112240',
            '--bg-input': '#0a192f',
            '--border-color': '#1e3a8a',
            '--text-primary': '#ccd6f6',
            '--text-secondary': '#8892b0',
            '--text-muted': '#64748b',
            '--accent-primary': '#06b6d4',
            '--accent-secondary': '#3b82f6',
            '--text-accent': '#06b6d4',
            '--sidebar-icon-active': '#22d3ee',
            '--orb-1': '#0891b2',
            '--orb-2': '#1d4ed8'
        },
        emerald: {
            '--font-family': "'DM Sans', sans-serif",
            '--font-size-base': '14px',
            '--bg-primary': '#0d1117',
            '--bg-sidebar': '#090d12',
            '--bg-secondary': '#161b22',
            '--bg-input': '#0d1117',
            '--border-color': '#30363d',
            '--text-primary': '#e6edf3',
            '--text-secondary': '#8b949e',
            '--text-muted': '#6e7681',
            '--accent-primary': '#10b981',
            '--accent-secondary': '#14b8a6',
            '--text-accent': '#10b981',
            '--sidebar-icon-active': '#34d399',
            '--orb-1': '#059669',
            '--orb-2': '#0d9488'
        },
        sunset: {
            '--font-family': "'Poppins', sans-serif",
            '--font-size-base': '14px',
            '--bg-primary': '#1a1023',
            '--bg-sidebar': '#140b1c',
            '--bg-secondary': '#2a1a36',
            '--bg-input': '#1a1023',
            '--border-color': '#4a2545',
            '--text-primary': '#fce7f3',
            '--text-secondary': '#d4a0b9',
            '--text-muted': '#a07090',
            '--accent-primary': '#f97316',
            '--accent-secondary': '#f43f5e',
            '--text-accent': '#f97316',
            '--sidebar-icon-active': '#fb923c',
            '--orb-1': '#ea580c',
            '--orb-2': '#e11d48'
        },
        midnight: {
            '--font-family': "'Space Grotesk', sans-serif",
            '--font-size-base': '14px',
            '--bg-primary': '#020617',
            '--bg-sidebar': '#010410',
            '--bg-secondary': '#0f172a',
            '--bg-input': '#020617',
            '--border-color': '#1e293b',
            '--text-primary': '#e2e8f0',
            '--text-secondary': '#94a3b8',
            '--text-muted': '#64748b',
            '--accent-primary': '#6366f1',
            '--accent-secondary': '#8b5cf6',
            '--text-accent': '#6366f1',
            '--sidebar-icon-active': '#818cf8',
            '--orb-1': '#4f46e5',
            '--orb-2': '#7c3aed'
        },
        rose: {
            '--font-family': "'Outfit', sans-serif",
            '--font-size-base': '14px',
            '--bg-primary': '#1c0a14',
            '--bg-sidebar': '#16070f',
            '--bg-secondary': '#2d1020',
            '--bg-input': '#1c0a14',
            '--border-color': '#501e38',
            '--text-primary': '#fdf2f8',
            '--text-secondary': '#e8b4cf',
            '--text-muted': '#c48ba8',
            '--accent-primary': '#f43f5e',
            '--accent-secondary': '#ec4899',
            '--text-accent': '#f43f5e',
            '--sidebar-icon-active': '#fb7185',
            '--orb-1': '#e11d48',
            '--orb-2': '#db2777'
        }
    };

    function updateThemeVar(prop, value) {
        document.documentElement.style.setProperty(prop, value);
        syncInputsFromVars();
        saveThemeToLocal();
    }

    function syncInputsFromVars() {
        const cs = getComputedStyle(document.documentElement);
        const get = (v) => cs.getPropertyValue(v).trim();
        
        const accent = get('--accent-primary');
        const accent2 = get('--accent-secondary');
        const bg = get('--bg-primary');
        const sidebar = get('--bg-sidebar');
        const card = get('--bg-secondary');
        const inputBg = get('--bg-input');
        const border = get('--border-color');
        const text = get('--text-primary');
        const text2 = get('--text-secondary');
        const font = get('--font-family').split(',')[0].replace(/'/g,'').trim();
        const size = parseInt(get('--font-size-base'));

        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
        
        setVal('theme-accent', accent);
        setVal('theme-accent-text', accent);
        setVal('theme-accent2', accent2);
        setVal('theme-accent2-text', accent2);
        setVal('theme-bg', bg);
        setVal('theme-bg-text', bg);
        setVal('theme-sidebar', sidebar);
        setVal('theme-sidebar-text', sidebar);
        setVal('theme-card', card);
        setVal('theme-card-text', card);
        
        let solidInput = inputBg;
        if (solidInput.startsWith('rgba')) solidInput = '#0f172a';
        setVal('theme-input', solidInput.substring(0, 7));
        setVal('theme-input-text', inputBg);
        
        // Strip alpha if present (e.g. rgba or #rgba) for the color picker input which only takes #rrggbb
        let solidBorder = border;
        if (solidBorder.startsWith('rgba')) {
            solidBorder = '#334155'; // Fallback for color picker if reading rgba
        }
        setVal('theme-border', solidBorder.substring(0, 7)); // Ensure only 6 hex chars
        setVal('theme-border-text', border);
        
        setVal('theme-text', text);
        setVal('theme-text-text', text);
        setVal('theme-text2', text2);
        setVal('theme-text2-text', text2);
        setVal('theme-font-size', size);
        
        const sizeDisplay = document.getElementById('font-size-display');
        if (sizeDisplay) sizeDisplay.textContent = size + 'px';

        const fontSelect = document.getElementById('theme-font');
        if (fontSelect) {
            for (let opt of fontSelect.options) {
                if (opt.value === font) { opt.selected = true; break; }
            }
        }
    }

    function applyPreset(name) {
        const preset = THEME_PRESETS[name];
        if (!preset) return;
        Object.entries(preset).forEach(([prop, val]) => {
            document.documentElement.style.setProperty(prop, val);
        });
        syncInputsFromVars();
        saveThemeToLocal();
    }

    function resetTheme() {
        applyPreset('default');
        localStorage.removeItem('concession_theme');
        fetch('api/save_theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme: THEME_PRESETS.default })
        });
        if (typeof showStatusModal === 'function') {
            showStatusModal(true, 'Theme has been reset to default!', 'Theme Reset');
        }
    }

    function getCurrentTheme() {
        const cs = getComputedStyle(document.documentElement);
        const vars = [
            '--font-family', '--font-size-base', '--bg-primary', '--bg-sidebar', '--bg-secondary', '--bg-input',
            '--border-color', '--text-primary', '--text-secondary', '--text-muted',
            '--accent-primary', '--accent-secondary', '--text-accent',
            '--sidebar-icon-active', '--orb-1', '--orb-2'
        ];
        const theme = {};
        vars.forEach(v => { theme[v] = cs.getPropertyValue(v).trim(); });
        return theme;
    }

    function saveThemeToLocal() {
        const theme = getCurrentTheme();
        localStorage.setItem('concession_theme', JSON.stringify(theme));
    }

    function loadThemeFromLocal() {
        const saved = localStorage.getItem('concession_theme');
        if (saved) {
            try {
                const theme = JSON.parse(saved);
                Object.entries(theme).forEach(([prop, val]) => {
                    document.documentElement.style.setProperty(prop, val);
                });
            } catch(e) {}
        }
    }

    function saveThemeToDB() {
        const theme = getCurrentTheme();
        fetch('api/save_theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme })
        })
        .then(r => r.json())
        .then(res => {
            if (typeof showStatusModal === 'function') {
                showStatusModal(res.success, res.message, res.success ? 'Theme Saved' : 'Save Failed');
            }
        })
        .catch(() => {
            if (typeof showStatusModal === 'function') {
                showStatusModal(false, 'Could not save theme. Network error.', 'Save Failed');
            }
        });
    }

    function loadThemeFromDB() {
        fetch('api/load_theme.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (res.theme) {
                    Object.entries(res.theme).forEach(([prop, val]) => {
                        document.documentElement.style.setProperty(prop, val);
                    });
                } else {
                    const preset = THEME_PRESETS['default'];
                    Object.entries(preset).forEach(([prop, val]) => {
                        document.documentElement.style.setProperty(prop, val);
                    });
                }
                saveThemeToLocal();
                syncInputsFromVars();
            }
        })
        .catch(() => {});
    }

    function toggleThemePanel() {
        const panel = document.getElementById('theme-panel');
        const overlay = document.getElementById('theme-panel-overlay');
        const isOpen = !panel.classList.contains('translate-x-full');
        
        if (isOpen) {
            panel.classList.add('translate-x-full');
            overlay.classList.add('opacity-0');
            setTimeout(() => overlay.classList.add('hidden'), 300);
        } else {
            syncInputsFromVars();
            overlay.classList.remove('hidden');
            panel.classList.remove('translate-x-full');
            setTimeout(() => overlay.classList.remove('opacity-0'), 10);
        }
    }

    // Load theme immediately (localStorage = instant, DB = async fallback)
    loadThemeFromLocal();
    document.addEventListener('DOMContentLoaded', () => {
        syncInputsFromVars();
        // Load from DB if no local theme exists (first visit on new device)
        if (!localStorage.getItem('concession_theme')) {
            loadThemeFromDB();
        }
    });
    </script>

    <style>
    /* Theme range slider styling */
    .theme-range {
        background: linear-gradient(to right, var(--accent-primary), var(--accent-secondary));
        -webkit-appearance: none;
        border-radius: 9999px;
    }
    .theme-range::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: white;
        box-shadow: 0 0 8px rgba(168, 85, 247, 0.5);
        cursor: pointer;
    }
    .theme-range::-moz-range-thumb {
        width: 16px;
        height: 16px;
        border: none;
        border-radius: 50%;
        background: white;
        box-shadow: 0 0 8px rgba(168, 85, 247, 0.5);
        cursor: pointer;
    }
    /* Color input styling */
    input[type="color"] {
        -webkit-appearance: none;
        border: none;
        padding: 0;
    }
    input[type="color"]::-webkit-color-swatch-wrapper {
        padding: 2px;
    }
    input[type="color"]::-webkit-color-swatch {
        border: none;
        border-radius: 8px;
    }
    </style>
    <script>
    // Setup Flatpickr
    window.initFlatpickr = function() {
        const dateInputs = document.querySelectorAll('input[type="date"]');
        if (typeof flatpickr !== 'undefined' && dateInputs.length > 0) {
            flatpickr(dateInputs, {
                dateFormat: "Y-m-d",
                disableMobile: true,
                onChange: function(selectedDates, dateStr, instance) {
                    // Only auto-submit if it's a filter form and doesn't have a required submit button
                    // Note: Since new forms shouldn't auto submit, we skip auto submit if it's a date input for new entries
                    if (instance.element.form && instance.element.closest('.filter-form')) {
                        instance.element.form.submit();
                    } else if (instance.element.id === 'start_date' || instance.element.id === 'end_date') {
                        // Support existing table filter bindings
                        if (typeof handleSearch === 'function') handleSearch();
                    }
                }
            });
            // Convert input type text so browser default icon doesn't show
            dateInputs.forEach(input => {
                input.type = 'text';
            });
        }
    };

    document.addEventListener("DOMContentLoaded", function() {
        window.initFlatpickr();
    });
    </script>
    <script>
    // Register PWA Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    }
    </script>
</body>
</html>

