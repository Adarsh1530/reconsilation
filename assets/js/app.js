/**
 * Core Application JavaScript
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    initLiveClock();
    initSidebarToggle();
    initSuperAdminNotificationsModal();
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
 * Global helper to close any modal by backdrop ID
 */
window.closeModalById = function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('show');
        if (el.style.display && el.style.display !== 'none') {
            el.style.display = 'none';
        }
    }
};

/**
 * Global CSRF Token getter
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Super Admin Announcements / Notifications Global Management
 */
window.openSuperAdminNotificationsModal = function() {
    const modalBackdrop = document.getElementById('notificationsModalBackdrop');
    if (modalBackdrop) {
        modalBackdrop.classList.add('show');
        loadSuperAdminNotificationsList();
    }
};

window.closeSuperAdminNotificationsModal = function() {
    const modalBackdrop = document.getElementById('notificationsModalBackdrop');
    if (modalBackdrop) {
        modalBackdrop.classList.remove('show');
    }
};

function initSuperAdminNotificationsModal() {
    const modalBackdrop = document.getElementById('notificationsModalBackdrop');
    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) {
                window.closeSuperAdminNotificationsModal();
            }
        });
    }

    // Auto-load announcements list if present on the page (e.g. announcements.php)
    if (document.getElementById('notificationsManagerList')) {
        loadSuperAdminNotificationsList();
    }

    const publishForm = document.getElementById('notificationPublishForm');
    if (publishForm) {
        publishForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const title = document.getElementById('notifTitle')?.value.trim();
            const message = document.getElementById('notifMessage')?.value.trim();
            const type = document.getElementById('notifType')?.value || 'info';

            if (!title || !message) {
                showToast('warning', 'Validation Error', 'Please provide both title and announcement message.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('title', title);
            formData.append('message', message);
            formData.append('type', type);
            formData.append('csrf_token', getCsrfToken());

            fetch('api/notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Announcement Published', 'Notification is now live for all users!');
                    publishForm.reset();
                    loadSuperAdminNotificationsList();
                    if (typeof window.fetchActiveNotifications === 'function') {
                        window.fetchActiveNotifications();
                    }
                } else {
                    showToast('error', 'Publish Failed', data.message || 'Could not publish announcement.');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to reach notifications API.');
            });
        });
    }
}

function loadSuperAdminNotificationsList() {
    const listContainer = document.getElementById('notificationsManagerList');
    if (!listContainer) return;

    fetch('api/notifications.php?action=get_all')
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                if (res.data.length === 0) {
                    listContainer.innerHTML = '<p style="text-align: center; color: var(--text-muted); font-size: 13px; padding: 20px;">No announcements published yet.</p>';
                    return;
                }

                let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
                res.data.forEach(n => {
                    const isActive = n.is_active == 1;

                    html += `
                        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                            <div style="flex-grow: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <strong style="font-size: 13.5px; color: var(--text-main);">${escapeHtml(n.title)}</strong>
                                    <span style="font-size: 10.5px; padding: 2px 6px; border-radius: 4px; font-weight: 700; background: ${isActive ? '#dcfce7; color: #15803d;' : '#f1f5f9; color: #64748b;'}">
                                        ${isActive ? 'Active (Live)' : 'Deactivated'}
                                    </span>
                                    <span style="font-size: 10.5px; padding: 2px 6px; border-radius: 4px; background: #e0e7ff; color: #4338ca; font-weight: 600;">
                                        ${escapeHtml(n.type)}
                                    </span>
                                </div>
                                <div style="font-size: 12.5px; color: var(--text-muted); max-height: 40px; overflow: hidden; text-overflow: ellipsis;">
                                    ${escapeHtml(n.message)}
                                </div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                                    ${escapeHtml(n.created_at)} by ${escapeHtml(n.author_name)}
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                <button type="button" class="btn btn-sm ${isActive ? 'btn-outline-secondary' : 'btn-outline-success'}" onclick="toggleNotificationStatus(${n.id}, ${isActive ? 0 : 1})">
                                    ${isActive ? 'Deactivate' : 'Activate'}
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteNotificationPrompt(${n.id})">
                                    Delete
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                listContainer.innerHTML = html;
            }
        })
        .catch(err => {
            console.error(err);
            listContainer.innerHTML = '<p style="color: #dc2626; font-size: 12px; padding: 10px;">Failed to load announcements.</p>';
        });
}
window.loadSuperAdminNotificationsList = loadSuperAdminNotificationsList;

function toggleNotificationStatus(id, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('id', id);
    formData.append('is_active', newStatus);
    formData.append('csrf_token', getCsrfToken());

    fetch('api/notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('info', 'Status Updated', 'Notification status updated.');
            loadSuperAdminNotificationsList();
            if (typeof window.fetchActiveNotifications === 'function') {
                window.fetchActiveNotifications();
            }
        } else {
            showToast('error', 'Error', data.message);
        }
    });
}
window.toggleNotificationStatus = toggleNotificationStatus;

function deleteNotificationPrompt(id) {
    confirmModal({
        title: '⚠️ Confirm Announcement Deletion',
        message: `
            <div style="font-size: 14px; line-height: 1.5;">
                <p style="margin-bottom: 10px; color: var(--text-main);">
                    Are you sure you want to permanently delete this announcement?
                </p>
                <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 8px; padding: 12px 14px; color: #991b1b; font-size: 13px;">
                    <strong>⚠️ Notice:</strong> It will be permanently removed from all staff and administrator dashboards immediately.
                </div>
            </div>
        `,
        confirmText: 'Yes, Delete Announcement',
        cancelText: 'Cancel',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('csrf_token', getCsrfToken());

            fetch('api/notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Deleted', 'Notification removed.');
                    loadSuperAdminNotificationsList();
                    if (typeof window.fetchActiveNotifications === 'function') {
                        window.fetchActiveNotifications();
                    }
                } else {
                    showToast('error', 'Error', data.message);
                }
            });
        }
    });
}
window.deleteNotificationPrompt = deleteNotificationPrompt;
