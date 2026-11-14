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

<!-- Base JS -->
<script src="assets/js/app.js"></script>

<?php if (isset($extra_js)): ?>
    <?php foreach ($extra_js as $js_file): ?>
        <script src="<?= e($js_file) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
