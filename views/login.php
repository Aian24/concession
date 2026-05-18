<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Concession System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/concessiontab.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes spinReverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        .animate-spin-reverse {
            animation: spinReverse 1s linear infinite;
        }

        /* Login responsive fixes */
        @media (max-height: 700px), (max-width: 480px) {
            .login-card {
                padding: 1.5rem !important;
                margin: 1rem !important;
                border-radius: 1rem !important;
            }
            .login-card .login-logo {
                height: 5rem !important;
                margin-bottom: 0.5rem !important;
            }
            .login-card .login-subtitle {
                font-size: 0.8rem !important;
                margin-bottom: 1rem !important;
            }
            .login-card form {
                gap: 1rem !important;
            }
            .login-card input,
            .login-card .input-modern {
                padding: 0.6rem 0.8rem !important;
                font-size: 0.85rem !important;
            }
            .login-card button[type="submit"] {
                padding: 0.6rem !important;
                font-size: 1rem !important;
            }
        }

        @media (max-width: 480px) {
            .login-blob {
                width: 12rem !important;
                height: 12rem !important;
            }
        }
    </style>
</head>
<body class="bg-slate-900 bg-[url('images/bg.png')] bg-cover bg-center bg-no-repeat bg-fixed text-white font-outfit min-h-screen min-h-[100dvh] flex items-center justify-center relative overflow-x-hidden overflow-y-auto">


    <!-- Animated background glowing orbs -->
    <div class="login-blob absolute top-[-10%] left-[-10%] w-72 h-72 md:w-96 md:h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob"></div>
    <div class="login-blob absolute top-[-10%] right-[-10%] w-72 h-72 md:w-96 md:h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-2000"></div>
    <div class="login-blob absolute bottom-[-20%] left-[20%] w-72 h-72 md:w-96 md:h-96 bg-pink-600 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-4000"></div>

    <div class="login-card glass-panel p-6 sm:p-8 md:p-12 w-full max-w-md relative z-10 mx-4 my-6">
        <div class="text-center mb-6 md:mb-8">
            <img src="images/concession.png" alt="Concession Logo" class="login-logo h-24 sm:h-32 mx-auto mb-3 md:mb-4 object-contain">
            <p class="login-subtitle text-gray-400 text-sm md:text-base">Sign in to your account</p>
        </div>

        <?php if (!empty($login_error ?? '')): ?>
        <div class="mb-5 px-4 py-3 rounded-xl bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-medium flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($login_error) ?>
        </div>
        <?php endif; ?>

        <?php
        $db_login = db_connect();
        $stores_res = $db_login->query("SELECT scode, sname FROM storecode ORDER BY sname ASC");
        $stores = $stores_res ? $stores_res->fetch_all(MYSQLI_ASSOC) : [];
        ?>
        <form action="index.php" method="POST" class="space-y-4 md:space-y-6" id="login-form">
            <input type="hidden" name="login" value="1">
            
            <!-- 1. Username -->
            <div>
                <label class="block text-[10px] font-black text-purple-400 uppercase tracking-widest mb-1 ml-1">Username</label>
                <input type="text" name="username" id="login-username" required class="input-modern w-full" placeholder="Enter username" autocomplete="username" value="<?= htmlspecialchars($remembered_username ?? '') ?>">
            </div>

            <!-- 2. Password -->
            <div class="space-y-1">
                <label class="block text-[10px] font-black text-purple-400 uppercase tracking-widest mb-1 ml-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="login-password" required class="input-modern w-full pr-12" placeholder="••••••••" autocomplete="current-password">
                    <button type="button" id="toggle-password" class="absolute right-0 top-0 h-full px-4 text-gray-500 hover:text-purple-400 transition-colors focus:outline-none">
                        <i class="fas fa-eye text-sm" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <!-- 3. Store Code (Selectable + Auto-lock) -->
            <div class="relative" id="store-dropdown-wrapper">
                <label class="block text-[10px] font-black text-purple-400 uppercase tracking-widest mb-1 ml-1">Assigned Store</label>
                <div class="relative" id="store-input-group">
                    <div id="store-loading-overlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px] z-10 hidden flex items-center justify-center rounded-xl">
                        <i class="fas fa-circle-notch animate-spin text-purple-500"></i>
                    </div>
                    
                    <input type="text" id="store-search-input" placeholder="Search or enter username..." autocomplete="off" required 
                           class="w-full bg-slate-900 text-white border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-purple-500/50 transition-all font-medium">
                    
                    <!-- Selected Store Display Overlay (Locked State) -->
                    <div id="store-selected-display" class="absolute inset-0 bg-slate-900 border border-purple-500/50 rounded-xl px-4 py-3 flex items-center justify-between hidden">
                        <div class="flex items-center truncate mr-4">
                            <span id="display-name" class="text-white text-sm font-medium truncate"></span>
                            <span id="display-code" class="text-purple-400 font-bold text-sm ml-1 shrink-0"></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i id="lock-icon" class="fas fa-lock text-[10px] text-purple-500/50 hidden"></i>
                            <button type="button" id="change-store-btn" class="text-[9px] font-black text-gray-500 hover:text-white uppercase tracking-widest transition-colors shrink-0">Change</button>
                        </div>
                    </div>

                    <input type="hidden" name="store_code" id="store-hidden-value" required>
                </div>
                
                <!-- Dropdown Menu -->
                <div class="absolute z-50 left-0 right-0 mt-2 max-h-60 overflow-y-auto bg-slate-900/95 backdrop-blur-xl border border-purple-500/20 rounded-xl shadow-[0_0_20px_rgba(168,85,247,0.15)] hidden" id="store-options-container">
                    <?php foreach ($stores as $store): ?>
                        <div class="px-4 py-3 hover:bg-purple-600/30 text-white text-sm font-medium cursor-pointer transition-colors border-b border-white/5 last:border-0 option-item" 
                             data-code="<?= htmlspecialchars($store['scode']) ?>" 
                             data-name="<?= htmlspecialchars($store['sname']) ?>">
                            <?= htmlspecialchars($store['sname']) ?> <span class="text-purple-400 font-bold ml-1">(<?= htmlspecialchars($store['scode']) ?>)</span>
                        </div>
                    <?php endforeach; ?>
                    <div class="px-4 py-3 text-gray-500 text-sm font-medium hidden" id="store-no-results">No stores found</div>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
                <label for="remember-me" class="flex items-center gap-2.5 cursor-pointer group select-none">
                    <div class="relative flex items-center justify-center w-4 h-4">
                        <input type="checkbox" name="remember_me" id="remember-me"
                               class="peer w-4 h-4 appearance-none rounded border border-white/20 bg-slate-800 checked:bg-purple-600 checked:border-purple-600 transition-all cursor-pointer"
                               <?= !empty($remembered_username) ? 'checked' : '' ?>>
                        <i class="fas fa-check text-[7px] text-white absolute pointer-events-none opacity-0 peer-checked:opacity-100 transition-opacity"></i>
                    </div>
                    <span class="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">Remember me</span>
                </label>
            </div>

            <button type="submit" name="login" id="login-btn" class="btn-primary w-full py-3 rounded-xl font-semibold text-lg hover:-translate-y-1 transition-transform shadow-lg shadow-purple-500/30">
                Sign In
            </button>
        </form>

        <script>
        document.addEventListener("DOMContentLoaded", () => {
            const usernameInput = document.getElementById('login-username');
            const passwordInput = document.getElementById('login-password');
            const togglePasswordBtn = document.getElementById('toggle-password');
            const eyeIcon = document.getElementById('eye-icon');
            
            const storeWrapper = document.getElementById('store-dropdown-wrapper');
            const storeSearchInput = document.getElementById('store-search-input');
            const storeHiddenInput = document.getElementById('store-hidden-value');
            const storeSelectedDisplay = document.getElementById('store-selected-display');
            const displayName = document.getElementById('display-name');
            const displayCode = document.getElementById('display-code');
            const changeStoreBtn = document.getElementById('change-store-btn');
            const lockIcon = document.getElementById('lock-icon');
            const storeOptionsContainer = document.getElementById('store-options-container');
            const storeItems = Array.from(document.querySelectorAll('.option-item'));
            const noResults = document.getElementById('store-no-results');
            const storeLoadingOverlay = document.getElementById('store-loading-overlay');
            const loginBtn = document.getElementById('login-btn');
            const loginForm = document.getElementById('login-form');

            // ── Pre-fill Remembered Store ────────────────────────────
            const rememberedStoreCode = <?= json_encode($remembered_store_code ?? '') ?>;
            if (rememberedStoreCode) {
                const matchedOpt = storeItems.find(o => o.dataset.code === rememberedStoreCode);
                if (matchedOpt) {
                    selectStore(matchedOpt.dataset.code, matchedOpt.dataset.name, false);
                }
            }

            // --- Password Toggle ---
            if (togglePasswordBtn) {
                togglePasswordBtn.addEventListener('click', () => {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    eyeIcon.classList.toggle('fa-eye');
                    eyeIcon.classList.toggle('fa-eye-slash');
                });
            }

            // --- Manual Store Selection ---
            function showDropdown() { storeOptionsContainer.classList.remove('hidden'); }
            function hideDropdown() { storeOptionsContainer.classList.add('hidden'); }

            storeSearchInput.addEventListener('focus', showDropdown);
            document.addEventListener('click', (e) => {
                if (!storeWrapper.contains(e.target)) hideDropdown();
            });

            storeSearchInput.addEventListener('input', () => {
                const query = storeSearchInput.value.toLowerCase().trim();
                let hasMatches = false;

                storeItems.forEach(opt => {
                    const code = opt.dataset.code.toLowerCase();
                    const name = opt.dataset.name.toLowerCase();
                    if (code.includes(query) || name.includes(query)) {
                        opt.classList.remove('hidden');
                        hasMatches = true;
                    } else {
                        opt.classList.add('hidden');
                    }
                });

                noResults.classList.toggle('hidden', hasMatches);
                showDropdown();
            });

            storeItems.forEach(opt => {
                opt.addEventListener('click', () => {
                    selectStore(opt.dataset.code, opt.dataset.name, false);
                });
            });

            changeStoreBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                resetStoreField();
                storeSearchInput.focus();
                showDropdown();
            });

            function selectStore(code, name, isLocked = false) {
                storeHiddenInput.value = code;
                storeSearchInput.value = `${name} (${code})`; // Satisfy 'required' attribute
                displayName.textContent = name;
                displayCode.textContent = `(${code})`;
                
                storeSelectedDisplay.classList.remove('hidden');
                storeSearchInput.style.opacity = '0';
                lockIcon.classList.toggle('hidden', !isLocked);
                
                // Only allow changing if NOT locked
                if (isLocked) {
                    changeStoreBtn.classList.add('hidden');
                } else {
                    changeStoreBtn.classList.remove('hidden');
                }
                
                hideDropdown();
            }

            function resetStoreField() {
                storeHiddenInput.value = '';
                storeSearchInput.value = '';
                storeSelectedDisplay.classList.add('hidden');
                storeSearchInput.style.opacity = '1';
                lockIcon.classList.add('hidden');
                changeStoreBtn.classList.remove('hidden');
            }

            // --- Auto-fill Logic ---
            let lookupTimer;
            usernameInput.addEventListener('input', () => {
                clearTimeout(lookupTimer);
                const username = usernameInput.value.trim();
                
                // If username is cleared, fully reset and unlock the store field
                if (username.length === 0) {
                    resetStoreField();
                } else if (username.length >= 3) {
                    lookupTimer = setTimeout(() => fetchUserStore(username), 600);
                }
            });

            usernameInput.addEventListener('blur', () => {
                const username = usernameInput.value.trim();
                if (username.length >= 3 && !storeHiddenInput.value) {
                    fetchUserStore(username);
                }
            });

            function fetchUserStore(username) {
                storeLoadingOverlay.classList.remove('hidden');
                fetch(`api/get_user_store.php?username=${encodeURIComponent(username)}`)
                    .then(res => res.json())
                    .then(data => {
                        storeLoadingOverlay.classList.add('hidden');
                        if (data.success) {
                            selectStore(data.store_code, data.sname, true);
                            // Highlight verified state
                            storeSelectedDisplay.classList.add('ring-2', 'ring-purple-500/50');
                            setTimeout(() => storeSelectedDisplay.classList.remove('ring-2', 'ring-purple-500/50'), 1000);
                        }
                    })
                    .catch(() => {
                        storeLoadingOverlay.classList.add('hidden');
                    });
            }

            // --- Form Submit ---
            loginForm.addEventListener('submit', (e) => {
                const loader = document.getElementById('global-loader');
                loader.classList.remove('hidden');
                setTimeout(() => loader.classList.remove('opacity-0'), 10);
                
                loginBtn.innerHTML = `<i class="fas fa-circle-notch animate-spin mr-2"></i> Signing In...`;
                loginBtn.classList.add('opacity-80', 'cursor-not-allowed');
            });
        });
        </script>
    </div>

    <!-- Global Loading Overlay -->
    <div id="global-loader" class="fixed inset-0 w-screen h-[100dvh] bg-slate-900/80 backdrop-blur-xl z-[100] flex items-center justify-center hidden opacity-0 transition-all duration-300">
        <div class="flex flex-col items-center justify-center p-6 text-center">
            <div class="relative w-20 h-20 mb-6">
                <div class="absolute inset-0 border-4 border-purple-500/20 border-t-purple-500 rounded-full animate-spin shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                <div class="absolute top-4 left-4 w-12 h-12 border-4 border-pink-500/20 border-b-pink-500 rounded-full animate-spin-reverse"></div>
            </div>
            <p id="loader-text" class="text-purple-400 font-semibold tracking-widest animate-pulse uppercase text-sm">Authenticating...</p>
        </div>
    </div>
</body>
</html>
