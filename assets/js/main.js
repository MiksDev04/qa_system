/* assets/js/main.js – ByteBandits QA System */

// ─── Theme ────────────────────────────────────────────
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('qa_theme', next);
    updateThemeUI(next);
}

function updateThemeUI(theme) {
    const icon = document.getElementById('theme-icon');
    const label = document.getElementById('theme-label');
    if (!icon) return;
    if (theme === 'light') {
        icon.className = 'bi bi-sun';
        if (label) label.textContent = 'Light Mode';
    } else {
        icon.className = 'bi bi-moon-stars';
        if (label) label.textContent = 'Dark Mode';
    }
}

// Restore theme on load
(function() {
    const saved = localStorage.getItem('qa_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeUI(saved);
})();

// ─── Sidebar ──────────────────────────────────────────
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
}

// ─── Toast ────────────────────────────────────────────
function showToast(message, type = 'info') {
    let container = document.querySelector('.qa-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'qa-toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
    const colors = { success: 'var(--success)', error: 'var(--danger)', info: 'var(--info)' };

    const toast = document.createElement('div');
    toast.className = `qa-toast ${type}`;
    toast.innerHTML = `
        <i class="bi ${icons[type] || icons.info}" style="color:${colors[type]};font-size:1rem;flex-shrink:0"></i>
        <span style="flex:1">${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem;padding:0">&times;</button>
    `;
    container.appendChild(toast);
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
}

// ─── AJAX Helper ─────────────────────────────────────
function qaAjax(url, data, onSuccess, onError) {
    $.ajax({
        url: url,
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                showToast(res.message || 'Done!', 'success');
                if (onSuccess) onSuccess(res);
            } else {
                showToast(res.message || 'An error occurred.', 'error');
                if (onError) onError(res);
            }
        },
        error: function(xhr) {
            showToast('Server error. Please try again.', 'error');
            if (onError) onError(xhr);
        }
    });
}

// ─── Delete confirmation ──────────────────────────────
function confirmDelete(url, data, callback) {
    if (!confirm('Are you sure you want to delete this record? This cannot be undone.')) return;
    qaAjax(url, data, callback);
}

// ─── Pagination helper ────────────────────────────────
function buildPagination(containerId, currentPage, totalPages, onPageChange) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let html = '';
    // Prev
    html += `<button class="qa-page-btn" onclick="${onPageChange}(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>
                <i class="bi bi-chevron-left"></i>
             </button>`;

    // Pages
    let start = Math.max(1, currentPage - 2);
    let end = Math.min(totalPages, currentPage + 2);
    if (start > 1) html += `<button class="qa-page-btn" onclick="${onPageChange}(1)">1</button>${start > 2 ? '<span class="qa-page-btn" style="border:none;cursor:default">…</span>' : ''}`;
    for (let p = start; p <= end; p++) {
        html += `<button class="qa-page-btn ${p === currentPage ? 'active' : ''}" onclick="${onPageChange}(${p})">${p}</button>`;
    }
    if (end < totalPages) {
        html += `${end < totalPages - 1 ? '<span class="qa-page-btn" style="border:none;cursor:default">…</span>' : ''}
                 <button class="qa-page-btn" onclick="${onPageChange}(${totalPages})">${totalPages}</button>`;
    }

    // Next
    html += `<button class="qa-page-btn" onclick="${onPageChange}(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>
                <i class="bi bi-chevron-right"></i>
             </button>`;

    container.innerHTML = html;
}

// ─── Chart defaults ───────────────────────────────────
function getChartDefaults() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
        gridColor: dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
        textColor:  dark ? '#8891b0' : '#5a6080',
        fontFamily: "'Space Grotesk', sans-serif"
    };
}

Chart.defaults.font.family = "'Space Grotesk', sans-serif";
