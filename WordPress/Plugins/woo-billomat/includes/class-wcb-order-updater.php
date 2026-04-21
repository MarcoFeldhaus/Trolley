<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce/Billomat Order Updater class
 *
 * Handles sync of WooCommerce Order -> Billomat Invoice
 *
 * @class 		WCB_Order_Updater
 * @version		1.0
 * @package		WooCommerceBillomat/Classes/Updater
 * @category	Class
 * @author 		Billomat
 */
class WCB_Order_Updater {
  public function __construct($customer_updater, $article_updater) {
    $this->init_hooks();
		$this->customer_updater = $customer_updater;
		$this->article_updater = $article_updater;
  }

  /**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		if('yes' != get_option('wcb_disable_invoice_creation') && get_option( 'wcb_connected' ) ) {
			add_action( 'woocommerce_order_status_pending', array( $this, 'export_create_or_send_invoice' ), 10, 1 );
			add_action( 'woocommerce_order_status_on-hold', array( $this, 'export_create_or_send_invoice' ), 10, 1 );
			add_action( 'woocommerce_order_status_processing', array( $this, 'export_create_or_send_invoice' ), 10, 1 );
			add_action( 'woocommerce_order_status_completed', array( $this, 'export_create_or_send_invoice' ), 10, 1 );

			add_action( 'woocommerce_order_status_cancelled', array( $this, 'export_cancel_invoice' ), 10, 1 );
			add_filter( 'woocommerce_email_attachments', array( $this, 'email_attachments' ), 10, 3);

      // Webhooks
			add_action( 'admin_post_nopriv_wcb_delete_invoice', array( $this, 'import_delete_invoice' ) );
			add_action( 'admin_post_nopriv_wcb_change_invoice_status', array( $this, 'import_remove_billomat_draft_meta' ) );
			add_action( 'admin_post_nopriv_wcb_change_invoice_status', array( $this, 'import_update_order_status' ) );
			add_action( 'admin_post_nopriv_wcb_add_delivery_note', array( $this, 'import_add_delivery_note' ) );
			add_action( 'admin_post_nopriv_wcb_delete_delivery_note', array( $this, 'import_delete_delivery_note' ) );
		}
	}

	/**
	 * Create and/or send Billomat invoice.
	 * @param int $order_id WordPress post ID
	 */
  public function export_create_or_send_invoice($order_id) {
    $order = new WC_Order($order_id);

		$this->export_create_invoice($order);

		if(get_option('wcb_send_invoice') == 'billomat') {
			$this->send_invoice($order);
		}
  }

	public function export_create_invoice($order, $add_payment = true) {
		$order_id = $order->id;

		$invoice_creation_order_status = null;

		// Check if invoice creation is disabled for choosen payment method
		$payment_method = $order->get_payment_method();

		// Check if order status setting matches current order status
		$invoice_creation_order_status = get_option("invoice_creation_{$payment_method}");

		// @since 2.3.2 - default status for none/unknown payment method
		if(!$invoice_creation_order_status) {
			$invoice_creation_order_status = get_option("invoice_creation");
		}

		// Do nothing if status don't match
		if($order->status !== $invoice_creation_order_status) {
			return;
		}

    $invoice_data = $this->map_data($order);

		if(!$invoice_data) {
			return;
		}

    if(!get_post_meta($order_id, 'billomat_id', true)) {
			$payment_method = $order->get_payment_method();
			$payment_status_setting = get_option("wcb_invoice_status_{$payment_method}");

			// @since 2.3.2 - default status for none/unknown payment method
			if(!$payment_status_setting) {
				$payment_status_setting = get_option("wcb_invoice_status");
			}

      $billomat_invoice = WCB()->client->create_invoice($invoice_data, array("Order ID" => $order_id));

			if(!$billomat_invoice) {
				return;
			}

			update_post_meta($order_id, 'billomat_id', $billomat_invoice['id']);
			update_post_meta($order_id, 'billomat_draft', 1);

			$invoices_count = (int) get_option('wcb_invoices_created');
			$invoices_count++;
			update_option('wcb_invoices_created', $invoices_count);
			$this->check_rating_notice($invoices_count);

			if($tags = get_option('wcb_invoice_tags')) {
				$tags = explode(',', $tags);
				foreach($tags as $tag) {
					WCB()->client->add_invoice_tag($billomat_invoice['id'], $tag, array("Order ID" => $order_id));
				}
      }

			if($payment_status_setting != 'draft') {
				$complete_response = WCB()->client->complete_invoice($billomat_invoice['id'], array(), array("Order ID" => $order_id));

				if($complete_response) {
					delete_post_meta($order_id, 'billomat_draft');

					if($add_payment) {
						if($payment_status_setting == 'paid') {
							$billomat_payment_method = null;
							$invoice_mapping = get_option("wcb_invoice_mapping_{$payment_method}");

							// @since 2.3.2 - default status for none/unknown payment method
							if(!$invoice_mapping) {
								$invoice_mapping = get_option("wcb_invoice_mapping");
							}

							if($invoice_mapping) {
								$billomat_payment_method = $invoice_mapping;
							}

							$payment_total = $order->get_total();

							WCB()->client->add_invoice_payment($billomat_invoice['id'], $payment_total, $billomat_payment_method, array("Order ID" => $order_id));
						}
					}
				}
			}
    }
	}

