<?php
/**
 * Super Admin - System Settings & Rules
 * School Reconciliation Management System
 */

$page_title = 'System Settings';

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_role(ROLE_SUPER_ADMIN);

$am_start = get_setting('am_start_time', '07:00');
$am_end = get_setting('am_end_time', '13:00');
$pm_start = get_setting('pm_start_time', '14:00');
$pm_end = get_setting('pm_end_time', '19:00');
$warning_len = get_setting('issue_ref_warning_len', '10');
$session_timeout = get_setting('session_timeout_minutes', '60');
$app_title = get_setting('app_title', APP_NAME);

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main);">System Rules & Configuration</h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Configure AM/PM operational schedules, validation thresholds, and security parameters.
        </p>
    </div>
</div>

<div style="max-width: 800px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Operational Settings</h2>
            <span style="font-size: 12px; color: var(--text-muted);">Super Admin Configuration</span>
        </div>

        <form id="settingsForm">
            <div class="card-body">
                <h3 style="font-size: 14px; font-weight: 700; color: #1e3a8a; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.05em;">
                    ⏰ 1. Reconciliation Sessions Schedule
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f8fafc; padding: 18px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 24px;">
                    <div>
                        <h4 style="font-size: 13px; font-weight: 700; color: #2563eb; margin-bottom: 10px;">☀️ Morning Session Schedule</h4>
                        <div class="form-group">
                            <label class="form-label">Morning Start Time</label>
                            <input type="time" name="settings[am_start_time]" class="form-control" value="<?= e($am_start) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Morning End Time</label>
                            <input type="time" name="settings[am_end_time]" class="form-control" value="<?= e($am_end) ?>" required>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size: 13px; font-weight: 700; color: #7c3aed; margin-bottom: 10px;">🌙 Evening Session Schedule</h4>
                        <div class="form-group">
                            <label class="form-label">Evening Start Time</label>
                            <input type="time" name="settings[pm_start_time]" class="form-control" value="<?= e($pm_start) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Evening End Time</label>
                            <input type="time" name="settings[pm_end_time]" class="form-control" value="<?= e($pm_end) ?>" required>
                        </div>
                    </div>
                </div>

                <h3 style="font-size: 14px; font-weight: 700; color: #1e3a8a; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.05em;">
                    ⚠️ 2. Validation & Security Rules
                </h3>

                <div class="form-group">
                    <label class="form-label" for="settingWarningLen">
                        Issue Reference Warning Threshold (Characters)
                    </label>
                    <input type="number" id="settingWarningLen" name="settings[issue_ref_warning_len]" class="form-control" value="<?= e($warning_len) ?>" min="1" max="100" required>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        When staff enters an issue reference code exceeding this character count (default 10), a warning badge is displayed. Does not block saving.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="settingTimeout">
                        Session Inactivity Timeout (Minutes)
                    </label>
                    <input type="number" id="settingTimeout" name="settings[session_timeout_minutes]" class="form-control" value="<?= e($session_timeout) ?>" min="5" max="1440" required>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        Inactive staff sessions will automatically expire after this period.
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="settingAppTitle">
                        Portal Header Title
                    </label>
                    <input type="text" id="settingAppTitle" name="settings[app_title]" class="form-control" value="<?= e($app_title) ?>" required>
                </div>
            </div>

            <div class="card-header" style="justify-content: flex-end; background: #f8fafc;">
                <button type="submit" class="btn btn-primary">
                    💾 Save System Configuration
                </button>
            </div>
        </form>
    </div>

    <!-- ==========================================================================
         3. DATABASE MANAGEMENT (BACKUP DATA & CLEAR DATA)
         ========================================================================== -->
    <div class="card" style="margin-top: 24px;">
        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                    <span>🗄️</span> Database Management
                </h2>
                <span style="font-size: 12px; color: var(--text-muted);">Backup, download, restore, or clear system records</span>
            </div>
            <span class="status-badge" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-weight: 700; padding: 4px 10px; border-radius: 6px;">Super Admin Only</span>
        </div>

        <div class="card-body">
            <!-- 1. BACKUP DATA SECTION -->
            <div style="background: #f8fafc; border: 1.5px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 20px;">
                <h3 style="font-size: 15px; font-weight: 800; color: #1e3a8a; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <span>💾</span> 1. Backup Data
                </h3>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">
                    Create a complete backup of the entire database, including all school records, reconciliation data, completed tasks, pending tasks, user activity, and all related records.
                </p>

                <!-- Download Complete Backup -->
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 14px 16px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 16px;">
                    <div>
                        <div style="font-weight: 700; font-size: 13.5px; color: var(--text-main);">Download Complete Database Backup</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Exports complete database schema and all table records into a downloadable SQL file.</div>
                    </div>
                    <a href="api/database.php?action=download_backup" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; text-decoration: none;">
                        <span>📥</span> Download Database Backup (.sql)
                    </a>
                </div>

                <!-- Restore Backup File -->
                <div style="padding: 14px 16px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                    <div style="font-weight: 700; font-size: 13.5px; color: var(--text-main); margin-bottom: 4px;">Import / Restore Backup</div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                        Restore the complete database from an existing .sql backup file. Before importing or restoring a backup, password confirmation is required (<strong>reconsilation</strong>).
                    </div>
                    
                    <form id="restoreBackupForm" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;" onsubmit="handleRestoreSubmit(event)">
                        <input type="file" id="backupFileInput" name="backup_file" accept=".sql" class="form-control" style="max-width: 380px; font-size: 13px;" required>
                        <button type="submit" class="btn btn-warning" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                            <span>↺</span> Restore Database Backup
                        </button>
                    </form>
                </div>
            </div>

            <!-- 2. CLEAR DATA SECTION -->
            <div style="background: #fff5f5; border: 1.5px solid #fecaca; border-radius: var(--radius-lg); padding: 20px;">
                <h3 style="font-size: 15px; font-weight: 800; color: #991b1b; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <span>🗑️</span> 2. Clear Data
                </h3>
                <p style="font-size: 13px; color: #7f1d1d; line-height: 1.5; margin-bottom: 16px;">
                    Allow Super Admin to clear database records when required. Shows a clear warning and requires password confirmation (<strong>reconsilation</strong>) before permanently deleting data.
                </p>

                <div style="background: #ffffff; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 16px; margin-bottom: 14px;">
                    <div style="font-weight: 700; font-size: 13.5px; color: #991b1b; margin-bottom: 4px;">Clear Reconciliation & Task Records</div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">
                        Permanently deletes all completed tasks, pending tasks, reconciliation records, task history, and urgent case records. Schools and user accounts will remain intact.
                    </div>
                    <button type="button" class="btn btn-danger" onclick="promptClearData('recon_only')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        <span>⚠️</span> Clear Reconciliation Tasks Data
                    </button>
                </div>

                <div style="background: #ffffff; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 16px;">
                    <div style="font-weight: 700; font-size: 13.5px; color: #991b1b; margin-bottom: 4px;">Clear All Operational Records (Full Reset)</div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">
                        Permanently deletes all reconciliation records, task history, urgent cases, announcements, and activity logs. Preserves Super Admin account and system settings.
                    </div>
                    <button type="button" class="btn btn-danger" onclick="promptClearData('full')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; background: #991b1b; border-color: #7f1d1d;">
                        <span>🔥</span> Clear All Operational Records
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('settingsForm');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            confirmModal({
                title: 'Save System Settings',
                message: 'Are you sure you want to apply these operational configuration changes?',
                confirmText: 'Save Settings',
                confirmClass: 'btn-primary',
                onConfirm: () => {
                    const formData = new FormData(form);
                    formData.append('csrf_token', getCsrfToken());

                    fetch('api/settings.php?action=save', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            showToast('success', 'Configuration Saved', res.message);
                        } else {
                            showToast('error', 'Error', res.message);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast('error', 'Network Error', 'Failed to reach server.');
                    });
                }
            });
        });
    }
});

