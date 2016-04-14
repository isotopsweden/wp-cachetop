<?php

/**
 * Plugin name: Cachetop
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/class-minify.php';
require_once __DIR__ . '/src/stores/class-store.php';
require_once __DIR__ . '/src/stores/class-filesystem.php';
require_once __DIR__ . '/src/stores/class-redis.php';
require_once __DIR__ . '/src/class-cachetop.php';

add_action( 'plugins_loaded', function () {
	new Cachetop();
} );
