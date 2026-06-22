// Sidebar Toggle Logic
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Select All Logic
function toggleAllRows(source) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

// Notification System
function showNotification(message, type = 'success') {
    const notif = document.createElement('div');
    const colors = type === 'success' ? 'bg-green-500/90 border-green-500' : 'bg-red-500/90 border-red-500';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    notif.className = `fixed bottom-4 right-4 z-50 px-6 py-4 rounded-xl text-white font-medium shadow-2xl backdrop-blur-md border border-white/20 flex items-center gap-3 transform translate-y-20 opacity-0 transition-all duration-300 ${colors}`;
    notif.innerHTML = `<i class="fas ${icon} text-xl"></i> ${message}`;
    
    document.body.appendChild(notif);
    
    // Animate in
    setTimeout(() => {
        notif.classList.remove('translate-y-20', 'opacity-0');
    }, 10);
    
    // Animate out
    setTimeout(() => {
        notif.classList.add('translate-y-20', 'opacity-0');
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}

// Export Logic
function exportTable(type) {
    const table = document.getElementById('dataTable');
    if (!table) return;

    // Get selected rows
    const checkboxes = table.querySelectorAll('tbody .row-checkbox');
    const selectedRows = [];
    
    // Grab headers (skip the first column which is checkbox)
    const headers = [];
    table.querySelectorAll('thead th').forEach((th, idx) => {
        if(idx > 0) headers.push(th.innerText.trim()); 
    });
    selectedRows.push(headers);

    let targetRows = Array.from(checkboxes).filter(cb => cb.checked);
    if (targetRows.length === 0) {
        // Determine if we should trigger a deep background fetch natively from the API!
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        
        if (action === 'monitoring' && (type === 'csv' || type === 'txt')) {
            let baseUrl = `api/export_sales.php?type=${type}`;
            if(urlParams.get('status')) baseUrl += `&status=${urlParams.get('status')}`;
            if(urlParams.get('search')) baseUrl += `&search=${encodeURIComponent(urlParams.get('search'))}`;
            if(urlParams.get('start_date')) baseUrl += `&start_date=${encodeURIComponent(urlParams.get('start_date'))}`;
            if(urlParams.get('end_date')) baseUrl += `&end_date=${encodeURIComponent(urlParams.get('end_date'))}`;
            
            if(typeof showGlobalLoader === 'function') {
                showGlobalLoader(`EXTRACTING NATIVE ${type.toUpperCase()}...`);
                // Auto hide because downloading a file doesn't refresh the page!
                setTimeout(() => hideGlobalLoader(), 5000); 
            } else {
                showNotification(`Extracting native ${type.toUpperCase()} database...`, 'success');
            }
            
            window.location.href = baseUrl;
            return;
        }
        
        // Automatically default to all local visible rows for non-API tabs or local formats (XLS)
        targetRows = Array.from(checkboxes);
    }

    if (targetRows.length === 0) {
        showNotification("No data available to export.", "error");
        return;
    }

    targetRows.forEach((cb) => {
        const rowData = [];
        const tr = cb.closest('tr');
        tr.querySelectorAll('td').forEach((td, idx) => {
            if(idx > 0) {
                // Flatten multi-line nested divs into one clean string, and escape quotes
                let cellText = td.innerText.replace(/\s+/g, ' ').trim();
                if (type === 'csv') {
                    // Protect CSV structure from being broken by native commas
                    if (cellText.includes(',') || cellText.includes('"')) {
                        cellText = `"${cellText.replace(/"/g, '""')}"`;
                    }
                }
                rowData.push(cellText);
            }
        });
        selectedRows.push(rowData);
    });

    // Process export based on type
    if (typeof openGlobalFilenameModal === 'function') {
        openGlobalFilenameModal(type, 'export_data', function(customFilename) {
            if(typeof showGlobalLoader === 'function') {
                showGlobalLoader(`GENERATING ${type.toUpperCase()}...`);
            }
            
            setTimeout(() => {
                try {
                    if (type === 'csv' || type === 'txt') {
                        let mime = type === 'csv' ? 'text/csv' : 'text/plain';
                        let separator = type === 'csv' ? ',' : '\t';
                        
                        const BOM = "\uFEFF";
                        let fileContent = BOM + selectedRows.map(e => e.join(separator)).join("\n");
                        
                        let encodedUri = `data:${mime};charset=utf-8,${encodeURIComponent(fileContent)}`;
                        
                        let link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", `${customFilename}.${type}`);
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else if (type === 'xls') {
                        if (typeof XLSX === 'undefined') {
                            if(typeof hideGlobalLoader === 'function') hideGlobalLoader();
                            showNotification("Excel export library is still loading.", "error");
                            return;
                        }
                        const wb = XLSX.utils.book_new();
                        const ws = XLSX.utils.aoa_to_sheet(selectedRows);
                        XLSX.utils.book_append_sheet(wb, ws, "Selections");
                        XLSX.writeFile(wb, `${customFilename}.xlsx`);
                    }
                    
                    if(typeof hideGlobalLoader === 'function') hideGlobalLoader();
                    if (typeof showStatusModal === 'function') {
                        showStatusModal(true, `${type.toUpperCase()} data has been exported successfully!`, 'Export Success');
                    } else {
                        showNotification(`${type.toUpperCase()} export successful!`);
                    }
                } catch(err) {
                    if(typeof hideGlobalLoader === 'function') hideGlobalLoader();
                    showNotification("Export failed: " + err.message, "error");
                }
            }, 500);
        });
    }
}

// Global Custom Filename Modal Logic
let currentGlobalExportCallback = null;

window.openGlobalFilenameModal = function(type, defaultFilename, callback) {
    const modal = document.getElementById('global-filename-modal');
    if (!modal) return;
    
    // Hide global loader if present
    if (typeof hideGlobalLoader === 'function') hideGlobalLoader();
    const loader = document.getElementById('loading-overlay');
    if (loader) loader.classList.add('opacity-0', 'pointer-events-none');
    
    currentGlobalExportCallback = callback;
    
    // Update text
    const title = document.getElementById('global-filename-title');
    const ext = document.getElementById('global-filename-ext');
    const input = document.getElementById('global-export-filename-input');
    
    if (title) title.innerText = `Export ${type.toUpperCase()}`;
    if (ext) ext.innerText = '.' + (type === 'csv' ? 'csv' : (type === 'xls' ? 'xls' : 'txt'));
    if (input) {
        input.value = defaultFilename || 'export_data';
        setTimeout(() => input.focus(), 100);
    }
    
    modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
    modal.classList.add('scale-100');
};

window.closeGlobalFilenameModal = function() {
    const modal = document.getElementById('global-filename-modal');
    if (!modal) return;
    modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
    modal.classList.remove('scale-100');
};

window.confirmGlobalExportFilename = function() {
    const input = document.getElementById('global-export-filename-input');
    if (!input) return;
    const filename = input.value.trim() || 'export_data';
    
    closeGlobalFilenameModal();
    
    if (typeof currentGlobalExportCallback === 'function') {
        currentGlobalExportCallback(filename);
    }
};

// ── Auto Logout on Inactivity ────────────────────────────────
let inactivityTimer;
const INACTIVITY_LIMIT_MS = 30 * 60 * 1000; // 30 minutes

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        window.location.href = '?logout=1';
    }, INACTIVITY_LIMIT_MS);
}

