<?php /* 
--------------------------------------------------------------------------------
CiviCRM_WP_Mail_Sync_Admin Class
--------------------------------------------------------------------------------
*/



/**
 * Class for encapsulating admin functionality
 */
class CiviCRM_WP_Mail_Sync_Admin {

	/** 
	 * Properties
	 */
	
	// CiviCRM utilities
	public $civi;
	
	// WordPress utilities
	public $wp;
	
	// admin pages
	public $settings_page;
	
	// settings
	public $settings = array();
	
	
	
	/** 
	 * Initialise this object
	 * 
	 * @return object
	 */
	function __construct() {
		
		// init
		$this->initialise();
		
		// --<
		return $this;
		
	}
	
	
	
	/**
	 * Set references to other objects
	 * 
	 * @param object $wp_object Reference to this plugin's WP object
	 * @param object $civi_object Reference to this plugin's CIviCRM object
	 * @return void
	 */
	public function set_references( &$wp_object, &$civi_object ) {
	
		// store reference to WordPress reference
		$this->wp = $wp_object;
		
		// store reference to CiviCRM object
		$this->civi = $civi_object;
		
	}
	
	
		
	/**
	 * Perform activation tasks
	 * 
	 * @return void
	 */
	public function activate() {
		
		// kick out if we are re-activating
		if ( civiwpmailsync_site_option_get( 'civicrm_wp_mail_sync_installed', 'false' ) == 'true' ) return;
	
		// store default settings
		civiwpmailsync_site_option_set( 'civicrm_wp_mail_sync_settings', $this->settings_get_defaults() );
		
		// store version
		civiwpmailsync_site_option_set( 'civicrm_wp_mail_sync_version', CIVICRM_WP_MAIL_SYNC_VERSION );
		
		// store installed flag
		civiwpmailsync_site_option_set( 'civicrm_wp_mail_sync_installed', 'true' );
		
	}
	
	
	
	/**
	 * Perform deactivation tasks
	 * 
	 * @return void
	 */
	public function deactivate() {
		
		// we delete our options in uninstall.php
		
	}
	
	
	
	/**
	 * Initialise
	 * 
	 * @return void
	 */
	public function initialise() {
		
		// load settings array
		$this->settings = civiwpmailsync_site_option_get( 'civicrm_wp_mail_sync_settings', $this->settings );
		
		// is this the back end?
		if ( is_admin() ) {
		
			// multisite?
			if ( is_multisite() ) {
	
				// add admin page to Network menu
				add_action( 'network_admin_menu', array( $this, 'admin_menu' ), 30 );
			
			} else {
			
				// add admin page to menu
				add_action( 'admin_menu', array( $this, 'admin_menu' ) ); 
			
			}
			
		}
		
	}
	
	
	
	//##########################################################################
	
	
	
	/** 
	 * Add an admin page for this plugin
	 * 
	 * @return void
	 */
	public function admin_menu() {
		
		// we must be network admin in multisite
		if ( is_multisite() AND !is_super_admin() ) { return false; }
		
		// check user permissions
		if ( !current_user_can('manage_options') ) { return false; }
		
		// try and update settings
		$saved = $this->update_settings();

		// multisite?
		if ( is_multisite() ) {
				
			// add the admin page to the Network Settings menu
			$page = add_submenu_page( 
		
				'settings.php', 
				__( 'CiviCRM WordPress Mail Sync', 'civicrm-wp-mail-sync' ), 
				__( 'CiviCRM WordPress Mail Sync', 'civicrm-wp-mail-sync' ), 
				'manage_options', 
				'civiwpmailsync_admin_page', 
				array( $this, 'admin_form' )
			
			);
		
		} else {
		
			// add the admin page to the Settings menu
			$page = add_options_page( 
		
				'settings.php', 
				__( 'CiviCRM WordPress Mail Sync', 'civicrm-wp-mail-sync' ), 
				__( 'CiviCRM WordPress Mail Sync', 'civicrm-wp-mail-sync' ), 
				'manage_options', 
				'civiwpmailsync_admin_page', 
				array( $this, 'admin_form' )
			
			);
		
		}
		
		// add styles only on our admin page, see:
		// http://codex.wordpress.org/Function_Reference/wp_enqueue_script#Load_scripts_only_on_plugin_pages
		//add_action( 'admin_print_styles-'.$page, array( $this, 'add_admin_styles' ) );
	
	}
	
	
	
