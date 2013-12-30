=== Recently Edited Content Widget ===
Contributors: webdeveric
Author URI: http://webdeveric.com/
Donate link: http://phplug.in/donate/
Tags: dashboard, widget, edited, post types
Requires at least: 3.0.0
Tested up to: 3.8
Stable tag: 0.2.7.2

This plugin provides a dashboard widget that lists recently edited content for quick access.

== Description ==

This plugin provides a dashboard widget that lists recently edited content for quick access.

Options (per user settings):

* Number of items to show
* Excerpt length (# of words) - 0 = hide
* Show only your edits
* What post types to show
* What post status to show

== Installation ==

1. Upload `Recently-Edited-Content-Widget` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to dashboard and see for yourself.

== Changelog ==

= 0.2.7.2 =
* Minor code clean-up.

= 0.2.7.1 =
* Updated permissions check. The widget does not get added with `wp_add_dashboard_widget` unless the current user can `edit_posts` or `edit_others_posts`.

= 0.2.7 =
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
* Updated CSS - added post status bg images
* Lots of other stuff

= 0.1 =
* Initial build of plugin. Nothing fancy.