// Attach event listeners to reset the timer on user interaction
['mousemove', 'mousedown', 'keypress', 'touchmove', 'scroll'].forEach(event => {
    document.addEventListener(event, resetInactivityTimer, { passive: true });
});

// Initialize the timer on load
resetInactivityTimer();

// ── Global Quick Date Filters Logic ────────────────────────────
window.tableSelectedYears = new Set();
window.tableSelectedMonths = new Set();

window.initQuickFiltersState = function() {
    window.tableSelectedYears.clear();
    window.tableSelectedMonths.clear();
    document.querySelectorAll('.active-year').forEach(btn => {
        window.tableSelectedYears.add(parseInt(btn.getAttribute('data-year')));
    });
    document.querySelectorAll('.active-month').forEach(btn => {
        window.tableSelectedMonths.add(parseInt(btn.getAttribute('data-month')));
    });
};

window.applyTableQuickFilter = function() {
    if (window.tableSelectedYears.size === 0 || window.tableSelectedMonths.size === 0) return;
    const minY = Math.min(...window.tableSelectedYears), maxY = Math.max(...window.tableSelectedYears);
    const minM = Math.min(...[...window.tableSelectedMonths].map(Number));
    const maxM = Math.max(...[...window.tableSelectedMonths].map(Number));
    const pad = v => String(v).padStart(2,'0');
    const startDate = `${minY}-${pad(minM)}-01`;
    const lastDay   = new Date(maxY, maxM, 0).getDate();
    const endDate   = `${maxY}-${pad(maxM)}-${lastDay}`;
    
    const startInput = document.querySelector('[name="start_date"]');
    const endInput = document.querySelector('[name="end_date"]');
    if (startInput) startInput.value = startDate;
    if (endInput) endInput.value = endDate;
    
    if (typeof window.refreshTable === 'function') window.refreshTable(1);
    else if (typeof window.refreshSaleTable === 'function') window.refreshSaleTable(1);
    else if (typeof window.refreshReturnTable === 'function') window.refreshReturnTable(1);
    else if (typeof window.refreshReceivingTable === 'function') window.refreshReceivingTable(1);
    else if (typeof window.refreshPulloutTable === 'function') window.refreshPulloutTable(1);
};