	/**
	 * Enqueue any styles and scripts needed by our admin page
	 * 
	 * @return void
	 */
	public function add_admin_styles() {
		
		// add admin css
		wp_enqueue_style(
			
			'civiwpmailsync-admin-style', 
			CIVICRM_WP_MAIL_SYNC_URL . 'assets/css/admin.css',
			null,
			CIVICRM_WP_MAIL_SYNC_VERSION,
			'all' // media
			
		);
		
	}
	
	
	
	/**
	 * Update settings supplied by our admin page
	 * 
	 * @return void
	 */
	public function update_settings() {
		
	 	// was the form submitted?
		if( ! isset( $_POST['civiwpmailsync_submit'] ) ) return;
		
		// check that we trust the source of the data
		check_admin_referer( 'civiwpmailsync_admin_action', 'civiwpmailsync_nonce' );
		
		// debugging switch for admins and network admins - if set, triggers do_debug() below
		if ( is_super_admin() AND isset( $_POST['civiwpmailsync_debug'] ) ) {
			$settings_debug = absint( $_POST['civiwpmailsync_debug'] );
			$debug = $settings_debug ? 1 : 0;
			if ( $debug ) { $this->do_debug(); }
			return;
		}
		
		// --<
		return;
		
	}
	
	
	
	/**
	 * Show our admin page
	 * 
	 * @return void
	 */
	public function admin_form() {
	
		// we must be network admin in multisite
		if ( is_multisite() AND ! is_super_admin() ) {
			
			// disallow
			wp_die( __( 'You do not have permission to access this page.', 'civicrm-wp-mail-sync' ) );
			
		}
		
		// get sanitised admin page url
		$url = $this->admin_form_url_get();
		
		// open admin page
		echo '
		
		<div class="wrap" id="civiwpmailsync_admin_wrapper">

		<div class="icon32" id="icon-options-general"><br/></div>

		<h2>'.__( 'CiviCRM WordPress Mail Sync', 'civicrm-wp-mail-sync' ).'</h2>

		<form method="post" action="'.htmlentities($url.'&updated=true').'">

		'.wp_nonce_field( 'civiwpmailsync_admin_action', 'civiwpmailsync_nonce', true, false ).'
		'.wp_referer_field( false ).'

		';
		
		// open div
		echo '<div id="civiwpmailsync_admin_options">
		
		';
		
		// show debugger
		echo '
		<h3>'.__( 'Developer Testing', 'civicrm-wp-mail-sync' ).'</h3> 

		<table class="form-table">

			<tr>
				<th scope="row">'.__( 'Debug', 'civicrm-wp-mail-sync' ).'</th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="civiwpmailsync_debug" id="civiwpmailsync_debug" value="1" />
					<label class="civi_wp_member_sync_settings_label" for="civiwpmailsync_debug">'.__( 'Check this to trigger do_debug().', 'civiwpmailsync' ).'</label>
				</td>
			</tr>
		
		</table>'."\n\n";
	
		// close div
		echo '
		
		</div>';
		
		// show submit button
		echo '
	
		<hr>
		<p class="submit">
			<input type="submit" name="civiwpmailsync_submit" value="'.__( 'Submit', 'civicrm-wp-mail-sync' ).'" class="button-primary" />
		</p>
	
		';
	
		// close form
		echo '

		</form>

		</div>
		'."\n\n\n\n";



	}
	
	
	
	/** 
	 * Get the URL for the form action
	 * 
	 * @return string $target_url The URL for the admin form action
	 */
	public function admin_form_url_get() {
	
		// sanitise admin page url
		$target_url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $target_url );
		if ( $url_array ) { $target_url = htmlentities( $url_array[0].'&updated=true' ); }
		
