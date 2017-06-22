<div class="wrap">
    <h2><?php _e($plugin->vendorName().' Plugin Licenses'); ?></h2>

    <?= apply_filters($plugin->licenseFieldsHook(), '') ?>
</div>


