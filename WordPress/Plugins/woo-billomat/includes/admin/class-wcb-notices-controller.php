<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce/Billomat admin notices controller
 *
 * Manages and displays admin notices.
 *
 * @class 		WCB_Notices_Controller
 * @version		2.2.0
 * @package		WooCommerceBillomat\Admin
 * @category	Class
 * @author 		Billomat
 */
class WCB_Notices_Controller {
  public function __construct() {
    $this->init_hooks();
  }

  /**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
    add_action( 'admin_notices', array(&$this, 'show_admin_notices'));
		add_action( 'wp_ajax_wcb_remove_admin_notice', array(&$this, 'ajax_remove_admin_notice') );
  }

  public function get_admin_notices() {
		$notices_option = get_option('wcb_admin_notices');
		$notices = $notices_option ? maybe_unserialize($notices_option) : array();
		if(!is_array($notices)) {
			$notices = array();
		}
		return $notices;
	}

	public function add_admin_notice($message, $key, $class = null, $permanent = false, $dismissible = true) {
		// Remove already existing notice eventually
		$this->remove_admin_notice($key);

		// Get existing notices
		$notices = $this->get_admin_notices();

		// Add notice to notices
		$notice = array(
			'message' => $message,
			'key' => $key,
			'permanent' => $permanent,
			'dismissible' => $dismissible,
		);
		if($class) { $notice['class'] = $class; }
		$notices[] = $notice;

		// Save extended notices array
		update_option('wcb_admin_notices', maybe_serialize($notices));
	}

	public function remove_admin_notice($key) {
		$notices_option = get_option('wcb_admin_notices');
		if($notices_option) {
			// Unset notice by id
			$notices = maybe_unserialize($notices_option);
			foreach($notices as $i => $notice) {
				if($notice['key'] === $key) {
					unset($notices[$i]);
				}
			}

			// Save updated notices array
			update_option('wcb_admin_notices', maybe_serialize($notices));
		}
	}

	public function show_admin_notices() {
		if($notices_option = get_option('wcb_admin_notices')) {
			foreach($this->get_admin_notices() as $notice) {
				$class = isset($notice['class']) ? $notice['class'] : null;
				$dismissible = isset($notice['dismissible']) ? $notice['dismissible'] : true;
				$dismissible_class = $dismissible ? ' is-dismissible' : '';
				printf('<div class="notice notice-%1$s' . $dismissible_class . '"><p>%2$s</p></div>', esc_attr($class), $notice['message']);

				// Remove notice if not permanent
				if(!$notice['permanent']) {
					$this->remove_admin_notice($notice['key']);
				}
			}
		}
	}

	public function ajax_remove_admin_notice() {
		$key = isset($_POST['notice_key']) ? $_POST['notice_key'] : null;
		if($key) {
			$this->remove_admin_notice($key);
		}
		wp_die();
	}
}