/**
 * Main JavaScript
 * Sistem Rekomendasi Jurusan & PTN
 */

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initThemeToggle();
    initAlerts();
    initSearch();
    initFileUpload();
    initTables();
});

/**
 * Sidebar Toggle
 */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
    }
    
    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 992) {
            if (!sidebar.contains(e.target) && !mobileToggle?.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });
}

/**
 * Theme Toggle (Dark/Light Mode)
 */
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    const icon = themeToggle?.querySelector('i');
    
    // Check saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(icon, savedTheme);
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(icon, newTheme);
        });
    }
}

function updateThemeIcon(icon, theme) {
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

/**
 * Alert Dismissal
 */
function initAlerts() {
    document.querySelectorAll('.alert-close').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        });
    });
}

/**
 * Global Search
 */
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();
            
            timeout = setTimeout(() => {
                if (query.length >= 2) {
                    // Redirect to siswa page with search query
                    window.location.href = `siswa.php?search=${encodeURIComponent(query)}`;
                }
            }, 500);
        });
    }
}

/**
 * File Upload with Drag & Drop
 */
function initFileUpload() {
    const fileUpload = document.querySelector('.file-upload');
    const fileInput = fileUpload?.querySelector('input[type="file"]');
    
    if (!fileUpload || !fileInput) return;
    
    // Drag events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileUpload.addEventListener(eventName, preventDefaults);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        fileUpload.addEventListener(eventName, () => fileUpload.classList.add('dragover'));
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        fileUpload.addEventListener(eventName, () => fileUpload.classList.remove('dragover'));
    });
    
    // Handle drop
    fileUpload.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
            handleFileSelect(files[0]);
        }
    });
    
    // Handle file select
    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFileSelect(this.files[0]);
        }
    });
}

function handleFileSelect(file) {
    const fileUpload = document.querySelector('.file-upload');
    const allowedTypes = ['xlsx', 'xls', 'csv'];
    const ext = file.name.split('.').pop().toLowerCase();
    
    if (!allowedTypes.includes(ext)) {
        showAlert('Format file tidak didukung. Gunakan .xlsx, .xls, atau .csv', 'error');
        return;
    }
    
    // Update UI
    fileUpload.querySelector('h3').textContent = file.name;
    fileUpload.querySelector('p').textContent = formatFileSize(file.size);
    
    // Auto submit if form exists
    const form = document.getElementById('importForm');
    if (form && form.dataset.autoSubmit === 'true') {
        form.submit();
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Table Sorting
 */
function initTables() {
    document.querySelectorAll('.table th[data-sort]').forEach(function(th) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const column = this.dataset.sort;
            const type = this.dataset.type || 'string';
            const isAsc = this.classList.contains('asc');
            
            // Remove sorting classes from other columns
            table.querySelectorAll('th').forEach(t => t.classList.remove('asc', 'desc'));
            
            // Sort rows
            rows.sort((a, b) => {
                let aVal = a.querySelector(`td[data-${column}]`)?.dataset[column] || 
                           a.cells[Array.from(th.parentNode.children).indexOf(th)]?.textContent.trim();
                let bVal = b.querySelector(`td[data-${column}]`)?.dataset[column] || 
                           b.cells[Array.from(th.parentNode.children).indexOf(th)]?.textContent.trim();
                
                if (type === 'number') {
                    aVal = parseFloat(aVal) || 0;
                    bVal = parseFloat(bVal) || 0;
                }
                
                if (aVal < bVal) return isAsc ? 1 : -1;
                if (aVal > bVal) return isAsc ? -1 : 1;
                return 0;
            });
            
            // Update class
            this.classList.add(isAsc ? 'desc' : 'asc');
            
            // Reorder rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}

/**
 * Show Alert
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="alert-close">&times;</button>
    `;
    
    const contentArea = document.querySelector('.content-area');
    contentArea.insertBefore(alertDiv, contentArea.firstChild);
    
    // Auto dismiss
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateY(-10px)';
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
    
    // Manual dismiss
    alertDiv.querySelector('.alert-close').addEventListener('click', function() {
        alertDiv.remove();
    });
}

/**
 * Confirm Dialog
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * Format Number
 */
function formatNumber(num, decimals = 2) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

/**
 * Animate Counter
 */
function animateCounter(element, target, duration = 1000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 16);
}

// Initialize counter animations
document.querySelectorAll('[data-counter]').forEach(el => {
    const target = parseInt(el.dataset.counter);
    animateCounter(el, target);
});
