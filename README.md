# FK Bulk User Editor

[![Coding standards](https://github.com/fk-digital/fk-bulk-user-editor/actions/workflows/coding-standards.yml/badge.svg?branch=main)](https://github.com/futurekings/fk-bulk-user-editor/actions/workflows/coding-standards.yml)

Add a batch of users to one or more sites in a WordPress multisite network, with a chosen role, in one step.

WordPress multisite has no built-in way to do this. You either add each user to each site one by one, or rely on a single site's "Add Existing User" screen. FK Bulk User Editor adds an **Add to Sites…** option to the Bulk Actions dropdown on **Network Admin › Users**.

Built and maintained by [FutureKings](https://futurekings.co.uk).

## Features

- **Bulk action on Network Admin › Users.** Select users, choose **Add to Sites…**, then pick the sites and the role.
- **Choose any role.** The role you pick is applied to every selected site. It defaults to the network's default user role.
- **Pick some sites or all of them.** The screen lists every active site in the network (spam, deleted and archived sites are left out), with a **Select all** option.
- **Leaves existing roles alone by default.** Users who are already on a selected site are skipped, so an administrator can't be demoted to subscriber by accident. If you do want to change their role, tick **Change the role of users who are already members**.
- **Works for super admins.** Core hides the bulk-action checkbox for super admins. This plugin adds it back on this screen only, so super admins can be given an explicit role on a site. Core's own Delete and Spam actions still refuse to touch super admins.
- **Results summary.** After it runs, you see how many memberships were added, changed, skipped or failed.

## Requirements

- WordPress 6.0 or later, set up as a **multisite network**
- PHP 7.4 or later
- The `manage_network_users` capability (super admins have this by default)

The plugin refuses to activate on a single-site install.

## Installation

### From a release zip

1. Download the latest `fk-bulk-user-editor.zip` from the [Releases](../../releases) page.
2. Go to **Network Admin › Plugins › Add New › Upload Plugin** and upload the zip.
3. Click **Network Activate**.

### From source

```bash
cd wp-content/plugins
git clone https://github.com/fk-digital/fk-bulk-user-editor.git fk-bulk-user-editor
```

Then network activate it from **Network Admin › Plugins**, or with WP-CLI:

```bash
wp plugin activate fk-bulk-user-editor --network
```

## Usage

1. Go to **Network Admin › Users**.
2. Tick the users you want to add.
3. Choose **Add to Sites…** from the **Bulk actions** dropdown and click **Apply**.
4. On the **Add Users to Sites** screen:
   - Check the list of selected users.
   - Pick a **Role**.
   - Decide whether to **change the role of users who are already members**. Leave it unticked to only add people to sites they aren't on yet.
   - Tick the **Sites** to add them to, or use **Select all**.
5. Click **Add to Selected Sites**. You're taken back to the Users list with a summary of what changed.

## Notes

- **Roles come from the main site.** The role dropdown lists the roles registered on the network's main site. If a custom role only exists on some sites, make sure it exists on each site you pick.
- **Large selections.** The selected user IDs are passed to the configuration screen in the URL. Selecting hundreds of users at once can hit server URL-length limits. If that happens, work in smaller batches.
- **No data stored.** The plugin doesn't create tables or options. Each run's result is kept in a short-lived site transient until the summary notice has been shown, so there's nothing to clean up when you uninstall it.

## Development

The plugin has no build step. PHP code lives in `includes/` and admin scripts in `assets/js/`.

Coding standards are checked with [WPCS](https://github.com/WordPress/WordPress-Coding-Standards):

```bash
composer install
composer lint        # check
composer lint:fix    # auto-fix what can be fixed
```

The same check runs on GitHub Actions ([`.github/workflows/coding-standards.yml`](.github/workflows/coding-standards.yml)) for every pull request and every push to `main`. Any violations are shown inline on the pull request diff.

To regenerate the translation template:

```bash
wp i18n make-pot . languages/fk-bulk-user-editor.pot
```

### Releasing

1. Bump the version in `fk-bulk-user-editor.php` (both the header and `FK_BULK_USER_EDITOR_VERSION`) and the `Stable tag` in `readme.txt`.
2. Add an entry to `CHANGELOG.md` and the changelog in `readme.txt`.
3. Tag the release (for example `git tag v0.0.2`) and push the tag.
4. Build the distributable zip. Development files are excluded by `.gitattributes`:

   ```bash
   git archive --format=zip --prefix=fk-bulk-user-editor/ -o fk-bulk-user-editor.zip HEAD
   ```

5. Attach the zip to a GitHub release.

## Credits

Based on the approach described by Misha Rudrastyh in [Add user to all sites in a multisite network](https://rudrastyh.com/wordpress-multisite/add-user-to-all-sites.html) and [Custom bulk actions](https://rudrastyh.com/wordpress/custom-bulk-actions.html).

## License

[GPL-2.0-or-later](LICENSE). © FutureKings.
