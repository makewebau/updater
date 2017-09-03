<?php

namespace MakeWeb\Updater;

class Plugin
{
    protected $data;

    protected $licenseKey;

    protected $path;

    protected $slug;

    protected $options = [];

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function getLicenseKey()
    {
        return trim(get_option($this->slug().'_license_key'));
    }

    public function slug()
    {
        if (is_null($this->slug)) {
            $this->slug = basename($this->path, '.php');
        }

        return $this->slug;
    }

    public function updateServerUrl()
    {
        return $this->getParameter('AuthorURI');
    }

    public function name()
    {
        return $this->getParameter('Name');
    }

    public function filteredName()
    {
        $allowedTags = [
            'a'       => ['href' => [], 'title' => []],
            'abbr'    => ['title' => []],
            'acronym' => ['title' => []],
            'code'    => [],
            'em'      => [],
            'strong'  => [],
        ];

        return wp_kses($this->name(), $allowedTags);
    }

    public function basename()
    {
        return plugin_basename($this->path());
    }

    public function path()
    {
        return $this->path;
    }

    public function version()
    {
        return $this->getParameter('Version');
    }

    public function licenseKey()
    {
        return $this->getOption('license_key');
    }

    public function licenseKeyFieldName()
    {
        return $this->slug().'_license_key';
    }

    public function status()
    {
        return $this->getOption('status');
    }

    public function vendorName()
    {
        return $this->getParameter('AuthorName');
    }

    public function formattedVendorName()
    {
        return sprintf('<a href="%s">%s</a>', $this->updateServerUrl(), $this->vendorName());
    }

    public function vendorSlug()
    {
        return sanitize_title_with_dashes($this->vendorName());
    }

    public function activationNonceKey()
    {
        return $this->slug().'_nonce';
    }

    public function activationActionName()
    {
        return 'activate-'.$this->slug();
    }

    public function licensePageSlug()
    {
        return $this->vendorSlug().'/licences';
    }

    public function licensePageTitle()
    {
        return $this->vendorName().' Licenses';
    }

    public function licensePageUrl()
    {
        return admin_url('plugins.php?page='.$this->licensePageSlug());
    }

    public function optionsGroupName()
    {
        return $this->slug().'_license';
    }

    public function licenseFieldsHook()
    {
        return $this->vendorSlug().'_licenses';
    }

    public function deleteLicenseStatus()
    {
        delete_option($this->slug().'_status');
    }

    public function saveLicenseKey($licenseKey)
    {
        update_option($this->licenseKeyFieldName(), $licenseKey);
    }

    public function setLicenseStatus($status)
    {
        update_option($this->slug().'_status', $status);
    }

    public function licensePageAlreadyRegistered()
    {
        global $submenu;

        return count(array_filter($submenu['plugins.php'], function ($submenuPage) {
            return $submenuPage[0] == $this->licensePageTitle();
        })) > 0;
    }

    protected function getOption($key)
    {
        if (empty($this->options[$key])) {
            $this->options[$key] = get_option($this->slug().'_'.$key);
        }

        return $this->options[$key];
    }

    protected function getParameter($key)
    {
        if (is_null($this->data)) {
            $this->data = get_file_data($this->path(), [
                'Name'       => 'Plugin Name',
                'AuthorName' => 'Author',
                'AuthorURI'  => 'Author URI',
                'Version'    => 'Version',
            ]);
        }

        return $this->data[$key];
    }
}
