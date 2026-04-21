<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce/Billomat Article Updater class
 *
 * Handles sync of WooCommerce Product -> Billomat Article
 *
 * @class 		WCB_Product_Updater
 * @version		1.0
 * @package		WooCommerceBillomat/Classes/Updater
 * @category	Class
 * @author 		Billomat
 */
class WCB_Product_Updater {
	/**
	 * Wether to use WooCoomerce description or short description as Billomat article description.
	 *
	 * @var string description|short_description
	 */
	protected $article_description_source = null;

	/**
	 * Sync article numbers
	 *
	 * @var bool
	 */
	protected $sync_article_numbers = null;

	/**
	 * Article number prefix.
	 *
	 * @var string
	 */
	protected $article_number_prefix = null;

	/**
	 * Billomat tax setting
	 *
	 * @var string NET|GROSS
	 */
	protected $billomat_net_gross = null;

  public function __construct() {
		$this->article_description_source = get_option('wcb_article_description_source');
		$this->article_number_prefix = get_option('wcb_article_number_prefix');
		$this->sync_article_numbers = get_option('wcb_sync_article_numbers') == 'yes';
    $this->init_hooks();
  }

  /**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		if( get_option( 'wcb_connected' ) ) {
			add_action( 'save_post', array( $this, 'export_update_article' ), 10, 2 );
	    add_action( 'woocommerce_save_product_variation', array( $this, 'export_update_article' ), 10, 2 );
	    add_action( 'before_delete_post', array( $this, 'export_delete_article' ), 0 );
	    add_action( 'woocommerce_product_duplicate', array( $this, 'remove_billomat_id' ), 0, 10, 2 );

      // Webhooks
			add_action( 'admin_post_nopriv_wcb_update_product', array( $this, 'import_update_product' ) );
      add_action( 'admin_post_nopriv_wcb_delete_product', array( $this, 'import_delete_product' ) );
		}
	}

  /**
	 * Update Billomat article.
	 * @param int $post_id WordPress post ID
   * @param WP_Post $post WordPress post object
	 */
  public function export_update_article($post_id) {
    if(!current_user_can('edit_product', $post_id)) {
      return $post_id;
    }

    $post = get_post($post_id);

		// Check if post is a WC product
    if(!in_array($post->post_type, array('product', 'product_variation')) || 'publish' !== $post->post_status) {
      return;
    }

		// Get WC product by post
    $product = wc_get_product($post_id);

		// Check if WC product type is supported
    if(get_class($product) == 'WC_Product_Variable' || (!$product->is_purchasable() && get_class($product) != 'WC_Product_External')) {
      return;
    }

		// Get or create Billomat tax rate
    $billomat_tax = null;
		if($product->is_taxable()) {
			$tax_class = $product->get_tax_class();

			if($tax_class) {
				$tax_rates = WC_Tax::find_rates(array(
					'tax_class' => $tax_class,
					'country' => WC()->countries->get_base_country()
				));
			} else {
				$tax_rates = WC_Tax::get_base_tax_rates();
			}

	    if($tax_rate = array_shift($tax_rates)) {
	      $billomat_tax = $this->get_billomat_tax($tax_rate);

	      if(!$billomat_tax) {
	        $billomat_tax = WCB()->client->create_tax_rate(array(
	          'name' => $tax_rate['label'],
	          'rate' => $tax_rate['rate'],
	        ));
	      }
	    }
		}

		// Map WC product to BM article data
    $data = $this->map_data_export($product, $billomat_tax);
		$billomat_article = null;

		// Try to update if product already has a Billomat article id
    if($product_billomat_id = get_post_meta($post_id, 'billomat_id', true)) {
			// Get Billomat article
			$existing_article = WCB()->client->get_article($product_billomat_id, false);

			// Return with error if Billomat article doesn´t exist
			if(!$existing_article) {
				if(get_class($product) != 'WC_Product_Variable') {
					$this->add_error('article_404', $post_id);
				}
				return false;
			} else {
				// Update existing Billomat article
				$result = WCB()->client->update_article($product_billomat_id, $data);
				if($result->success) {
					$billomat_article = $result->body['article'];
				}
			}
    } else {
			// Create new Billomat article
			$billomat_article = WCB()->client->create_article($data);
			if($billomat_article) {
				update_post_meta($post_id, 'billomat_id', $billomat_article['id']);
			}
		}

		return $billomat_article;
  }

