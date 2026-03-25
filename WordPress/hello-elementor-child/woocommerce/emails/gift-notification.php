<?php
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Gift Notification
 * 
 * Type : HTML
 * 
 * $first_name			: displays the first name of customer
 * $last_name			: displays the last name of customer
 * $recipient_name		: displays the recipient name
 * $product_price	    : displays the product price
 * $voucher_link		: displays the voucher download link
 * $recipient_message	: displays the recipient message
 * 
 * @package WooCommerce - PDF Vouchers
 * @since 2.3.4
 */
?>

<?php do_action('woocommerce_email_header', $email_heading); ?>

<?php 
$card_name = do_shortcode('[card_name]');
?>

<p>Herzlichen Glückwunsch,<br />
<br />
jemand hat an Sie gedacht und Ihnen einen Gutschein der <?php echo $card_name; ?> geschenkt.</p>

<p><?php echo sprintf('%s', $recipient_message); ?></p>

<p>
Sie können Ihren Gutschein hier herunterladen: <?php echo sprintf(esc_html__('%s', 'woovoucher'), $voucher_link); ?>
</p>

<p>Herzliche Grüße von der<br /><?php echo $card_name; ?></p>

<?php do_action('woocommerce_email_footer'); ?>