<?php
/**
 * Rename peer modal.
 *
 * Rendered on demand by src/modal.php and injected into the dashboard by
 * assets/js/app.js, so the markup stays out of the initial page weight.
 *
 * @var array $lang Translations for the active language.
 * @var array $config Active server configuration.
 */
?>
<div class="modal-backdrop" id="editModalBackdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="editModalHeading">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-heading" id="editModalHeading"><?php echo t($lang, 'modal.edit.title'); ?></h2>
            <button class="close-btn" data-action="close-modal" data-modal="editModalBackdrop" aria-label="<?php echo t($lang, 'modal.close'); ?>">&times;</button>
        </div>

        <form id="editPeerForm">
            <input type="hidden" id="editPeerId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="editPeerName"><?php echo t($lang, 'modal.edit.label_name'); ?></label>
                    <input type="text" id="editPeerName" required autocomplete="off">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="editModalBackdrop"><?php echo t($lang, 'modal.edit.cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo t($lang, 'modal.edit.submit'); ?></button>
            </div>
        </form>
    </div>
</div>
