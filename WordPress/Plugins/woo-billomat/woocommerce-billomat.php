<?php
/**
 * Plugin Name: WooCommerce Billomat
 * Description: Connect WooCommerce to Billomat and generate clients, articles and invoices automatically.
 * Version: 2.4.8
 * Author: Billomat
 * Author URI: https://www.billomat.com/
 *
 * Text Domain: woocommerce-billomat
 * Domain Path: /languages/
 *
 * @package WooCommerceBillomat
 * @category Core
 * @author Billomat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WooCommerce_Billomat' ) ) :

/**
 * Main WooCommerce/Billomat Class.
 *
 * @class WooCommerce_Billomat
 * @version	1.0
 */
class WooCommerce_Billomat {
  /**
	 * WooCommerce/Billomat version.
	 *
	 * @var string
	 */
	public $version = '3.9';

	/**
	 * The single instance of the class.
	 *
	 * @var WooCommerce_Billomat
	 */
	protected static $_instance = null;

  /**
	 * Main WooCommerce/Billomat Instance.
	 *
	 * Ensures only one instance of WooCommerce/Billomat is loaded or can be loaded.
	 *
	 * @static
	 * @see WCB()
	 * @return WooCommerce_Billomat - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Auto-load in-accessible properties on demand.
	 * @param mixed $key
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( in_array( $key, array( 'client' ) ) ) {
			return $this->$key();
		}
	}

  public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();

		do_action( 'woocommerce_billomat_loaded' );
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( 'WCB_Install', 'install' ) );
		add_action( 'plugins_loaded', array( 'WCB_Updater', 'check_update' ) );
		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ), 0 );
	}

  /**
	 * Define WCB Constants.
	 */
	private function define_constants() {
		$this->define( 'WCB_PLUGIN_FILE', __FILE__ );
		$this->define( 'WCB_ABSPATH', dirname( __FILE__ ) . '/' );
		$this->define( 'WCB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		$this->define( 'WCB_VERSION', $this->version );
		$this->define( 'WOOCOMMERCE_BILLOMAT_VERSION', $this->version );
		$this->define( 'WCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

  /**
	 * Define constant if not already set.
	 *
	 * @param  string $name
	 * @param  string|bool $value
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

  /**
	 * Include required core files.
	 */
	public function includes() {
		include_once( WCB_ABSPATH . 'includes/class-wcb-install.php' );
		include_once( WCB_ABSPATH . 'includes/class-wcb-updater.php' );
		include_once( WCB_ABSPATH . 'includes/class-wcb-client.php' );
		include_once( WCB_ABSPATH . 'includes/class-wcb-customer-updater.php' );
		include_once( WCB_ABSPATH . 'includes/class-wcb-product-updater.php' );
		include_once( WCB_ABSPATH . 'includes/class-wcb-order-updater.php' );
		include_once( WCB_ABSPATH . 'includes/admin/order-meta-box.php' );
		include_once( WCB_ABSPATH . 'includes/admin/class-wcb-notices-controller.php' );
    if( is_admin() ) {
      include_once( WCB_ABSPATH . 'includes/admin/settings.php' );
			include_once( WCB_ABSPATH . 'includes/admin/admin-order-actions.php' );
			include_once( WCB_ABSPATH . 'includes/admin/order-meta-box.php' );
			include_once( WCB_ABSPATH . 'includes/admin/class-wcb-article-meta-box.php' );
			include_once( WCB_ABSPATH . 'includes/admin/class-wcb-variation-fields.php' );
			include_once( WCB_ABSPATH . 'includes/admin/class-wcb-user-fields.php' );
    } else {
			include_once( WCB_ABSPATH . 'includes/frontend/frontend-order-actions.php' );
		}
	}

	/**
	 * Init WooCommerce/Billomat when WordPress Initialises.
	 */
	public function init() {
		// Before init action.
		do_action( 'before_woocommerce_billomat_init' );

		// Set up localisation.
		$this->load_plugin_textdomain();

		// Intitalize meta boxes and custom fields.
		if(is_admin()) {
			new WCB_Order_Meta_Box();
			new WCB_Article_Meta_Box();
			new WCB_Variation_Fields();
			new WCB_User_Fields();
		}

		// Load class instances.
		$this->customer_updater = new WCB_Customer_Updater();
		$this->product_updater = new WCB_Product_Updater();
		$this->order_updater = new WCB_Order_Updater($this->customer_updater, $this->product_updater);
		$this->notices_controller = new WCB_Notices_Controller();

		// Init action.
		do_action( 'woocommerce_billomat_init' );
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/woocommerce-billomat/woocommerce-billomat-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/woocommerce-billomat.mo
	 */
	public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'woocommerce-billomat' );

		load_textdomain( 'woocommerce-billomat', WP_LANG_DIR . '/woocommerce-billomat/woocommerce-billomat-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-billomat', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	public function admin_assets() {
		wp_enqueue_style( 'wcb-admin', WCB_PLUGIN_URL . 'admin-v2.css', false, '1.0.0' );
		wp_enqueue_script( 'wcb-admin', WCB_PLUGIN_URL . 'admin-v2.js', false, '1.0.0' );
		$translation_array = array(
			'reset_warning' => __('Warning: you selected data to be resetted - by continuing references will be lost and this cannot be undone.', 'woocommerce-billomat'),
		);
		wp_localize_script('wcb-admin', 'wcb', $translation_array);
	}

	/**
	 * Get Billomat API client class.
	 * @return WCB_Client
	 */
	public function client() {
		return WCB_Client::instance();
	}
}

endif;

/**
 * Main instance of WooCommerce/Billomat.
 *
 * Returns the main instance of WCB to prevent the need to use globals.
 *
 * @return WooCommerce_Billomat
 */
function WCB() {
	return WooCommerce_Billomat::instance();
}

WCB();