  /**
	 * Delete Billomat article.
	 * @param int $post_id WordPress post ID
	 */
  public function export_delete_article($post_id) {
    $post = get_post($post_id);

    if(!in_array($post->post_type, array('product', 'product_variation'))) {
      return;
    }

    if($billomat_id = get_post_meta($post_id, 'billomat_id', true)) {
			$existing_article = WCB()->client->get_article($billomat_id, false);
			if($existing_article) {
				WCB()->client->delete_article($billomat_id);
			}
		}
  }

  public function remove_billomat_id($duplicate, $product) {
    delete_post_meta($duplicate->get_id(), 'billomat_id');
  }

  /**
	 * Update WooCommerce product.
	 */
  public function import_update_product() {
		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$article_id = $data['article']['id'];

    $product_id = (int) $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$article_id}' LIMIT 1");
    $product = wc_get_product($product_id);

    if(!$product_id || !$product) {
			return;
		}

    $product_data = $this->map_data_import($data['article']);

    remove_action('save_post', array( $this, 'export_update_article' ));

		$post_data = array(
      'ID' => $product_id,
      'post_title' => $product_data['name'],
    );

		if($this->article_description_source == 'description') {
			$post_data['post_content'] = $product_data['description'];
		} else if($this->article_description_source == 'short_description') {
			$post_data['post_excerpt'] = $product_data['description'];
		}

		wp_update_post($post_data);

		$update_price_field = $product->sale_price ? '_sale_price' : '_regular_price';
    update_post_meta($product_id, $update_price_field, $product_data['price']);
    update_post_meta($product_id, '_price', $product_data['price']);
    update_post_meta($product_id, '_tax_class', $product_data['tax_class']);
		if(isset($product_data['sku'])) {
			update_post_meta($product_id, '_sku', $product_data['sku']);
		}

    if($product->post_type == 'product_variation') {
      update_post_meta($product_id, '_variation_description', $product_data['description']);
    }

