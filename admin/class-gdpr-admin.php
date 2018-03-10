<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://trewknowledge.com
 * @since      1.0.0
 *
 * @package    GDPR
 * @subpackage GDPR/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    GDPR
 * @subpackage GDPR/admin
 * @author     Fernando Claussen <fernandoclaussen@gmail.com>
 */
class GDPR_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Holds the option name for the cookie banner content
	 * @var string
	 */
	protected static $key_cookie_banner_content = 'gdpr_cookie_banner_content';

	/**
	 * Holds the option name for the cookie popup tabs content
	 * @var string
	 */
	protected static $key_cookie_popup_content = 'gdpr_cookie_popup_content';

	/**
	 * Holds the option name for the cookie privacy excerpt.
	 * @var string
	 */
	protected static $key_cookie_privacy_excerpt = 'gdpr_cookie_privacy_excerpt';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		add_thickbox();
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/gdpr-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/gdpr-admin.js', array( 'jquery', 'wp-util' ), $this->version, false );

		wp_localize_script( $this->plugin_name, 'GDPR', array(
			'cookie_popup_content' => self::$key_cookie_popup_content
		) );
	}

	/**
	 * Adds a menu page for the plugin with all it's sub pages.
	 *
	 * @since   1.0.0
	 */
	public function add_menu() {
		$parent_menu_title = esc_html__( 'GDPR', 'gdpr' );
		$capability        = 'manage_options';
		$parent_slug       = 'gdpr-settings';
		$function          = array( $this, 'settings_page_template' );
		$icon_url          = 'dashicons-id';

		add_menu_page( $parent_menu_title, $parent_menu_title, $capability, $parent_slug, $function, $icon_url );

		$menu_title = esc_html__( 'Settings', 'gdpr' );
		$menu_slug  = 'gdpr-settings';
		$function   = array( $this, 'settings_page_template' );

		add_submenu_page( $parent_slug, $menu_title, $menu_title, $capability, $menu_slug, $function );

		$menu_title = esc_html__( 'Requests', 'gdpr' );
		$menu_slug  = 'gdpr-requests';
		$function   = array( $this, 'requests_page_template' );

		add_submenu_page( $parent_slug, $menu_title, $menu_title, $capability, $menu_slug, $function );

		$menu_slug  = 'edit.php?post_type=telemetry';

		$cpt = 'telemetry';
		$cpt_obj = get_post_type_object( $cpt );

		add_submenu_page( $parent_slug, $cpt_obj->labels->name, $cpt_obj->labels->menu_name, $cpt_obj->cap->edit_posts, $menu_slug );

	}

	function cookie_tabs_sanitize( $tabs ) {

		$output = array();

		foreach ( $tabs as $key => $props ) {
			if ( '' === $props['name'] || '' === $props['how_we_use'] || '' === $props['cookies_used'] ) {
				unset( $tabs[ $key ] );
				continue;
			}
			$output[ $key ] = array(
				'name'          => sanitize_text_field( wp_unslash( $props['name'] ) ),
				'always_active' => sanitize_text_field( wp_unslash( $props['always_active'] ) ),
				'how_we_use'    => wp_kses_post( $props['how_we_use'] ),
				'cookies_used'  => sanitize_text_field( wp_unslash( $props['cookies_used'] ) ),
			);

			if ( isset( $props['hosts'] ) ) {
				foreach ( $props['hosts'] as $host_key => $host ) {
					if ( empty( $host['name'] ) || empty( $host['cookies_used'] ) || empty( $host['cookies_used'] ) ) {
						unset( $props['hosts'][ $host_key ] );
						continue;
					}
					$output[ $key ]['hosts'][ $host_key ] = array(
						'name'         => sanitize_text_field( wp_unslash( $host['name'] ) ),
						'cookies_used' => sanitize_text_field( wp_unslash( $host['cookies_used'] ) ),
						'optout'       => esc_url_raw( $host['optout'] ),
					);
				}
			}
		}
		return $output;
	}

	public function register_settings() {
		register_setting( 'gdpr', self::$key_cookie_banner_content, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'gdpr', self::$key_cookie_privacy_excerpt, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'gdpr', self::$key_cookie_popup_content, array( 'sanitize_callback' => array( $this, 'cookie_tabs_sanitize' ) ) );

		$section_id       = 'cookie_banner_section';
		$admin_page_title = 'Cookie Settings';
		$page             = 'gdpr-settings';

		add_settings_section(
			$section_id,
			$admin_page_title,
			null,
			$page
		);

		$field_title = esc_html__( 'Banner content', 'gdpr' );
		add_settings_field(
			self::$key_cookie_banner_content,
			$field_title,
			array( $this, 'field_textarea' ),
			$page,
			$section_id,
			array(
				'label_for' => self::$key_cookie_banner_content,
			)
		);

		$field_title = esc_html__( 'Cookie Privacy Excerpt', 'gdpr' );
		add_settings_field(
			self::$key_cookie_privacy_excerpt,
			$field_title,
			array( $this, 'field_textarea' ),
			$page,
			$section_id,
			array(
				'label_for' => self::$key_cookie_privacy_excerpt,
			)
		);

		$field_title = esc_html__( 'Cookie Categories', 'gdpr' );
		add_settings_field(
			self::$key_cookie_popup_content,
			$field_title,
			array( $this, 'cookie_tabs' ),
			$page,
			$section_id,
			array(
				'label_for' => self::$key_cookie_popup_content,
			)
		);

	}


	function field_textarea( $args ) {
		if ( ! isset( $args['label_for'] ) || empty( $args['label_for'] ) ) {
			_doing_it_wrong( 'field_textarea', 'All settings fields must have the label_for argument.', '1.0.0' );
		}
		$value = get_option( $args['label_for'], '' );
		?>
		<textarea name="<?php echo esc_attr( $args['label_for'] ); ?>" id="<?php echo esc_attr( $args['label_for'] ); ?>" cols="53" rows="5"><?php echo wp_kses_post( $value ); ?></textarea>
		<?php if ( isset( $args['description'] ) && ! empty( $args['description'] ) ) : ?>
			<span class="description"><?php echo esc_html( $args['description'] ); ?></span>
		<?php endif; ?>
		<?php
	}

	function cookie_tabs( $args ) {
		if ( ! isset( $args['label_for'] ) || empty( $args['label_for'] ) ) {
			_doing_it_wrong( 'cookie_tabs', 'All settings fields must have the label_for argument.', '1.0.0' );
		}
		$value = get_option( $args['label_for'], array() );
		?>
		<input type="text" id="cookie-tabs" class="regular-text" placeholder="<?php esc_attr_e( 'Category name', 'gdpr' ); ?>">
		<button class="button button-primary add-tab"><?php esc_html_e( 'Add', 'gdpr' ); ?></button>
		<div id="tabs">
			<?php if ( ! empty( $value ) ) : ?>
				<?php foreach ( $value as $tab_key => $tab ) : ?>
					<div class="postbox" id="cookie-tab-content-<?php echo esc_attr( $tab_key ); ?>">
						<h2 class="hndle"><?php echo esc_html( $tab['name'] ); ?><button class="notice-dismiss" type="button"><span class="screen-reader-text"><?php esc_html_e( 'Remove this tab.', 'gdpr' ); ?></span></button></h2>
						<input type="hidden" name="<?php echo esc_attr( self::$key_cookie_popup_content ); ?>[<?php echo esc_attr( $tab_key ); ?>][name]" value="<?php echo esc_attr( $tab['name'] ); ?>" />
						<div class="inside">
							<table class="form-table">
								<tr>
									<th><label for="always-active-<?php echo esc_attr( $tab_key ); ?>"><?php esc_html_e( 'Always active', 'gdpr' ); ?></label></th>
									<td>
										<label class="gdpr-switch">
											<input type="checkbox" name="<?php echo esc_attr( self::$key_cookie_popup_content ); ?>[<?php echo esc_attr( $tab_key ); ?>][always_active]" <?php checked( esc_attr( $tab['always_active'] ), 'on' ); ?> id="always-active-<?php echo esc_attr( $tab_key ); ?>">
											<span class="gdpr-slider round"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><label for="tab-how-we-use-<?php echo esc_attr( $tab_key ); ?>"><?php esc_html_e( 'How we use', 'gdpr' ); ?></label></th>
									<td><textarea name="<?php echo esc_attr( self::$key_cookie_popup_content ); ?>[<?php echo esc_attr( $tab_key ); ?>][how_we_use]" id="tab-how-we-use-<?php echo esc_attr( $tab_key ); ?>" cols="53" rows="5"><?php echo esc_html( $tab['how_we_use'] ); ?></textarea></td>
								</tr>
								<tr>
									<th><label for="cookies-used-<?php echo esc_attr( $tab_key ); ?>"><?php esc_html_e( 'Cookies used by the site', 'gdpr' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo esc_attr( self::$key_cookie_popup_content ); ?>[<?php echo esc_attr( $tab_key ); ?>][cookies_used]" value="<?php echo esc_attr( $tab['cookies_used'] ); ?>" id="cookies-used-<?php echo esc_attr( $tab_key ); ?>" class="regular-text" />
										<br>
										<span class="description"><?php esc_html_e( 'Comma separated list.', 'gdpr' ); ?></span>
									</td>
								</tr>
								<tr>
									<th><label for="hosts-<?php echo esc_attr( $tab_key ); ?>"><?php esc_html_e( 'Hosts', 'gdpr' ); ?></label></th>
									<td>
										<input type="text" id="hosts-<?php echo esc_attr( $tab_key ); ?>" class="regular-text" />
										<button class="button button-primary add-host" data-tabid="<?php echo esc_attr( $tab_key ); ?>"><?php esc_html_e( 'Add', 'gdpr' ); ?></button>
										<br>
										<span class="description"><?php esc_html_e( '3rd party cookie hosts.', 'gdpr' ); ?></span>
									</td>
								</tr>
							</table>
							<div class="tab-hosts" data-tabid="<?php echo esc_attr( $tab_key ); ?>">
								<?php if ( $tab['hosts'] ) : ?>
									<?php foreach ( $tab['hosts'] as $host_key => $host ) : ?>
										<div class="postbox">
											<h2 class="hndle"><?php echo esc_attr( $host_key ); ?><button class="notice-dismiss" type="button"><span class="screen-reader-text"><?php esc_html_e( 'Remove this host.', 'gdpr' ); ?></span></button></h2>
											<input type="hidden" name="<?php echo esc_attr( self::$key_cookie_popup_content ); ?>[<?php echo esc_attr( $tab_key ); ?>][hosts][<?php echo esc_attr( $host_key ); ?>][name]" value="<?php echo esc_attr( $host_key ); ?>" />
											<div class="inside">
												<table class="form-table">
													<tr>
														<th><label for="hosts-cookies-used-<?php echo esc_attr( $host_key ); ?>"><?php esc_html_e( 'Cookies used', 'gdpr' ); ?></label></th>
														<td>
															<input type="text" name="<?php echo esc_attr( self::$key_cookie_popup_content ); ?>[<?php echo esc_attr( $tab_key ); ?>][hosts][<?php echo esc_attr( $host_key ); ?>][cookies_used]" value="<?php echo esc_attr( $host['cookies_used'] ); ?>" id="hosts-cookies-used-<?php echo esc_attr( $host_key ); ?>" class="regular-text" />
															<br>
															<span class="description"><?php esc_html_e( 'Comma separated list.', 'gdpr' ); ?></span>
														</td>
													</tr>
													<tr>
														<th><label for="hosts-cookies-optout-<?php echo esc_attr( $host_key ); ?>"><?php esc_html_e( 'How to Opt Out', 'gdpr' ); ?></label></th>
														<td>
															<input type="text" name="<?php echo esc_attr( self::$key_cookie_popup_content ); ?>[<?php echo esc_attr( $tab_key ); ?>][hosts][<?php echo esc_attr( $host_key ); ?>][optout]" value="<?php echo esc_attr( $host['optout'] ); ?>" id="hosts-cookies-optout-<?php echo esc_attr( $host_key ); ?>" class="regular-text" />
															<br>
															<span class="description"><?php esc_html_e( 'Url with instructions on how to opt out.', 'gdpr' ); ?></span>
														</td>
													</tr>
												</table>
											</div>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div><!-- .inside -->
					</div><!-- .postbox -->
				<?php endforeach ?>
			<?php endif ?>
		</div>
		<?php
	} // end sandbox_toggle_header_callback

	/**
	 * Settings Page Template
	 *
	 * @since 1.0.0
	 */
	public function settings_page_template() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'cookies'; // Input var okay. CSRF ok.
		$settings    = get_option( 'gdpr_options', array() );
		$tabs        = array(
			'cookies' => array(
				'name' => 'Cookies',
				'page' => 'gdpr-settings',
			),
		);

		$tabs = apply_filters( 'gdpr_settings_pages', $tabs );

		include plugin_dir_path( __FILE__ ) . 'partials/settings.php';
	}

	/**
	 * Requests Page Template
	 *
	 * @since 1.0.0
	 */
	public function requests_page_template() {
		$requests = ( array ) get_option( 'gdpr_requests', array() );
		// $requests = array(
		// 	array(
		// 		'email' => 'fernandoclaussen@gmail.com',
		// 		'date' =>  date( "F j, Y" ),
		// 		'type' =>  'delete',
		// 	),
		// 	array(
		// 		'email' => 'amoore@trewknowledge.com',
		// 		'date' =>  date( "F j, Y" ),
		// 		'type' =>  'delete',
		// 	),
		// 	array(
		// 		'email' => 'amoore@trewknowledge.com',
		// 		'date' =>  date( "F j, Y" ),
		// 		'type' =>  'access',
		// 	),
		// 	array(
		// 		'email' => 'sbarrans@trewknowledge.com',
		// 		'date' =>  date( "F j, Y" ),
		// 		'type' =>  'access',
		// 	),
		// 	array(
		// 		'email' => 'sbarrans@trewknowledge.com',
		// 		'date'  => date( "F j, Y" ),
		// 		'type'  => 'rectify',
		// 		'data'  => 'Data X is wrong. Please fix it to Y.',
		// 	),
		// 	array(
		// 		'email' => 'fernandoclaussen@gmail.com',
		// 		'date'  => date( "F j, Y" ),
		// 		'type'  => 'portability',
		// 	),
		// 	array(
		// 		'email' => 'mfarlymn@trewknowledge.com',
		// 		'date'  => date( "F j, Y" ),
		// 		'type'  => 'complaint',
		// 		'data'  => 'I want to complain because of reasons.',
		// 	),
		// );

		foreach ( $requests as $request ) {
			${$request['type']}[] = $request;
		}

		$tabs = array(
			'access' => array(
				'name' => 'Access Data',
				'count' => isset( $access ) ? count( $access ) : 0,
			),
			'rectify' => array(
				'name' => 'Rectify Data',
				'count' => isset( $rectify ) ? count( $rectify ) : 0,
			),
			'portability' => array(
				'name' => 'Data Portability',
				'count' => isset( $portability ) ? count( $portability ) : 0,
			),
			'complaint' => array(
				'name' => 'Complaint',
				'count' => isset( $complaint ) ? count( $complaint ) : 0,
			),
			'delete' => array(
				'name' => 'Erasure',
				'count' => isset( $delete ) ? count( $delete ) : 0,
			),
		);

		include plugin_dir_path( __FILE__ ) . 'partials/requests.php';
	}

	function user_has_content( $user ) {
		if ( ! $user instanceof WP_User ) {
			if ( ! is_int( $user ) ) {
				return;
			}
			$user = get_user_by( 'ID', $user );
		}

		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $pt ) {
			$post_count = count_user_posts( $user->ID, $pt);
			if ( $post_count > 0 ) {
				return true;
			}
		}

		$comments = get_comments( array(
			'author_email' => $user->user_email,
			'include_unapproved' => true,
			'number' => 1,
			'count' => true,
		) );

		if ( $comments ) {
			return true;
		}

		$extra_checks = apply_filters( 'gdpr_user_has_content', false );

		return $extra_checks;
	}

	protected function delete_user( $user ) {
		if ( ! $user instanceof WP_User ) {
			if ( ! is_int( $user ) ) {
				add_settings_error( 'gdpr-requests', 'invalid-user', esc_html__( 'An invalid user was provided for deletion. It must either be a WP_User object or a user ID.', 'gdpr' ), 'error' );
			}
			$user = get_user_by( 'ID', $user );
		}

		do_action( 'gdpr_delete_extra_content', $user );

		return wp_delete_user( $user->ID );
	}

	private function _remove_from_requests( $email, $type ) {
		$requests = ( array ) get_option( 'gdpr_requests', array() );
		$email = sanitize_email( $email );
		$type = sanitize_text_field( $type );

		$filtered_requests = array_filter( $requests, function( $arr ) use ( $type ) {
			return $type === $arr['type'];
		});
		$index = array_search( $email, array_column( $filtered_requests, 'email' ) );
		$request_index = array_keys( $requests, $filtered_requests[ $index ] );
		$request_index = $request_index[0];

		unset( $requests[ $request_index ] );
		update_option( 'gdpr_requests', $requests );
	}

	function remove_from_deletion_requests() {
		if ( ! isset( $_REQUEST['gdpr_delete_remove_user'], $_REQUEST['user_email'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['gdpr_delete_remove_user'] ), 'gdpr-request-delete-user' ) ) {
			wp_die( esc_html__( 'We could not verify the user email or the security token. Please try again.', 'gdpr' ) );
		}

		$email = sanitize_email( $_REQUEST['user_email'] );

		$this->_remove_from_requests( $email, 'delete' );

		add_settings_error( 'gdpr-requests', 'remove-request', sprintf( esc_html__( 'User %s was removed from the deletion table.', 'gdpr' ), $email ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'settings-updated' => true
					),
					wp_get_referer() . '#delete'
				)
			)
		);
	}

	function add_to_deletion_requests() {
		if ( ! isset( $_REQUEST['gdpr_delete_email_lookup'], $_REQUEST['user_email'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['gdpr_delete_email_lookup'] ), 'gdpr-request-email-lookup' ) ) {
			wp_die( esc_html__( 'We could not verify the user email or the security token. Please try again.', 'gdpr' ) );
		}

		$email = sanitize_email( $_REQUEST['user_email'] );
		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof WP_User ) {
			add_settings_error( 'gdpr-requests', 'invalid-user', esc_html__( 'User not found.', 'gdpr' ), 'error' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'settings-updated' => true
						),
						wp_get_referer() . '#delete'
					)
				)
			);
			exit;
		}

		$requests = ( array ) get_option( 'gdpr_requests', array() );

		if ( empty( $requests ) ) {
			$requests[] = array(
				'email' => $email,
				'date'  => date( "F j, Y" ),
				'type'  => 'delete',
			);
			update_option( 'gdpr_requests', $requests );
			add_settings_error( 'gdpr-requests', 'new-request', sprintf( esc_html__( 'User %s was added to the deletion table.', 'gdpr' ), $email ), 'updated' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'settings-updated' => true
						),
						wp_get_referer() . '#delete'
					)
				)
			);
			exit;
		}

		$deletion_requests = array_filter( $requests, function( $arr ) {
			return 'delete' === $arr['type'];
		});
		$user_has_already_requested = array_search( $email, array_column( $deletion_requests, 'email' ) );

		if ( false !== $user_has_already_requested ) {
			wp_safe_redirect( home_url( esc_url( $_REQUEST['_wp_http_referer'] ) ) );
			add_settings_error( 'gdpr-requests', 'invalid-user', esc_html__( 'User already placed a deletion request.', 'gdpr' ), 'error' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'settings-updated' => true
						),
						wp_get_referer() . '#delete'
					)
				)
			);
			exit;
		}

		$requests[] = array(
			'email' => $email,
			'date'  => date( "F j, Y" ),
			'type'  => 'delete',
		);

		update_option( 'gdpr_requests', $requests );

		add_settings_error( 'gdpr-requests', 'new-request', sprintf( esc_html__( 'User %s was added to the deletion table.', 'gdpr' ), $email ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'settings-updated' => true
					),
					wp_get_referer() . '#delete'
				)
			)
		);
		exit;
	}

	function process_user_deletion() {
		if ( ! isset( $_REQUEST['gdpr_delete_user'], $_REQUEST['user_email'] ) || ! wp_verify_nonce( $_REQUEST['gdpr_delete_user'], 'gdpr-request-delete-user' ) ) {
			wp_die( esc_html__( 'We could not verify the user email or the security token. Please try again.', 'gdpr' ) );
		}

		$email = sanitize_email( $_REQUEST['user_email'] );
		$user = get_user_by( 'email', $email );
		$this->delete_user( $user );

		$this->_remove_from_requests( $email, 'delete' );

		add_settings_error( 'gdpr-requests', 'new-request', sprintf( esc_html__( 'User %s was deleted from the site.', 'gdpr' ), $email ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'settings-updated' => true
					),
					wp_get_referer() . '#delete'
				)
			)
		);
		exit;
	}

}
