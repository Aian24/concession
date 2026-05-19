<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Concession System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/concessiontab.png">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SheetJS for Excel export -->
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <!-- Barcode Scanner (Quagga2 - accurate 1D barcode localization) -->
    <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2/dist/quagga.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-900 text-white font-outfit min-h-screen flex overflow-hidden">
    


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
                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest"><?= $_SESSION['role'] ?> • STORE: <?= htmlspecialchars($_SESSION['store_code']) ?></p>
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
                    <div class="absolute inset-0 pointer-events-none">
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
    <div class="fixed top-[-10%] left-[-10%] w-[40rem] h-[40rem] bg-purple-600 rounded-full mix-blend-multiply filter blur-[120px] opacity-20 animate-blob pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[40rem] h-[40rem] bg-blue-600 rounded-full mix-blend-multiply filter blur-[120px] opacity-20 animate-blob animation-delay-2000 pointer-events-none"></div>

    <!-- Mobile Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar-glass w-64 h-[100dvh] lg:h-screen fixed lg:relative z-50 flex flex-col transition-transform duration-300 transform -translate-x-full lg:translate-x-0 overflow-hidden">
        <div class="p-6 flex flex-col items-center justify-center relative">
            <img src="images/concession.png" alt="Concession Logo" class="h-16 lg:h-24 w-auto object-contain transition-all">
            <button class="lg:hidden text-gray-400 hover:text-white transition-colors absolute right-6 top-1/2 -translate-y-1/2" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="px-6 pb-4">
            <?php
            // Fallback for current sessions that don't have store_name yet
            if (!isset($_SESSION['store_name']) && isset($_SESSION['store_code'])) {
                $db_store = db_connect();
                $s_stmt = $db_store->prepare("SELECT sname FROM storecode WHERE scode = ? LIMIT 1");
                $s_stmt->bind_param("s", $_SESSION['store_code']);
                $s_stmt->execute();
                $s_data = $s_stmt->get_result()->fetch_assoc();
                $_SESSION['store_name'] = $s_data['sname'] ?? 'N/A';
                $s_stmt->close();
            }
            ?>
            <div class="bg-slate-800/80 backdrop-blur-md rounded-xl px-4 py-3 border border-white/5 shadow-lg flex items-center gap-3 overflow-hidden">
                <div class="w-1.5 h-1.5 rounded-full bg-green-400 shadow-[0_0_8px_rgba(74,222,128,0.6)] animate-pulse shrink-0"></div>
                <div class="flex items-center gap-1.5 min-w-0 overflow-hidden">
                    <span class="text-[11px] font-black text-white truncate"><?= htmlspecialchars($_SESSION['store_name'] ?? 'N/A') ?></span>
                    <span class="text-[10px] font-bold text-gray-500 shrink-0 tracking-tighter uppercase">(<?= htmlspecialchars($_SESSION['store_code'] ?? '') ?>)</span>
                </div>
            </div>
        </div>
        
        <nav class="flex-1 overflow-y-auto px-4 space-y-[2px]">
            <?php
            $nav_items = [];
            if ($is_admin) {
                $nav_items['dashboard']  = ['icon' => 'fa-home',           'title' => 'Dashboard'];
            }
            
            if ($role === 'user') {
                $nav_items['history'] = ['icon' => 'fa-history', 'title' => "Today's Transact"];
            }
            
            $nav_items['sale']       = ['icon' => 'fa-shopping-cart',  'title' => 'Sale'];
            $nav_items['return']     = ['icon' => 'fa-undo',           'title' => 'Return'];
            $nav_items['receiving']  = ['icon' => 'fa-box-open',       'title' => 'Receiving'];
            // $nav_items['pullout']    = ['icon' => 'fa-arrow-right-from-bracket', 'title' => 'Pullout'];
            $nav_items['ros_supplies'] = ['icon' => 'fa-boxes-stacked','title' => 'ROS Supplies'];
            
            if ($is_full_admin) {
                $nav_items['admin'] = ['icon' => 'fa-users-cog', 'title' => 'Manage Users'];
                $nav_items['stores'] = ['icon' => 'fa-store', 'title' => 'Manage Stores'];
                $nav_items['prism_data'] = ['icon' => 'fa-gem', 'title' => 'Manage Prism Data'];
                $nav_items['recent_activity'] = ['icon' => 'fa-clock-rotate-left', 'title' => 'Recent Activity'];
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
                <?php if ($is_admin): ?>
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
                <?php endif; ?>
            </div>
        </header>

        <!-- Dynamic Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pt-6 sm:pt-8 pb-24">
            <div class="w-full mx-auto h-full animate-fade-in-up">
                <?php
                // Securely handle page inclusion
                $page_file = "views/pages/" . basename($action) . ".php";
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

    <script src="assets/js/app.js"></script>
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

            try {
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.getElementById('scanner-viewport'),
                    constraints: {
                        facingMode: "environment",
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    area: {
                        top: "40%",
                        right: "0%",
                        left: "0%",
                        bottom: "40%"
                    }
                },
                locator: {
                    patchSize: "small",
                    halfSample: true
                },
                numOfWorkers: 0,
                frequency: 10,
                decoder: {
                    readers: [
                        "code_128_reader",
                        "ean_reader",
                        "ean_8_reader",
                        "upc_reader",
                        "upc_e_reader",
                        "code_39_reader",
                        "codabar_reader"
                    ]
                },
                locate: false
            }, function(err) {
                if (err) {
                    console.error("Quagga init error:", err);
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
                
                // Style the video element Quagga creates (delay to let DOM render)
                setTimeout(() => {
                    const viewport = document.getElementById('scanner-viewport');
                    if (viewport) {
                        const video = viewport.querySelector('video');
                        if (video) { 
                            video.style.width = '100%'; 
                            video.style.height = '100%'; 
                            video.style.objectFit = 'cover';
                            if (statusText) statusText.innerHTML = '<i class="fas fa-search text-green-400 animate-pulse"></i> Scanning — point at barcode';
                        } else {
                            if (statusText) statusText.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-400"></i> Video element not found';
                        }
                        // Hide ALL canvas elements Quagga injects
                        viewport.querySelectorAll('canvas').forEach(c => { c.style.display = 'none'; });
                    }
                }, 300);
            });
            } catch(initErr) {
                console.error("Quagga init exception:", initErr);
                if (errorText) { errorText.textContent = 'Init Exception: ' + initErr.message; errorText.classList.remove('hidden'); }
                if (statusText) statusText.innerHTML = '<i class="fas fa-exclamation-triangle text-red-400"></i> Scanner crashed';
                return;
            }

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
        /* Quagga scanner viewport styling */
        #scanner-viewport { position: relative; }
        #scanner-viewport video { width: 100% !important; height: 100% !important; object-fit: cover !important; }
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
</body>
</html>