window.toggleTableYear = function(btn, year) {
    if (window.tableSelectedYears.has(year)) { window.tableSelectedYears.delete(year); }
    else { window.tableSelectedYears.add(year); }
    window.applyTableQuickFilter();
};

window.toggleTableMonth = function(btn, monthNum) {
    const m = parseInt(monthNum);
    if (window.tableSelectedYears.size === 0) window.tableSelectedYears.add(2026);
    if (window.tableSelectedMonths.has(m)) { window.tableSelectedMonths.delete(m); }
    else { window.tableSelectedMonths.add(m); }
    window.applyTableQuickFilter();
};

window.scrollTableQuickYears = function(direction) {
    const container = document.getElementById('table-years-container');
    // Scroll by half the container width so we don't skip items entirely
    if (container) container.scrollBy({ left: direction * (container.clientWidth / 2), behavior: 'smooth' });
};

window.scrollTableQuickMonths = function(direction) {
    const container = document.getElementById('table-months-container');
    // Scroll by roughly 2-3 months to avoid skipping visible ones
    if (container) container.scrollBy({ left: direction * 150, behavior: 'smooth' });
};

window.centerTableQuickFilters = function() {
    setTimeout(() => {
        const yc = document.getElementById('table-years-container');
        const activeYBtns = yc ? Array.from(yc.querySelectorAll('.active-year')) : [];
        if (activeYBtns.length > 0 && yc) {
            const firstBtn = activeYBtns[0];
            const lastBtn = activeYBtns[activeYBtns.length - 1];
            const centerOffset = firstBtn.offsetLeft + ((lastBtn.offsetLeft + lastBtn.clientWidth) - firstBtn.offsetLeft) / 2;
            yc.scrollTo({ left: centerOffset - yc.clientWidth/2, behavior: 'smooth' });
        }
        
        const mc = document.getElementById('table-months-container');
        const activeMBtns = mc ? Array.from(mc.querySelectorAll('.active-month')) : [];
        if (activeMBtns.length > 0 && mc) {
            const firstBtn = activeMBtns[0];
            const lastBtn = activeMBtns[activeMBtns.length - 1];
            const centerOffset = firstBtn.offsetLeft + ((lastBtn.offsetLeft + lastBtn.clientWidth) - firstBtn.offsetLeft) / 2;
            mc.scrollTo({ left: centerOffset - mc.clientWidth/2, behavior: 'smooth' });
        }
    }, 300);
};

// Call init on page load
document.addEventListener('DOMContentLoaded', () => {
    window.initQuickFiltersState();
    window.centerTableQuickFilters();
});
