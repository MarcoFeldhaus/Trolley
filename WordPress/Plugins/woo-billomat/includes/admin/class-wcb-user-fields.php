<?php
class WCB_User_Fields {
  public function __construct() {
		$this->init_hooks();
  }

  public function init_hooks() {
    add_action('edit_user_profile', array(&$this, 'add'));
    add_action('edit_user_profile_update', array(&$this, 'save'), 10);
  }

  public function add($profileuser) {
    $error = false;
    $billomat_id = get_the_author_meta('billomat_id', $profileuser->ID);
    if($billomat_id) {
      $existing_client = WCB()->client->get_client($billomat_id, false);
      if(!$existing_client) {
        $error = true;
      }
    }
    ?>
  	<table class="form-table">
  		<tr>
        <th><label for="billomat_id">Billomat ID</label></th>
  			<td>
  				<input type="text" name="billomat_id" id="billomat_id" value="<?php echo esc_attr( $billomat_id ); ?>" class="regular-text">
          <?php if($error): ?>
            <br><span class="description wcb-meta-error">
              <?php _e("<strong>Error:</strong> The referenced client doesn´t exist in Billomat. There are two options to fix this:<br> 1. Enter a Billomat ID of an existing client<br> 2. Remove the Billomat ID above to create a new client in Billomat", 'woocommerce-billomat'); ?>
            </span>
          <?php endif; ?>
  			</td>
  		</tr>
  	</table>
    <?php
  }

  public function save($user_id) {
    if(!current_user_can( 'edit_user', $user_id)) {
      return false;
    }

    if(isset($_POST['billomat_id'])) {
      update_user_meta($user_id, 'billomat_id', esc_attr($_POST['billomat_id']));
    }
  }
}