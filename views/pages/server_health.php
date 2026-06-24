<?php
if (!isset($_SESSION['user'])) {
    die('Access Denied');
}

if (!in_array('server_health', $_SESSION['user_permissions'])) {
    echo "<div class='glass-panel p-8 text-center animate-fade-in'><h2 class='text-2xl font-bold text-red-400 mb-2'>Access Denied</h2><p class='text-gray-400'>You do not have permission to view server health.</p></div>";
    return;
}

require_once 'includes/db.php';

function getWmiData() {
    $data = [
        'cpu' => 0,
        'mem_free' => 0,
        'mem_total' => 0
    ];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @exec('wmic cpu get loadpercentage 2>&1', $cpu);
        if (!empty($cpu) && isset($cpu[1])) {
            $data['cpu'] = intval(trim($cpu[1]));
        }
        
        @exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>&1', $mem);
        if (!empty($mem)) {
            foreach($mem as $m) {
                if (strpos($m, 'FreePhysicalMemory') !== false) {
                    $data['mem_free'] = intval(explode('=', $m)[1]);
                }
                if (strpos($m, 'TotalVisibleMemorySize') !== false) {
                    $data['mem_total'] = intval(explode('=', $m)[1]);
                }
            }
        }
    } else {
        // Linux CPU
        $cpu_usage = 0;
        
        // Try getting instantaneous CPU usage from top
        @exec("top -bn1 | grep 'Cpu(s)' 2>&1", $top);
        if (!empty($top) && preg_match('/(\d+\.\d+)\s+id/', $top[0], $matches)) {
            $idle = floatval($matches[1]);
            $cpu_usage = 100 - $idle;
            $data['cpu'] = min(100, max(0, round($cpu_usage)));
        } else {
            // Fallback to load average
            $load = sys_getloadavg();
            if ($load !== false) {
                @exec("grep -c ^processor /proc/cpuinfo", $cores);
                $core_count = (!empty($cores) && intval($cores[0]) > 0) ? intval($cores[0]) : 1;
                $data['cpu'] = min(100, max(0, round(($load[0] / $core_count) * 100)));
            }
        }

        // Linux Memory (in KB, same as wmic output)
        $meminfo = @file_get_contents("/proc/meminfo");
        if ($meminfo) {
            preg_match("/MemTotal:\s+(\d+)/", $meminfo, $matches);
            $data['mem_total'] = isset($matches[1]) ? intval($matches[1]) : 0;
            preg_match("/MemAvailable:\s+(\d+)/", $meminfo, $matches);
            if(isset($matches[1])) {
                $data['mem_free'] = intval($matches[1]);
            } else {
                preg_match("/MemFree:\s+(\d+)/", $meminfo, $matches);
                $free = isset($matches[1]) ? intval($matches[1]) : 0;
                preg_match("/Buffers:\s+(\d+)/", $meminfo, $matches);
                $buffers = isset($matches[1]) ? intval($matches[1]) : 0;
                preg_match("/Cached:\s+(\d+)/", $meminfo, $matches);
                $cached = isset($matches[1]) ? intval($matches[1]) : 0;
                $data['mem_free'] = $free + $buffers + $cached;
            }
        }
    }
    
    return $data;
}

function getPhpProcesses() {
    $count = 0;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @exec('tasklist /fi "IMAGENAME eq php.exe" /nh 2>&1', $tasks);
        if (!empty($tasks)) {
            foreach ($tasks as $task) {
                if (strpos(strtolower($task), 'php.exe') !== false) {
                    $count++;
                }
            }
        }
    } else {
        @exec('ps -C php,php-cgi,php-fpm,lsphp --no-headers | wc -l 2>&1', $tasks);
        if (!empty($tasks) && intval($tasks[0]) > 0) {
            $count = intval($tasks[0]);
        } else {
            // Fallback for restricted environments
            @exec('ps aux | grep -E "php|lsphp" | grep -v grep | wc -l 2>&1', $fallback);
            if (!empty($fallback)) {
                $count = intval($fallback[0]);
            }
        }
    }
    return $count;
}

$wmi = getWmiData();
$cpu_percent = $wmi['cpu'];
$mem_total_gb = number_format($wmi['mem_total'] / 1024 / 1024, 2);
$mem_free_gb = number_format($wmi['mem_free'] / 1024 / 1024, 2);
$mem_used_gb = number_format(($wmi['mem_total'] - $wmi['mem_free']) / 1024 / 1024, 2);
$mem_percent = $wmi['mem_total'] > 0 ? round((($wmi['mem_total'] - $wmi['mem_free']) / $wmi['mem_total']) * 100) : 0;

