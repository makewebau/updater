<?php $activated = $plugin->status() === 'valid' ?>

<tr class="<?= $activated ? 'active' : 'inactive' ?>">
    <td class="plugin-title column-primary">
        <b><?php _e($plugin->name()) ?></b>

        <div class="license-status<?= $activated ? ' active' : ' inactive' ?>">
            <?= $activated ? 'Active' : 'Not active' ?>
        </div>
    </td>

    <td class="column-description desc">
        <div class="plugin-description">
            <p><?php _e($plugin->description()) ?></p>
        </div>

        <div class="second plugin-version-author-uri">
            Version <?php _e($plugin->version()) ?>
            <?php if ($url = $plugin->url()): ?>
            | <a href="<?= esc_url($url) ?>" target="_blank">Visit plugin site</a>
            <?php endif; ?>
        </div>
    </td>

    <td class="column-license">
        <div class="license-key-row">
            <form method="post" action="options.php">
                <?php settings_fields($plugin->optionsGroupName()); ?>

                <span class="license-key-input-container">
                    <input
                        type="hidden"
                        name="edd_license_key_action"
                        value="<?= $activated && !empty($plugin->licenseKey()) ? 'deactivate' : 'activate' ?>"
                        />
                    <input type="text"
                        class="regular-text"
                        placeholder="Enter your license key..."
                        id="<?php esc_attr_e($plugin->licenseKeyFieldName()) ?>"
                        name="<?php esc_attr_e($plugin->licenseKeyFieldName()) ?>"
                        value="<?php esc_attr_e($plugin->licenseKey()); ?>"
                        <?= $activated && !empty($plugin->licenseKey()) ? 'disabled' : '' ?>
                        \>
                </span>

                <span class="activate-button-container">
                    <?php wp_nonce_field($plugin->activationActionName(), $plugin->activationNonceKey()); ?>
                    <input type="submit"
                        class="button-secondary"
                        value="<?= $activated && !empty($plugin->licenseKey()) ? 'Deactivate' : 'Activate' ?>"/>
                </span>
            </form>
        </div>
    </td>
</tr>