/**
 * Handle Restore Database Backup Submit with Password Confirmation
 */
function handleRestoreSubmit(e) {
    e.preventDefault();
    const fileInput = document.getElementById('backupFileInput');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showToast('warning', 'Missing File', 'Please select a .sql database backup file to restore.');
        return;
    }

    const file = fileInput.files[0];

    confirmModal({
        title: '⚠️ Confirm Database Restore',
        message: `
            <div style="font-size: 13.5px; line-height: 1.5;">
                <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 12px; color: #92400e; margin-bottom: 14px;">
                    <strong>⚠️ Caution:</strong> Restoring will replace existing database records with the contents of <strong>${escapeHtml(file.name)}</strong>. Please ensure you have downloaded a current backup before proceeding.
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
                        Enter Password to Confirm Restore:
                    </label>
                    <input type="password" id="modalRestorePasswordInput" class="form-control" placeholder="Enter confirmation password" style="width: 100%;" autocomplete="off">
                </div>
                <div style="font-size: 12px; color: var(--text-muted);">
                    Required Password: <code>reconsilation</code>
                </div>
            </div>
        `,
        confirmText: '↺ Restore Database Now',
        confirmClass: 'btn-warning',
        onConfirm: () => {
            const pw = document.getElementById('modalRestorePasswordInput')?.value.trim();
            if (pw !== 'reconsilation') {
                showToast('error', 'Authentication Failed', 'Incorrect password! Required: reconsilation');
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('password', pw);
            formData.append('backup_file', file);

            showToast('info', 'Restoring Database', 'Restoring database tables from backup file...');

            fetch('api/database.php?action=restore', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'Database Restored', res.message);
                    fileInput.value = '';
                    setTimeout(() => { location.reload(); }, 1500);
                } else {
                    showToast('error', 'Restore Failed', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        }
    });
}

/**
 * Handle Clear Database Data with Warning & Password Confirmation
 */
function promptClearData(scope) {
    const isFull = scope === 'full';
    const title = isFull ? '🔥 Confirm Complete Operational Data Reset' : '⚠️ Confirm Clear Reconciliation Records';
    const scopeDesc = isFull
        ? 'All reconciliation records, completed tasks, pending tasks, urgent cases, announcements, and activity logs will be permanently deleted. (Super Admin user and system settings will be preserved).'
        : 'All completed tasks, pending tasks, urgent cases, and reconciliation audit history will be permanently deleted. Schools and user accounts will remain intact.';

    confirmModal({
        title: title,
        message: `
            <div style="font-size: 13.5px; line-height: 1.5;">
                <div style="background: #fef2f2; border: 2px solid #f87171; border-radius: 8px; padding: 14px; color: #991b1b; margin-bottom: 14px;">
                    <div style="font-weight: 800; font-size: 14px; margin-bottom: 4px;">🚨 DANGER: PERMANENT DATA DELETION</div>
                    <div>${scopeDesc}</div>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
                        Enter Password to Confirm Permanent Deletion:
                    </label>
                    <input type="password" id="modalClearPasswordInput" class="form-control" placeholder="Enter password (reconsilation)" style="width: 100%;" autocomplete="off">
                </div>
                <div style="font-size: 12px; color: #dc2626; font-weight: 600;">
                    Required Password: <code>reconsilation</code>
                </div>
            </div>
        `,
        confirmText: '⚠️ Permanently Delete Data',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const pw = document.getElementById('modalClearPasswordInput')?.value.trim();
            if (pw !== 'reconsilation') {
                showToast('error', 'Authentication Failed', 'Incorrect password! Required: reconsilation');
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('password', pw);
            formData.append('scope', scope);

            showToast('info', 'Clearing Data', 'Permanently clearing database records...');

            fetch('api/database.php?action=clear', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'Data Cleared', res.message);
                    setTimeout(() => { location.reload(); }, 1500);
                } else {
                    showToast('error', 'Clear Failed', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
