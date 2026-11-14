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
                        <h4 style="font-size: 13px; font-weight: 700; color: #2563eb; margin-bottom: 10px;">☀️ AM Session Schedule</h4>
                        <div class="form-group">
                            <label class="form-label">AM Start Time</label>
                            <input type="time" name="settings[am_start_time]" class="form-control" value="<?= e($am_start) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">AM End Time</label>
                            <input type="time" name="settings[am_end_time]" class="form-control" value="<?= e($am_end) ?>" required>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size: 13px; font-weight: 700; color: #7c3aed; margin-bottom: 10px;">🌙 PM Session Schedule</h4>
                        <div class="form-group">
                            <label class="form-label">PM Start Time</label>
                            <input type="time" name="settings[pm_start_time]" class="form-control" value="<?= e($pm_start) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">PM End Time</label>
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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
