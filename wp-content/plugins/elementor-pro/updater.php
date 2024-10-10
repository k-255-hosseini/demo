<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allows plugins to use their own update API.
 *
 * @author Easy Digital Downloads
 * @version 1.9.2
 */
class EDD_SL_Plugin_Updater {

	private $api_url              = '';
	private $api_data             = array();
	private $plugin_file          = '';
	private $name                 = '';
	private $slug                 = '';
	private $version              = '';
	private $wp_override          = false;
	private $beta                 = false;
	private $failed_request_cache_key;

	/**
	 * Class constructor.
	 *
	 * @uses plugin_basename()
	 * @uses hook()
	 *
	 * @param string  $_api_url     The URL pointing to the custom API endpoint.
	 * @param string  $_plugin_file Path to the plugin file.
	 * @param array   $_api_data    Optional data to send with API calls.
	 */
	public function __construct( $_api_url, $_plugin_file, $_api_data = null ) {

		global $edd_plugin_data;

		$this->api_url                  = trailingslashit( $_api_url );
		$this->api_data                 = $_api_data;
		$this->plugin_file              = $_plugin_file;
		$this->name                     = plugin_basename( $_plugin_file );
		$this->slug                     = basename( $_plugin_file, '.php' );
		$this->version                  = $_api_data['version'];
		$this->wp_override              = isset( $_api_data['wp_override'] ) ? (bool) $_api_data['wp_override'] : false;
		$this->beta                     = ! empty( $this->api_data['beta'] ) ? true : false;
		$this->failed_request_cache_key = 'edd_sl_failed_http_' . md5( $this->api_url );

		$edd_plugin_data[ $this->slug ] = $this->api_data;

		/**
		 * Fires after the $edd_plugin_data is setup.
		 *
		 * @since x.x.x
		 *
		 * @param array $edd_plugin_data Array of EDD SL plugin data.
		 */
		do_action( 'post_edd_sl_plugin_updater_setup', $edd_plugin_data );

		// Set up hooks.
		$this->init();

	}

	/**
	 * Set up WordPress filters to hook into WP's update process.
	 *
	 * @uses add_filter()
	 *
	 * @return void
	 */
	public function init() {

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
		add_action( 'after_plugin_row', array( $this, 'show_update_notification' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'show_changelog' ) );

	}

	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update API just when WordPress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native WordPress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @uses api_request()
	 *
	 * @param array   $_transient_data Update array build by WordPress.
	 * @return array Modified update array with custom plugin data.
	 */
	public function check_update( $_transient_data ) {

		global $pagenow;

		if ( ! is_object( $_transient_data ) ) {
			$_transient_data = new stdClass();
		}

		if ( ! empty( $_transient_data->response ) && ! empty( $_transient_data->response[ $this->name ] ) && false === $this->wp_override ) {
			return $_transient_data;
		}

		$current = $this->get_repo_api_data();
		if ( false !== $current && is_object( $current ) && isset( $current->new_version ) ) {
			if ( version_compare( $this->version, $current->new_version, '<' ) ) {
				$_transient_data->response[ $this->name ] = $current;
			} else {
				// Populating the no_update information is required to support auto-updates in WordPress 5.5.
				$_transient_data->no_update[ $this->name ] = $current;
			}
		}
		$_transient_data->last_checked           = time();
		$_transient_data->checked[ $this->name ] = $this->version;

		return $_transient_data;
	}

	/**
	 * Get repo API data from store.
	 * Save to cache.
	 *
	 * @return \stdClass
	 */
	public function get_repo_api_data() {
		$version_info = $this->get_cached_version_info();

		if ( false === $version_info ) {
			$version_info = $this->api_request(
				'plugin_latest_version',
				array(
					'slug' => $this->slug,
					'beta' => $this->beta,
				)
			);
			if ( ! $version_info ) {
				return false;
			}

			// This is required for your plugin to support auto-updates in WordPress 5.5.
			$version_info->plugin = $this->name;
			$version_info->id     = $this->name;
			$version_info->tested = $this->get_tested_version( $version_info );

			$this->set_version_info_cache( $version_info );
		}

		return $version_info;
	}