    status_header(200);
		die;
  }

  /**
	 * Delete billomat_id reference from WooCommerce product.
	 */
  public function import_delete_product() {
		$secret_key = get_option('wcb_secret_key');
		$request_secret_key = $_GET['secret_key'];

		if($secret_key != $request_secret_key) {
			die("Error: invalid secret key");
		}

		global $wpdb;

    $data = json_decode(file_get_contents("php://input"), true);
		$article_id = $data['article']['id'];
    $product_id = (int) $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'billomat_id' AND meta_value = '{$article_id}' LIMIT 1");

    if($product_id) {
      delete_post_meta($product_id, 'billomat_id');
    }
  }

  /**
	 * Map WooCommerce Product data to Billomat API fields.
   * @param WC_Product_Simple|WC_Product_Variation|WC_Product_External
	 * @return array Billomat API data
	 */
	private function map_data_export($product, $billomat_tax) {
		$data = array(
      'title'         => $product->get_name(),
      'currency_code' => get_woocommerce_currency(),
    );

		if($this->sync_article_numbers) {
			$article_number_valid = true;

			if($product->get_sku()) {
				$sku_without_prefix = str_replace($this->article_number_prefix, '', $product->get_sku());
				if(is_numeric($sku_without_prefix)) {
					$data['number'] = $sku_without_prefix;
				} else {
					$article_number_valid = false;
				}
			}

			if($this->article_number_prefix && $article_number_valid) {
				$data['number_pre'] = $this->article_number_prefix;
			}
		}

		if($this->article_description_source != 'disable') {
			$article_description = $this->article_description_source == 'short_description' ? $product->get_short_description() : $product->get_description();
			$article_description = wp_strip_all_tags($article_description);
			$data['description'] = $article_description;
		}

		$sales_price = $product->get_price();

		if($billomat_tax) {
			$data['tax_id'] = (int) $billomat_tax['id'];

			// Convert article price if WooCommerce and Billomat tax settings (net/gross) differ
			if(wc_prices_include_tax() && $this->billomat_net_gross() === 'NET') {
				$sales_price = $sales_price / (1 + ($billomat_tax['rate'] / 100));
			} else if(!wc_prices_include_tax() && $this->billomat_net_gross() === 'GROSS') {
				$sales_price = $sales_price * (1 + ($billomat_tax['rate'] / 100));
			}
		} else {
			$data['tax_id'] = null;
		}

		$data['sales_price'] = $sales_price;

		return apply_filters('woocommerce_billomat_product_export_data', $data, $product);
	}

  /**
	 * Map Billomat API fields to WooCommerce Product data.
   * @param array $api_data Billomat API data
	 * @return array WooCommerce Product data
	 */
  private function map_data_import($api_data) {
    global $wpdb;

		$data = array(
			'name' => $api_data['title'],
			'description' => $api_data['description'],
		);

		if($this->sync_article_numbers) {
			$valid_sku = true;
			if($this->article_number_prefix) {
				if(!strstr($api_data['article_number'], $this->article_number_prefix)) {
					$valid_sku = false;
				}
			}

			$article_number_without_prefix = str_replace($this->article_number_prefix, '', $api_data['article_number']);
			if(!is_numeric($article_number_without_prefix)) {
				$valid_sku = false;
			}

			if($valid_sku) {
				$data['sku'] = $api_data['article_number'];
			}
		}

		$billomat_taxes = WCB()->client->get_taxes();

    // Try to get WC tax class by Bilomat tax rate
    foreach($billomat_taxes as $billomat_tax) {
      if($billomat_tax['id'] == $api_data['tax_id']) {
        $billomat_tax_rate = number_format($billomat_tax['rate'], 4);

				// Find valid tax class
				$tax_class_slugs = WC_Tax::get_tax_class_slugs();
        $_tax_classes = $wpdb->get_col("SELECT tax_rate_class FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate = '{$billomat_tax_rate}'");
				foreach($_tax_classes as $_tax_class) {
					if(in_array($_tax_class, $tax_class_slugs)) {
						$tax_class = $_tax_class;
						break;
					}
				}

        if($tax_class) {
          $data['tax_class'] = $tax_class;
        }
				break;
      }
    }

		$price = $api_data['sales_price'];

		// Convert article price if WooCommerce and Billomat tax settings (net/gross) differ
		if(wc_prices_include_tax() && $this->billomat_net_gross() === 'NET') {
			$price = $price * (1 + ($billomat_tax['rate'] / 100));
		} else if(!wc_prices_include_tax() && $this->billomat_net_gross() === 'GROSS') {
			$price = $price / (1 + ($billomat_tax['rate'] / 100));
		}

    $data['price'] = round($price, 2);

		return apply_filters('woocommerce_billomat_product_import_data', $data, $api_data);
  }

	/**
	 * Get Billomat tax by rate
   * @param float $tax_rate
	 * @return array Billomat tax
	 */
  private function get_billomat_tax($tax_rate) {
    $found_billomat_tax = null;
    $billomat_taxes = WCB()->client->get_taxes();

    // Wrap single-tax response in array
    $billomat_taxes_keys = array_keys($billomat_taxes);
    if(!is_numeric(array_shift($billomat_taxes_keys))) {
      $billomat_taxes = array($billomat_taxes);
    }

    // Try to get Billomat tax by WC tax rate
    foreach($billomat_taxes as $billomat_tax) {
      if($tax_rate['rate'] == $billomat_tax['rate']) {
        $found_billomat_tax = $billomat_tax;
        break;
      }
    }

    return $found_billomat_tax;
  }

	private function billomat_net_gross() {
		if(!$this->billomat_net_gross) {
			$billomat_settings = WCB()->client->get_settings();
			$billomat_net_gross = $billomat_settings['net_gross'];
			$this->billomat_net_gross = $billomat_net_gross;
		}

		return $this->billomat_net_gross;
	}

	private function add_error($type, $post_id) {
		switch($type) {
			case 'article_404':
				$message = __("Billomat article could not be updated - the referenced Billomat article doesn´t exist. Please fix this by updating the reference in the Billomat meta box below.", 'woocommerce-billomat');
				break;
		}

		if($message) {
			WCB()->notices_controller->add_admin_notice($message, "product_{$post_id}_{$type}", 'error');
		}
	}
}