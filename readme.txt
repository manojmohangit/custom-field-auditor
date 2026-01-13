=== Custom Field Auditor ===
Contributors: manojmohan
Tags: meta, revision, custom fields, audit, history, versioning
Requires at least: 6.4
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Advanced tracking and versioning for custom post meta fields with a modern modular UI.

== Description ==

Custom Field Auditor brings native-like revision tracking to your WordPress custom fields (post meta). Tired of losing data when updating custom fields? This plugin allows you to audit every change made to specific meta keys, view comprehensive diffs in the standard WordPress Revisions screen, and manage your audit trails.

Key features include:
1. Version Tracking: Capture changes to custom fields even when updated via AJAX or REST API.
2. Intuitive Diff UI: View what changed, when, and by whom using the native WordPress revisions interface.
3. Meta Management: A modern dashboard to create, track, and manage custom fields.
4. Safety Checks: Prevent deletion of fields that are currently in use by your content.
5. Real-time sync: Revisions list updates instantly in both Block (Gutenberg) and Classic Editors.

== Installation ==

1. Upload the "custom-field-auditor" folder to the "/wp-content/plugins/" directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to "Settings > Field Auditor" to start managing your custom fields.

== Frequently Asked Questions ==

= Does it work with Gutenberg? =
Yes, Custom Field Auditor fully supports the Block Editor and refreshes the revisions list in real-time.

= Can I track dynamic meta keys? =
Currently, you can manage specific meta keys. Dynamic pattern matching is planned for a future update.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added support for Block and Classic editors.
* Implemented modern admin UI with modal creation.
* Added deletion safety checks.
