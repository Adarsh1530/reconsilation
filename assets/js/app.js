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
                    <button class="modal-close-btn" id="globalModalCloseBtn" type="button">&times;</button>
                </div>
                <div class="modal-body" id="globalModalBody"></div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" id="globalModalCancelBtn" type="button"></button>
                    <button class="btn" id="globalModalConfirmBtn" type="button"></button>
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

    titleEl.innerHTML = title;
    bodyEl.innerHTML = message;
    cancelBtn.textContent = cancelText;
    confirmBtn.textContent = confirmText;
    confirmBtn.className = `btn ${confirmClass}`;

    // Danger / delete styling for the modal header
    const isDanger = confirmClass.includes('danger') || 
                     title.toLowerCase().includes('delete') || 
                     confirmText.toLowerCase().includes('delete') ||
                     title.toLowerCase().includes('reset');
    if (isDanger) {
        titleEl.style.color = '#dc2626';
    } else {
        titleEl.style.color = 'var(--text-main)';
    }

    function closeModal() {
        backdrop.classList.remove('show');
        document.removeEventListener('keydown', handleEscape);
    }

    function handleEscape(e) {
        if (e.key === 'Escape') closeModal();
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

    document.addEventListener('keydown', handleEscape);
    backdrop.classList.add('show');
}

/**
 * Global Interceptor for Deletion / Destructive Actions Across Entire Website
 * Guarantees any button or link marked with data-confirm or generic delete action
 * is intercepted with a prominent confirmation popup.
 */
document.addEventListener('click', function(e) {
    const target = e.target.closest('button, a, [role="button"]');
    if (!target) return;

    if (target.hasAttribute('data-confirm')) {
        e.preventDefault();
        e.stopPropagation();
        const msg = target.getAttribute('data-confirm') || 'Are you sure you want to delete this record? This action cannot be undone.';
        const title = target.getAttribute('data-title') || 'Confirm Deletion';
        confirmModal({
            title: '⚠️ ' + title,
            message: `
                <div style="font-size: 14px; line-height: 1.5;">
                    <p style="margin-bottom: 10px; color: var(--text-main);">${escapeHtml(msg)}</p>
                    <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 8px; padding: 12px 14px; color: #991b1b; font-size: 13px;">
                        <strong>⚠️ Warning:</strong> Deleting this item is permanent and cannot be undone.
                    </div>
                </div>
            `,
            confirmText: 'Yes, Delete',
            cancelText: 'Cancel',
            confirmClass: 'btn-danger',
            onConfirm: () => {
                if (target.tagName === 'A' && target.href && target.href !== '#' && !target.href.startsWith('javascript:')) {
                    window.location.href = target.href;
                } else if (target.type === 'submit' && target.form) {
                    target.form.submit();
                } else if (typeof target.dataset.callback === 'string' && typeof window[target.dataset.callback] === 'function') {
                    window[target.dataset.callback](target);
                }
            }
        });
    }
}, true);

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
