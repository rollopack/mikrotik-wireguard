<?php
/**
 * Delete peer confirmation modal.
 *
 * Rendered on demand by src/modal.php and injected into the dashboard by
 * assets/js/app.js, so the markup stays out of the initial page weight.
 *
 * @var array $lang Translations for the active language.
 * @var array $config Active server configuration.
 */
?>
<div class="modal-backdrop" id="deleteModalBackdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="deleteModalHeading">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h2 class="modal-heading" id="deleteModalHeading" style="color: var(--danger);"><?php echo t($lang, 'modal.delete.title'); ?></h2>
            <button class="close-btn" data-action="close-modal" data-modal="deleteModalBackdrop" aria-label="<?php echo t($lang, 'modal.close'); ?>">&times;</button>
        </div>

        <div class="modal-body">
            <p><?php printf(t($lang, 'modal.delete.confirm'), '<strong id="deletePeerNameText" style="color: var(--danger);"></strong>'); ?></p>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">
                <?php echo t($lang, 'modal.delete.description'); ?>
            </p>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="deleteModalBackdrop"><?php echo t($lang, 'modal.delete.cancel'); ?></button>
            <button type="button" class="btn btn-danger" id="btnConfirmDelete" data-action="confirm-delete"><?php echo t($lang, 'modal.delete.submit'); ?></button>
        </div>
    </div>
</div>
