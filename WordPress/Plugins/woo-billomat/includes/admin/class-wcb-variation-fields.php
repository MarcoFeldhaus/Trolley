<?php
class WCB_Variation_Fields {
  public function __construct() {
		$this->init_hooks();
  }

  public function init_hooks() {
    add_action('woocommerce_product_after_variable_attributes', array(&$this, 'add'), 10, 3);
    add_action('woocommerce_save_product_variation', array(&$this, 'save' ), 10, 1);
  }

  public function add($loop, $variation_data, $variation) {
    $error = false;
    $billomat_id = get_post_meta($variation->ID, 'billomat_id', true);
    if($billomat_id) {
      $existing_article = WCB()->client->get_article($billomat_id, false);
      if(!$existing_article) {
        $error = true;
      }
    }
    echo '<div class="variation-custom-fields">';
    woocommerce_wp_text_input(
      array(
        'id'          => 'billomat_id['. $loop .']',
        'label'       => 'Billomat ID',
        'wrapper_class' => 'form-row form-row-first',
        'description' => $error ? '<span class="wcb-meta-error">' . __( "<strong>Error:</strong> The referenced article doesn´t exist in Billomat. There are two options to fix this:<br> 1. Enter a Billomat ID of an existing article<br> 2. Remove the Billomat ID above to create a new article in Billomat", 'woocommerce-billomat' ) . '</span>' : '',
        'value'       => get_post_meta($variation->ID, 'billomat_id', true)
      )
    );
    echo '</div>';
  }

  public function save($post_id) {
    if(array_key_exists('billomat_id', $_POST) && get_post_type($post_id) == 'product_variation') {
      update_post_meta($post_id, 'billomat_id', array_shift($_POST['billomat_id']));
    }
  }
}