	/**
	 * Gets the plugin's tested version.
	 *
	 * @since 1.9.2
	 * @param object $version_info
	 * @return null|string
	 */
	private function get_tested_version( $version_info ) {

		// There is no tested version.
		if ( empty( $version_info->tested ) ) {
			return null;
		}

		// Strip off extra version data so the result is x.y or x.y.z.
		list( $current_wp_version ) = explode( '-', get_bloginfo( 'version' ) );

		// The tested version is greater than or equal to the current WP version, no need to do anything.
		if ( version_compare( $version_info->tested, $current_wp_version, '>=' ) ) {
			return $version_info->tested;
		}
		$current_version_parts = explode( '.', $current_wp_version );
		$tested_parts          = explode( '.', $version_info->tested );

		// The current WordPress version is x.y.z, so update the tested version to match it.
		if ( isset( $current_version_parts[2] ) && $current_version_parts[0] === $tested_parts[0] && $current_version_parts[1] === $tested_parts[1] ) {
			$tested_parts[2] = $current_version_parts[2];
		}

		return implode( '.', $tested_parts );
	}

	/**
	 * Show the update notification on multisite subsites.
	 *
	 * @param string  $file
	 * @param array   $plugin
	 */
	public function show_update_notification( $file, $plugin ) {

		// Return early if in the network admin, or if this is not a multisite install.
		if ( is_network_admin() || ! is_multisite() ) {
			return;
		}

		// Allow single site admins to see that an update is available.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( $this->name !== $file ) {
			return;
		}

		// Do not print any message if update does not exist.
		$update_cache = get_site_transient( 'update_plugins' );

		if ( ! isset( $update_cache->response[ $this->name ] ) ) {
			if ( ! is_object( $update_cache ) ) {
				$update_cache = new stdClass();
			}
			$update_cache->response[ $this->name ] = $this->get_repo_api_data();
		}

		// Return early if this plugin isn't in the transient->response or if the site is running the current or newer version of the plugin.
		if ( empty( $update_cache->response[ $this->name ] ) || version_compare( $this->version, $update_cache->response[ $this->name ]->new_version, '>=' ) ) {
			return;
		}

		printf(
			'<tr class="plugin-update-tr %3$s" id="%1$s-update" data-slug="%1$s" data-plugin="%2$s">',
			$this->slug,
			$file,
			in_array( $this->name, $this->get_active_plugins(), true ) ? 'active' : 'inactive'
		);

		echo '<td colspan="3" class="plugin-update colspanchange">';
		echo '<div class="update-message notice inline notice-warning notice-alt"><p>';

		$changelog_link = '';
		if ( ! empty( $update_cache->response[ $this->name ]->sections->changelog ) ) {
			$changelog_link = add_query_arg(
				array(
					'edd_sl_action' => 'view_plugin_changelog',
					'plugin'        => urlencode( $this->name ),
					'slug'          => urlencode( $this->slug ),
					'TB_iframe'     => 'true',
					'width'         => 77,
					'height'        => 911,
				),
				self_admin_url( 'index.php' )
			);
		}
		$update_link = add_query_arg(
			array(
				'action' => 'upgrade-plugin',
				'plugin' => urlencode( $this->name ),
			),
			self_admin_url( 'update.php' )
		);

		printf(
			/* translators: the plugin name. */
			esc_html__( 'There is a new version of %1$s available.', 'easy-digital-downloads' ),
			esc_html( $plugin['Name'] )
		);

		if ( ! current_user_can( 'update_plugins' ) ) {
			echo ' ';
			esc_html_e( 'Contact your network administrator to install the update.', 'easy-digital-downloads' );
		} elseif ( empty( $update_cache->response[ $this->name ]->package ) && ! empty( $changelog_link ) ) {
			echo ' ';
			printf(
				/* translators: 1. opening anchor tag, do not translate 2. the new plugin version 3. closing anchor tag, do not translate. */
				__( '%1$sView version %2$s details%3$s.', 'easy-digital-downloads' ),
				'<a target="_blank" class="thickbox open-plugin-details-modal" href="' . esc_url( $changelog_link ) . '">',
				esc_html( $update_cache->response[ $this->name ]->new_version ),
				'</a>'
			);
		} elseif ( ! empty( $changelog_link ) ) {
			echo ' ';
			printf(
				__( '%1$sView version %2$s details%3$s or %4$supdate now%5$s.', 'easy-digital-downloads' ),
				'<a target="_blank" class="thickbox open-plugin-details-modal" href="' . esc_url( $changelog_link ) . '">',
				esc_html( $update_cache->response[ $this->name ]->new_version ),
				'</a>',
				'<a target="_blank" class="update-link" href="' . esc_url( wp_nonce_url( $update_link, 'upgrade-plugin_' . $file ) ) . '">',
				'</a>'
			);
		} else {
			printf(
				' %1$s%2$s%3$s',
				'<a target="_blank" class="update-link" href="' . esc_url( wp_nonce_url( $update_link, 'upgrade-plugin_' . $file ) ) . '">',
				esc_html__( 'Update now.', 'easy-digital-downloads' ),
				'</a>'
			);
		}

