=== Recently Edited Content Widget ===
Plugin Name: Recently Edited Content Widget
Contributors: webdeveric
Author URI: http://webdeveric.com/
Plugin URI: http://phplug.in/
Tags: dashboard, widget, dashboard widget, edited, post types, recent content, recently edited, user preferences
Requires at least: 3.0.0
Tested up to: 4.4.0
Stable tag: 0.3.2

This plugin provides a dashboard widget that lists recently edited content for quick access.

== Description ==

This plugin provides a dashboard widget that lists recently edited content for quick access.

Options (per user settings):

* Number of items to show
* Excerpt length - number of words (0 = hide)
* Show only your edits
* What post types to show
* What post statuses to show

== Installation ==

1. Upload `recently-edited-content-widget` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to dashboard and see for yourself.

== Screenshots ==

1. This dashboard widget shows you content that was edited recently and styles each item by status (draft, publish, etc).
2. There are several configuration options available.

== Changelog ==

= 0.3.2 =
* Verified compatibility with WordPress 4.4.

= 0.3.1 =
* Verified compatibility with WordPress 4.2.
* Grunt workflow updates.

= 0.3 =
* Added a search box with autocomplete and ajax search results.

= 0.2.14 =
* Updated which post types and statuses are checked by default. It will now show public post types by default.
* Minor style updates for configuration panel.

= 0.2.13 =
* Compatibility fixes for WordPress 3.0.

= 0.2.12 =
* Updated styles for WordPress versions older than 3.8.

= 0.2.11 =
* Updated layout and styles.
* Updated empty featured image box styles to use a color from the user's admin color scheme.

= 0.2.10 =
* Updated styles.
* Began using Grunt and SASS/Compass.
* Added action links like what is on the edit posts screen.

= 0.2.9 =
* Fixed PHP warning when WP_DEBUG is true.

= 0.2.8 =
* Updated HTML input type number to have min and max attributes.

= 0.2.7 =
* Updated permissions check. The widget does not get added with `wp_add_dashboard_widget` unless the current user can `edit_posts` or `edit_others_posts`.
* Minor updates so it works better in WP 3.5.

= 0.2.6 =
* Changed default value of "Only show my edits" to unchecked.

= 0.2.5 =
* Style update on configure panel.
* Updated no content message for new sites without any new content.

= 0.2.4 =
* Updated no content message for when you have imported data, but haven't made any edits yet.

= 0.2.3 =
* Rewrote configuration options - new options, saved per user.
* Updated CSS - added post status background images.

= 0.1 =
* Initial build of plugin. Nothing fancy.
