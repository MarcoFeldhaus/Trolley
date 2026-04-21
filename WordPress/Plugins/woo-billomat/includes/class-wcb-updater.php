<?php
/**
 * Update / database migration functions.
 *
 * @author   Billomat
 * @category Admin
 * @package  WooCommerceBillomat/Classes
 * @version  2.2.0
 */

if(!defined('ABSPATH')) {
	exit;
}

/**
 * WCB_Updater Class.
 */
class WCB_Updater {
	/**
	 * Database versions.
	 *
	 * @var array
	 */
	private static $db_versions = array(
		'1.0.0',
	);

	/**
	 * Trigger updates.
	 *
	 * @static
	 */
  public static function check_update() {
		$target_version = end(self::$db_versions);
		$install_version = get_site_option('wcb_db_version');

		// Compare installation's current version with target version
    if($install_version < $target_version) {
			foreach(self::$db_versions as $db_version) {
				// Run update functions for each version
				// as long as current version is lower than target version
				if($install_version < $db_version) {
					switch($db_version) {
		        case '1.0.0':
		          $updated = self::update_1_0_0();
		          break;
		      }

					if($updated) {
		        update_option("wcb_db_version", $db_version);
		      } else {
						// Something went wrong
						break;
					}
				}
			}
    }
  }

	/**
	 * Update function for DB version 1.0.0
	 *
	 * Create option `wcb_invoices_created` as counter for Billomat invoices created by the plugin.
	 * Use the total of shop orders that have a `billomat_id` as initial value.
	 *
	 * @static
	 * @return bool to indicate whether the update was successful or not.
	 */
  private static function update_1_0_0() {
    global $wpdb;

    $count = (int) $wpdb->get_var("SELECT count(meta_id) FROM $wpdb->postmeta as meta
                                   LEFT JOIN $wpdb->posts as posts ON posts.ID = meta.post_id
                                   WHERE posts.post_type = 'shop_order'
                                   AND meta.meta_key = 'billomat_id'");

    update_option('wcb_invoices_created', $count);

    return true;
  }
}