		do_action( "in_plugin_update_message-{$file}", $plugin, $plugin );

		echo '</p></div></td></tr>';
	}

	/**
	 * Gets the plugins active in a multisite network.
	 *
	 * @return array
	 */
	private function get_active_plugins() {
		$active_plugins         = (array) get_option( 'active_plugins' );
		$active_network_plugins = (array) get_site_option( 'active_sitewide_plugins' );

		return array_merge( $active_plugins, array_keys( $active_network_plugins ) );
	}

	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param mixed   $_data
	 * @param string  $_action
	 * @param object  $_args
	 * @return object $_data
	 */
	public function plugins_api_filter( $_data, $_action = '', $_args = null ) {

		if ( 'plugin_information' !== $_action ) {

			return $_data;

		}

		if ( ! isset( $_args->slug ) || ( $_args->slug !== $this->slug ) ) {

			return $_data;

		}

		$to_send = array(
			'slug'   => $this->slug,
			'is_ssl' => is_ssl(),
			'fields' => array(
				'banners' => array(),
				'reviews' => false,
				'icons'   => array(),
			),
		);

		// Get the transient where we store the api request for this plugin for 24 hours
		$edd_api_request_transient = $this->get_cached_version_info();

		//If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
		if ( empty( $edd_api_request_transient ) ) {

			$api_response = $this->api_request( 'plugin_information', $to_send );

			// Expires in 3 hours
			$this->set_version_info_cache( $api_response );

			if ( false !== $api_response ) {
				$_data = $api_response;
			}
		} else {
			$_data = $edd_api_request_transient;
		}

		// Convert sections into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->sections ) && ! is_array( $_data->sections ) ) {
			$_data->sections = $this->convert_object_to_array( $_data->sections );
		}

		// Convert banners into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->banners ) && ! is_array( $_data->banners ) ) {
			$_data->banners = $this->convert_object_to_array( $_data->banners );
		}

		// Convert icons into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->icons ) && ! is_array( $_data->icons ) ) {
			$_data->icons = $this->convert_object_to_array( $_data->icons );
		}

		// Convert contributors into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->contributors ) && ! is_array( $_data->contributors ) ) {
			$_data->contributors = $this->convert_object_to_array( $_data->contributors );
		}

		if ( ! isset( $_data->plugin ) ) {
			$_data->plugin = $this->name;
		}

		return $_data;
	}

	/**
	 * Convert some objects to arrays when injecting data into the update API
	 *
	 * Some data like sections, banners, and icons are expected to be an associative array, however due to the JSON
	 * decoding, they are objects. This method allows us to pass in the object and return an associative array.
	 *
	 * @since 3.6.5
	 *
	 * @param stdClass $data
	 *
	 * @return array
	 */
	private function convert_object_to_array( $data ) {
		if ( ! is_array( $data ) && ! is_object( $data ) ) {
			return array();
		}
		$new_data = array();
		foreach ( $data as $key => $value ) {
			$new_data[ $key ] = is_object( $value ) ? $this->convert_object_to_array( $value ) : $value;
		}

		return $new_data;
	}

	/**
	 * Disable SSL verification in order to prevent download update failures
	 *
	 * @param array   $args
	 * @param string  $url
	 * @return object $array
	 */
	public function http_request_args( $args, $url ) {

		if ( strpos( $url, 'https://' ) !== false && strpos( $url, 'edd_action=package_download' ) ) {
			$args['sslverify'] = $this->verify_ssl();
		}
		return $args;

	}

	/**
	 * Calls the API and, if successfull, returns the object delivered by the API.
	 *
	 * @uses get_bloginfo()
	 * @uses wp_remote_post()
	 * @uses is_wp_error()
	 *
	 * @param string  $_action The requested action.
	 * @param array   $_data   Parameters for the API action.
	 * @return false|object|void
	 */
	private function api_request( $_action, $_data ) {
		$data = array_merge( $this->api_data, $_data );

		if ( $data['slug'] !== $this->slug ) {
			return;
		}

		// Don't allow a plugin to ping itself
		if ( trailingslashit( home_url() ) === $this->api_url ) {
			return false;
		}

		if ( $this->request_recently_failed() ) {
			return false;
		}

		return $this->get_version_from_remote();
	}

	/**
	 * Determines if a request has recently failed.
	 *
	 * @since 1.9.1
	 *
	 * @return bool
	 */
	private function request_recently_failed() {
		$failed_request_details = get_option( $this->failed_request_cache_key );

		// Request has never failed.
		if ( empty( $failed_request_details ) || ! is_numeric( $failed_request_details ) ) {
			return false;
		}

		/*
		 * Request previously failed, but the timeout has expired.
		 * This means we're allowed to try again.
		 */
		if ( time() > $failed_request_details ) {
			delete_option( $this->failed_request_cache_key );

			return false;
		}

		return true;
	}

	/**
	 * Logs a failed HTTP request for this API URL.
	 * We set a timestamp for 1 hour from now. This prevents future API requests from being
	 * made to this domain for 1 hour. Once the timestamp is in the past, API requests
	 * will be allowed again. This way if the site is down for some reason we don't bombard
	 * it with failed API requests.
	 *
	 * @see EDD_SL_Plugin_Updater::request_recently_failed
	 *
	 * @since 1.9.1
	 */
	private function log_failed_request() {
		update_option( $this->failed_request_cache_key, strtotime( '+1 hour' ) );
	}

	/**
	 * If available, show the changelog for sites in a multisite install.
	 */
	public function show_changelog() {

		if ( empty( $_REQUEST['edd_sl_action'] ) || 'view_plugin_changelog' !== $_REQUEST['edd_sl_action'] ) {
			return;
		}

		if ( empty( $_REQUEST['plugin'] ) ) {
			return;
		}

		if ( empty( $_REQUEST['slug'] ) || $this->slug !== $_REQUEST['slug'] ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to install plugin updates', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
		}

		$version_info = $this->get_repo_api_data();
		if ( isset( $version_info->sections ) ) {
			$sections = $this->convert_object_to_array( $version_info->sections );
			if ( ! empty( $sections['changelog'] ) ) {
				echo '<div style="background:#fff;padding:10px;">' . wp_kses_post( $sections['changelog'] ) . '</div>';
			}
		}

		exit;
	}

	/**
	 * Gets the current version information from the remote site.
	 *
	 * @return array|false
	 */
	private function get_version_from_remote() {
		$api_params = array(
			'edd_action'  => 'get_version',
			'license'     => ! empty( $this->api_data['license'] ) ? $this->api_data['license'] : '',
			'item_name'   => isset( $this->api_data['item_name'] ) ? $this->api_data['item_name'] : false,
			'item_id'     => isset( $this->api_data['item_id'] ) ? $this->api_data['item_id'] : false,
			'version'     => isset( $this->api_data['version'] ) ? $this->api_data['version'] : false,
			'slug'        => $this->slug,
			'author'      => $this->api_data['author'],
			'url'         => home_url(),
			'beta'        => $this->beta,
			'php_version' => phpversion(),
			'wp_version'  => get_bloginfo( 'version' ),
		);

		/**
		 * Filters the parameters sent in the API request.
		 *
		 * @param array  $api_params        The array of data sent in the request.
		 * @param array  $this->api_data    The array of data set up in the class constructor.
		 * @param string $this->plugin_file The full path and filename of the file.
		 */
		$api_params = apply_filters( 'edd_sl_plugin_updater_api_params', $api_params, $this->api_data, $this->plugin_file );

		$request = wp_remote_post(
			$this->api_url,
			array(
				'timeout'   => 15,
				'sslverify' => $this->verify_ssl(),
				'body'      => $api_params,
			)
		);

		if ( is_wp_error( $request ) || ( 200 !== wp_remote_retrieve_response_code( $request ) ) ) {
			$this->log_failed_request();

			return false;
		}

		$request = json_decode( wp_remote_retrieve_body( $request ) );

		if ( $request && isset( $request->sections ) ) {
			$request->sections = maybe_unserialize( $request->sections );
		} else {
			$request = false;
		}

		if ( $request && isset( $request->banners ) ) {
			$request->banners = maybe_unserialize( $request->banners );
		}

		if ( $request && isset( $request->icons ) ) {
			$request->icons = maybe_unserialize( $request->icons );
		}

		if ( ! empty( $request->sections ) ) {
			foreach ( $request->sections as $key => $section ) {
				$request->$key = (array) $section;
			}
		}

		return $request;
	}

	/**
	 * Get the version info from the cache, if it exists.
	 *
	 * @param string $cache_key
	 * @return object
	 */
	public function get_cached_version_info( $cache_key = '' ) {

		if ( empty( $cache_key ) ) {
			$cache_key = $this->get_cache_key();
		}

		$cache = get_option( $cache_key );

		// Cache is expired
		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false;
		}

		// We need to turn the icons into an array, thanks to WP Core forcing these into an object at some point.
		$cache['value'] = json_decode( $cache['value'] );
		if ( ! empty( $cache['value']->icons ) ) {
			$cache['value']->icons = (array) $cache['value']->icons;
		}

		return $cache['value'];

	}

	/**
	 * Adds the plugin version information to the database.
	 *
	 * @param string $value
	 * @param string $cache_key
	 */
	public function set_version_info_cache( $value = '', $cache_key = '' ) {

		if ( empty( $cache_key ) ) {
			$cache_key = $this->get_cache_key();
		}

		$data = array(
			'timeout' => strtotime( '+3 hours', time() ),
			'value'   => wp_json_encode( $value ),
		);

		update_option( $cache_key, $data, 'no' );

		// Delete the duplicate option
		delete_option( 'edd_api_request_' . md5( serialize( $this->slug . $this->api_data['license'] . $this->beta ) ) );
	}

	/**
	 * Returns if the SSL of the store should be verified.
	 *
	 * @since  1.6.13
	 * @return bool
	 */
	private function verify_ssl() {
		return (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true, $this );
	}

	/**
	 * Gets the unique key (option name) for a plugin.
	 *
	 * @since 1.9.0
	 * @return string
	 */
	private function get_cache_key() {
		$string = $this->slug . $this->api_data['license'] . $this->beta;

		return 'edd_sl_' . md5( serialize( $string ) );
	}

}

