<?php
/**
 * Global Footer & Scripts
 * School Reconciliation Management System
 */
?>
        </div> <!-- /content-wrapper -->
    </main> <!-- /app-main -->
</div> <!-- /app-layout -->

<!-- Global Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Global Confirmation Modal Container -->
<div id="globalModalBackdrop" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="globalModalTitle">Confirmation</h3>
            <button class="modal-close-btn" id="globalModalCloseBtn" type="button">&times;</button>
        </div>
        <div class="modal-body" id="globalModalBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary" id="globalModalCancelBtn" type="button">Cancel</button>
            <button class="btn btn-primary" id="globalModalConfirmBtn" type="button">Confirm</button>
        </div>
    </div>
</div>

<?php if (has_role(ROLE_SUPER_ADMIN)): ?>
<!-- ==========================================================================
     GLOBAL SUPER ADMIN NOTIFICATIONS MANAGEMENT MODAL
     ========================================================================== -->
<div class="modal-backdrop" id="notificationsModalBackdrop">
    <div class="modal-dialog" style="max-width: 680px; width: 95%;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                    <span>📣</span>
                    <span>Super Admin Notification Module</span>
                </h3>
                <p class="modal-subtitle">Publish important alerts, information, and updates prominently on all user and admin dashboards.</p>
            </div>
            <button type="button" class="modal-close-btn" id="btnCloseNotificationModal" onclick="closeSuperAdminNotificationsModal()">&times;</button>
        </div>

        <div class="modal-body" style="padding: 20px;">
            <!-- New Notification Form -->
            <form id="notificationPublishForm" style="margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h4 style="font-size: 13px; font-weight: 700; color: #1e3a8a; margin-bottom: 12px; text-transform: uppercase;">Publish New Announcement</h4>
                <div class="form-group">
                    <label class="form-label" for="notifTitle">Title / Subject</label>
                    <input type="text" id="notifTitle" class="form-control" placeholder="e.g. System Maintenance Notice / Urgent Instructions" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label" for="notifMessage">Announcement Message</label>
                    <textarea id="notifMessage" class="form-control" rows="3" placeholder="Enter message or important information for staff and admins..." required></textarea>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label class="form-label" for="notifType" style="margin-bottom: 0;">Type:</label>
                        <select id="notifType" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;">
                            <option value="info">ℹ️ General Info (Blue)</option>
                            <option value="warning">⚠️ Important Warning (Amber)</option>
                            <option value="urgent">🚨 Urgent Priority (Red)</option>
                            <option value="success">✅ Notice / Success (Green)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnPublishNotif">
                        <span>Publish to All Dashboards</span>
                    </button>
                </div>
            </form>

            <!-- Published Announcements List -->
            <div>
                <h4 style="font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 10px;">Existing Announcements</h4>
                <div id="notificationsManagerList" style="max-height: 280px; overflow-y: auto;">
                    <p style="text-align: center; color: var(--text-muted); font-size: 13px; padding: 20px;">Loading announcements...</p>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDoneNotificationModal" onclick="closeSuperAdminNotificationsModal()">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Base JS -->
<script src="assets/js/app.js"></script>

<?php if (isset($extra_js)): ?>
    <?php foreach ($extra_js as $js_file): ?>
        <script src="<?= e($js_file) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
