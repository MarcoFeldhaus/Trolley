<?php
/**
 * WCB_Frontend_Order_Actions class
 *
 * Adds actions/filters for displaying Billomat invoices in WooCommerce frontend.
 *
 * @class 		WCB_Frontend_Order_Actions
 * @version		1.2.0
 * @package		WooCommerceBillomat/Classes/Frontend
 * @category	Class
 * @author 		Billomat
 */
class WCB_Frontend_Order_Actions {
  /**
   * Bootstraps the class and hooks required actions & filters.
   */
  public static function init() {
    if(get_option('wcb_add_order_actions_pdf_button') == 'yes') {
      add_action('woocommerce_my_account_my_orders_actions', __CLASS__ . '::order_actions_pdf_button', 10, 2);
    }

    if(get_option('wcb_add_order_details_pdf_link') == 'yes') {
      add_action('woocommerce_order_details_after_order_table', __CLASS__ . '::order_details_pdf_link', 10, 1);
    }
  }

  public static function order_actions_pdf_button($actions, $order) {
    $billomat_invoice_id = get_post_meta($order->get_id(), 'billomat_id', true);
    $billomat_draft = get_post_meta($order->get_id(), 'billomat_draft', true);

    if($billomat_invoice_id && $billomat_draft !== "1") {
      $invoice_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_download_invoice&order_id=' . $order->get_id()), 'wcb_download_invoice');

      $invoice_button_action = array(
        'url'  => $invoice_url,
        'name' => _x('Invoice', 'Invoice button text in order actions', 'woocommerce-billomat'),
      );

      $invoice_button_action = apply_filters('woocommerce_billomat_invoice_button_action', $invoice_button_action);
      $actions['wcb-invoice-button'] = $invoice_button_action;
    }

    return $actions;
  }

  public static function order_details_pdf_link($order) {
    $billomat_invoice_id = get_post_meta($order->get_id(), 'billomat_id', true);
    $billomat_draft = get_post_meta($order->get_id(), 'billomat_draft', true);

    if($billomat_invoice_id && $billomat_draft !== "1") {
      $invoice_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_download_invoice&order_id=' . $order->get_id()), 'wcb_download_invoice');
      $linktext = _x('Invoice', 'Invoice link text in order details', 'woocommerce-billomat');
      $link = "<p class=\"wcb-invoice-link-wrapper\"><a class=\"wcb-invoice-link\" href=\"{$invoice_url}\">{$linktext}</a></p>";
      echo apply_filters('woocommerce_billomat_invoice_link', $link, $invoice_url);
    }
  }
}

WCB_Frontend_Order_Actions::init();