$is_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$disk_path = $is_win ? "C:" : __DIR__;

$disk_total = @disk_total_space($disk_path) ?: 1;
$disk_free = @disk_free_space($disk_path) ?: 0;
$disk_used = $disk_total - $disk_free;

// Attempt to get exact cPanel quota on Linux if available
if (!$is_win) {
    @exec('quota -u $(whoami) -v 2>&1', $quota_out);
    if (empty($quota_out)) {
        @exec('quota -v 2>&1', $quota_out);
    }
    if (!empty($quota_out)) {
        $best_used = -1;
        $best_limit = -1;
        foreach($quota_out as $line) {
            // Strip asterisks which denote over-quota
            $clean_line = str_replace('*', '', $line);
            if (preg_match('/^\s*\S+\s+(\d+)\s+(\d+)\s+(\d+)/', $clean_line, $matches)) {
                $blocks_used = intval($matches[1]);
                $blocks_limit = intval($matches[2]) > 0 ? intval($matches[2]) : intval($matches[3]);
                
                if ($blocks_limit > 0 && $blocks_used > $best_used) {
                    $best_used = $blocks_used;
                    $best_limit = $blocks_limit;
                }
            }
        }
        if ($best_limit > 0) {
            // quota reports in 1KB blocks
            $disk_total = $best_limit * 1024;
            $disk_used = $best_used * 1024;
            $disk_free = max(0, $disk_total - $disk_used);
        }
    }
}
$disk_total_gb = number_format($disk_total / 1073741824, 2);
$disk_free_gb = number_format($disk_free / 1073741824, 2);
$disk_used_gb = number_format($disk_used / 1073741824, 2);
$disk_percent = $disk_total > 0 ? round(($disk_used / $disk_total) * 100) : 0;

$php_procs = getPhpProcesses();

$db = db_connect();
$db_res = $db->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Uptime', 'Questions')");
$db_stats = [];
if ($db_res) {
    while($row = $db_res->fetch_assoc()) {
        $db_stats[$row['Variable_name']] = $row['Value'];
    }
}
$db_uptime_seconds = isset($db_stats['Uptime']) ? intval($db_stats['Uptime']) : 0;
$db_uptime = $db_uptime_seconds > 0 ? gmdate("H:i:s", $db_uptime_seconds) : 'N/A';

if (isset($_GET['ajax']) && isset($_GET['refresh_data'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'cpu' => $cpu_percent,
        'mem_used_gb' => $mem_used_gb,
        'mem_total_gb' => $mem_total_gb,
        'mem_percent' => $mem_percent,
        'disk_used_gb' => $disk_used_gb,
        'disk_total_gb' => $disk_total_gb,
        'disk_percent' => $disk_percent,
        'php_procs' => $php_procs,
        'db_connected' => $db_stats['Threads_connected'] ?? 0,
        'db_running' => $db_stats['Threads_running'] ?? 0,
        'db_uptime' => $db_uptime,
        'db_uptime_seconds' => $db_uptime_seconds
    ]);
    exit;
}

function getColorClass($percent) {
    if ($percent < 60) return "text-emerald-400";
    if ($percent < 85) return "text-amber-400";
    return "text-red-500";
}
function getBgColorClass($percent) {
    if ($percent < 60) return "bg-emerald-500";
    if ($percent < 85) return "bg-amber-500";
    return "bg-red-500";
}
?>

