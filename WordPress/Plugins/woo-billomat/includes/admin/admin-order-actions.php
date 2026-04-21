<?php
/**
 * WCB_Admin_Order_Actions class
 *
 * Adds actions for handling Billomat invoices in WooCommerce admin.
 *
 * @class 		WCB_Admin_Order_Actions
 * @version		1.2.0
 * @package		WooCommerceBillomat/Classes/Admin
 * @category	Class
 * @author 		Billomat
 */
class WCB_Admin_Order_Actions {
  /**
   * Bootstraps the class and hooks required actions & filters.
   */
  public static function init() {
    add_action('woocommerce_admin_order_actions_end', __CLASS__ . '::invoice_actions');
    add_action('wp_ajax_wcb_complete_invoice', __CLASS__ . '::complete_invoice');
    add_action('wp_ajax_wcb_download_invoice', __CLASS__ . '::download_invoice');
    add_action('wp_ajax_nopriv_wcb_download_invoice', __CLASS__ . '::download_invoice');
    add_action('wp_ajax_wcb_send_invoice', __CLASS__ . '::send_invoice');
    add_action('wp_ajax_wcb_download_delivery_note', __CLASS__ . '::download_delivery_note');
    add_action('wp_ajax_wcb_create_delivery_note', __CLASS__ . '::create_delivery_note');
    add_action('wp_ajax_wcb_send_delivery_note', __CLASS__ . '::send_delivery_note');
  }

  public static function invoice_actions($order) {
    $billomat_invoice_id = get_post_meta($order->get_id(), 'billomat_id', true);
    $billomat_draft = get_post_meta($order->get_id(), 'billomat_draft', true);
    $invoice_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_download_invoice&order_id=' . $order->get_id()), 'wcb_download_invoice');

    if($billomat_invoice_id && $billomat_draft !== "1") { ?>
      <a href="<?php echo $invoice_url ?>" class="button tips view invoice" data-tip="<?php _e('Download invoice', 'woocommerce-billomat') ?>">
        <?php _e('Download invoice', 'woocommerce-billomat') ?>
      </a>
    <?php }

    $billomat_delivery_note_id = get_post_meta($order->get_id(), 'billomat_delivery_note_id', true);
    if($billomat_delivery_note_id) {
      $delivery_note_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_download_delivery_note&order_id=' . $order->get_id()), 'wcb_download_delivery_note');
      ?>
      <a href="<?php echo $delivery_note_url ?>" class="button tips view download-delivery-note" data-tip="<?php _e('Download delivery note', 'woocommerce-billomat') ?>">
        <?php _e('Download delivery note', 'woocommerce-billomat') ?>
      </a>
      <?php
    } else {
      if(1==2):
      $delivery_note_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_create_delivery_note&order_id=' . $order->get_id()), 'wcb_create_delivery_note');
      ?>
      <a href="<?php echo $delivery_note_url ?>" class="button tips view create-delivery-note" data-tip="<?php _e('Create delivery note', 'woocommerce-billomat') ?>">
        <?php _e('Create delivery note', 'woocommerce-billomat') ?>
      </a>
      <?php
      endif;
    }
  }

  public static function complete_invoice() {
    if(empty($_GET['action']) || !is_user_logged_in() || !check_admin_referer($_GET['action']) || empty($_GET['order_id']) || !current_user_can('manage_woocommerce_orders') && !current_user_can('edit_shop_orders')) {
      wp_die(__('You are not authorized to perform this action.', 'woocommerce-billomat'));
    }

    $order_id = $_GET['order_id'];
    $order = new WC_Order($order_id);
    $payment_method = $order->get_payment_method();
    $billomat_id = get_post_meta($order->get_id(), 'billomat_id', true);
    $complete_success = false;

    $invoice_data = WCB()->client->get_invoice_data($billomat_id, array("Order ID" => $order_id));
    if($invoice_data['status'] == 'DRAFT') {
      $billomat_invoice_template_id = get_option("wcb_invoice_template_{$payment_method}");
			if(!$billomat_invoice_template_id) {
				// Fallback, wcb_default_template was removed in v1.2.0
				$billomat_invoice_template_id = get_option('wcb_default_template');
			}

      if($billomat_invoice_template_id) {
        $complete_data = array('template_id' => $billomat_invoice_template_id);
      } else {
        $complete_data = array();
      }
      $request_status = WCB()->client->complete_invoice($billomat_id, $complete_data, array("Order ID" => $order_id));
      $complete_success = $request_status == 200;
    } else {
      $complete_success = true;
    }

    if($complete_success) {
      delete_post_meta($order_id, 'billomat_draft');
    }

    header("Location: ".$_SERVER["HTTP_REFERER"]."&message=1");
    exit;
  }