/************************************
* the code below is just a standard
* options page. Substitute with
* your own.
*************************************/

/**
 * Adds the plugin license page to the admin menu.
 *
 * @return void
 */
function efa_2710_license_menu() {
	add_plugins_page(
		__( 'Elementor Pro License' ),
		__( 'Elementor Pro License' ),
		'manage_options',
		EFA_2710_LICENSE_PAGE,
		'efa_2710_license_page'
	);
}
add_action( 'admin_menu', 'efa_2710_license_menu' );

function efa_2710_license_page() {
	add_settings_section(
		'efa_2710_license',
		__( 'Elementor Pro License' ),
		'efa_2710_license_key_settings_section',
		EFA_2710_LICENSE_PAGE
	);
	add_settings_field(
		'efa_2710_license_key',
		'<label for="efa_2710_license_key">' . __( 'License Key' ) . '</label>',
		'efa_2710_license_key_settings_field',
		EFA_2710_LICENSE_PAGE,
		'efa_2710_license',
	);
	?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Activate the plugin license' ); ?></h2>
		<form method="post" action="options.php">

			<?php
			do_settings_sections( EFA_2710_LICENSE_PAGE );
			settings_fields( 'efa_2710_license' );
			submit_button();
			?>

		</form>
	<?php
}

/**
 * Adds content to the settings section.
 *
 * @return void
 */
