<?php

/**
 * Plugin name: Cachetop
 * Plugin URI: https://github.com/isotopsweden/wc-cachetop
 * Description: Cache pages by generating static HTML and store it in Redis or Filesystem.
 * Author: Isotop AB
 * Author URI: https://www.isotop.se
 * Version: 1.0.0
 * Textdomain: cachetop
 * Domain Path: /languages/
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Bootstrap the Cachetop plugin.
 *
 * @return \Isotop\Cachetop\Cachetop
 */
add_action( 'plugins_loaded', function () {
	return \Isotop\Cachetop\Cachetop::instance();
} );
