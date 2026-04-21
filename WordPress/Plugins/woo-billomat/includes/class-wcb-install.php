<?php
/**
 * Installation related functions and actions.
 *
 * @author   Billomat
 * @category Admin
 * @package  WooCommerceBillomat/Classes
 * @version  1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCB_Install Class.
 */
class WCB_Install {
  /**
	 * Install WCB.
	 */
	public static function install() {
		if ( ! is_blog_installed() ) {
			return;
		}

		if ( ! defined( 'WCB_INSTALLING' ) ) {
			define( 'WCB_INSTALLING', true );
		}

    self::create_files();
  }

	/**
	 * Uninstall WCB.
	 */
	public static function uninstall() {
		if ( ! defined( 'WCB_UNINSTALLING' ) ) {
			define( 'WCB_UNINSTALLING', true );
		}

    self::delete_files();
  }

  /**
	 * Create files/directories.
	 */
	private static function create_files() {
    $upload_dir = wp_upload_dir();

    $files = array(
      array(
				'base'    => $upload_dir['basedir'] . '/woocommerce_billomat_uploads',
				'file'    => 'index.html',
				'content' => '',
			),
    );

    $files[] = array(
			'base'     => $upload_dir['basedir'] . '/woocommerce_billomat_uploads',
			'file'     => '.htaccess',
			'content'  => 'deny from all',
		);

    foreach($files as $file) {
			if(wp_mkdir_p($file['base']) && !file_exists(trailingslashit($file['base']) . $file['file'])) {
				if($file_handle = @fopen(trailingslashit($file['base']) . $file['file'], 'w')) {
					fwrite($file_handle, $file['content']);
					fclose($file_handle);
				}
			}
		}
  }
}