function efa_2710_license_key_settings_section() {
	esc_html_e( '' );
}

/**
 * Outputs the license key settings field.
 *
 * @return void
 */
function efa_2710_license_key_settings_field() {
	$license = get_option( 'efa_2710_license_key' );
	$status  = get_option( 'efa_2710_license_status' );
	printf(
		'<input type="text" class="regular-text" id="efa_2710_license_key" name="efa_2710_license_key" value="%s" />',
		esc_attr( $license )
	);
	$button = array(
		'name'  => 'edd_license_deactivate',
		'label' => __( 'Deactive License' ),
	);
	if ( 'valid' !== $status ) {
		$button = array(
			'name'  => 'edd_license_activate',
			'label' => __( 'Active License' ),
		);
	}
	wp_nonce_field( 'efa_2710_nonce', 'efa_2710_nonce' );
	?>
	<input style="background: #92003b!important; border-color: #92003b!important; color: #FFF!important; outline: none!important;" type="submit" class="button-secondary" name="<?php echo esc_attr( $button['name'] ); ?>" value="<?php echo esc_attr( $button['label'] ); ?>"/>
	<?php
	
	if ( 'valid' == $status ) {
	?>
	<p class="description" style="background: #e9fcf2; color: #0f6738; width: fit-content; padding: 5px; border-radius: 4px;"><?php esc_html_e( 'The license has been successfully activated' ); ?></p>
	<?php
}
	if ( 'valid' !== $status ) {
	?>
	<p class="description" style="background: #fde8eb; color: #b21028; width: fit-content; padding: 5px; border-radius: 4px;"><?php esc_html_e( 'Your license is inactive. Click on the activation button' ); ?></p>
	<?php
}
}