<div class="mb-8 flex justify-end items-end">
    <div class="flex items-center gap-3 bg-slate-800/60 p-2 rounded-2xl border border-white/10 shadow-xl">
        <div class="flex items-center gap-2 px-3 text-sm font-semibold text-gray-300">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
            Auto-refreshing
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- CPU Usage -->
    <div class="glass-panel border border-white/5 p-6 rounded-2xl shadow-xl flex flex-col justify-between relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-xl group-hover:bg-purple-500/20 transition-all animate-pulse" style="animation-duration: 3s;"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div>
                <h3 class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-1">CPU Usage</h3>
                <div class="text-3xl font-black text-white flex items-baseline gap-1" id="cpu-val">
                    <?= $cpu_percent ?><span class="text-lg text-gray-500 font-medium">%</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-full bg-purple-500/20 flex items-center justify-center border border-purple-500/30">
                <i class="fas fa-microchip text-purple-400 text-lg"></i>
            </div>
        </div>
        <div class="w-full bg-slate-900 rounded-full h-1.5 border border-white/5 relative z-10 overflow-hidden">
            <div id="cpu-bar" class="h-1.5 rounded-full <?= getBgColorClass($cpu_percent) ?> transition-all duration-1000 ease-out" style="width: <?= $cpu_percent ?>%"></div>
        </div>
    </div>

    <!-- Memory Usage -->
    <div class="glass-panel border border-white/5 p-6 rounded-2xl shadow-xl flex flex-col justify-between relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full blur-xl group-hover:bg-blue-500/20 transition-all animate-pulse" style="animation-duration: 4s;"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div>
                <h3 class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-1">Memory Usage</h3>
                <div class="text-3xl font-black text-white flex items-baseline gap-1" id="mem-val">
                    <?= $mem_percent ?><span class="text-lg text-gray-500 font-medium">%</span>
                </div>
                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1" id="mem-text">
                    <?= $mem_used_gb ?> GB / <?= $mem_total_gb ?> GB
                </div>
            </div>
            <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center border border-blue-500/30">
                <i class="fas fa-memory text-blue-400 text-lg"></i>
            </div>
        </div>
        <div class="w-full bg-slate-900 rounded-full h-1.5 border border-white/5 relative z-10 overflow-hidden">
            <div id="mem-bar" class="h-1.5 rounded-full <?= getBgColorClass($mem_percent) ?> transition-all duration-1000 ease-out" style="width: <?= $mem_percent ?>%"></div>
        </div>
    </div>

    <!-- Storage -->
    <div class="glass-panel border border-white/5 p-6 rounded-2xl shadow-xl flex flex-col justify-between relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl group-hover:bg-emerald-500/20 transition-all animate-pulse" style="animation-duration: 3.5s;"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div>
                <h3 class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-1">Disk Storage</h3>
                <div class="text-3xl font-black text-white flex items-baseline gap-1" id="disk-val">
                    <?= $disk_percent ?><span class="text-lg text-gray-500 font-medium">%</span>
                </div>
                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1" id="disk-text">
                    <?= $disk_used_gb ?> GB / <?= $disk_total_gb ?> GB
                </div>
            </div>
            <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center border border-emerald-500/30">
                <i class="fas fa-hdd text-emerald-400 text-lg"></i>
            </div>
        </div>
        <div class="w-full bg-slate-900 rounded-full h-1.5 border border-white/5 relative z-10 overflow-hidden">
            <div id="disk-bar" class="h-1.5 rounded-full <?= getBgColorClass($disk_percent) ?> transition-all duration-1000 ease-out" style="width: <?= $disk_percent ?>%"></div>
        </div>
    </div>

    <!-- DB Connections -->
    <div class="glass-panel border border-white/5 p-6 rounded-2xl shadow-xl flex flex-col justify-between relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-500/10 rounded-full blur-xl group-hover:bg-pink-500/20 transition-all animate-pulse" style="animation-duration: 2.5s;"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div>
                <h3 class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-1">DB Threads</h3>
                <div class="text-3xl font-black text-white flex items-baseline gap-1" id="db-val">
                    <?= $db_stats['Threads_connected'] ?? 0 ?>
                </div>
                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1" id="db-running-text">
                    <?= $db_stats['Threads_running'] ?? 0 ?> Running
                </div>
            </div>
            <div class="w-10 h-10 rounded-full bg-pink-500/20 flex items-center justify-center border border-pink-500/30">
                <i class="fas fa-database text-pink-400 text-lg"></i>
            </div>
        </div>
        <div class="text-[10px] text-gray-400 uppercase font-bold tracking-widest relative z-10 border-t border-white/5 pt-2 mt-2" id="db-uptime-text" data-seconds="<?= $db_uptime_seconds ?>">
            Uptime: <?= $db_uptime ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Background Processes -->
    <div class="glass-panel border border-white/5 p-6 rounded-2xl shadow-xl">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-white/5">
            <div class="w-10 h-10 rounded-full bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                <i class="fas fa-cogs"></i>
            </div>
            <div>
                <h3 class="text-white font-bold tracking-wide">Background Processes</h3>
                <p class="text-xs text-gray-400">Active PHP executables on the server.</p>
            </div>
        </div>
        <div class="flex items-center justify-between p-4 bg-slate-900/50 border border-white/5 rounded-xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-slate-800 border border-white/10 flex items-center justify-center text-gray-400">
                    <i class="fab fa-php text-2xl"></i>
                </div>
                <div>
                    <h4 class="text-white font-bold"><?= $is_win ? 'php.exe' : 'PHP Workers' ?></h4>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Active CLI Processes</p>
                </div>
            </div>
            <div class="text-2xl font-black text-cyan-400" id="php-procs-val">
                <?= $php_procs ?>
            </div>
        </div>
    </div>

    <!-- System Info -->
    <div class="glass-panel border border-white/5 p-6 rounded-2xl shadow-xl">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-white/5">
            <div class="w-10 h-10 rounded-full bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
                <i class="fas fa-info-circle"></i>
            </div>
            <div>
                <h3 class="text-white font-bold tracking-wide">System Information</h3>
                <p class="text-xs text-gray-400">OS and environment details.</p>
            </div>
        </div>
        <div class="space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-white/5">
                <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">Operating System</span>
                <span class="text-sm font-medium text-white"><?= php_uname('s') . ' ' . php_uname('r') ?></span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-white/5">
                <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">Server Name</span>
                <span class="text-sm font-medium text-white"><?= php_uname('n') ?></span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-white/5">
                <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">PHP Version</span>
                <span class="text-sm font-medium text-white"><?= phpversion() ?></span>
            </div>
            <div class="flex justify-between items-center py-2">
                <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">System Architecture</span>
                <span class="text-sm font-medium text-white"><?= php_uname('m') ?></span>
            </div>
        </div>
    </div>
