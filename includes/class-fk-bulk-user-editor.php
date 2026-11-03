<?php
/**
 * Main plugin class.
 *
 * Based on the approach described at:
 * - https://rudrastyh.com/wordpress-multisite/add-user-to-all-sites.html
 * - https://rudrastyh.com/wordpress/custom-bulk-actions.html
 *
 * @package FK_Bulk_User_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the "Add to Sites…" bulk action to Network Admin › Users, plus the
 * configuration screen it leads to and the handler that does the work.
 */
final class FK_Bulk_User_Editor {

	/**
	 * Capability required to use any part of the plugin.
	 */
	const CAP = 'manage_network_users';

	/**
	 * Bulk action key, as it appears in the Bulk Actions dropdown.
	 */
	const ACTION = 'fk_add_to_sites';

	/**
	 * Slug of the (hidden) configuration screen.
	 */
	const PAGE = 'fk-bulk-user-editor';

	/**
	 * Nonce action for the configuration form.
	 */
	const NONCE = 'fk_bulk_user_editor_apply';

	/**
	 * Query arg flagging that a result notice is waiting to be shown.
	 */
	const DONE_ARG = 'fk_bue_done';

	/**
	 * Prefix for the per-user site transient holding the last result.
	 */
	const RESULT_TRANSIENT = 'fk_bue_result_';

	/**
	 * Singleton instance.
	 *
	 * @var FK_Bulk_User_Editor|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared instance, creating (and hooking) it on first call.
	 *
	 * @return FK_Bulk_User_Editor
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Registers all WordPress hooks.
	 */
	private function hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Step 1: the bulk action itself, on Network Admin › Users.
		add_filter( 'bulk_actions-users-network', array( $this, 'register_bulk_action' ) );
		add_action( 'load-users.php', array( $this, 'load_users_screen' ) );

		// Step 2: catch the submitted bulk action and redirect to our screen.
		add_filter( 'handle_network_bulk_actions-users-network', array( $this, 'handle_bulk_action' ), 10, 3 );

		// Step 3: the configuration screen and its form handler.
		add_action( 'network_admin_menu', array( $this, 'register_page' ) );

