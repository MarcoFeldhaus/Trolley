<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce/Billomat API Client class
 *
 * Handles calls to the Billomat REST API
 *
 * @class 		WCB_Client
 * @version		1.0
 * @package		WooCommerceBillomat/Classes/Client
 * @category	Class
 * @author 		Billomat
 */
class WCB_Client {
  /**
	 * The single instance of the class.
	 *
	 * @var WCB_Client
	 */
	protected static $_instance = null;

  /**
	 * billomatID.
	 *
	 * @var string
	 */
  protected $billomat_id = null;

  /**
	 * Billomat API key.
	 *
	 * @var string
	 */
  protected $api_key = null;

	/**
	 * Billomat APP ID.
	 *
	 * @var string
	 */
  protected $app_id = null;

	/**
	 * Billomat APP secret.
	 *
	 * @var string
	 */
  protected $app_secret = null;

  /**
	 * API client.
	 *
	 * @var GuzzleHttp\Client
	 */
  public $client = null;

  /**
	 * Main WooCommerce/Billomat API Client Instance.
	 *
	 * Ensures only one instance of WooCommerce/Billomat API Client is loaded or can be loaded.
	 *
	 * @static
	 * @return WCB_Client - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

  public function __construct() {
    $this->set_credentials();

    if( $this->billomat_id && $this->api_key ) {
      $this->init_client();
    }
  }

	public function set_credentials() {
		$this->billomat_id = get_option( 'wcb_billomat_id' );
    $this->api_key = get_option( 'wcb_api_key' );
		$this->app_id = get_option( 'wcb_app_id' );
		$this->app_secret = get_option( 'wcb_app_secret' );
	}

  /**
	 * Initialize client for handling API calls.
	 */
  private function init_client() {
		include_once( WCB_ABSPATH . 'vendor/autoload.php' );

		$headers = array(
			'X-BillomatApiKey' => $this->api_key,
			'content-type' => 'application/json',
			'Accept' => 'application/json',
			'WooBillomatVersion' => WCB_VERSION,
			'User-Agent' => 'Billomat-WooCommerce-Plugin'
		);

		if($this->app_id && $this->app_secret) {
			$headers['X-AppId'] = $this->app_id;
			$headers['X-AppSecret'] = $this->app_secret;
		}

    $this->client = new GuzzleHttp\Client([
      'base_uri' => "https://{$this->billomat_id}.billomat.net",
      'headers' => $headers
    ]);
  }

	/**
	 * Get account from Billomat API.
	 * @return array
	 */
  public function get_account($error_handling = false) {
		$this->set_credentials();
		$this->init_client();
		if($this->client) {
			try {
				$res = $this->client->request('GET', '/api/clients/myself');
		    $body = json_decode($res->getBody(), true);
		    return $body['client'];
			} catch (Exception $e) {
				if($error_handling) {
					$this->handle_exception($e);
				}
			}
		}

		return false;
	}

	/**
	 * Get settings from Billomat API.
	 * @return array
	 */
  public function get_settings() {
		if($this->client) {
			try {
				$res = $this->client->request('GET', '/api/settings');
		    $body = json_decode($res->getBody(), true);
		    return $body['settings'];
			} catch (Exception $e) {
				$this->handle_exception($e);
				return false;
			}
		}

		return false;
	}