	private function send_invoice($order) {
		$invoice_mail_status = $this->get_invoice_mail_status($order);

		if($order->status == $invoice_mail_status) {
			$order_data = $order->get_data();
			if($order_data['billing']['email']) {
				$billomat_id = get_post_meta($order->id, 'billomat_id', true);
				$billomat_invoice = WCB()->client->get_invoice($billomat_id);
				$email_data = array('recipients' => array('to' => $order_data['billing']['email']));
				WCB()->client->mail_invoice($billomat_invoice['invoice_id'], $email_data, array("Order ID" => $order->ID));
			}
		}
	}

	/**
	 * Cancel Billomat invoice.
	 * @param int $order_id WordPress post ID
	 */
	public function export_cancel_invoice($order_id) {
		$billomat_id = get_post_meta($order_id, 'billomat_id', true);
		if(!$billomat_id) { return; }
		$create_correction_invoice_option = get_option('wcb_create_correction_invoice');
		$create_correction_invoice = $create_correction_invoice_option === 'yes';
		$order = new WC_Order($order_id);
		$invoice_data = WCB()->client->get_invoice_data($billomat_id, array("Order ID" => $order_id));

		if($invoice_data) {
			if(!$create_correction_invoice || $invoice_data['status'] == 'PAID') {
				WCB()->client->cancel_invoice($billomat_id, array("Order ID" => $order_id));
			}

			if($create_correction_invoice) {
				$correction_invoice = WCB()->client->correct_invoice(
					$billomat_id,
					array('template_id' => $invoice_data['template_id']),
					array("Order ID" => $order_id)
				);

				if($correction_invoice) {
					update_post_meta($order_id, 'billomat_id', $correction_invoice['id']);
				}
			}
		}
	}

	/**
	 * Delete billomat_id reference from WooCommerce order.
	 */
  public function import_delete_invoice() {
		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$invoice_id = $data['invoice']['id'];
    $order_id = (int) $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$invoice_id}' LIMIT 1");

