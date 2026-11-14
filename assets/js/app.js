/**
 * Core Application JavaScript
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    initLiveClock();
    initSidebarToggle();
});

/**
 * 1. Live Clock & Date Display
 */
function initLiveClock() {
    const timeDisplay = document.getElementById('liveTimeDisplay');
    const dateDisplay = document.getElementById('liveDateDisplay');

    function updateClock() {
        const now = new Date();

        // 12-hour format with AM/PM
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 becomes 12
        const formattedHours = String(hours).padStart(2, '0');

        if (timeDisplay) {
            timeDisplay.textContent = `${formattedHours}:${minutes}:${seconds} ${ampm}`;
        }

        if (dateDisplay) {
            const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
            dateDisplay.textContent = now.toLocaleDateString('en-US', options);
        }
    }

    updateClock();
    setInterval(updateClock, 1000);
}

/**
 * 2. Sidebar Mobile Toggle
 */
function initSidebarToggle() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.querySelector('.app-sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
        });

        // Close when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });
    }
}

/**
 * 3. Modern Toast Notification System
 * @param {string} type - 'success' | 'error' | 'warning' | 'info'
 * @param {string} title - Heading
 * @param {string} message - Body description
 */
function showToast(type, title, message) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    let icon = 'ℹ️';
    if (type === 'success') icon = '✓';
    else if (type === 'error') icon = '✕';
    else if (type === 'warning') icon = '⚠️';

    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            <div class="toast-title">${escapeHtml(title)}</div>
            <div class="toast-message">${escapeHtml(message)}</div>
        </div>
    `;

    container.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // Auto remove after 4.5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 4500);
}

/**
 * 4. Custom Confirmation Modal (Replaces browser confirm())
 * @param {Object} options - { title, message, confirmText, cancelText, confirmClass, onConfirm }
 */
function confirmModal({
    title = 'Confirm Action',
    message = 'Are you sure you want to proceed?',
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    confirmClass = 'btn-primary',
    onConfirm = () => {}
}) {
    let backdrop = document.getElementById('globalModalBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'globalModalBackdrop';
        backdrop.className = 'modal-backdrop';
        backdrop.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 class="modal-title" id="globalModalTitle"></h3>
                    <button class="modal-close-btn" id="globalModalCloseBtn">&times;</button>
                </div>
                <div class="modal-body" id="globalModalBody"></div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" id="globalModalCancelBtn"></button>
                    <button class="btn" id="globalModalConfirmBtn"></button>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);
    }

    const titleEl = document.getElementById('globalModalTitle');
    const bodyEl = document.getElementById('globalModalBody');
    const cancelBtn = document.getElementById('globalModalCancelBtn');
    const confirmBtn = document.getElementById('globalModalConfirmBtn');
    const closeBtn = document.getElementById('globalModalCloseBtn');

    titleEl.textContent = title;
    bodyEl.innerHTML = message;
    cancelBtn.textContent = cancelText;
    confirmBtn.textContent = confirmText;
    confirmBtn.className = `btn ${confirmClass}`;

    function closeModal() {
        backdrop.classList.remove('show');
    }

    closeBtn.onclick = closeModal;
    cancelBtn.onclick = closeModal;

    backdrop.onclick = (e) => {
        if (e.target === backdrop) closeModal();
    };

    confirmBtn.onclick = () => {
        closeModal();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    };

    backdrop.classList.add('show');
}

/**
 * HTML Escape Helper
 */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Global CSRF Token getter
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}
