<?php
class WCB_Order_Meta_Box {
  public function __construct() {
		$this->init_hooks();
  }

  public function init_hooks() {
    if(get_option('wcb_connected')) {
      add_action('add_meta_boxes', array(&$this, 'add'));
      add_action('save_post', array(&$this, 'save' ), 10, 1);
    }
  }

  public function add() {
    add_meta_box(
      'wcb_order_meta_box',
      'Billomat',
      array(&$this, 'html'),
      'shop_order',
      'side'
    );
  }

  public function save($post_id) {
    if(array_key_exists('billomat_id', $_POST) && get_post_type($post_id) == 'shop_order') {
      WCB()->notices_controller->remove_admin_notice("invoice_{$post_id}_404");
      update_post_meta($post_id, 'billomat_id', $_POST['billomat_id']);
    }
    if(array_key_exists('billomat_delivery_note_id', $_POST) && get_post_type($post_id) == 'shop_order') {
      WCB()->notices_controller->remove_admin_notice("billomat_delivery_note_id_{$post_id}_404");
      update_post_meta($post_id, 'billomat_delivery_note_id', $_POST['billomat_id']);
    }
  }

  public function html($post) {
    $billomat_invoice_id = get_post_meta($post->ID, 'billomat_id', true);
    $billomat_draft = get_post_meta($post->ID, 'billomat_draft', true);
    $billomat_delivery_note_id = get_post_meta($post->ID, 'billomat_delivery_note_id', true);
    $error = false;

    // Set error if referenced Billomat invoice doesn´t exist
    if($billomat_invoice_id) {
      $existing_invoice = WCB()->client->get_invoice($billomat_invoice_id, false);
      if(!$existing_invoice) {
        $error = 'invoice';
        $error_message = __("<strong>Error:</strong> The referenced invoice doesn´t exist in Billomat. There are two options to fix this:<br> 1. Enter a Billomat ID of an existing invoice<br> 2. Remove the Billomat ID above to create a new invoice in Billomat", 'woocommerce-billomat');
      }
    }

    // Set error if referenced Billomat delivery note doesn´t exist
    if($billomat_delivery_note_id) {
      $existing_delivery_note = WCB()->client->get_delivery_note($billomat_delivery_note_id, false);
      if(!$existing_delivery_note) {
        $error = 'delivery_note';
        $error_message = __("<strong>Error:</strong> The referenced delivery note doesn´t exist in Billomat. There are two options to fix this:<br> 1. Enter a Billomat ID of an existing delivery note<br> 2. Remove the Billomat ID above and create a new delivery note", 'woocommerce-billomat');
      }
    }

    // Set invoice action links (download / complete)
    if(!$billomat_draft) {
      $invoice_text = __( "Download", 'woocommerce-billomat' );
      $invoice_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_download_invoice&order_id=' . $post->ID), 'wcb_download_invoice');
      $send_invoice_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_send_invoice&order_id=' . $post->ID), 'wcb_send_invoice');
    } else {
      $invoice_text = __( "Complete Billomat invoice", 'woocommerce-billomat' );
      $invoice_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_complete_invoice&order_id=' . $post->ID), 'wcb_complete_invoice');
    }

    // Set delivery note action links (download / create)
    if($billomat_delivery_note_id) {
      $delivery_note_text = __( "Download", 'woocommerce-billomat' );
      $delivery_note_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_create_delivery_note&order_id=' . $post->ID), 'wcb_create_delivery_note');
      $send_delivery_note_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_send_delivery_note&order_id=' . $post->ID), 'wcb_send_delivery_note');
    } else {
      $delivery_note_text = __( "Create", 'woocommerce-billomat' );
      $delivery_note_url = wp_nonce_url(admin_url('admin-ajax.php?action=wcb_create_delivery_note&order_id=' . $post->ID), 'wcb_create_delivery_note');
    }
    ?>

    <?php if( $billomat_invoice_id ): ?>
      <p>
        <span class="dashicons dashicons-media-document"></span> <strong><?php _e( "Invoice", 'woocommerce-billomat' ) ?></strong><br>
        <?php if( $error != 'invoice' ): ?>
          <?php if( $billomat_draft ): ?>
            <p><?php _e( "The Billomat invoice is currently in <em>draft</em> status. In order to create and send it as PDF file you can complete it by clicking on this button.<br>", 'woocommerce-billomat' ) ?></p>
            <p>
              <a href="<?php echo $invoice_url ?>" class="button-secondary" onclick="return confirm('<?php _e( "The Billomat invoice for this order will be completed. Continue?", 'woocommerce-billomat' ) ?>')">
                <?php echo $invoice_text ?>
              </a>
            </p>
          <?php elseif( $billomat_invoice_id ): ?>
            <a title="<?php _e( "Download Billomat invoice", 'woocommerce-billomat' ) ?>" href="<?php echo $invoice_url; ?>"><?php _e( "Download", 'woocommerce-billomat' ) ?></a>
            | <a href="<?php echo $send_invoice_url; ?>" title="<?php _e( "Send invoice via Billomat", 'woocommerce-billomat' ) ?>"><?php _e( "Send", 'woocommerce-billomat' ) ?></a>
          <?php endif; ?>
        <?php else: ?>
          <p>
            <label for="billomat_id" style="vertical-align:inherit;">Billomat ID</label>
            <input type="text" name="billomat_id" id="billomat_id" value="<?php echo $billomat_invoice_id; ?>">
          </p>
          <?php if($error): ?>
            <span class="wcb-meta-error"><?php echo $error_message; ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p><?php _e( "Complete the order to download or send invoices.", 'woocommerce-billomat' ) ?></p>
    <?php endif; ?>
    <?php if( $billomat_delivery_note_id || ( $billomat_invoice_id && $error != 'invoice' ) ): ?>
      <p>
        <span class="dashicons dashicons-media-spreadsheet"></span> <strong><?php _e( "Delivery note", 'woocommerce-billomat' ) ?></strong><br>
        <?php if( $error != 'delivery_note' ): ?>
          <a title="<?php _e( "Download Billomat delivery note", 'woocommerce-billomat' ) ?>" href="<?php echo $delivery_note_url ?>"><?php echo $delivery_note_text ?></a>
          <?php if($billomat_delivery_note_id): ?>
            | <a href="<?php echo $send_delivery_note_url; ?>" title="<?php _e( "Send delivery note via Billomat", 'woocommerce-billomat' ) ?>"><?php _e( "Send", 'woocommerce-billomat' ) ?></a>
          <?php endif; ?>
        <?php else: ?>
          <p>
            <label for="billomat_delivery_note_id" style="vertical-align:inherit;">Billomat ID</label>
            <input type="text" name="billomat_delivery_note_id" id="billomat_delivery_note_id" value="<?php echo $billomat_delivery_note_id; ?>">
          </p>
          <?php if($error): ?>
            <span class="wcb-meta-error"><?php echo $error_message; ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <?php
  }
}