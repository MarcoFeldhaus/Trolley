<?php
/**
 * WCB_Settings_Tab class
 *
 * Adds a new tab to the WooCommerce settings screen.
 *
 * @class 		WCB_Settings_Tab
 * @version		1.0
 * @package		WooCommerceBillomat/Classes/Settings
 * @category	Class
 * @author 		Billomat
 */
class WCB_Settings_Tab {
  /**
   * Bootstraps the class and hooks required actions & filters.
   */
  public static function init() {
    add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
    add_action( 'woocommerce_settings_tabs_billomat', __CLASS__ . '::settings_tab' );
    add_action( 'woocommerce_update_options_billomat', __CLASS__ . '::update_settings' );
  }

  /**
   * Add a new settings tab to the WooCommerce settings tabs array.
   *
   * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
   * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
   */
  public static function add_settings_tab( $settings_tabs ) {
    $settings_tabs['billomat'] = __( 'Billomat', 'woocommerce-billomat' );
    return $settings_tabs;
  }

  /**
   * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
   *
   * @uses woocommerce_admin_fields()
   * @uses self::get_settings()
   */
  public static function settings_tab() {
    woocommerce_admin_fields( self::get_settings() );
  }

  /**
   * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
   *
   * @uses woocommerce_update_options()
   * @uses self::get_settings()
   */
  public static function update_settings() {
    woocommerce_update_options( self::get_settings( false ) );

    // Billomat API test call
    $account = WCB()->client->get_account();
    if(!$account) {
      WCB()->client->remove_connection();
      wp_die( __( "<strong>ERROR:</strong> The Billomat API credentials are not valid.", 'woocommerce-billomat' ) );
    } else {
      woocommerce_update_options( self::get_settings() );
      update_option('wcb_connected', '1');
      delete_option('wcb_client_error');
      WCB()->notices_controller->remove_admin_notice('client_error');
    }

    // Reset data
    if(isset($_POST['wcb_reset_clients']) && $_POST['wcb_reset_clients'] == '1') {
      self::reset_wcb_data('clients');
    }

    if(isset($_POST['wcb_reset_articles']) && $_POST['wcb_reset_articles'] == '1') {
      self::reset_wcb_data('articles');
    }

    if(isset($_POST['wcb_reset_invoices']) && $_POST['wcb_reset_invoices'] == '1') {
      self::reset_wcb_data('invoices');
    }
  }

  public static function reset_wcb_data($type) {
    global $wpdb;

    switch($type) {
      case 'clients':
        $wpdb->delete($wpdb->usermeta, array('meta_key' => 'billomat_id'));
        break;
      case 'articles':
        $wpdb->query("DELETE meta FROM $wpdb->postmeta as meta
                      LEFT JOIN $wpdb->posts as posts ON posts.ID = meta.post_id
                      WHERE posts.post_type IN ('product', 'product_variation')
                      AND meta.meta_key = 'billomat_id'"
        );
        break;
      case 'invoices':
        $wpdb->query("DELETE meta FROM $wpdb->postmeta as meta
                      LEFT JOIN $wpdb->posts as posts ON posts.ID = meta.post_id
                      WHERE posts.post_type = 'shop_order'
                      AND meta.meta_key IN('billomat_id', 'billomat_delivery_note_id')"
        );
        break;
    }

    delete_option("wcb_reset_{$type}");
  }