    if($order_id) {
      delete_post_meta($order_id, 'billomat_id');
    }
	}

	/**
	 * Remove Billomat draft post meta from order.
	 */
  public function import_remove_billomat_draft_meta() {
		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$invoice_id = $data['invoice']['id'];
		$order_id = (int) $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$invoice_id}' LIMIT 1");
		$status = $data['invoice']['status'];

		if($status != 'DRAFT' && $order_id) {
			delete_post_meta($order_id, 'billomat_draft');
		}
	}

	/**
	 * Update WooCommerce order based on Billomat status.
	 */
  public function import_update_order_status() {
		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$invoice_id = $data['invoice']['id'];
		$order_id = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$invoice_id}' LIMIT 1");
		if(!$order_id) {
			return;
		} else {
			$order_id = (int) $order_id;
		}
		$status = $data['invoice']['status'];

		switch($status) {
			case 'CANCELED':
			remove_action('woocommerce_order_status_cancelled', array( $this, 'export_cancel_invoice' ), 10);
				$cancel_orders = get_option('wcb_cancel_orders');

				if($cancel_orders == 'yes') {
					$order = new WC_Order($order_id);
					if($order->status != 'cancelled') {
						$order->set_status('cancelled');
						$order->save();
					}
				}
				break;
			case 'PAID':
				$order = new WC_Order($order_id);

				// @since 2.4.1 - don't update cancelled orders
				if($order->status == 'cancelled') {
					break;
				}

				$complete_orders_status = null;

				// @since 2.3.2 - option to select order status for cancelled invoices per payment method
				$payment_method = $order->get_payment_method();
				$complete_orders_status = get_option("wcb_complete_orders_status_{$payment_method}");

				// @since 2.3.0 - option to select order status for cancelled invoices
				if(!$complete_orders_status) {
					$complete_orders_status = get_option('wcb_complete_orders_status');
				}

				// Fallback to old option value
				if(!$complete_orders_status) {
					if(get_option('wcb_complete_orders') == 'yes') {
						$complete_orders_status = 'completed';
					}
				}

				if($complete_orders_status && $complete_orders_status != 'none') {
					if($order->status != $complete_orders_status) {
						$order->set_status($complete_orders_status);
						$order->save();
					}
				}
				break;
		}
	}

  /**
	 * Add delivery note to order.
	 */
  public function import_add_delivery_note() {
    $secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

    global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$invoice_id = $data['delivery-note']['invoice_id'];

    if(!$invoice_id || $data['delivery-note']['status'] != 'CREATED') {
      return;
    }

    $order_id = (int) $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$invoice_id}' LIMIT 1");

    if($order_id) {
      update_post_meta($order_id, 'billomat_delivery_note_id', $data['delivery-note']['id']);
    }
  }

	/**
	 * Delete billomat_delivery_note_id reference from WooCommerce order.
	 */
  public function import_delete_delivery_note() {
		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$delivery_note_id = $data['delivery-note']['id'];
    $order_id = (int) $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'billomat_delivery_note_id' AND meta_value = '{$delivery_note_id}' LIMIT 1");

    if($order_id) {
      delete_post_meta($order_id, 'billomat_delivery_note_id');
    }
  }

	public function email_attachments($attachments, $status, $order) {
		$trigger_statuses = array('new_order', 'customer_on_hold_order', 'processing', 'customer_processing_order', 'customer_invoice', 'customer_completed_order');
		if(!in_array($status, $trigger_statuses) || get_option('wcb_send_invoice') != 'attach') {
			return $attachments;
		}

		$invoice_mail_status = $this->get_invoice_mail_status($order);

		// Do nothing if status don't match
		if($order->status !== $invoice_mail_status) {
			return $attachments;
		}

		$order_id = $order->get_id();
		$billomat_id = get_post_meta($order->get_id(), 'billomat_id', true);

		if(!$billomat_id) {
			return $attachments;
		}

		// Ignore draft invoices
		$invoice_data = WCB()->client->get_invoice_data($billomat_id, array("Order ID" => $order_id));
		if($invoice_data['status'] == 'DRAFT') {
			return $attachments;
		}

		// Attach Billomat invoice to email
		$billomat_invoice = WCB()->client->get_invoice($billomat_id, array("Order ID" => $order_id));
		$pdf_data = base64_decode($billomat_invoice['base64file']);
		$upload_dir = wp_upload_dir();

		$pdf_dirname = get_post_meta($order_id, 'billomat_upload_dir', true);
		if(!$pdf_dirname) {
			$pdf_dirname = $order_id . '-' . sha1($pdf_data);
			update_post_meta($order_id, 'billomat_upload_dir', $pdf_dirname);
		}

		$pdf_dir = $upload_dir['basedir'] . '/woocommerce_billomat_uploads/' . $pdf_dirname;
		$pdf_filename = $pdf_dir . '/' . $billomat_invoice['filename'];

		if(wp_mkdir_p($pdf_dir) && file_put_contents($pdf_filename, $pdf_data)) {
			$attachments[] = $pdf_filename;
		}

		return $attachments;
	}

	/**
	 * Map WooCommerce Order data to Billomat API fields.
   * @param WC_Order
	 * @return array Billomat API data
	 */
  private function map_data($order) {
		$billomat_settings = WCB()->client->get_settings();
		$billomat_net_gross = $billomat_settings['net_gross'];
    $order_data = $order->get_data();
    $order_items = $order->get_items();
		$payment_method = $order->get_payment_method();
    $customer_id = $order_data['customer_id'];
    $billomat_client_id = get_user_meta($customer_id, 'billomat_id', true);
		// Check if Billomat client exists
		if($billomat_client_id) {
			$existing_client = WCB()->client->get_client($billomat_client_id, false);
			if(!$existing_client) {
				$this->add_error('client_404', $customer_id);
				return false;
			}
		}

		$address = $this->build_address(apply_filters('woocommerce_billomat_invoice_address_data', $order_data['billing'], $customer_id));

		if($customer_id === 0) {
			$billomat_client = $this->customer_updater->export_create_client(null, array(
				'name'          => $order_data['billing']['company'],
	      'street'        => $order_data['billing']['address_1'],
	      'zip'           => $order_data['billing']['postcode'],
	      'city'          => $order_data['billing']['city'],
	      'country_code'  => $order_data['billing']['country'],
	      'first_name'    => $order_data['billing']['first_name'],
	      'last_name'     => $order_data['billing']['last_name'],
	      'phone'         => $order_data['billing']['phone'],
	      'email'         => $order_data['billing']['email'],
			));
			$billomat_client_id = $billomat_client['id'];
		}

		$tax_based_on = get_option('woocommerce_tax_based_on');
		switch($tax_based_on) {
			case 'shipping':
				$tax_country = $order->get_shipping_country();
				if(!$tax_country) {
					$tax_country = $order->get_billing_country();
				}
			case 'billing':
				$tax_country = $order->get_billing_country();
				break;
			default:
				$tax_country = WC()->countries->get_base_country();
				break;
		}

    $invoice_items = array();
    foreach($order_items as $order_item) {
			$invoice_item = $this->build_invoice_item($order_item, $tax_country, $billomat_net_gross);
			if(!$invoice_item) {
				return false;
			}
      $invoice_items[] = $invoice_item;
    }

		// Add shipping as invoice items
		if($order->get_shipping_total() && (float) $order->get_shipping_total() > 0.00) {
			$shipping_invoice_items = array();

			// Calculate totals per tax rate ($tax_totals)
			// used for subsequent calculations
			$tax_totals = array();

			// 1. Add totals for taxable order items
			foreach($order->get_items() as $_item) {
				$_item_data = $_item->get_data();
				foreach($_item_data['taxes']['subtotal'] as $_tax_key => $_tax_value) {
					if($_tax_value) {
						if(!isset($tax_totals[$_tax_key])) {
							$tax_totals[$_tax_key] = 0;
						}
						$tax_totals[$_tax_key] += (float) $_item_data['subtotal'];
					}
				}
			}

			// 2. Add totals for non-taxable but shipping-taxable items
			foreach($order->get_items('tax') as $_tax_item) {
				$_tax_item_data = $_tax_item->get_data();
				if($_tax_item_data['shipping_tax_total'] > 0) {
					$_rate_id = $_tax_item_data['rate_id'];
					if(!isset($tax_totals[$_rate_id])) {
						$tax_totals[$_rate_id] = 0;
					}

					foreach($order->get_items() as $_order_item) {
						$_order_item_data = $_order_item->get_data();
						if(!$_order_item_data['taxes']['subtotal'][$_rate_id]) {
							$_product = new WC_Product($_order_item_data['product_id']);
							if($_product->get_tax_class() === $_tax_item_data['name'] && $_product->tax_status === 'shipping') {
								$tax_totals[$_rate_id] += (float) $_order_item['subtotal'];
							}
						}
					}
				}
			}

			$shipping_price_total = 0;
			foreach($order->get_items('tax') as $_tax_item) {
				$_tax_item_data = $_tax_item->get_data();

				$_tax_rate = WC_Tax::_get_tax_rate($_tax_item_data['rate_id']);
				$_tax_rate_tax = (float) $_tax_rate['tax_rate'];
				$_tax_rate_totals = $tax_totals[$_tax_item_data['rate_id']];

				if(!$_tax_rate_totals) {
					continue;
				}

				$_total = 0;
				foreach($tax_totals as $_tax_key => $_tax_value) {
					$_total += $_tax_value + ($_tax_value / 100 * WC_Tax::_get_tax_rate($_tax_key)['tax_rate']);
				}

				$shipping_total_incl_tax = $order->get_shipping_total() + $order->get_shipping_tax();

				if($_tax_rate_totals && $_total) {
					$shipping_price = ($_tax_rate_totals / $_total ) * $shipping_total_incl_tax;
				} else {
					$shipping_price = $order->get_shipping_total();
				}

				if($billomat_net_gross == 'GROSS') {
					$shipping_price += $shipping_price / 100 * $_tax_rate_tax;
				}

				$shipping_price_total += $shipping_price;

				// Round shipping price if WooCommerce and Billomat tax net/gross setting is identical
				if((wc_prices_include_tax() && $billomat_net_gross === 'GROSS') || (!wc_prices_include_tax() && $billomat_net_gross === 'NET')) {
					$shipping_price = round($shipping_price, 2);
				}

				$_shipping_invoice_item = array(
		      'quantity' => 1,
					'unit_price' => $shipping_price,
		      'tax_name' => $_tax_rate['tax_rate_name'],
		      'tax_rate' => $_tax_rate_tax,
		      'title' => sprintf(__( "Shipping (%s)", 'woocommerce-billomat' ), $_tax_rate['tax_rate_name']),
				);

				$shipping_invoice_items[] = $_shipping_invoice_item;
			}

			if($shipping_price_total === 0) {
				if($first_tax_item = array_shift($order->get_items('tax'))) {
					$_tax_rate = WC_Tax::_get_tax_rate($_tax_item_data['rate_id']);
					$_tax_rate_tax = (float) $_tax_rate['tax_rate'];
					$tax_name = $_tax_rate['tax_rate_name'];
		      $tax_rate = $_tax_rate_tax;
				} else {
					$tax_name = null;
					$tax_rate = null;
				}

				$shipping_invoice_items[] = array(
		      'quantity' => 1,
					'unit_price' => $order->get_shipping_total() + $order->get_shipping_tax(),
		      'tax_name' => $tax_name,
		      'tax_rate' => $tax_rate,
		      'title' => sprintf(__( "Shipping", 'woocommerce-billomat' )),
				);
			}

			$invoice_items = array_merge($invoice_items, $shipping_invoice_items);
		}

    $data = array(
      'client_id'			=> $billomat_client_id,
			'label'					=> $order->get_order_number(),
      'address'				=> $address,
      'currency_code'	=> $order->get_currency(),
      'invoice-items'	=> array( 'invoice-item' => $invoice_items )
    );

		// Add discount as reduction
		if($order->get_discount_total() > 0) {
			$billomat_settings = WCB()->client->get_settings();
			$billomat_net_gross = $billomat_settings['net_gross'];
			$dp = get_option('woocommerce_price_num_decimals');
			$discount = $order->get_discount_total();

			// @since 2.3.8 - add discount tax if Billomat tax setting is gross
			if($billomat_net_gross == 'GROSS') {
				$discount_tax = $order->get_discount_tax();
				$discount += $discount_tax;
			}

			$discount_rounded = round($discount, $dp, WC_DISCOUNT_ROUNDING_MODE);
			$data['reduction'] = $discount;
		}

		if($free_text_id = get_option('wcb_free_text')) {
			$data['free_text_id'] = $free_text_id;
		}

		if($prefix = get_option('wcb_invoice_prefix')) {
			$data['number_pre'] = $prefix;
		}

		// Set template_id
		$billomat_invoice_template_id = get_option("wcb_invoice_template_{$payment_method}");

		// @since 2.3.2 - default status for none/unknown payment method
		if(!$billomat_invoice_template_id) {
			$billomat_invoice_template_id = get_option('wcb_invoice_template');
		}

		if(!$billomat_invoice_template_id) {
			// Fallback, wcb_default_template was removed in v1.2.0
			$billomat_invoice_template_id = get_option('wcb_default_template');
		}

		if($billomat_invoice_template_id) {
			$data['template_id'] = $billomat_invoice_template_id;
		}

    return apply_filters('woocommerce_billomat_invoice_data', $data, $order);
  }

	/**
	 * Retrieve the order status on which to attach the invoice.
	 * @return string the order status
	 */
	private function get_invoice_mail_status($order) {
		// @since 2.3.0 - select option based on status
		$status = get_option('wcb_send_invoice_status');

		// @since 1.1.0 - status based on invoice creation setting
		if(!$status) {
			$payment_method = $order->get_payment_method();
			$status = get_option("invoice_creation_{$payment_method}");
		}

		// Fallback for older versions - send on `completed` status
		if(!$status) {
			$status = 'completed';
		}

		return $status;
	}

	/**
	 * Build address string based on billing address data.
   * @param array $address_data
	 * @return string with address information
	 */
  private function build_address($address_data) {
    $company = $address_data['company'];
    $first_name = $address_data['first_name'];
    $last_name = $address_data['last_name'];
    $address_1 = $address_data['address_1'];
    $address_2 = $address_data['address_2'];
    $city = $address_data['city'];
    $postcode = $address_data['postcode'];
    $country = $address_data['country'];
    $address_parts = array();

    if($company) {
      $address_parts[] = $company;
    }

    if($first_name && $last_name) {
      $address_parts[] = "${first_name} {$last_name}";
    }

    if($address_1) {
      $address_parts[] = $address_1;
    }

    if($address_2) {
      $address_parts[] = $address_2;
    }

    $city_parts = array();
    if($postcode) { $city_parts[] = $postcode; }
    if($city) { $city_parts[] = $city; }

    if(count($city_parts) > 0) {
      $address_parts[] = join(' ', $city_parts);
    }

    if($country && $country != WC()->countries->get_base_country()) {
      $address_parts[] = $country;
    }

    return join("\n", $address_parts);
  }

	/**
	 * Build Billomat invoice-item from WC order item and tax country
   * @param WC_Order_Item $order_item
	 * @param string $tax_country
	 * @return array with Billomat invoice-item data
	 */
  private function build_invoice_item($order_item, $tax_country, $billomat_net_gross) {
		$item_data = $order_item->get_data();

    $product_id = $item_data['variation_id'] ? $item_data['variation_id'] : $item_data['product_id'];
    $quantity = $item_data['quantity'];
    $total = $item_data['subtotal'];

		if($billomat_net_gross == 'GROSS') {
			$total += $item_data['subtotal_tax'];
		}

    $tax_class = $item_data['tax_class'];
    $name = $item_data['name'];
    $unit_price = $total / $quantity;
    $billomat_article_id = get_post_meta($product_id, 'billomat_id', true);

		// Check if Billomat article exists
		if($billomat_article_id) {
			$existing_article = WCB()->client->get_article($billomat_article_id, false);
			if(!$existing_article) {
				$this->add_error('article_404', $product_id);
				return false;
			}
		} else {
			$billomat_article = $this->article_updater->export_update_article($product_id);
			if(isset($billomat_article['id'])) {
				$billomat_article_id = $billomat_article['id'];
			}
		}

		$tax_name = null;
    $tax_rate = null;

		if($tax_class || $item_data['subtotal_tax']) {
			$tax_rates = WC_Tax::find_rates(array(
				'tax_class' => $tax_class,
				'country' => $tax_country
			));

		  if($tax_rate = array_shift($tax_rates)) {
		    $tax_name = $tax_rate['label'];
		    $tax_rate = $tax_rate['rate'];
		  }
		}

		$invoice_item_data = array(
      'article_id' => $billomat_article_id,
      'quantity' => $quantity,
      'unit_price' => $unit_price,
      'tax_name' => $tax_name,
      'tax_rate' => $tax_rate,
      'title' => $name,
    );

    return apply_filters('woocommerce_billomat_invoice_item_data', $invoice_item_data, $order_item);
  }

	/**
	 * Build Billomat delivery-noteitem from WC order item
   * @param WC_Order_Item $order_item
	 * @return array with Billomat delivery-note-item data
	 */
  public static function build_delivery_note_item($order_item) {
		$item_data = $order_item->get_data();

    $product_id = $item_data['variation_id'] ? $item_data['variation_id'] : $item_data['product_id'];
    $quantity = $item_data['quantity'];
    $name = $item_data['name'];
    $billomat_article_id = get_post_meta($product_id, 'billomat_id', true);

    return array(
      'article_id' => $billomat_article_id,
      'quantity' => $quantity,
      'title' => $name,
    );
  }

	public function add_error($type, $entity_id) {
		switch($type) {
			case 'client_404':
				$userdata = get_userdata($entity_id);
				$message = sprintf(__("Billomat invoice could not be created - the referenced Billomat client for %s doesn´t exist. Please fix this by updating/deleting the reference in the user profile.", 'woocommerce-billomat'), $userdata->display_name);
				break;
			case 'article_404':
				$message = sprintf(__("Billomat invoice could not be created - the referenced Billomat article for %s doesn´t exist. Please fix this by updating/deleting the reference in the article's Billomat Meta Box.", 'woocommerce-billomat'), get_the_title($entity_id));
				break;
		}

		if($message) {
			WCB()->notices_controller->add_admin_notice($message, "client_{$entity_id}_{$type}", 'error');
		}
	}

	private function check_rating_notice($invoices_count) {
		$invoices_totals = array(10, 30, 50);
		foreach(array_reverse($invoices_totals) as $invoice_total) {
			$option_key = "wcb_showed_rating_notice_invoices_{$invoice_total}";
			if($invoices_count >= $invoice_total && !get_option($option_key)) {
				$rating_url = __( 'https://wordpress.org/plugins/woo-billomat/', 'woocommerce-billomat' );
				$support_url = __( 'https://www.billomat.com/en/contact/', 'woocommerce-billomat' );
				$message = sprintf(__("<strong>Do you like our plugin?</strong><br> Rate our plugin and give us your feedback to continually improve WooBillomat.", 'woocommerce-billomat'), $invoice_total);
				$message .= '<ul class="wcb-rating-options">';
				$message .= '<li>' . sprintf(__("<a data-wcb-remove-admin-notice=\"rating_cta\" href=\"%s\">Rate WooBillomat</a>", 'woocommerce-billomat'), $rating_url) . '</li>';
				$message .= '<li>' . __("<a data-wcb-remove-admin-notice=\"rating_cta\" class=\"wcb-nolink\" href=\"#\">I already did</a>", 'woocommerce-billomat') . '</li>';
				$message .= '<li>' . sprintf(__("<a data-wcb-remove-admin-notice=\"rating_cta\" href=\"%s\">I am not satisfied</a>", 'woocommerce-billomat'), $support_url) . '</li>';
				$message .= '</ul>';
				WCB()->notices_controller->add_admin_notice($message, 'rating_cta', 'info', true, false);
				update_option($option_key, '1');
			}
		}
	}
}
