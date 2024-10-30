=== Catenis API Client for WordPress ===
Contributors: catenisadmin
Tags: Catenis, Catenis API, Blockchain of Things, blockchain
Requires at least: 5.8
Tested up to: 6.0
Requires PHP: 5.6
Stable tag: 4.0.0
License: MIT

Provides a way to use the Catenis services from within WordPress

== Description ==

Catenis API Client for WordPress enables (JavaScript) code on WordPress pages to interact with the Catenis API.

= Enabling the Catenis API client =

To enable the Catenis API client for a given WordPress page, go to the page's edit page and look for a section (meta box) named "Catenis API Client" below the page's main editing panel. Make sure the section is expanded, and check the `Load Catenis API Client` checkbox.

You can then choose to override the global settings used for instantiating the Catenis API client on that given page, like using a different device ID and its associated API access secret. Otherwise, whatever is configured in the plugin's global settings -- configured under "Settings" | "Catenis API Client" -- is going to be used.

= Using the Catenis API client =

Once enabled, a global JavaScript variable named `ctnApiClient` is made available on the page. That variable holds the instantiated Catenis API client object.

Use the *ctnApiClient* variable to call the Catenis API methods by invoking the corresponding method on that object.

For a reference of the available methods, please refer to the [Catenis API JavaScript Client](https://github.com/blockchainofthings/CatenisAPIClientJS) as it is functionally identical to the Catenis API Client for WordPress, except for notifications support and error handling.

= Notifications support =

The notification feature on Catenis API Client for WordPress is almost identical to the one found on the Catenis API JavaScript client. The two noticeable differences are:

1. The Catenis API client object can emit a `comm-error` event.
1. The `open` event emitted by the WebSocket notification channel object may return an error.

Please refer to the "Receiving Notifications" section below for detailed information on how to receive Catenis notifications from within WordPress pages.

= Error handling =

Errors that take place while calling the Catenis API methods are returned as standard JavaScript Error objects.

== Installation ==

= System requirements =

The PHP executable should be in the system PATH so that the plugin can spawn the process used to handle notifications.

= Installation procedure =

1. Upload the plugin files to the "/wp-content/plugins/" directory.
1. Activate the plugin through the "Plugins" menu in WordPress.
1. Go to "Settings" | "Catenis API Client" to configure the global settings for instantiating the Catenis API client.
1. A meta box named "Catenis API Client" will be available on every WordPress page's edit page. Use it to make the Catenis API client available from a given page, and optionally configure custom settings for instantiating the Catenis API client for that given page.

Please refer to [Catenis API documentation](https://www.catenis.com/docs/api) for further information on accessing the Catenis API.

== Frequently Asked Questions ==

= What client options settings should I use to connect with the Catenis API sandbox environment? =

When doing it on the plugin's global settings ("Settings" | "Catenis API Client"), just leave all fields of the "Client Options" section blank.

However, when doing it on the "Catenis API Client" meta box on a WordPress page's edit page, use the following settings to make sure that all client options fields of the plugin's global settings are properly overridden:
- Host: `catenis.io`
- Environment: `sandbox`
- Secure Connection: `On`

== Screenshots ==

1. The plugin's global settings menu

2. The "Catenis API Client" meta box on a WordPress page's edit page.

== Changelog ==

= 4.0.0 =
* Added support for changes introduced by version 0.12 of the Catenis API: new non-fungible assets feature, including
the new API methods Issue Non-Fungible Asset, Reissue Non-Fungible Asset, Retrieve Non-Fungible Asset Issuance
Progress, Retrieve Non-Fungible Token, Retrieve Non-Fungible Token Retrieval Progress, Transfer Non-Fungible Token,
and Retrieve Non-Fungible Token Transfer Progress.
* The issuance event entries returned by the *retrieveAssetIssuanceHistory* method for non-fungible assets are different
from the ones returned for regular (fungible) assets as per the new behavior of version 0.12 of the Catenis API. The
observed differences are: the `amount` key is replaced by a new `nfTokenIds` key, which lists the IDs of the
non-fungible tokens that have been issued; and the `holdingDevice` key is replaced by a new `holdingDevices` key, which
lists the Catenis virtual devices to which the issued non-fungible tokens have been assigned.

= 3.0.0 =
* Added support for changes introduced by version 0.11 of the Catenis API: new asset export feature, including the new
API methods Export Asset, Migrate Asset, Asset Export Outcome, Asset Migration Outcome, List Exported Assets, and
List Asset Migrations.
* The list of current asset holders returned by the *listAssetHolders* method may now include an entry that reports the
total asset amount that is currently migrated to foreign blockchains as per the new behavior of version 0.11 of the
Catenis API. That entry differs from the regular ones in that the `holder` property is missing and a new boolean type
property named `migrated`, the value of which is always `true`, is present.

= 2.2.0 =
* Added support for changes introduced by version 0.10 of the Catenis API.

= 2.1.0 =
* Updated dependency package Catenis API PHP client library to its latest version (4.0), which targets version 0.9 of the Catenis API.
* Added workaround to avoid that Catenis device credentials fields — *Device ID* and *API Access Secret* — be automatically filled by the web browser when editing a page. This behavior has been observed on Google Chrome 79.0 on Linux.

= 2.0.0 =
* Added support for changes introduced by version 0.7 of the Catenis API.
* Added support for changes introduced by version 0.8 of the Catenis API.
* Updated dependency package Catenis API PHP client library to its latest version (3.0), which targets version 0.8 of the Catenis API.
* New `Compression Threshold` settings used for instantiating the Catenis API client.
* API client methods with an update interface: *logMessage*, *sendMessage*, *readMessage*, *listMessages*, *retrieveAssetIssuanceHistory*
* New API client method: *retrieveMessageProgress*
* Whole new (not backwards compatible) and improved notifications implementation.

= 1.1.2 =
* Internal adjustments to usage of WP Heartbeat API.

= 1.1.1 =
* Fix issue with deleting plugin's data when plugin is uninstalled from multi-site WordPress environments.

= 1.1.0 =
* Add support for Catenis notifications.
* **WARNING**: this version only works on Unix-like OS's like Linux and macOS. It does not work on Windows.

= 1.0.0 =
* Initial working version. Exposes all Catenis API methods (as of version 0.6 of the Catenis API), but does not include support for notifications.

== Upgrade Notice ==

= 4.0.0 =
Upgrade to this version to take advantage of the new features found in version 0.12 of the Catenis API. This is also a requirement if using the plugin with version 6.0 of WordPress.

= 3.0.0 =
Upgrade to this version to take advantage of the new features found in version 0.11 of the Catenis API.

= 2.2.0 =
Upgrade to this version to take advantage of the new features found in version 0.10 of the Catenis API.

= 2.1.0 =
Upgrade to this version to take advantage of the new features found in version 0.9 of the Catenis API, and to improve the end user's experience when using Google Chrome.

= 2.0.0 =
Upgrade to this version to take advantage of the new features found in version 0.8 of the Catenis API and the improved notifications implementation.

= 1.1.2 =
All users are advised to upgrade to this version.

= 1.1.1 =
Upgrade to this version if using the plugin in a multi-site WordPress environment.

= 1.1.0 =
All users are advised to upgrade to this version even if not planning to use notifications since it also adds several enhancements and fixes to the basic functionality.

== Receiving Notifications ==

= Instantiate WebSocket notification channel object

Create a WebSocket notification channel for a given Catenis notification event.

`
var wsNotifyChannel = ctnApiClient.createWsNotifyChannel(eventName);
`

= Add listeners =

Add event listeners to monitor activity on the notification channel.

`
ctnApiClient.on('comm-error', function (error) {
    // Error communicating with Catenis notification process
});

wsNotifyChannel.on('open', function (error) {
    if (error) {
        // Error establishing underlying WebSocket connection
    }
    else {
        // Notification channel successfully open
    }
});

wsNotifyChannel.on('error', function (error) {
    // Error in the underlying WebSocket connection
});

wsNotifyChannel.on('close', function (code, reason) {
    // Underlying WebSocket connection has been closed
});

wsNotifyChannel.on('notify', function (eventData) {
    // Received notification
});
`

> **Note**: the 'comm-error' event is emitted by the Catenis API client object while all other events are emitted by the WebSocket notification channel object.

= Open the notification channel =

Open the WebSocket notification channel to start receiving notifications.

`
wsNotifyChannel.open(function (error) {
    if (err) {
        // Error sending command to open notification channel
    }
});
`

= Close the notification channel =

Close the WebSocket notification channel to stop receiving notifications.

`
wsNotifyChannel.close(function (error) {
    if (err) {
        // Error sending command to close notification channel
    }
});
`