/**
 * Registers the license key setting in the options table.
 *
 * @return void
 */
function efa_2710_register_option() {
	register_setting( 'efa_2710_license', 'efa_2710_license_key', 'edd_sanitize_license' );
}
add_action( 'admin_init', 'efa_2710_register_option' );

/**
 * Sanitizes the license key.
 *
 * @param string  $new The license key.
 * @return string
 */
function edd_sanitize_license( $new ) {
	$old = get_option( 'efa_2710_license_key' );
	if ( $old && $old !== $new ) {
		delete_option( 'efa_2710_license_status' ); // new license has been entered, so must reactivate
	}

	return sanitize_text_field( $new );
}

/**
 * Activates the license key.
 *
 * @return void
 */
function efa_2710_activate_license() {

	// listen for our activate button to be clicked
	if ( ! isset( $_POST['edd_license_activate'] ) ) {
		return;
	}

	// run a quick security check
	if ( ! check_admin_referer( 'efa_2710_nonce', 'efa_2710_nonce' ) ) {
		return; // get out if we didn't click the Activate button
	}

	// retrieve the license from the database
	$license = trim( get_option( 'efa_2710_license_key' ) );
	if ( ! $license ) {
		$license = ! empty( $_POST['efa_2710_license_key'] ) ? sanitize_text_field( $_POST['efa_2710_license_key'] ) : '';
	}
	if ( ! $license ) {
		return;
	}

	// data to send in our API request
	$api_params = array(
		'edd_action'  => 'activate_license',
		'license'     => $license,
		'item_id'     => EFA_2710_ITEM_ID,
		'item_name'   => rawurlencode( EFA_2710_ITEM_NAME ), // the name of our product in EDD
		'url'         => home_url(),
		'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
	);

	// Call the custom API.
	$response = wp_remote_post(
		EFA_2710_STORE_URL,
		array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params,
		)
	);

		// make sure the response came back okay
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
		} else {
			$message = __( 'An error occurred, please try again.' );
		}
	} else {

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( false === $license_data->success ) {

			switch ( $license_data->error ) {

				case 'expired':
					$message = sprintf(
						/* translators: the license key expiration date */
						__( 'Your license key expired on %s.', 'efa-2710-plugin' ),
						date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
					);
					break;

				case 'disabled':
				case 'revoked':
					$message = __( 'Your license key has been disabled.', 'efa-2710-plugin' );
					break;

				case 'missing':
					$message = __( 'Invalid license.', 'efa-2710-plugin' );
					break;

				case 'invalid':
				case 'site_inactive':
					$message = __( 'Your license is not active for this URL.', 'efa-2710-plugin' );
					break;

				case 'item_name_mismatch':
					/* translators: the plugin name */
					$message = sprintf( __( 'This appears to be an invalid license key for %s.', 'efa-2710-plugin' ), EFA_2710_ITEM_NAME );
					break;

				case 'no_activations_left':
					$message = __( 'Your license key has reached its activation limit.', 'efa-2710-plugin' );
					break;

				default:
					$message = __( 'An error occurred, please try again.', 'efa-2710-plugin' );
					break;
			}
		}
	}

		// Check if anything passed on a message constituting a failure
	if ( ! empty( $message ) ) {
		$redirect = add_query_arg(
			array(
				'page'          => EFA_2710_LICENSE_PAGE,
				'sl_activation' => 'false',
				'message'       => rawurlencode( $message ),
			),
			admin_url( 'plugins.php' )
		);

		wp_safe_redirect( $redirect );
		exit();
	}

	// $license_data->license will be either "valid" or "invalid"
	if ( 'valid' === $license_data->license ) {
		update_option( 'efa_2710_license_key', $license );
	}
	update_option( 'efa_2710_license_status', $license_data->license );
	wp_safe_redirect( admin_url( 'plugins.php?page=' . EFA_2710_LICENSE_PAGE ) );
	exit();
}
add_action( 'admin_init', 'efa_2710_activate_license' );

