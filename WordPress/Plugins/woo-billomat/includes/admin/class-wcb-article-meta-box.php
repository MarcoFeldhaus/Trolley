<?php
class WCB_Article_Meta_Box {
  public function __construct() {
		$this->init_hooks();
  }

  public function init_hooks() {
    add_action('add_meta_boxes', array(&$this, 'add'));
    add_action('save_post', array(&$this, 'save' ), 10, 1);
  }

  public function add() {
    add_meta_box(
      'wcb_article_meta_box',
      'Billomat',
      array(&$this, 'html'),
      'product',
      'side'
    );
  }

  public function save($post_id) {
    if(array_key_exists('billomat_id', $_POST) && get_post_type($post_id) == 'product') {
      if(!$this->product_supported($post_id)) {
        return;
      }

      WCB()->notices_controller->remove_admin_notice("product_{$post_id}_article_404");
      update_post_meta($post_id, 'billomat_id', $_POST['billomat_id']);
    }
  }

  public function html($post) {
    $error = false;
    $disabled = false;
    $billomat_id = get_post_meta($post->ID, 'billomat_id', true);

    if($this->product_supported($post->ID)) {
      if($billomat_id) {
        $existing_article = WCB()->client->get_article($billomat_id, false);
        if(!$existing_article) {
          $error = true;
        }
      }
    } else {
      $disabled = true;
    }
    ?>
    <?php if($disabled): ?>
      <div style="opacity:0.8;">
    <?php endif; ?>
    <label for="billomat_id" style="vertical-align:inherit;">Billomat ID</label>
    <input type="text" name="billomat_id" id="billomat_id" value="<?php echo $billomat_id ?>"<?php echo $disabled ? ' disabled' : ''; ?>>
    <?php if($error): ?>
      <p class="wcb-meta-error"><?php _e("<strong>Error:</strong> The referenced article doesn´t exist in Billomat. There are two options to fix this:<br> 1. Enter a Billomat ID of an existing article<br> 2. Remove the Billomat ID above to create a new article in Billomat", 'woocommerce-billomat'); ?></p>
    <?php endif; ?>
    <?php if($disabled): ?>
      </div>
    <?php endif; ?>
    <?php
  }

  private function product_supported($post_id) {
    $product = wc_get_product($post_id);
    if(get_class($product) == 'WC_Product_Variable'
      || get_class($product) == 'WC_Product_Variation'
      || (!$product->is_purchasable() && get_class($product) != 'WC_Product_External')
    ) {
      return false;
    }

    return true;
  }
}