  /**
	 * Get invoice templates from Billomat API.
	 * @return array
	 */
  public function get_invoice_templates() {
		if(!$this->client) {
			return false;
		}

		try {
			$res = $this->client->request('GET', '/api/templates?type=INVOICE');
	    $body = json_decode($res->getBody(), true);
	    return $body['templates']['template'];
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
  }

	/**
	 * Get delivery note templates from Billomat API.
	 * @return array
	 */
  public function get_delivery_note_templates() {
		if(!$this->client) {
			return false;
		}

		try {
			$res = $this->client->request('GET', '/api/templates?type=DELIVERY_NOTE');
	    $body = json_decode($res->getBody(), true);
	    return $body['templates']['template'];
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
  }

	/**
	 * Get free texts from Billomat API.
	 * @return array
	 */
  public function get_free_texts() {
		if(!$this->client) {
			return false;
		}

		try {
			$res = $this->client->request('GET', '/api/free-texts?type=INVOICE');
	    $body = json_decode($res->getBody(), true);
	    return isset($body['free-texts']['free-text']) ? $body['free-texts']['free-text'] : null;
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
  }

	public function get_client($id, $error_handling = true) {
		try {
			$req = $this->client->request('GET', "/api/clients/{$id}");
	    $body = json_decode($req->getBody(), true);
			return $body['client'];
		} catch (Exception $e) {
			if($error_handling) {
				$this->handle_exception($e);
			}
			return false;
		}
	}

	public function create_client($data) {
		try {
			$req = $this->client->request('POST', '/api/clients', [
				'json' => array('client' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body['client'];
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
	}

	public function update_client($id, $data) {
		$result = new stdClass;
		$result->success = false;
		$result->body = null;

		try {
			$req = $this->client->request('PUT', "/api/clients/{$id}", [
				'json' => array('client' => $data)
			]);
		} catch(Exception $e) {
			$this->handle_exception($e);
			$result->response = $e->getResponse();
			return $result;
		}

		$result->body = json_decode($req->getBody(), true);
		$result->success = true;
		return $result;
	}

	public function delete_client($id) {
		try {
			$req = $this->client->request('DELETE', "/api/clients/{$id}");
	    $body = json_decode($req->getBody(), true);
			return $body;
		} catch(Exception $e) {
			$this->handle_exception($e);
			return $e->getResponse();
		}
	}

	public function get_article($id, $error_handling = true) {
		try {
			$req = $this->client->request('GET', "/api/articles/{$id}");
	    $body = json_decode($req->getBody(), true);
			return $body['article'];
		} catch (Exception $e) {
			if($error_handling) {
				$this->handle_exception($e);
			}
			return false;
		}
	}

	public function create_article($data) {
		try {
			$req = $this->client->request('POST', '/api/articles', [
				'json' => array('article' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body['article'];
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
	}

	public function update_article($id, $data) {
		$result = new stdClass;
		$result->success = false;
		$result->body = null;

		try {
			$req = $this->client->request('PUT', "/api/articles/{$id}", [
				'json' => array('article' => $data)
			]);
		} catch(Exception $e) {
			$this->handle_exception($e);
			$result->response = $e->getResponse();
			return $result;
		}

		$result->body = json_decode($req->getBody(), true);
		$result->success = true;
		return $result;
	}

	public function delete_article($id) {
		try {
			$req = $this->client->request('DELETE', "/api/articles/{$id}");
			$body = json_decode($req->getBody(), true);
			return $body;
		} catch(Exception $e) {
			$this->handle_exception($e);
			return $e->getResponse();
		}
	}

	public function get_taxes() {
		try {
			$res = $this->client->request('GET', '/api/taxes');
	    $body = json_decode($res->getBody(), true);
	    return $body['taxes']['tax'];
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
	}

	public function create_tax_rate($data) {
		try {
			$req = $this->client->request('POST', '/api/taxes', [
				'json' => array('article' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body['tax'];
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
	}

	public function create_invoice($data, $context = null) {
		try {
			$req = $this->client->request('POST', '/api/invoices', [
				'json' => array('invoice' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body['invoice'];
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function update_invoice($id, $data) {
		try {
			$req = $this->client->request('PUT', "/api/invoices/{$id}", [
				'json' => array('invoice' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body['invoice'];
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
	}

	public function complete_invoice($id, $data, $context = null) {
		try {
			$req = $this->client->request('PUT', "/api/invoices/{$id}/complete", [
				'json' => array('complete' => $data)
			]);
			return $req->getStatusCode();
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function mail_invoice($id, $data, $context = null) {
		try {
			$req = $this->client->request('POST', "/api/invoices/{$id}/email", [
				'json' => array('email' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body;
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function mail_delivery_note($id, $data) {
		try {
			$req = $this->client->request('POST', "/api/delivery-notes/{$id}/email", [
				'json' => array('email' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body;
		} catch (Exception $e) {
			$this->handle_exception($e);
			return false;
		}
	}

	public function add_invoice_payment($id, $amount, $payment_method = null, $context = null) {
		try {
			$data = array('invoice_id' => $id, 'amount' => $amount, 'mark_invoice_as_paid' => true);
			if($payment_method) {
				$data['type'] = $payment_method;
			}
			$req = $this->client->request('POST', '/api/invoice-payments', [
				'json' => array('invoice-payment' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body;
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function add_invoice_tag($id, $name, $context = null) {
		try {
			$req = $this->client->request('POST', '/api/invoice-tags', [
				'json' => array('invoice-tag' => array('invoice_id' => $id, 'name' => $name))
			]);
	    $body = json_decode($req->getBody(), true);
			return $body;
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function get_invoice($id, $error_handling = true, $context = null) {
		try {
			$req = $this->client->request('GET', "/api/invoices/{$id}/pdf");
	  	$body = json_decode($req->getBody(), true);
			return $body['pdf'];
		} catch (Exception $e) {
			if($error_handling) {
				$this->handle_exception($e, $context);
			}
			return false;
		}
	}

	public function get_invoice_data($id, $context = null) {
		try {
			$req = $this->client->request('GET', "/api/invoices/{$id}");
	  	$body = json_decode($req->getBody(), true);
			return $body['invoice'];
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function create_delivery_note($id, $items, $context = null) {
		try {
			$invoice_data = self::get_invoice_data($id);
			$client_id = $invoice_data['client_id'];
			$req = $this->client->request('POST', '/api/delivery-notes', [
				'json' => array('delivery-note' => array(
					'invoice_id' => $id,
					'client_id' => $client_id,
					'delivery-note-items'	=> array( 'delivery-note-item' => $items )
				))
			]);
			$body = json_decode($req->getBody(), true);
			return $body['delivery-note'];
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function complete_delivery_note($id, $data, $context = null) {
		try {
			$req = $this->client->request('PUT', "/api/delivery-notes/{$id}/complete", [
				'json' => array('complete' => $data)
			]);
	    $body = json_decode($req->getBody(), true);
			return $body;
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return false;
		}
	}

	public function get_delivery_note($id, $error_handling = true, $context = null) {
		try {
			$req = $this->client->request('GET', "/api/delivery-notes/{$id}/pdf");
			$body = json_decode($req->getBody(), true);
			return $body['pdf'];
		} catch (Exception $e) {
			if($error_handling) {
				$this->handle_exception($e, $context);
			}
			return false;
		}
	}

	public function cancel_invoice($id, $context = null) {
		try {
			$req = $this->client->request('PUT', "/api/invoices/{$id}/cancel");
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return $e->getResponse();
		}
	}

	public function correct_invoice($id, $data, $context = null) {
		try {
			$req = $this->client->request('POST', "/api/invoices/{$id}/correct", [
				'json' => array('invoice' => $data)
			]);
			$body = json_decode($req->getBody(), true);
			return $body['invoice'];
		} catch (Exception $e) {
			$this->handle_exception($e, $context);
			return $e->getResponse();
		}
	}

	public function remove_connection() {
		update_option('wcb_billomat_id', null);
		update_option('wcb_api_key', null);
		update_option('wcb_connected', '0');
	}

	private function handle_exception($exception, $context = null) {
		if(true === WP_DEBUG) {
			error_log($exception);
		}
		$message = $exception->getMessage();
		if($context) {
			$context_r = print_r($context, true);
			$message .= "Context: $context_r";
		}
		$this->add_admin_error($message);
	}

	private function add_admin_error($error_message) {
		$account = $this->get_account();

		if($account) {
			foreach($account['quotas']['quota'] as $quota) {
				$entity = $quota['entity'];
				if($quota['available'] == $quota['used']) {
					$message = sprintf( __("Your Billomat quota limit for %1$s is reached.", 'woocommerce-billomat'), $entity );
					WCB()->notices_controller->add_admin_notice($message, "quota_{$entity}", 'error', true);
				} else {
					WCB()->notices_controller->remove_admin_notice("quota_{$entity}");
				}
			}
		}

		$message = __( "An error occured while trying to communicate with the Billomat API. Please visit the WooCommerce Billomat options and review the error message. If everything is ok, hit save to remove this warning.", 'woocommerce-billomat' );
		WCB()->notices_controller->add_admin_notice($message, 'client_error', 'error', true);

		update_option('wcb_client_error', '1');
		update_option('wcb_last_error', $error_message);
	}
}