/**
 * Deactivates the license key.
 * This will decrease the site count.
 *
 * @return void
 */
function efa_2710_deactivate_license() {

	// listen for our activate button to be clicked
	if ( isset( $_POST['edd_license_deactivate'] ) ) {

		// run a quick security check
		if ( ! check_admin_referer( 'efa_2710_nonce', 'efa_2710_nonce' ) ) {
			return; // get out if we didn't click the Activate button
		}

		// retrieve the license from the database
		$license = trim( get_option( 'efa_2710_license_key' ) );

		// data to send in our API request
		$api_params = array(
			'edd_action'  => 'deactivate_license',
			'license'     => $license,
			'item_id'     => EFA_2710_ITEM_ID,
			'item_name'   => rawurlencode( EFA_2710_ITEM_NAME ), // the name of our product in EDD
			'url'         => home_url(),
			'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
		);

		// Call the custom API.
		$response = wp_remote_post(
			EFA_2710_STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.' );
			}

			$redirect = add_query_arg(
				array(
					'page'          => EFA_2710_LICENSE_PAGE,
					'sl_activation' => 'false',
					'message'       => rawurlencode( $message ),
				),
				admin_url( 'plugins.php' )
			);

			wp_safe_redirect( $redirect );
			exit();
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		if ( 'deactivated' === $license_data->license ) {
			delete_option( 'efa_2710_license_status' );
		}

		wp_safe_redirect( admin_url( 'plugins.php?page=' . EFA_2710_LICENSE_PAGE ) );
		exit();

	}
}
add_action( 'admin_init', 'efa_2710_deactivate_license' );

/**
 * Checks if a license key is still valid.
 * The updater does this for you, so this is only needed if you want
 * to do somemthing custom.
 *
 * @return void
 */
function efa_2710_check_license() {

	$license = trim( get_option( 'efa_2710_license_key' ) );

	$api_params = array(
		'edd_action'  => 'check_license',
		'license'     => $license,
		'item_id'     => EFA_2710_ITEM_ID,
		'item_name'   => rawurlencode( EFA_2710_ITEM_NAME ),
		'url'         => home_url(),
		'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
	);

	// Call the custom API.
	$response = wp_remote_post(
		EFA_2710_STORE_URL,
		array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params,
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	if ( 'valid' === $license_data->license ) {
		echo 'valid';
		exit;
		// this license is still valid
	} else {
		echo 'invalid';
		exit;
		// this license is no longer valid
	}
}

/**
 * This is a means of catching errors from the activation method above and displaying it to the customer
 */
function efa_2710_admin_notices() {
	if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

		switch ( $_GET['sl_activation'] ) {

			case 'false':
				$message = urldecode( $_GET['message'] );
				?>
				<div class="error">
					<p><?php echo wp_kses_post( $message ); ?></p>
				</div>
				<?php
				break;

			case 'true':
			default:
				// Developers can put a custom success message here for when activation is successful if they way.
				break;

		}
	}
}
add_action( 'admin_notices', 'efa_2710_admin_notices' );

