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
    const saved = localStorage.getItem('qa_theme');
    const theme = (saved === 'light' || saved === 'dark') ? saved : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    if (saved !== theme) {
        localStorage.setItem('qa_theme', theme);
    }
    updateThemeUI(theme);
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
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 6000);
}

// ─── AJAX Helper ─────────────────────────────────────
function qaAjax(url, data, onSuccess, onError) {
    const isFormData = Object.prototype.toString.call(data) === '[object FormData]';

    const ajaxOptions = {
        url: url,
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(res) {
            console.log("AJAX success response:", JSON.stringify(res));
            if (res && res.status === 'success') {
                if (onSuccess) onSuccess(res);
            } else {
                console.log("API returned error, calling onError with:", res);
                if (onError) onError(res);
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX network error - Status:", status, "Error:", error);
            console.error("Response text:", xhr.responseText);
            if (onError) {
                let errorObj = { message: error || 'Network error' };
                try {
                    if (xhr.responseText) {
                        errorObj = JSON.parse(xhr.responseText);
                    }
                } catch (e) {
                    console.error("Failed to parse error response:", xhr.responseText);
                }
                onError(errorObj);
            }
        }
    };

    if (isFormData) {
        ajaxOptions.processData = false;
        ajaxOptions.contentType = false;
    }

    $.ajax(ajaxOptions);
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

    let html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';

    // Prev
    html += `
        <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
            <button type="button" class="page-link" onclick="${onPageChange}(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''} aria-label="Previous">
                <i class="bi bi-chevron-left"></i>
            </button>
        </li>
    `;

    // Pages
    let start = Math.max(1, currentPage - 2);
    let end = Math.min(totalPages, currentPage + 2);

    if (start > 1) {
        html += `<li class="page-item"><button type="button" class="page-link" onclick="${onPageChange}(1)">1</button></li>`;
        if (start > 2) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for (let p = start; p <= end; p++) {
        html += `
            <li class="page-item ${p === currentPage ? 'active' : ''}">
                <button type="button" class="page-link" onclick="${onPageChange}(${p})">${p}</button>
            </li>
        `;
    }

    if (end < totalPages) {
        if (end < totalPages - 1) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        html += `<li class="page-item"><button type="button" class="page-link" onclick="${onPageChange}(${totalPages})">${totalPages}</button></li>`;
    }

    // Next
    html += `
        <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
            <button type="button" class="page-link" onclick="${onPageChange}(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''} aria-label="Next">
                <i class="bi bi-chevron-right"></i>
            </button>
        </li>
    `;

    html += '</ul></nav>';
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
