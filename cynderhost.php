<?php

/**
 * @link              https://cynderhost.com
 * @since             1.0.0
 * @package           CynderHost
 *
 * @wordpress-plugin
 * Plugin Name:       CynderHost
 * Plugin URI:        https://cynderhost.com
 * Description:       Provides an easy interface to clear the CynderHost CDN cache, both automatically and programmatically.
 * Version:           1.0.3
 * Author:            CynderHost
 * Author URI:        https://profiles.wordpress.org/cynderhost/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cynderhost
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}




define( 'CYNDERHOST_VERSION', '1.0.3' );



if ( ! function_exists( 'purge_cynderhost' ) ) {
/**
 * Purge CynderHost CDN Cache
 *
 * @return string -- returns success or failure message
 */
function purge_cynderhost($resp = false) {
	$cynderhost_cdn_settings_options = get_option( 'cynderhost_cdn_settings_option_name' ); // Array of All Options
  $api_key = $cynderhost_cdn_settings_options['api_key_0'];
	//cache purge endpoint
	$response = wp_remote_post( "https://api.cynderhost.com/high-performance/cache/cdn/purge/", array(
	    'method'      => 'POST',
	    'timeout'     => 10,
	    'redirection' => 5,
	    'blocking'    => $resp,
	    'body'        => array(
	        'API' => "$api_key"
	     )
	    )
	);
	//Non-standard Error, 5XX, 4XX
	if ( is_wp_error( $response ) ) {
    $error_message = $response->get_error_message();
    $result = "Something went wrong: $error_message";
	} else {
	    $result = json_decode($response['body'], true)['status'];
	}
	$result = $resp ? $result : "Cache purge request sent.";
  	return $result;
  }
}

/**
 * Called to purge cache on updates and display status
 */
function do_cache_cynderhost_purge($p = false){
	 cynderhost_set_admin_notice(purge_cynderhost($p));
}

/**
 * Displays a notice with cache purge status
 */
function cynderhost_author_admin_notice(){
	$v = cynderhost_get_admin_notice();
	if($v){
		 echo '<div class="notice notice-info is-dismissible">
				  <p>'. $v['message'] .'</p>
				 </div>';
	}
}

/**
 * Sets admin notice trasients
 */
function cynderhost_set_admin_notice($message) {
    set_transient('cynderhost_message', [
        'message' => $message
    ], 30);
}
/**
 * Gets and removes admin notice trasients
 */
function cynderhost_get_admin_notice() {
    $transient = get_transient( 'cynderhost_message' );
	if ($transient){
    	delete_transient( 'cynderhost_message' );
	}
    return $transient;
}

/**
 * Check if cache should be purged
 */
function cynderhost_check_cache_purge(){
	$q = $_GET['cynderhost_purgecache'];
	if( isset($q) && $q == "true" && current_user_can('administrator') && wp_verify_nonce( $_GET['nonce'], 'cynderhost_purgecache' ) ){
		do_cache_cynderhost_purge(true);
	}
}
/**
 * Add cache purge action hooks:
 * Post publish, update, or delete, Theme switch, Plugin activate or deactivate
 */
add_action('publish_post', 'do_cache_cynderhost_purge');
add_action('save_post', 'do_cache_cynderhost_purge');
add_action('wp_trash_post', 'do_cache_cynderhost_purge');
add_action('switch_theme', 'do_cache_cynderhost_purge');
add_action('activated_plugin', 'do_cache_cynderhost_purge');
add_action('deactivated_plugin', 'do_cache_cynderhost_purge');
add_action('deactivated_plugin', 'do_cache_cynderhost_purge');
add_action('admin_notices', 'cynderhost_author_admin_notice');
add_action('wp_loaded', 'cynderhost_check_cache_purge');


/**
 * Adds Admin Page
 */
class CynderHostCDNSettings {
	private $cynderhost_cdn_settings_options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'cynderhost_cdn_settings_add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'cynderhost_cdn_settings_page_init' ) );
	}

	public function cynderhost_cdn_settings_add_plugin_page() {
		add_menu_page(
			'CynderHost CDN', // page_title
			'CynderHost CDN', // menu_title
			'manage_options', // capability
			'cynderhost-cdn-settings', // menu_slug
			array( $this, 'cynderhost_cdn_settings_create_admin_page' ), // function
			'dashicons-networking', // icon_url
			81 // position
		);
	}

	public function cynderhost_cdn_settings_create_admin_page() {
		$this->cynderhost_cdn_settings_options = get_option( 'cynderhost_cdn_settings_option_name' ); ?>

		<div class="wrap">
			<h2>CynderHost CDN Settings</h2>
			<p>Configure caching purging settings for CynderHost CDN</p>
			<p>API key can be found in the hosting panel under CDN</p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( 'cynderhost_cdn_settings_option_group' );
					do_settings_sections( 'cynderhost-cdn-settings-admin' );
					submit_button($text = "Save & Purge Cache");
				?>
			</form>
		</div>
	<?php }

	public function cynderhost_cdn_settings_page_init() {
		register_setting(
			'cynderhost_cdn_settings_option_group', // option_group
			'cynderhost_cdn_settings_option_name', // option_name
			array( $this, 'cynderhost_cdn_settings_sanitize' ) // sanitize_callback
		);

		add_settings_section(
			'cynderhost_cdn_settings_setting_section', // id
			'Settings', // title
			array( $this, 'cynderhost_cdn_settings_section_info' ), // callback
			'cynderhost-cdn-settings-admin' // page
		);

		add_settings_field(
			'api_key_0', // id
			'API Key', // title
			array( $this, 'api_key_0_callback' ), // callback
			'cynderhost-cdn-settings-admin', // page
			'cynderhost_cdn_settings_setting_section' // section
		);
	}

	public function cynderhost_cdn_settings_sanitize($input) {
		$sanitary_values = array();
		if ( isset( $input['api_key_0'] ) ) {
			$sanitary_values['api_key_0'] = sanitize_text_field( $input['api_key_0'] );
		}
		do_cache_cynderhost_purge(true);
		return $sanitary_values;
	}


	public function api_key_0_callback() {
		printf(
			'<input class="regular-text" type="text" name="cynderhost_cdn_settings_option_name[api_key_0]" id="api_key_0" value="%s">',
			isset( $this->cynderhost_cdn_settings_options['api_key_0'] ) ? esc_attr( $this->cynderhost_cdn_settings_options['api_key_0']) : ''
		);
	}

}

/**
 * Show admin settings only for admin
 */
if ( is_admin() )
	$cynderhost_cdn_settings = new CynderHostCDNSettings();

/**
 * Register Cache Purge button in WP Admin bar
 */
add_action('admin_bar_menu', 'cynderhost_add_item', 100);

function cynderhost_add_item( $admin_bar ){
  $url = add_query_arg("cynderhost_purgecache", "true");
	$url = add_query_arg("nonce", wp_create_nonce( 'cynderhost_purgecache' ), $url);
  global $wp_admin_bar;
  $wp_admin_bar->add_menu( array( 'id'=>'cache-purge','title'=>'Cache Purge','href'=> "$url"));
}
