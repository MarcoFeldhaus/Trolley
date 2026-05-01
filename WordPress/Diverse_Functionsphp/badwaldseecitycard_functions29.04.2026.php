<?php
/**
 * CORRECTED: Add PayPal fee if customer is company
 * FIX: Gutschein-Shop User sollte KEINE Gebühr bekommen - auch nicht auf PRO!
 * 
 * Diese Funktion ersetzt die alte woo_add_paypal_fee() in functions.php
 */

function woo_add_paypal_fee($cart) {

     if ( is_admin() && ! defined( 'DOING_AJAX' ) )  {
          return;
     }
     
     $post_data_raw = WC()->checkout()->get_value('post_data');
     if($post_data_raw !== null && $post_data_raw !== '') {
          parse_str($post_data_raw, $post_data);
     } else {
          $post_data = array();
     }

     if(count($post_data) == 0 && count($_POST) == 0) {
          return;
     }

     // ========== NEUE ZEILE: Gutschein-Shop ZUERST prüfen! ==========
     $user = wp_get_current_user();
     $is_gutschein_shop = in_array( 'gutschein-shop', (array) $user->roles ) || in_array( 'Gutschein-Shop', (array) $user->roles );
     
     // Gutschein-Shop User bekommt KEINE Bearbeitungsgebühr!
     if ( $is_gutschein_shop ) {
          return;  // ← SOFORT raus - keine Gebühr!
     }
     // ========== END: Gutschein-Shop Check ==========

     //info: on updating cart the data is in $post_data, and on checkout button clicked the data is in $_POST

     $percent = get_white_label_paypal_fee();
     $euro_per_voucher = get_white_label_paypal_fee_per_voucher();
     $cart_total = $cart->cart_contents_total;

     $fee = ($cart_total / 100 * $percent);

     if($euro_per_voucher > 0) {
          $quantity = 0;
          foreach ( $cart->get_cart() as $cart_item ) {
               $quantity += $cart_item['quantity']; 
          }
          $fee = ($cart_total / 100 * $percent) + ($quantity * $euro_per_voucher);
     }

     if((array_key_exists('billing_options', $post_data) && !empty($post_data['billing_options']) && $post_data['billing_options'] == 'firmenkauf' ) || (array_key_exists('billing_options', $_POST) && !empty($_POST['billing_options']) && $_POST['billing_options'] == 'firmenkauf')) {
          $cart->add_fee( 'Bearbeitungsgebühr', $fee, true, 'Steuern Fee' );
     }
}
add_action( 'woocommerce_cart_calculate_fees', 'woo_add_paypal_fee' );
?>