		// --<
		return $target_url;
		
	}
	
	
	
	
	/** 
	 * General debugging utility
	 *
	 * @return void
	 */
	public function do_debug() {
		
		// disabled
		return;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * Get default settings values for this plugin
	 *
	 * @return array $settings The default values for this plugin
	 */
	function settings_get_defaults() {
	
		// init return
		$settings = array();
	
		// --<
		return $settings;
	
	}
	
	
	
	/** 
	 * Save array as site option
	 *
	 * @return bool Success or failure
	 */
	public function settings_save() {
		
		// save array as site option
		return bpgsites_site_option_set( 'civicrm_wp_mail_sync_settings', $this->settings );
		
	}
	
	
	
	/** 
	 * Return a value for a specified setting
	 *
	 * @param string $setting_name The name of the setting
	 * @return bool Whether or not the setting exists
	 */
	public function setting_exists( $setting_name = '' ) {
	
		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_exists()', 'bpgsites' ) );
		}
	
		// get existence of setting in array
		return array_key_exists( $setting_name, $this->settings );
		
	}
	
	
	
	/** 
	 * Return a value for a specified setting
	 *
	 * @param string $setting_name The name of the setting
	 * @param mixed $default The default value if the setting does not exist
	 * @return mixed The setting or the default
	 */
	public function setting_get( $setting_name = '', $default = false ) {
	
		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_get()', 'bpgsites' ) );
		}
	
		// get setting
		return ( array_key_exists( $setting_name, $this->settings ) ) ? $this->settings[$setting_name] : $default;
		
	}
	
	
	
	/** 
	 * Sets a value for a specified setting
	 *
	 * @param string $setting_name The name of the setting
	 * @param mixed $value The value of the setting
	 * @return void
	 */
	public function setting_set( $setting_name = '', $value = '' ) {
	
		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_set()', 'bpgsites' ) );
		}
	
		// test for other than string
		if ( ! is_string( $setting_name ) ) {
			die( __( 'You must supply the setting as a string to setting_set()', 'bpgsites' ) );
		}
	
		// set setting
		$this->settings[$setting_name] = $value;
		
	}
	
	
	
	/** 
	 * Deletes a specified setting
	 *
	 * @param string $setting_name The name of the setting
	 * @return void
	 */
	public function setting_delete( $setting_name = '' ) {
	
		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_delete()', 'bpgsites' ) );
		}
	
		// unset setting
		unset( $this->settings[$setting_name] );
		
	}
	
	
	
	//##########################################################################
	
	
	
} // class ends



/*
================================================================================
Globally-available utility functions
================================================================================
*/



/* 
--------------------------------------------------------------------------------
The "site_option" functions below are useful because in multisite, they access
Network Options, while in single-site they access Blog Options.
--------------------------------------------------------------------------------
*/

/** 
 * Test existence of a specified site option
 *
 * @param str $option_name The name of the option
 * @return bool $exists Whether or not the option exists
 */
function civiwpmailsync_site_option_exists( $option_name = '' ) {

	// test for null
	if ( $option_name == '' ) {
		die( __( 'You must supply an option to civiwpmailsync_site_option_exists()', 'civicrm-wp-mail-sync' ) );
	}

	// test by getting option with unlikely default
	if ( civiwpmailsync_site_option_get( $option_name, 'fenfgehgefdfdjgrkj' ) == 'fenfgehgefdfdjgrkj' ) {
		return false;
	} else {
		return true;
	}
	
}



/** 
 * Return a value for a specified site option
 *
 * @param str $option_name The name of the option
 * @param str $default The default value of the option if it has no value
 * @return mixed $value the value of the option
 */
function civiwpmailsync_site_option_get( $option_name = '', $default = false ) {

	// test for null
	if ( $option_name == '' ) {
		die( __( 'You must supply an option to civiwpmailsync_site_option_get()', 'civicrm-wp-mail-sync' ) );
	}

	// get option
	return get_site_option( $option_name, $default );
	
}



/** 
 * Set a value for a specified site option
 *
 * @param str $option_name The name of the option
 * @param mixed $value The value to set the option to
 * @return bool $success If the value of the option was successfully saved
 */
function civiwpmailsync_site_option_set( $option_name = '', $value = '' ) {

	// test for null
	if ( $option_name == '' ) {
		die( __( 'You must supply an option to civiwpmailsync_site_option_set()', 'civicrm-wp-mail-sync' ) );
	}

	// set option
	return update_site_option( $option_name, $value );
	
}



