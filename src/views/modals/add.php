<?php
/**
 * Add peer modal (name field, generated configuration, QR code).
 *
 * Rendered on demand by src/modal.php and injected into the dashboard by
 * assets/js/app.js, so the markup stays out of the initial page weight.
 *
 * @var array $lang Translations for the active language.
 * @var array $config Active server configuration.
 */
?>
<div class="modal-backdrop" id="addModalBackdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="addModalHeading">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-heading" id="addModalHeading"><?php echo t($lang, 'modal.add.title'); ?></h2>
            <button class="close-btn" data-action="close-modal" data-modal="addModalBackdrop" aria-label="<?php echo t($lang, 'modal.close'); ?>">&times;</button>
        </div>

        <form id="addPeerForm">
            <div class="modal-body">
                <div id="modalFormContent">
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
                        <?php echo t($lang, 'modal.add.description'); ?>
                    </p>
                    <div class="form-group">
                        <label for="peerName"><?php echo t($lang, 'modal.add.label_name'); ?></label>
                        <input type="text" id="peerName" placeholder="<?php echo t($lang, 'modal.add.placeholder_name'); ?>" required autocomplete="off">
                    </div>
                </div>

                <!-- Result display after creation -->
                <div id="modalResultContent" style="display: none;">
                    <div class="success-card">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        <div class="success-card-content">
                            <h4><?php echo t($lang, 'modal.add.success_title'); ?></h4>
                            <p><?php echo t($lang, 'modal.add.success_desc'); ?></p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php echo t($lang, 'modal.add.label_ip'); ?></label>
                        <div class="peer-ip-badge" id="resIp" style="font-size: 1rem; padding: 0.35rem 0.75rem;">-</div>
                    </div>

                        <div class="tab-buttons">
                            <button type="button" class="tab-btn active" data-action="add-tab" data-tab="conf"><?php echo t($lang, 'modal.add.tab_conf'); ?></button>
                            <button type="button" class="tab-btn" data-action="add-tab" data-tab="script"><?php echo t($lang, 'modal.add.tab_script'); ?></button>
                            <button type="button" class="tab-btn" id="addQrTab" data-action="add-tab" data-tab="qrcode" disabled><?php echo t($lang, 'modal.add.tab_qrcode'); ?></button>
                        </div>

                    <div id="tab-conf" class="tab-content active">
                        <div class="code-box">
                            <button type="button" class="copy-btn-code" data-action="copy-code" data-target="code-conf-text" title="<?php echo t($lang, 'modal.add.copy_title'); ?>" aria-label="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                            </button>
                            <pre id="code-conf-text"></pre>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" id="btnDownloadConf" style="width: 100%;">
                            <?php echo t($lang, 'modal.add.download_conf'); ?>
                        </button>
                    </div>

                    <div id="tab-script" class="tab-content">
                        <div class="code-box">
                            <button type="button" class="copy-btn-code" data-action="copy-code" data-target="code-script-text" title="<?php echo t($lang, 'modal.add.copy_title'); ?>" aria-label="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                            </button>
                            <pre id="code-script-text"></pre>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" id="btnDownloadScript" style="width: 100%;">
                            <?php echo t($lang, 'modal.add.download_script'); ?>
                        </button>
                    </div>

                    <div id="tab-qrcode" class="tab-content">
                        <div class="qr-code-panel">
                            <div id="addQrCode" class="qr-code-frame" role="status"></div>
                            <p><?php echo t($lang, 'modal.qrcode.description'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" id="modalFooterActions">
                <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="addModalBackdrop"><?php echo t($lang, 'modal.add.cancel'); ?></button>
                <button type="submit" class="btn btn-primary" id="btnSubmitAdd" data-orig-text="<?php echo t($lang, 'modal.add.submit'); ?>"><?php echo t($lang, 'modal.add.submit'); ?></button>
            </div>
        </form>
    </div>
</div>
