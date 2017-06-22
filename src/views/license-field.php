<div class="license-key-row">
    <form method="post" action="options.php">
        <?php settings_fields($plugin->optionsGroupName()); ?>
        <div class="plugin-title-container">
            <h3> <?php _e($plugin->name()) ?> </h3>
        </div>
        <div class="license-key-input-container">
            <input
                id="<?= esc_attr_e($plugin->licenseKeyFieldName()) ?>"
                name="<?= esc_attr_e($plugin->licenseKeyFieldName()) ?>"
                type="text"
                class="regular-text"
                value="<?php esc_attr_e($plugin->licenseKey()); ?>"
                placeholder="Enter your license key."
            />
        </div>
        <div class="activate-button-container">
            <?php if ($plugin->status() !== false && $plugin->status() == 'valid') {
    ?>
                <span style="color:green;"><?php _e('active'); ?></span>
                <?php wp_nonce_field($plugin->activationActionName(), $plugin->activationNonceKey()); ?>
                <input
                    type="submit"
                    class="button-secondary"
                    name="edd_license_deactivate"
                    value="<?php _e('Deactivate License') ?>"
                />
            <?php
} else {
        wp_nonce_field($plugin->activationActionName(), $plugin->activationNonceKey()); ?>
                <input
                    type="submit"
                    class="button-secondary"
                    name="edd_license_activate"
                    value="<?php _e('Activate License'); ?>"
                />
            <?php
    } ?>
        </div>
    </form>
</div>
