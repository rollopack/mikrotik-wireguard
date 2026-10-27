<?php
/**
 * Export client configuration modal with key regeneration.
 *
 * Rendered on demand by src/modal.php and injected into the dashboard by
 * assets/js/app.js, so the markup stays out of the initial page weight.
 *
 * @var array $lang Translations for the active language.
 * @var array $config Active server configuration.
 */
?>
<div class="modal-backdrop" id="exportModalBackdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="exportModalHeading">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-heading" id="exportModalHeading"><?php echo t($lang, 'modal.export.title'); ?></h2>
            <button class="close-btn" data-action="close-modal" data-modal="exportModalBackdrop" aria-label="<?php echo t($lang, 'modal.close'); ?>">&times;</button>
        </div>

        <div class="modal-body">
                <div class="form-row-inline">
                <div class="form-group" style="flex:1;">
                    <label><?php echo t($lang, 'modal.export.label_ip'); ?></label>
                    <div class="peer-ip-badge" id="exportIp" style="font-size: 1rem; padding: 0.35rem 0.75rem;">-</div>
                </div>
                <div class="form-group" style="flex:1;">
                    <label><?php echo t($lang, 'modal.export.label_port'); ?></label>
                    <div class="peer-ip-badge" id="exportPort" style="font-size: 1rem; padding: 0.35rem 0.75rem;">-</div>
                </div>
            </div>

            <div style="text-align:center; margin-bottom:1.25rem;">
                <button class="btn btn-warning btn-sm" id="btnRegenerateKey" data-action="regenerate-key" style="width:100%;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    <?php echo t($lang, 'modal.export.regenerate_btn'); ?>
                </button>
                <p style="color:var(--text-muted); font-size:0.8rem; margin-top:0.5rem;">
                    <?php echo t($lang, 'modal.export.regenerate_desc'); ?>
                </p>
            </div>

            <div id="exportConfigSection" style="display:none;">
                <div class="tab-buttons">
                    <button type="button" class="tab-btn" id="tabBtnExportConf" data-action="export-tab" data-tab="conf"><?php echo t($lang, 'modal.export.tab_conf'); ?></button>
                    <button type="button" class="tab-btn" id="tabBtnExportScript" data-action="export-tab" data-tab="script"><?php echo t($lang, 'modal.export.tab_script'); ?></button>
                    <button type="button" class="tab-btn" id="tabBtnExportQr" data-action="export-tab" data-tab="qrcode" disabled><?php echo t($lang, 'modal.export.tab_qrcode'); ?></button>
                </div>

                <div id="tab-export-conf" class="tab-content">
                    <div class="code-box">
                        <button type="button" class="copy-btn-code" data-action="copy-code" data-target="code-export-conf-text" title="<?php echo t($lang, 'modal.add.copy_title'); ?>" aria-label="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                        </button>
                        <pre id="code-export-conf-text"></pre>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnDownloadExportConf" style="width: 100%;">
                        <?php echo t($lang, 'modal.export.download_conf'); ?>
                    </button>
                </div>

                <div id="tab-export-script" class="tab-content">
                    <div class="code-box">
                        <button type="button" class="copy-btn-code" data-action="copy-code" data-target="code-export-script-text" title="<?php echo t($lang, 'modal.add.copy_title'); ?>" aria-label="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                        </button>
                        <pre id="code-export-script-text"></pre>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnDownloadExportScript" style="width: 100%;">
                        <?php echo t($lang, 'modal.export.download_script'); ?>
                    </button>
                </div>

                <div id="tab-export-qrcode" class="tab-content">
                    <div class="qr-code-panel">
                        <div id="exportQrCode" class="qr-code-frame" role="status"></div>
                        <p><?php echo t($lang, 'modal.qrcode.description'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="exportModalBackdrop"><?php echo t($lang, 'modal.export.close'); ?></button>
        </div>
    </div>
</div>
