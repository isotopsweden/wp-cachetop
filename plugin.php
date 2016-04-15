<?php

/**
 * Plugin name: Cachetop
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Bootstrap the Cachetop plugin.
 *
 * @return \Cachetop\Cachetop
 */
add_action( 'plugins_loaded', function () {
	new \Cachetop\Cachetop();
} );
