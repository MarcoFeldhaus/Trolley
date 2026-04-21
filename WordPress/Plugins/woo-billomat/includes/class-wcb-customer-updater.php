<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce/Billomat Customer Updater class
 *
 * Handles sync of WooCommerce Customer -> Billomat Client
 *
 * @class 		WCB_Customer_Updater
 * @version		1.0
 * @package		WooCommerceBillomat/Classes/Updater
 * @category	Class
 * @author 		Billomat
 */
class WCB_Customer_Updater {
  public function __construct() {
    $this->init_hooks();
  }

  /**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		if( get_option( 'wcb_connected' ) ) {
			add_action( 'woocommerce_created_customer', array( $this, 'export_create_client' ), 0 );
	    add_action( 'woocommerce_update_customer', array( $this, 'export_update_client' ), 0 );
			add_action( 'profile_update', array( $this, 'export_update_client' ), 0 );

			// Webhooks
			add_action( 'admin_post_nopriv_wcb_update_customer', array( $this, 'import_update_customer' ) );
      add_action( 'admin_post_nopriv_wcb_delete_customer', array( $this, 'import_delete_customer' ) );
		}
	}

	/**
	 * Create Billomat client.
	 * @param int $customer_id WooCommerce customer_id (WP user ID)
	 * @param array|null $data Billomat client data
	 */
  public function export_create_client($customer_id = null, $data = null) {
		if(!$data) {
			$user_meta = get_user_meta($customer_id);
			$data = $this->map_data_export($user_meta, $customer_id);
		}

    $billomat_client = WCB()->client->create_client($data);
		update_user_meta($customer_id, 'billomat_id', $billomat_client['id']);

		return $billomat_client;
  }

	/**
	 * Update Billomat client.
	 * @param int $customer_id WooCommerce customer_id (WP user ID)
	 */
  public function export_update_client($customer_id) {
    $user_meta = get_user_meta($customer_id);
		$data = $this->map_data_export($user_meta, $customer_id);

		$billomat_client = null;

		// Try to update if customer already has a Billomat client id
		if(isset($user_meta['billomat_id'][0]) && !empty($user_meta['billomat_id'][0])) {
			// Get Billomat article
			$existing_client = WCB()->client->get_client($user_meta['billomat_id'][0], false);

			// Return with error if Billomat client doesn´t exist
			if(!$existing_client) {
				$this->add_error('client_404', $customer_id);
				return false;
			} else {
				// Update existing Billomat client
				$result = WCB()->client->update_client($user_meta['billomat_id'][0], $data);
				if($result->success) {
					$billomat_client = $result->body['client'];
				}
			}
		} else {
			// Create new Billomat client
			$billomat_client = $this->export_create_client($customer_id, $data);
		}

		return $billomat_client;
  }

	/**
	 * Update WooCommerce customer.
	 */
  public function import_update_customer() {
    remove_action( 'woocommerce_update_customer', array( $this, 'export_update_client' ), 0 );
    remove_action( 'profile_update', array( $this, 'export_update_client' ), 0 );

		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

		$data = json_decode(file_get_contents("php://input"), true);
		$client_id = $data['client']['id'];
		$user_id = (int) $wpdb->get_var("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$client_id}' LIMIT 1");

		if(!$user_id) {
			return;
		}

		$meta_data = $this->map_data_import($data['client']);

		foreach($meta_data as $key => $value) {
			update_user_meta($user_id, $key, $value);
		}

		wp_update_user(array(
      'ID' => $user_id,
      'first_name' => $meta_data['billing_first_name'],
      'last_name' => $meta_data['billing_last_name'],
    ));

		status_header(200);
		die;
	}

  /**
	 * Delete billomat_id reference from WooCommerce customer.
	 */
  public function import_delete_customer() {
		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$client_id = $data['client']['id'];
    $user_id = (int) $wpdb->get_var("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$client_id}' LIMIT 1");

    if($user_id) {
      delete_user_meta($user_id, 'billomat_id');
    }
  }

	/**
	 * Map WP user_meta to Billomat API fields.
	 * @param array $user_meta WordPress user_meta
	 * @return array Billomat API data
	 */
	private function map_data_export($user_meta, $customer_id) {
		$data = array(
      'name'          => $user_meta['billing_company'][0],
      'street'        => $user_meta['billing_address_1'][0],
      'zip'           => $user_meta['billing_postcode'][0],
      'city'          => $user_meta['billing_city'][0],
      'country_code'  => $user_meta['billing_country'][0],
      'first_name'    => $user_meta['billing_first_name'][0],
      'last_name'     => $user_meta['billing_last_name'][0],
      'phone'         => $user_meta['billing_phone'][0],
      'email'         => $user_meta['billing_email'][0],
    );

		$data = apply_filters('woocommerce_billomat_customer_export_data', $data, $user_meta);

		return $data;
	}

	/**
	 * Map Billomat API fields to WP user_meta.
	 * @param array $api_data Billomat API data
	 * @return array WordPress user_meta
	 */
	private function map_data_import($api_data) {
		$data = array(
      'billing_company'			=> $api_data['name'],
      'billing_address_1'		=> $api_data['street'],
      'billing_postcode'		=> $api_data['zip'],
      'billing_city'				=> $api_data['city'],
      'billing_country'			=> $api_data['country_code'],
      'billing_first_name'	=> $api_data['first_name'],
      'billing_last_name'		=> $api_data['last_name'],
      'billing_phone'				=> $api_data['phone'],
      'billing_email'				=> $api_data['email'],
    );

		$data = apply_filters('woocommerce_billomat_customer_import_data', $data, $api_data);

		return $data;
	}

	private function add_error($type, $user_id) {
		switch($type) {
			case 'client_404':
				$message = __("Billomat client could not be updated - the referenced Billomat ID doesn´t exist. Please fix this by updating/deleting the reference below.", 'woocommerce-billomat');
				break;
		}

		if($message) {
			WCB()->notices_controller->add_admin_notice($message, "client_{$user_id}_{$type}", 'error');
		}
	}
}