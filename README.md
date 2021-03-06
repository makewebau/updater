# Updater

Connect your plugin to a server running Easy Digital Downloads - Software Licensing


## Installation

Installation can be done via composer with:

    composer require makeweb/updater


## Usage

### Autoload package with composer

Make sure you are autoloading the package's classes by requiring the composer autoloader if you are not already. Your main plugin file is usually the best place to `require` the composer autoloader. You only need to do this once in your plugin.

    // Autoload composer dependencies
    require __DIR__.'/vendor/autoload.php';


### Boot the updater

To boot the uploader in your main plugin file:

    // Boot the plugin update client
    (new MakeWeb\Updater\Updater(__FILE__))->boot();

You could implement this in any other file as well, but be sure to pass in the full path of the main plugin file to the `Updater` constructure in place of `__FILE__`.


## Configuration

The update client pulls all neccessarry configuration from main plugin file, drawing from the header comments of the main plugin file and the filename.

For example, take the following header comment block:

    /*
    Plugin Name: MyCompany Thingamajiggy
    Description: Respiculate your stultiloquence
    Version: 1.0.0
    Author: MyCompany
    Author URI: http://example.org
    */


The package will look for an update server at `example.org` and will attempt to find updates available based on the given plugin name, and version.

## Wordpress Settings Page

The package will automatically create a settings page under the `Plugins` menu called `{MyCompany} Plugin Licenses`, where the value of `{MyCompany}` is drawn from the `Author` header from the main plugin file. If multiple plugins are installed which use this package, plugins with the same `Author` name will appear on the same page. Each plugin will have a text input field where a license key can be entered to allow the user to update their plugin with updates from the update server.
