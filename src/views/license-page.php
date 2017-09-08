<style>
    h2 small {
        display: block;
        font-size: 1rem;
    }
    h2 small a {
        text-decoration: none;
    }
    .license-key-row {
        padding-top: 10px;
    }
    .activate-button-container .button-secondary {
        min-width: 90px;
    }
    .license-key-input-container input {
        padding: 4px 5px;
    }
    .license-status.active {
        color: green;
    }
    .license-status.inactive {
        color: gray;
    }
</style>
<div class="wrap">
    <h2>
        <b><?php _e($plugin->vendorName()); ?></b> Plugin Licenses

        <small>
            <a href="<?= esc_url($plugin->updateServerUrl()) ?>" target="_blank">
                <?php _e($plugin->updateServerUrl()); ?>
            </a>
        </small>
    </h2>
    <br>

    <table class="wp-list-table widefat plugins">
        <thead>
            <tr>
                <th scope="col" id="name" class="manage-column column-name column-primary">Plugin</th>
                <th scope="col" id="description" class="manage-column column-description">Description</th>
                <th scope="col" id="license" class="manage-column column-license">License</th>
            </tr>
        </thead>

        <tbody id="the-list">
            <?= apply_filters($plugin->licenseFieldsHook(), '') ?>
        </tbody>

        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Plugin</th>
                <th scope="col" class="manage-column column-description">Description</th>
                <th scope="col" class="manage-column column-license">License</th>
            </tr>
        </tfoot>

    </table>
</div>