  public static function download_invoice() {
    $order_id = $_GET['order_id'];
    $order = new WC_Order($order_id);

    // Check if user is authorized to download invoice
    $user_invoice_authorized = false;
    if(current_user_can('manage_woocommerce_orders') || current_user_can('edit_shop_orders')) {
      // User is admin - grant permission
      $user_invoice_authorized = true;
    } else {
      // Check non-admin user permission
      if($order->customer_id === get_current_user_id()) {
        $user_invoice_authorized = true;
      }
    }

    if(empty($_GET['action']) || !is_user_logged_in() || !check_admin_referer($_GET['action']) || empty($_GET['order_id']) || !$user_invoice_authorized) {
      wp_die(__('You are not authorized to perform this action.', 'woocommerce-billomat'));
    }

    $billomat_id = get_post_meta($order->get_id(), 'billomat_id', true);
    $billomat_invoice = WCB()->client->get_invoice($billomat_id, true, array("Order ID" => $order_id));
    $data = base64_decode($billomat_invoice['base64file']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $billomat_invoice['filename'] . '"');
    echo $data;
  }

  public static function send_invoice() {
    $order_id = $_GET['order_id'];
    $order = new WC_Order($order_id);
    $order_data = $order->get_data();
    $billomat_id = get_post_meta($order_id, 'billomat_id', true);
    if($order_data['billing']['email']) {
      $email_data = array('recipients' => array('to' => $order_data['billing']['email']));
      WCB()->client->mail_invoice($billomat_id, $email_data, array("Order ID" => $order_id));
    }
    header("Location: ".$_SERVER["HTTP_REFERER"]."&message=1");
    exit;
  }

  public static function send_delivery_note() {
    $order_id = $_GET['order_id'];
    $order = new WC_Order($order_id);
    $order_data = $order->get_data();
    $billomat_delivery_note_id = get_post_meta($order_id, 'billomat_delivery_note_id', true);
    if($order_data['billing']['email']) {
      $email_data = array('recipients' => array('to' => $order_data['billing']['email']));
      WCB()->client->mail_delivery_note($billomat_delivery_note_id, $email_data);
    }
    header("Location: ".$_SERVER["HTTP_REFERER"]."&message=1");
    exit;
  }

  public static function download_delivery_note() {
    if(empty($_GET['action']) || !is_user_logged_in() || !check_admin_referer($_GET['action']) || empty($_GET['order_id']) || !current_user_can('manage_woocommerce_orders') && !current_user_can('edit_shop_orders')) {
      wp_die(__('You are not authorized to perform this action.', 'woocommerce-billomat'));
    }

    $order_id = $_GET['order_id'];
    $order = new WC_Order($order_id);
    $billomat_id = get_post_meta($order->get_id(), 'billomat_delivery_note_id', true);
    $billomat_delivery_note = WCB()->client->get_delivery_note($billomat_id);
    self::delivery_note_pdf($billomat_delivery_note);
  }

  public static function create_delivery_note() {
    if(empty($_GET['action']) || !is_user_logged_in() || !check_admin_referer($_GET['action']) || empty($_GET['order_id']) || !current_user_can('manage_woocommerce_orders') && !current_user_can('edit_shop_orders')) {
      wp_die(__('You are not authorized to perform this action.', 'woocommerce-billomat'));
    }

    $order_id = $_GET['order_id'];
    $order = new WC_Order($order_id);

    // First check if there is already a delivery note present
    $billomat_delivery_note_id = get_post_meta($order->get_id(), 'billomat_delivery_note_id', true);

    // If that is the case, send it and return
    if($billomat_delivery_note_id) {
      $billomat_delivery_note = WCB()->client->get_delivery_note($billomat_delivery_note_id);
      self::delivery_note_pdf($billomat_delivery_note);
      return;
    }

    $billomat_id = get_post_meta($order->get_id(), 'billomat_id', true);
    $order_items = $order->get_items();

    $delivery_note_items = array();
    foreach($order_items as $order_item) {
      $delivery_note_items[] = WCB_Order_Updater::build_delivery_note_item($order_item);
    }

    if($billomat_delivery_note_template_id = get_option('wcb_default_delivery_note_template')) {
      $complete_data = array('template_id' => $billomat_delivery_note_template_id);
    } else {
      $complete_data = array();
    }

    $billomat_delivery_note_data = WCB()->client->create_delivery_note($billomat_id, $delivery_note_items, array("Order ID" => $order->ID));
    WCB()->client->complete_delivery_note($billomat_delivery_note_data['id'], $complete_data, array("Order ID" => $order->ID));
    $billomat_delivery_note = WCB()->client->get_delivery_note($billomat_delivery_note_data['id'], array("Order ID" => $order->ID));
    update_post_meta($order_id, 'billomat_delivery_note_id', $billomat_delivery_note_data['id']);
    self::delivery_note_pdf($billomat_delivery_note);
  }

  private static function delivery_note_pdf($billomat_delivery_note) {
    $data = base64_decode($billomat_delivery_note['base64file']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $billomat_delivery_note['filename'] . '"');
    echo $data;
  }
}

WCB_Admin_Order_Actions::init();