</div>

<script>
    let currentUptimeSeconds = parseInt(document.getElementById('db-uptime-text').getAttribute('data-seconds')) || 0;

    function formatUptime(seconds) {
        if (!seconds || seconds <= 0) return 'N/A';
        const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const s = Math.floor(seconds % 60).toString().padStart(2, '0');
        return h + ':' + m + ':' + s;
    }

    // Live tick for uptime
    setInterval(() => {
        if (currentUptimeSeconds > 0) {
            currentUptimeSeconds++;
            document.getElementById('db-uptime-text').innerText = 'Uptime: ' + formatUptime(currentUptimeSeconds);
        }
    }, 1000);

    function updateServerHealth() {
        fetch('server_health?ajax=1&refresh_data=1')
            .then(res => res.json())
            .then(data => {
                // CPU
                document.getElementById('cpu-val').innerHTML = data.cpu + '<span class="text-lg text-gray-500 font-medium">%</span>';
                const cpuBar = document.getElementById('cpu-bar');
                cpuBar.style.width = data.cpu + '%';
                cpuBar.className = 'h-1.5 rounded-full transition-all duration-1000 ease-out ' + getBgColorClassJs(data.cpu);

                // Memory
                document.getElementById('mem-val').innerHTML = data.mem_percent + '<span class="text-lg text-gray-500 font-medium">%</span>';
                document.getElementById('mem-text').innerText = data.mem_used_gb + ' GB / ' + data.mem_total_gb + ' GB';
                const memBar = document.getElementById('mem-bar');
                memBar.style.width = data.mem_percent + '%';
                memBar.className = 'h-1.5 rounded-full transition-all duration-1000 ease-out ' + getBgColorClassJs(data.mem_percent);

                // Disk
                document.getElementById('disk-val').innerHTML = data.disk_percent + '<span class="text-lg text-gray-500 font-medium">%</span>';
                document.getElementById('disk-text').innerText = data.disk_used_gb + ' GB / ' + data.disk_total_gb + ' GB';
                const diskBar = document.getElementById('disk-bar');
                diskBar.style.width = data.disk_percent + '%';
                diskBar.className = 'h-1.5 rounded-full transition-all duration-1000 ease-out ' + getBgColorClassJs(data.disk_percent);

                // DB
                document.getElementById('db-val').innerText = data.db_connected;
                document.getElementById('db-running-text').innerText = data.db_running + ' Running';
                
                // Update uptime tracking variable
                if (data.db_uptime_seconds) {
                    currentUptimeSeconds = parseInt(data.db_uptime_seconds);
                    document.getElementById('db-uptime-text').innerText = 'Uptime: ' + formatUptime(currentUptimeSeconds);
                }

                // Procs
                document.getElementById('php-procs-val').innerText = data.php_procs;
            })
            .catch(err => console.error("Error fetching health data:", err));
    }

    function getBgColorClassJs(percent) {
        if (percent < 60) return "bg-emerald-500";
        if (percent < 85) return "bg-amber-500";
        return "bg-red-500";
    }

    // Auto refresh every 5 seconds
    setInterval(updateServerHealth, 5000);
</script>