if ( get_option( 'efa_2710_license_status', false) == 'valid' ){
add_filter( 'elementor/connect/additional-connect-info', '__return_empty_array', 999 );
add_action( 'plugins_loaded', function() {
	add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
		if ( strpos( $url, 'my.elementor.com/api/v2/licenses' ) !== false ) {
			return [
				'response' => [ 'code' => 200, 'message' => 'ОК' ],
				'body'     => json_encode( [ 'success' => true, 'license' => 'valid', 'expires' => '01.01.2030' ] )
			];
		} elseif ( strpos( $url, 'my.elementor.com/api/connect/v1/library/get_template_content' ) !== false ) {
			$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/New/{$parsed_args['body']['id']}.json", [ 'sslverify' => false, 'timeout' => 25 ] );
			if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
				return $response;
			} else {
				return $pre;
			}
		} else {
			return $pre;
		}
	}, 10, 3 );
} );
	
add_action( 'plugins_loaded', function() {
	add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
		if ( strpos( $url, 'my.elementor.com/api/v1/licenses' ) !== false ) {
			return [
				'response' => [ 'code' => 200, 'message' => 'ОК' ],
				'body'     => json_encode( [ 'success' => true, 'license' => 'valid', 'expires' => '01.01.2030' ] )
			];
		} elseif ( strpos( $url, 'my.elementor.com/api/connect/v1/library/get_template_content' ) !== false ) {
			$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/templates/{$parsed_args['body']['id']}.json", [ 'sslverify' => false, 'timeout' => 25 ] );
			if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
				return $response;
			} else {
				return $pre;
			}
		} else {
			return $pre;
		}
	}, 10, 3 );
} );
}

if ( get_option( 'efa_2710_license_status', false) == 'valid' ){
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'https://my.elementor.com/api/v1/kits-library' ) !== false ) {
		$id = array_slice(explode('/', rtrim($url, '/')), -2)[0];
		$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/kits-library/kits/{$id}/download-link.json", [ 'sslverify' => false, 'timeout' => 25 ] );
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return $response;
		}
	}
	return $pre;
}, 10, 3 );
}

if ( get_option( 'efa_2710_license_status', false) == 'valid' ){
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'https://my.elementor.com/api/v1/kits-library' ) !== false ) {
		$id = array_slice(explode('/', rtrim($url, '/')), -2)[0];
		$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/kits-library/new-kits/{$id}/download-link.json", [ 'sslverify' => false, 'timeout' => 25 ] );
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return $response;
		}
	}
	return $pre;
}, 10, 3 );
}




if ( get_option( 'efa_2710_license_status', false) == 'valid' ){
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'https://my.elementor.com/api/v2/pro/info' ) !== false ) {
		$basename = basename( parse_url( $url, PHP_URL_PATH ) );
		$response = wp_remote_get( "https://elementorfa.ir/", [ 'sslverify' => false, 'timeout' => 25 ] );
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return $response;
		}
	}
	return $pre;
}, 10, 3 );
}


if ( get_option( 'efa_2710_license_status', false) != 'valid' ){
	add_action( 'admin_notices', 'elementorfa_2710_license_notice' );
}
	function elementorfa_2710_license_notice() {
    ?>
	<div class="notice notice-error">
	<p><?php echo _e('Elementor Pro plugin is not active. <b> <a href="admin.php?page=efa-2710-license">Please activate from here</a> </b>', 'elementor-pro'); ?></p>
	</div>
    <?php
}

/**
 * Elementor Pro License
 */
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'https://my.elementor.com/api/v2/license/activate' ) !== false ) {
		$basename = basename( parse_url( $url, PHP_URL_PATH ) );
		$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/elementor-pro-license/activate", [ 'sslverify' => false, 'timeout' => 25 ] );
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return $response;
		}
	}
	return $pre;
}, 10, 3 );

add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'https://my.elementor.com/api/v2/license/validate' ) !== false ) {
		$basename = basename( parse_url( $url, PHP_URL_PATH ) );
		$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/elementor-pro-license/validate", [ 'sslverify' => false, 'timeout' => 25 ] );
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return $response;
		}
	}
	return $pre;
}, 10, 3 );