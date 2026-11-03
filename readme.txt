=== FK Bulk User Editor ===
Contributors: futurekings
Tags: multisite, users, bulk actions, network admin, roles
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a batch of users to one or more sites in a multisite network, with a chosen role, in one step.

== Description ==

WordPress multisite has no built-in way to add several users to several sites at once. FK Bulk User Editor adds an **Add to Sites…** option to the Bulk Actions dropdown on Network Admin › Users.

Select the users, choose **Add to Sites…**, then pick a role and the sites to add them to.

**Features**

* Adds an "Add to Sites…" bulk action to Network Admin › Users.
* Lets you pick any role. It defaults to the network's default user role.
* Lists every active site in the network, with a "Select all" option.
* Skips users who are already on a site by default, so existing roles aren't changed by accident. You can choose to change them instead.
* Lets you select super admins, whose checkbox core normally hides, so they can be given an explicit role on a site.
* Shows a summary of memberships added, changed, skipped and failed.

**Requirements**

This plugin only works on a multisite network and must be network activated. Using it requires the `manage_network_users` capability.

Built by [FutureKings](https://futurekings.co.uk).

== Installation ==

1. Upload the `fk-bulk-user-editor` folder to `/wp-content/plugins/`, or upload the zip from Network Admin › Plugins › Add New › Upload Plugin.
2. Network activate the plugin from Network Admin › Plugins.
3. Go to Network Admin › Users, select some users and choose **Add to Sites…** from the Bulk actions dropdown.

== Frequently Asked Questions ==

= Does it work on a single-site install? =

No. It needs a multisite network and won't activate without one.

= Will it change the role of users who are already on a site? =

Not unless you ask it to. By default, users who are already on a selected site are skipped. Tick "Change the role of users who are already members" to replace their role on those sites.

= Which roles are listed? =

The roles registered on the network's main site. If you use a custom role, make sure it exists on each site you add users to.

= Does it store any data? =

No. It creates no tables or options. Each run's result is kept in a short-lived transient until the summary has been shown.

== Changelog ==

= 0.0.1 =
* Initial release.
