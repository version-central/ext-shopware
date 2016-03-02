# VersionCentralTracker

## Support

**Shopware Version**

* Tested versions: 4.3.6, 5.1.2, 5.1.3
* If you run any other versions, just try it ;-)

## Installation

### Manual

Download the [current ZIP](https://github.com/version-central/ext-shopware/archive/master.zip) and copy the files into your shop directory into `engine/Shopware/Plugins/Local/VersionCentralTracker`.

Keep the directory structure of the ZIP intact.

### composer

You can also install the plugin via composer.

Add the following parts to your Shopware composer.json:

```
{
    # …
    "require": {
        # …
        "version-central/tracker": "dev-master"
    },
    "repositories": [
        # …
        {
            "type": "vcs",
            "url": "https://github.com/version-central/ext-shopware"
        }
    ]
}
```

Afterwards, run `composer install` and the plugin should be available

### via Community Store

(coming soon, waiting for approval from Shopware)

## Configuration

Open the Plugin Manager in your Shopware backend and install and activate the plugin.

Afterwards open the plugin configuration and add your application token from the VersionCentral project. After saving the configuration, information should appear for your application in the VersionCentral dashboard.

**Note:** Shopware-Cronjob-Plugin has to be installed, enabled and running. Have a look on the [Shopware Wiki](http://community.shopware.com/Cronjobs_detail_1102.html) for further information how to activate cronjobs.

## Contact

If you have any problems or suggestions, just email us at [versioncentral@k10r.de](mailto:versioncentral@k10r.de).