		// Step 4: the result notice back on Network Admin › Users.
		add_action( 'network_admin_notices', array( $this, 'render_result_notice' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
	}

	/**
	 * Loads translations from the plugin's /languages directory.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'fk-bulk-user-editor', false, dirname( plugin_basename( FK_BULK_USER_EDITOR_FILE ) ) . '/languages' );
	}

	/**
	 * Adds our option to the Bulk Actions dropdown.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		if ( current_user_can( self::CAP ) ) {
			$actions[ self::ACTION ] = __( 'Add to Sites…', 'fk-bulk-user-editor' );
		}

		return $actions;
	}

	/**
	 * Runs when Network Admin › Users loads.
	 *
	 * WP_MS_Users_List_Table::column_cb() deliberately leaves out the checkbox
	 * for super admins, so core's own bulk actions (Delete, Mark as spam) can
	 * never be applied to one - but that also means they can't be selected
	 * for ours. Those other actions stay safe either way: wpmu_delete_user()
	 * and the spam/notspam handlers each independently refuse to touch a
	 * super admin account, regardless of whether a checkbox was shown. So
	 * it's safe to add the missing checkbox back in on this one screen, using
	 * the same `allusers[]` field name/value core uses, which the existing
	 * bulk-action form picks up with no further changes needed.
	 */
	public function load_users_screen() {
		if ( ! is_network_admin() || ! current_user_can( self::CAP ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_users_screen_assets' ) );
	}

	/**
	 * Enqueues the super-admin checkbox script on Network Admin › Users.
	 */
	public function enqueue_users_screen_assets() {
		wp_enqueue_script(
			'fk-bulk-user-editor-network-users',
			FK_BULK_USER_EDITOR_URL . 'assets/js/network-users.js',
			array(),
			FK_BULK_USER_EDITOR_VERSION,
			true
		);
	}

	/**
	 * Catches the bulk action once submitted.
	 *
	 * Rather than acting immediately, we hand the selected user IDs off to
	 * our own screen so the sites and role can be chosen first. Core has
	 * already verified the bulk action nonce (check_admin_referer(
	 * 'bulk-users' )) before this filter runs.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Bulk action being performed.
	 * @param array  $user_ids    Selected user IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_to, $doaction, $user_ids ) {
		if ( self::ACTION !== $doaction || ! current_user_can( self::CAP ) ) {
			return $redirect_to;
		}

		$user_ids = self::parse_ids( $user_ids );
		if ( empty( $user_ids ) ) {
			return $redirect_to;
		}

		return add_query_arg(
			array(
				'page'     => self::PAGE,
				'user_ids' => implode( ',', $user_ids ),
			),
			network_admin_url( 'admin.php' )
		);
	}

	/**
	 * Registers the configuration screen the bulk action redirects to.
	 *
	 * It's deliberately not added to any menu (empty parent slug) - it's only
	 * ever reached via the bulk action. An empty string is used rather than
	 * `null`, which triggers deprecation notices on PHP 8.1+.
	 *
	 * The form submission is handled on the screen's `load-` hook, which
	 * fires before admin-header.php sends any output; inside the render
	 * callback would be too late to redirect.
	 */
	public function register_page() {
		$hook = add_submenu_page(
			'',
			__( 'Add Users to Sites', 'fk-bulk-user-editor' ),
			__( 'Add Users to Sites', 'fk-bulk-user-editor' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			add_action( "load-{$hook}", array( $this, 'load_page' ) );
		}
	}

	/**
	 * Runs before the configuration screen renders.
	 */
	public function load_page() {
		global $title;

		// Pages without a menu parent don't get a title resolved by core.
		$title = __( 'Add Users to Sites', 'fk-bulk-user-editor' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage network users.', 'fk-bulk-user-editor' ), 403 );
		}

		if ( isset( $_POST['fk_bulk_user_editor_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$this->handle_submit();
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_page_assets' ) );
	}

	/**
	 * Enqueues the configuration screen's script.
	 */
	public function enqueue_page_assets() {
		wp_enqueue_script(
			'fk-bulk-user-editor-add-to-sites',
			FK_BULK_USER_EDITOR_URL . 'assets/js/add-to-sites.js',
			array(),
			FK_BULK_USER_EDITOR_VERSION,
			true
		);
	}

	/**
	 * All sites in the network a user could meaningfully be added to.
	 *
	 * `number => 0` asks for every matching site - get_sites() only returns
	 * the first 100 by default.
	 *
	 * @param string $fields Optional. Pass 'ids' to return IDs only.
	 * @return WP_Site[]|int[]
	 */
	private static function get_sites( $fields = '' ) {
		return get_sites(
			array(
				'number'   => 0,
				'spam'     => 0,
				'deleted'  => 0,
				'archived' => 0,
				'fields'   => $fields,
			)
		);
	}

	/**
	 * Turns a comma-separated string or array into unique positive ints.
	 *
	 * @param string|array $raw Raw IDs.
	 * @return int[]
	 */
	private static function parse_ids( $raw ) {
		$raw = is_array( $raw ) ? $raw : explode( ',', (string) $raw );

		return array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
	}

	/**
	 * Renders the configuration screen: who was selected, then which sites
	 * and role to add them with. Submissions never reach this method -
	 * load_page() handles and redirects first.
	 */
	public function render_page() {
		// Read-only view: IDs are sanitised by parse_ids() and the form it renders is nonce-protected.
		$user_ids = self::parse_ids( isset( $_GET['user_ids'] ) ? wp_unslash( $_GET['user_ids'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Add Users to Sites', 'fk-bulk-user-editor' ) . '</h1>';

		if ( empty( $user_ids ) ) {
			echo '<p>' . esc_html__( 'No users were selected. Go back to the Users list, select some users, then choose "Add to Sites…" from the Bulk Actions dropdown.', 'fk-bulk-user-editor' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( network_admin_url( 'users.php' ) ) . '">' . esc_html__( 'Back to Users', 'fk-bulk-user-editor' ) . '</a></p>';
			echo '</div>';
			return;
		}

		$users = array_values( array_filter( array_map( 'get_userdata', $user_ids ) ) );

		if ( empty( $users ) ) {
			echo '<p>' . esc_html__( 'None of the selected users could be found.', 'fk-bulk-user-editor' ) . '</p>';
			echo '</div>';
			return;
		}

		$sites = self::get_sites();
		$roles = wp_roles()->get_names();
		$count = count( $users );
		?>
		<p>
			<?php
			if ( 1 === $count ) {
				printf(
					/* translators: %s: user display name */
					esc_html__( 'Add %s to the sites you choose below.', 'fk-bulk-user-editor' ),
					'<strong>' . esc_html( $users[0]->display_name ) . '</strong>'
				);
			} else {
				printf(
					/* translators: %s: number of users */
					esc_html( _n( 'Add this %s user to the sites you choose below:', 'Add these %s users to the sites you choose below:', $count, 'fk-bulk-user-editor' ) ),
					esc_html( number_format_i18n( $count ) )
				);
			}
			?>
		</p>

		<ul class="ul-disc">
			<?php foreach ( $users as $user ) : ?>
				<li>
					<?php echo esc_html( $user->display_name ); ?>
					(<?php echo esc_html( $user->user_email ); ?>)
					<?php if ( is_super_admin( $user->ID ) ) : ?>
						&mdash; <em><?php esc_html_e( 'super admin', 'fk-bulk-user-editor' ); ?></em>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<form method="post">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="user_ids" value="<?php echo esc_attr( implode( ',', wp_list_pluck( $users, 'ID' ) ) ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="fk-bulk-user-editor-role"><?php esc_html_e( 'Role', 'fk-bulk-user-editor' ); ?></label></th>
					<td>
						<select name="role" id="fk-bulk-user-editor-role">
							<?php foreach ( $roles as $role_slug => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( $role_slug, get_site_option( 'default_user_role', 'subscriber' ) ); ?>>
									<?php echo esc_html( translate_user_role( $role_name ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Existing members', 'fk-bulk-user-editor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="update_existing" value="1" />
							<?php esc_html_e( 'Change the role of users who are already members of a selected site', 'fk-bulk-user-editor' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Leave unticked to only add users to sites they are not on yet. Ticking this will replace their current role on that site, which could demote an administrator.', 'fk-bulk-user-editor' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sites', 'fk-bulk-user-editor' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Sites', 'fk-bulk-user-editor' ); ?></legend>
							<p>
								<label>
									<input type="checkbox" id="fk-bulk-user-editor-select-all" />
									<strong><?php esc_html_e( 'Select all', 'fk-bulk-user-editor' ); ?></strong>
								</label>
							</p>
							<?php foreach ( $sites as $site ) : ?>
								<p>
									<label>
										<input type="checkbox" name="site_ids[]" value="<?php echo esc_attr( $site->blog_id ); ?>" class="fk-bulk-user-editor-site" />
										<?php echo esc_html( $site->blogname ? $site->blogname : $site->domain . $site->path ); ?>
										<code><?php echo esc_html( $site->domain . $site->path ); ?></code>
									</label>
								</p>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Add to Selected Sites', 'fk-bulk-user-editor' ), 'primary', 'fk_bulk_user_editor_submit' ); ?>
		</form>
		</div>
		<?php
	}

	/**
	 * Processes the configuration form.
	 *
	 * Adds each selected user - including super admins, who don't otherwise
	 * get an explicit per-site membership - to each selected site with the
	 * chosen role, then redirects back to Network Admin › Users with a
	 * summary notice.
	 */
	private function handle_submit() {
		// Nonce is verified in load_page() before this is called.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$user_ids        = self::parse_ids( isset( $_POST['user_ids'] ) ? wp_unslash( $_POST['user_ids'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by parse_ids().
		$site_ids        = self::parse_ids( isset( $_POST['site_ids'] ) ? (array) wp_unslash( $_POST['site_ids'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by parse_ids().
		$role            = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		$update_existing = ! empty( $_POST['update_existing'] );
		// phpcs:enable

		if ( ! wp_roles()->is_role( $role ) ) {
			$role = get_site_option( 'default_user_role', 'subscriber' );
		}

		// Only operate on sites that actually exist in the network, not
		// arbitrary IDs a tampered-with form could submit.
		$site_ids = array_values( array_intersect( $site_ids, array_map( 'intval', self::get_sites( 'ids' ) ) ) );
		$user_ids = array_values( array_filter( $user_ids, 'get_userdata' ) );

		$result = array(
			'users'   => count( $user_ids ),
			'sites'   => count( $site_ids ),
			'added'   => 0,
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);

		foreach ( $user_ids as $user_id ) {
			foreach ( $site_ids as $site_id ) {
				$is_member = is_user_member_of_blog( $user_id, $site_id );

				if ( $is_member && ! $update_existing ) {
					++$result['skipped'];
					continue;
				}

				$added = add_user_to_blog( $site_id, $user_id, $role );

				if ( is_wp_error( $added ) ) {
					++$result['failed'];
				} elseif ( $is_member ) {
					++$result['updated'];
				} else {
					++$result['added'];
				}
			}
		}

		set_site_transient( self::RESULT_TRANSIENT . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( self::DONE_ARG, 1, network_admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Shows a summary of the last run on Network Admin › Users.
	 *
	 * The result is stored server-side per user rather than passed in the
	 * URL, so the notice can't be spoofed and only appears once.
	 */
	public function render_result_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
		if ( empty( $_GET[ self::DONE_ARG ] ) || ! current_user_can( self::CAP ) ) {
			return;
		}

		$key    = self::RESULT_TRANSIENT . get_current_user_id();
		$result = get_site_transient( $key );
		delete_site_transient( $key );

		if ( ! is_array( $result ) ) {
			return;
		}

		if ( 0 === $result['users'] || 0 === $result['sites'] ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'No users were added. Make sure at least one site was selected.', 'fk-bulk-user-editor' )
			);
			return;
		}

		$messages = array(
			sprintf(
				/* translators: 1: number of users, 2: number of sites */
				__( 'Users processed: %1$s. Sites selected: %2$s.', 'fk-bulk-user-editor' ),
				number_format_i18n( $result['users'] ),
				number_format_i18n( $result['sites'] )
			),
		);

		$parts = array(
			/* translators: %s: number of site memberships */
			'added'   => _n( '%s membership added.', '%s memberships added.', $result['added'], 'fk-bulk-user-editor' ),
			/* translators: %s: number of site memberships */
			'updated' => _n( '%s existing role changed.', '%s existing roles changed.', $result['updated'], 'fk-bulk-user-editor' ),
			/* translators: %s: number of site memberships */
			'skipped' => _n( '%s skipped (already a member).', '%s skipped (already members).', $result['skipped'], 'fk-bulk-user-editor' ),
			/* translators: %s: number of site memberships */
			'failed'  => _n( '%s failed.', '%s failed.', $result['failed'], 'fk-bulk-user-editor' ),
		);

		foreach ( $parts as $field => $format ) {
			if ( $result[ $field ] > 0 ) {
				$messages[] = sprintf( $format, number_format_i18n( $result[ $field ] ) );
			}
		}

		$type = $result['failed'] > 0 ? 'warning' : 'success';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( implode( ' ', $messages ) )
		);
	}

	/**
	 * Strips our result flag from the URL after the page loads, so a refresh
	 * or shared link doesn't try to show the notice again.
	 *
	 * @param string[] $args Removable query args.
	 * @return string[]
	 */
	public function removable_query_args( $args ) {
		$args[] = self::DONE_ARG;
		return $args;
	}
}
