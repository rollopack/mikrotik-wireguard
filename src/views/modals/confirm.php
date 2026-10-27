<?php
/**
 * Generic confirmation modal (enable/disable, key regeneration).
 *
 * Rendered on demand by src/modal.php and injected into the dashboard by
 * assets/js/app.js, so the markup stays out of the initial page weight.
 *
 * @var array $lang Translations for the active language.
 * @var array $config Active server configuration.
 */
?>
<div class="modal-backdrop" id="confirmModalBackdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="confirmModalHeading">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h2 class="modal-heading" id="confirmModalHeading" style="color: var(--warning);"><?php echo t($lang, 'modal.confirm.title'); ?></h2>
            <button class="close-btn" data-action="close-modal" data-modal="confirmModalBackdrop" aria-label="<?php echo t($lang, 'modal.close'); ?>">&times;</button>
        </div>

        <div class="modal-body">
            <p id="confirmModalText"></p>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="confirmModalBackdrop"><?php echo t($lang, 'modal.confirm.cancel'); ?></button>
            <button type="button" class="btn btn-warning" id="btnConfirmAction" data-action="confirm-action"><?php echo t($lang, 'modal.confirm.submit'); ?></button>
        </div>
    </div>
</div>
