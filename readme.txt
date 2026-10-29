=== Threshold Easy Downloader ===
Contributors: pugboy
Tags: download, plugin, theme, zip, backup
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Download any installed plugin or theme as a ZIP file, from the Plugins list or the Tools page.

== Description ==

Threshold Easy Downloader adds one-click "Download ZIP" links for every installed plugin and theme:

* A **Download ZIP** row action on the Plugins screen (and the network Themes screen on multisite).
* A **Tools → Easy Downloader** page listing all plugins and themes with version, status, on-disk size, a live filter, and download buttons.
* ZIPs are named `slug-version.zip` and built on the fly in the WP temp directory; nothing is stored.
* Capability-gated (`install_plugins` / `install_themes`) and nonce-protected; requested items are validated against the real installed plugin and theme lists.
* Symbolic links inside a plugin or theme folder are left out of the ZIP.
* Uses ZipArchive with automatic PclZip fallback.
* No settings, no outside services, and nothing sent anywhere: everything happens on your own server.

User guide: https://spencer-taylor.com/threshold-easy-downloader/#guide

Built by Spencer Taylor, Threshold Solutions, LLC.

== Changelog ==

= 1.2.0 =
* Changed: updates now come from the WordPress.org plugin directory. The self-hosted updater, the auto-update enrolment, and the "Check for updates now" button are removed.
* Changed: plugin renamed to Threshold Easy Downloader.

= 1.1.0 =
* Security: symbolic links inside a plugin or theme folder are no longer followed into the ZIP (both the ZipArchive and PclZip builders).
* Security: the update manifest is shape-checked, and the package URL must be on the Threshold update server over HTTPS.
* Fixed: a malformed manifest could stop every admin page on PHP 8 (version_compare TypeError).
* Fixed: downloads could hang or arrive truncated when output buffering or zlib compression was on; both are switched off before streaming.
* Fixed: a cancelled or timed-out download could leave a temp ZIP behind; it is always removed.
* Fixed: "Download ZIP" no longer appears on must-use plugin and drop-in rows, where it could not work.
* Fixed: the PclZip fallback now packs a single-file plugin under its own folder, like ZipArchive.
* Fixed: the capability is checked before the nonce, so an unauthorised user sees "not allowed" rather than "link expired".
* Fixed: the auto-update backfill flag and manifest cache are network-wide on multisite.
* Fixed: "Check for updates now" refreshes the plugin-update data properly and reports request failures.
* Fixed: uninstall now removes the backfill flag, the size cache, and the plugin's own entry in the auto-update list.
* Changed: directory sizes on the Tools page are cached for a day instead of being recounted on every view.
* Changed: 2026 Threshold brand on the Tools page (charcoal and gold, bundled logo, 16px base type, no hot-linked images) and an accessibility pass (labels, captions, live regions, new-tab hints).
* Changed: child themes are marked "child of …"; the parent is downloaded separately.

= 1.0.2 =
* Auto-update enrolment at activation and a one-time backfill for existing installs.

= 1.0.1 =
* Updater: never keep the plugin in both the "response" and "no_update" lists (reinstall-over-itself fix).

= 1.0.0 =
* Initial release: plugin + theme ZIP downloads (row actions + Tools page), Threshold brand admin UI, self-hosted updater.
