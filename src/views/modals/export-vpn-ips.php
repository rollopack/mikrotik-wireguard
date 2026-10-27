<?php
/**
 * Export VPN IPs modal.
 *
 * Rendered on demand by src/modal.php and injected into the dashboard by
 * assets/js/app.js, so the markup stays out of the initial page weight.
 *
 * @var array $lang Translations for the active language.
 * @var array $config Active server configuration.
 */
?>
<div class="modal-backdrop" id="exportVpnIpsModalBackdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="exportVpnIpsModalHeading">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h2 class="modal-heading" id="exportVpnIpsModalHeading"><?php echo t($lang, 'modal.export_vpn.title'); ?></h2>
            <button class="close-btn" data-action="close-modal" data-modal="exportVpnIpsModalBackdrop" aria-label="<?php echo t($lang, 'modal.close'); ?>">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.25rem;">
                <?php echo t($lang, 'modal.export_vpn.description'); ?>
            </p>
            <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.25rem;">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <input type="checkbox" id="includeSstpCheck" style="width:18px;height:18px;accent-color:var(--primary); flex-shrink:0;">
                    <label for="includeSstpCheck" style="margin:0; display:inline; font-size:0.9rem; color:var(--text-muted); cursor:pointer;"><?php echo t($lang, 'modal.export_vpn.include_sstp'); ?></label>
                </div>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <input type="checkbox" id="includePptpCheck" style="width:18px;height:18px;accent-color:var(--primary); flex-shrink:0;">
                    <label for="includePptpCheck" style="margin:0; display:inline; font-size:0.9rem; color:var(--text-muted); cursor:pointer;"><?php echo t($lang, 'modal.export_vpn.include_pptp'); ?></label>
                </div>
            </div>
            <div id="exportVpnIpsResult" style="display:none; margin-top:1rem;">
                <div class="success-card">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    <div class="success-card-content">
                        <h4><?php echo t($lang, 'modal.export_vpn.success_title'); ?></h4>
                        <p><?php echo t($lang, 'modal.export_vpn.stats_wg'); ?> <span id="exportVpnIpsWgCount">0</span> &mdash; <?php echo t($lang, 'modal.export_vpn.stats_sstp'); ?> <span id="exportVpnIpsSstpCount">0</span> &mdash; <?php echo t($lang, 'modal.export_vpn.stats_pptp'); ?> <span id="exportVpnIpsPptpCount">0</span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" id="exportVpnIpsFooter">
            <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="exportVpnIpsModalBackdrop"><?php echo t($lang, 'modal.export_vpn.cancel'); ?></button>
            <button type="button" class="btn btn-primary" id="btnExportVpnIps" data-action="export-vpn-ips">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;margin-right:4px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                <?php echo t($lang, 'modal.export_vpn.download'); ?>
            </button>
        </div>
    </div>
</div>