  /**
   * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
   *
   * @return array Array of settings for @see woocommerce_admin_fields() function.
   */
  public static function get_settings( $dynamic = true ) {
    $invoice_template_options = array();
    $invoice_templates = null;
    $free_text_options = array( '' => __( "No text template", 'woocommerce-billomat' ) );
    $free_texts = null;
    $delivery_note_template_options = array();
    $delivery_note_templates = null;
    $default_invoice_template = null;

    if(get_option('wcb_connected')) {
      $account = WCB()->client->get_account(true);
      if($account) {
        if( $dynamic ) {
          if( $invoice_templates = WCB()->client->get_invoice_templates() ) {
            if( isset( $invoice_templates['id'] ) ) {
              $invoice_templates = array( $invoice_templates );
            }
            array_walk($invoice_templates, function(&$template) use (&$invoice_template_options) {
              $invoice_template_options[$template['id']] = $template['name'];
            });
            foreach($invoice_templates as $template) {
              if($template['is_default']) {
                $default_invoice_template = $template['id'];
                break;
              }
            }
          }
          if( $free_texts = WCB()->client->get_free_texts() ) {
            if(isset($free_texts['id'])) {
              $free_texts = array($free_texts);
            }
            array_walk($free_texts, function(&$text) use (&$free_text_options) {
              $free_text_options[$text['id']] = $text['name'];
            });
          }
          if( $delivery_note_templates = WCB()->client->get_delivery_note_templates() ) {
            if( isset( $delivery_note_templates['id'] ) ) {
              $delivery_note_templates = array( $delivery_note_templates );
            }
            array_walk($delivery_note_templates, function(&$template) use (&$delivery_note_template_options) {
              $delivery_note_template_options[$template['id']] = $template['name'];
            });
          }
        }
      }
    }

    $secret_key = get_option('wcb_secret_key');
    if(!$secret_key) {
      $secret_key = uniqid();
      update_option('wcb_secret_key', $secret_key);
    }

    $wc_payment_gateways = new WC_Payment_Gateways();
    $payment_gateways = $wc_payment_gateways->get_available_payment_gateways();

    $settings = array();

    if( get_option('wcb_client_error') === '1' ) {
      $last_error = get_option('wcb_last_error');

      if( $last_error ) {
        $settings['errors_section_title'] = array(
          'name'     => '',
          'type'     => 'title',
          'desc'     => sprintf(
            __( '<div class="wcb-error-log"><p><strong>Warning:</strong> the following error was returned by the Billomat API. Please contact the Billomat support if you aren\'t able to fix the cause:</p><pre>%s</pre></div>', 'woocommerce-billomat' ),
            $last_error
          ),
          'id'       => 'wc_billomat_errors_section_title'
        );
      }
    }

    $settings['errors_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_errors_section_end'
    );

    // API section
    $settings['api_section_title'] = array(
      'name'     => __( 'API options', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => '',
      'id'       => 'wc_billomat_api_section_title'
    );
    $settings['billomat_id'] = array(
      'name' => __( 'billomatID', 'woocommerce-billomat' ),
      'type' => 'text',
      'id'   => 'wcb_billomat_id'
    );
    $settings['api_key'] = array(
      'name' => __( 'API key', 'woocommerce-billomat' ),
      'type' => 'text',
      'desc' => sprintf(
        __( 'Enter the %s that you have activated under <strong>Settings > Employees</strong> in Billomat.', 'woocommerce-billomat' ),
        __( '<a href="https://www.billomat.com/en/api/basics/authentication/">API key</a>', 'woocommerce-billomat' )
      ),
      'id'   => 'wcb_api_key'
    );
    $settings['app_id'] = array(
      'name' => __( 'APP-ID', 'woocommerce-billomat' ),
      'type' => 'text',
      'desc' =>  __( 'If you want to use a Billomat App, enter its ID here.', 'woocommerce-billomat' ),
      'id'   => 'wcb_app_id'
    );
    $settings['app_secret'] = array(
      'name' => __( 'APP-Secret', 'woocommerce-billomat' ),
      'type' => 'text',
      'desc' =>  __( 'If you want to use a Billomat App, enter its secret here.', 'woocommerce-billomat' ),
      'id'   => 'wcb_app_secret'
    );

    $settings['api_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_api_section_end'
    );

    // Invoice section
    $settings['invoice_section_title'] = array(
      'name'     => __( 'Invoices', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => '',
      'id'       => 'wc_billomat_invoice_section_title'
    );
    $settings['invoice_prefix'] = array(
      'name' => __( 'Prefix', 'woocommerce-billomat' ),
      'type' => 'text',
      'desc' => __( 'Text prepended to invoice numbers.', 'woocommerce-billomat' ),
      'id'   => 'wcb_invoice_prefix'
    );
    $settings['invoice_tags'] = array(
      'name' => __( 'Tag(s)', 'woocommerce-billomat' ),
      'type' => 'text',
      'desc' => __( 'One or more tags, comma-separated.', 'woocommerce-billomat' ),
      'id'   => 'wcb_invoice_tags'
    );
    if(get_option('wcb_client_error') !== '1') {
      $settings['free_text'] = array(
        'name' => __( 'Text template', 'woocommerce-billomat' ),
        'type' => 'select',
        'options' => $free_text_options,
        'desc' => !$free_texts ? __( 'Please enter your API details above to select a template.', 'woocommerce-billomat' ) : '',
        'id'   => 'wcb_free_text',
        'disabled' => 'disabled'
      );
    }
    $settings['invoice_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_invoice_section_end'
    );

    // Invoice templates section
    if(get_option('wcb_client_error') !== '1') {
      $settings['invoice_template_section_title'] = array(
        'name'     => __( 'Invoice templates', 'woocommerce-billomat' ),
        'type'     => 'title',
        'desc'     => __( 'Select an invoice template per payment gateway.', 'woocommerce-billomat' ),
        'id'       => 'wc_billomat_invoice_template_section_title'
      );

      if(count($payment_gateways) < 1) {
        $settings['invoice_template_no_gateways_message'] = array(
          'type'     => 'title',
          'desc'     => __('<strong>Currently there are no active payment gateways. Please define them first.</strong>', 'woocommerce-billomat'),
          'id'       => 'invoice_status_section_no_gateways_message'
        );
      } else {
        $settings["invoice_template"] = array(
          'name' => __( 'Default', 'woocommerce-billomat' ),
          'type' => 'select',
          'options' => $invoice_template_options,
          'default' => $default_invoice_template,
          'desc' => !$invoice_templates ? __( 'Please enter your API details above to select a template.', 'woocommerce-billomat' ) : '',
          'id'   => "wcb_invoice_template"
        );
        foreach($payment_gateways as $payment_gateway) {
          $settings["invoice_template_{$payment_gateway->id}"] = array(
            'name' => $payment_gateway->title,
            'type' => 'select',
            'options' => $invoice_template_options,
            'default' => $default_invoice_template,
            'desc' => !$invoice_templates ? __( 'Please enter your API details above to select a template.', 'woocommerce-billomat' ) : '',
            'id'   => "wcb_invoice_template_{$payment_gateway->id}"
          );
        }
      }
    }

    $settings['invoice_template_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_invoice_template_section_end'
    );

    // Delivery note section
    if(get_option('wcb_client_error') !== '1') {
      $settings['delivery_note_section_title'] = array(
        'name'     => __( 'Delivery notes', 'woocommerce-billomat' ),
        'type'     => 'title',
        'desc'     => '',
        'id'       => 'wc_billomat_delivery_note_section_title'
      );

      $settings['delivery_note_template'] = array(
        'name' => __( 'Delivery note template', 'woocommerce-billomat' ),
        'type' => 'select',
        'options' => $delivery_note_template_options,
        'desc' => !$delivery_note_templates ? __( 'Please enter your API details above to select a template.', 'woocommerce-billomat' ) : '',
        'id'   => 'wcb_default_delivery_note_template'
      );
    }
    $settings['delivery_note_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_delivery_note_section_end'
    );

    // Article section
    $settings['article_section_title'] = array(
      'name'     => __( 'Articles', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => '',
      'id'       => 'wc_billomat_article_section_title'
    );
    $settings['sync_article_numbers'] = array(
      'name' => __( 'Sync article numbers', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Note that only numeric article numbers (that have to contain the prefix specified below, or no prefix at all) can be synced with Billomat.', 'woocommerce-billomat' ),
      'id'   => 'wcb_sync_article_numbers'
    );
    $settings['article_number_prefix'] = array(
      'name' => __( 'Article number prefix', 'woocommerce-billomat' ),
      'type' => 'text',
      'desc' => __( 'Text prepended to article numbers.', 'woocommerce-billomat' ),
      'id'   => 'wcb_article_number_prefix'
    );
    $settings['article_description_source'] = array(
      'name' => __( 'Article description source', 'woocommerce-billomat' ),
      'type' => 'select',
      'options' => array(
        'short_description' => __( 'Product short description', 'woocommerce-billomat' ),
        'description' => __( 'Product description', 'woocommerce-billomat' ),
        'disable' => __( 'Disable description sync', 'woocommerce-billomat' ),
      ),
      'id'   => 'wcb_article_description_source'
    );
    $settings['article_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_article_section_end'
    );

    // Invoice creation section
    $settings['invoice_creation_section_title'] = array(
      'name'     => __( 'Invoice creation', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => __( 'Select the WooCommerce order status as Billomat invoice creation trigger per gateway.', 'woocommerce-billomat' ),
      'id'       => 'wc_billomat_invoice_creation_section_title'
    );
    $settings['disable_invoice_creation'] = array(
      'name' => __( 'Disable completely', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Disable creation and cancellation of invoices globally (overrides per-gateway setting below).', 'woocommerce-billomat' ),
      'id'   => 'wcb_disable_invoice_creation'
    );
    $status_options = array(
      'pending'     => __('Pending payment', 'woocommerce-billomat'),
      'processing'  => __('Processing', 'woocommerce-billomat'),
      'on-hold'     => __('On hold', 'woocommerce-billomat'),
      'completed'   => __('Completed', 'woocommerce-billomat'),
      'disable'     => __('Disable', 'woocommerce-billomat'),
    );
    $settings["invoice_creation"] = array(
      'name' => __( 'Default', 'woocommerce-billomat' ),
      'type' => 'select',
      'options' => $status_options,
      'default' => 'completed',
      'id'   => "invoice_creation"
    );
    foreach($payment_gateways as $payment_gateway) {
      $settings["invoice_creation_{$payment_gateway->id}"] = array(
        'name' => $payment_gateway->title,
        'type' => 'select',
        'options' => $status_options,
        'default' => 'completed',
        'id'   => "invoice_creation_{$payment_gateway->id}"
      );
    }
    $settings['create_correction_invoice'] = array(
      'name' => __( 'Create correction invoice', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Create a correction invoice if a order is cancelled.', 'woocommerce-billomat' ),
      'id'   => 'wcb_create_correction_invoice'
    );
    $settings['invoice_creation_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_invoice_creation_section_end'
    );

    // Invoice delivery section
    $settings['invoice_delivery_section_title'] = array(
      'name'     => __( 'Invoice delivery', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => __( 'Select how and when Billomat invoices are delivered to the customer.', 'woocommerce-billomat' ),
      'id'       => 'wc_billomat_invoice_delivery_section_title'
    );
    $settings['invoice_mail'] = array(
      'name' => __( 'Delivery type', 'woocommerce-billomat' ),
      'type' => 'select',
      'desc' => __( 'Note that \'Attach to email\' only works for Billomat invoices with status <strong>open</strong> or <strong>paid</strong> (see \'Invoice status\' section below).', 'woocommerce-billomat' ),
      'options' => array(
        'none' => __( 'Don´t send invoices', 'woocommerce-billomat' ),
        'attach' => __( 'Attach to email', 'woocommerce-billomat' ),
        'billomat' => __( 'Send via Billomat', 'woocommerce-billomat' ),
      ),
      'id'   => 'wcb_send_invoice'
    );
    $settings['invoice_mail_status'] = array(
      'name' => __( 'Order status', 'woocommerce-billomat' ),
      'type' => 'select',
      'desc' => __( 'Choose the order status that triggers the mail delivery or attachment.', 'woocommerce-billomat' ),
      'options' => array(
        ''            => __('On invoice creation (see setting above)', 'woocommerce-billomat'),
        'pending'     => __('Pending payment', 'woocommerce-billomat'),
        'processing'  => __('Processing', 'woocommerce-billomat'),
        'on-hold'     => __('On hold', 'woocommerce-billomat'),
        'completed'   => __('Completed', 'woocommerce-billomat'),
      ),
      'id'   => 'wcb_send_invoice_status'
    );
    $settings['invoice_delivery_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_invoice_delivery_section_end'
    );

    // Invoice status section
    $settings['invoice_status_section_title'] = array(
      'name'     => __( 'Payment method / invoice and order status mapping', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => __('Define the Billomat payment type and invoice status as well as order status after payment per payment gateway.<br> <em>Please notice that invoices are not automatically sent after order completion if the status is set to <strong>draft</strong></em>.', 'woocommerce-billomat'),
      'id'       => 'wc_billomat_invoice_status_section_title'
    );

    if(count($payment_gateways) < 1) {
      $settings['invoice_status_section_no_gateways_message'] = array(
        'type'     => 'title',
        'desc'     => __('<strong>Currently there are no active payment gateways. Please define them first.</strong>', 'woocommerce-billomat'),
        'id'       => 'invoice_status_section_no_gateways_message'
      );
    } else {
      $payment_methods = array(
        '' => __('- Select -', 'woocommerce-billomat'),
        'BANK_CARD' => __('Bank card', 'woocommerce-billomat'),
        'BANK_TRANSFER' => __('Transfer', 'woocommerce-billomat'),
        'DEBIT' => __('Direct debit', 'woocommerce-billomat'),
        'CASH' => __('Payment in cash', 'woocommerce-billomat'),
        'CHECK' => __('Check', 'woocommerce-billomat'),
        'PAYPAL' => __('PayPal', 'woocommerce-billomat'),
        'CREDIT_CARD' => __('Credit card', 'woocommerce-billomat'),
        'COUPON' => __('Voucher', 'woocommerce-billomat'),
        'MISC' => __('Other', 'woocommerce-billomat'),
      );
      $settings["invoice_mapping"] = array(
        'name' => __( 'Default', 'woocommerce-billomat' ),
        'type' => 'select',
        'options' => $payment_methods,
        'id'   => "wcb_invoice_mapping"
      );
      $settings["invoice_status"] = array(
        'name' => __( "- Invoice status", 'woocommerce-billomat' ),
        'type' => 'select',
        'options' => array(
          'paid'      => __('Paid', 'woocommerce-billomat'),
          'not_paid'  => __('Open', 'woocommerce-billomat'),
          'draft'  => __('Draft', 'woocommerce-billomat'),
        ),
        'id'   => "wcb_invoice_status"
      );
      foreach($payment_gateways as $payment_gateway) {
        $settings["invoice_mapping_{$payment_gateway->id}"] = array(
          'name' => $payment_gateway->title,
          'type' => 'select',
          'options' => $payment_methods,
          'id'   => "wcb_invoice_mapping_{$payment_gateway->id}"
        );

        $settings["invoice_status_{$payment_gateway->id}"] = array(
          'name' => __( "- Invoice status", 'woocommerce-billomat' ),
          'type' => 'select',
          'options' => array(
            'paid'      => __('Paid', 'woocommerce-billomat'),
            'not_paid'  => __('Open', 'woocommerce-billomat'),
            'draft'  => __('Draft', 'woocommerce-billomat'),
          ),
          'id'   => "wcb_invoice_status_{$payment_gateway->id}"
        );

        $settings["complete_orders_status_{$payment_gateway->id}"] = array(
          'name' => __( '- Order status on invoice payment', 'woocommerce-billomat' ),
          'type' => 'select',
          'options' => array(
            'none'        => __('Do not complete orders', 'woocommerce-billomat'),
            'pending'     => __('Pending', 'woocommerce-billomat'),
            'processing'  => __('Processing', 'woocommerce-billomat'),
            'on-hold'     => __('On hold', 'woocommerce-billomat'),
            'completed'   => __('Completed', 'woocommerce-billomat'),
            'cancelled'   => __('Cancelled', 'woocommerce-billomat'),
            'refunded'    => __('Refunded', 'woocommerce-billomat'),
            'failed'      => __('Failed', 'woocommerce-billomat'),
          ),
          'default' => get_option('wcb_complete_orders') == 'yes' ? 'completed' : '',
          'desc' => __( 'Select a status to update a WooCommerce order when the invoice status changes to <strong>paid</strong> in Billomat.', 'woocommerce-billomat' ),
          'id'   => "wcb_complete_orders_status_{$payment_gateway->id}"
        );
      }
    }

    $settings['invoice_status_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_invoice_status_section_end'
    );

    // Order update section
    $settings['order_update_section_title'] = array(
      'name'     => __( 'Update orders', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => __('Update WooCommerce orders automatically based on Billomat invoice status change webhooks.', 'woocommerce-billomat'),
      'id'       => 'order_update_section_title'
    );

    $settings['cancel_orders'] = array(
      'name' => __( 'Cancel orders', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Check this to cancel a WooCommerce order when it\'s status changes to <strong>canceled</strong> in Billomat. Notice: in case of undoing a cancellation in Billomat you have to update the order status manually.', 'woocommerce-billomat' ),
      'id'   => 'wcb_cancel_orders'
    );

    $settings['order_update_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'order_update_section_end'
    );

    // Order summary section
    $settings['order_summary_section_title'] = array(
      'name'     => __( 'Order summary', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => '',
      'id'       => 'order_summary_section_title'
    );

    $settings['add_order_actions_pdf_button'] = array(
      'name' => __( 'Order actions invoice button', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Adds an invoice download button to the order table in \'my account\'.', 'woocommerce-billomat'),
      'id'   => 'wcb_add_order_actions_pdf_button'
    );

    $settings['add_order_details_pdf_link'] = array(
      'name' => __( 'Order detail invoice link', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Adds an invoice download link to the order detail page in \'my account\'.', 'woocommerce-billomat'),
      'id'   => 'wcb_add_order_details_pdf_link'
    );

    $settings['order_summary_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'order_summary_section_end'
    );

    // API section
    $settings['webhooks_section_title'] = array(
      'name'     => __( 'Billomat webhooks', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => __('Please register Webhooks with this secret key in Billomat in order to synchronize WooCommerce data. <i>This is mandatory for the plugin to work correctly.</i><br> Refer to the Readme for further instructions.', 'woocommerce-billomat'),
      'id'       => 'wc_billomat_webhooks_section_title'
    );
    $settings['secret_id'] = array(
      'name' => __( 'Secret key', 'woocommerce-billomat' ),
      'type' => 'text',
      'id'   => 'wcb_secret_key'
    );
    $settings['webhooks_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_webhooks_section_end'
    );

    // Reset references section
    $settings['reset_section_title'] = array(
      'name'     => __( 'Reset data', 'woocommerce-billomat' ),
      'type'     => 'title',
      'desc'     => __('Below you can delete the references of WooCommerce items to their corresponding Billomat items. Data in Billomat won´t be affected by this.<br><strong>Please be aware that this action is irreversible, so use it with caution!</strong>', 'woocommerce-billomat') . '</span>',
      'id'       => 'wc_billomat_reset_section_title',
      'class' => 'wcb-reset'
    );
    $settings['reset_clients'] = array(
      'name' => __( 'Reset customers -> clients', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Resets references between WooCommerce customers and Billomat clients.', 'woocommerce-billomat' ),
      'id'   => 'wcb_reset_clients',
      'class' => 'wcb-reset'
    );
    $settings['reset_articles'] = array(
      'name' => __( 'Reset products -> articles', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Resets references between WooCommerce products and Billomat articles.', 'woocommerce-billomat' ),
      'id'   => 'wcb_reset_articles',
      'class' => 'wcb-reset'
    );
    $settings['reset_invoices'] = array(
      'name' => __( 'Reset orders -> invoices', 'woocommerce-billomat' ),
      'type' => 'checkbox',
      'desc' => __( 'Resets references between WooCommerce orders and Billomat invoices.', 'woocommerce-billomat' ),
      'id'   => 'wcb_reset_invoices',
      'class' => 'wcb-reset'
    );
    $settings['reset_section_end'] = array(
      'type' => 'sectionend',
      'id' => 'wc_billomat_reset_section_end'
    );
    return apply_filters( 'wc_billomat_settings', $settings );
  }
}

WCB_Settings_Tab::init();