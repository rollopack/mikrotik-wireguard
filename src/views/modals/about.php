<?php
/**
 * About modal (application version and README).
 *
 * Rendered on demand by src/modal.php and injected into the dashboard by
 * assets/js/app.js, so the markup stays out of the initial page weight.
 *
 * @var array $lang Translations for the active language.
 * @var array $config Active server configuration.
 * @var string $appVersion Version read from the VERSION file.
 * @var string $readmeHtml README rendered from the Configuration heading.
 */
?>
<div class="modal-backdrop" id="aboutModalBackdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="aboutModalHeading">
    <div class="modal modal-about">
        <div class="modal-header">
            <h2 class="modal-heading" id="aboutModalHeading"><?php echo t($lang, 'about.title'); ?></h2>
            <button class="close-btn" data-action="close-modal" data-modal="aboutModalBackdrop" aria-label="<?php echo t($lang, 'modal.close'); ?>">&times;</button>
        </div>
        <div class="modal-body">
            <p class="about-meta">
                <strong><?php echo t($lang, 'header.title'); ?></strong> &middot; <?php echo t($lang, 'about.version'); ?> <?php echo htmlspecialchars($appVersion); ?>
            </p>
            <p class="about-meta">
                <a href="<?php echo htmlspecialchars(AppInfo::repositoryUrl()); ?>" target="_blank" rel="noopener noreferrer"><?php echo t($lang, 'about.repository'); ?></a>
            </p>
            <div class="about-markdown">
                <?php
                    if ($readmeHtml !== '') {
                        echo $readmeHtml;
                    } else {
                        echo '<p>' . t($lang, 'about.readme_unavailable') . '</p>';
                    }
                ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="aboutModalBackdrop"><?php echo t($lang, 'modal.close'); ?></button>
        </div>
    </div>
</div>
