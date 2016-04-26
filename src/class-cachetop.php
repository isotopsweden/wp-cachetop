<?php

namespace Cachetop;

use Cachetop\Stores\Filesystem;
use Cachetop\Stores\Redis;

final class Cachetop {

	/**
	 * Default options.
	 *
	 * @var array
	 */
	private $default_options = [
		'expires' => 3600,   // seconds
		'store'   => 'redis' // 'filesystem' or 'redis'
	];

	/**
	 * The store instance.
	 *
	 * @var Store
	 */
	private static $instance;

	/**
	 * Cachetop options.
	 *
	 * @var object
	 */
	private $options;

	/**
	 * The cache store.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->load_textdomain();
		$this->setup_actions();
		$this->setup_options();
		$this->setup_store();
	}

	/**
	 * Add dashboard count with pages cached.
	 *
	 * @param  array $items
	 *
	 * @return array
	 */
	public function add_dashboard_count( array $items = [] ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count   = $this->store->count();
		$items[] = sprintf(
			'<a href="%s" title="%s">%s %s</a>',
			add_query_arg( ['page' => 'cachetop'], admin_url( 'options-general.php' ) ),
			__( 'Pages cached', 'cachetop' ),
			$count,
			__( 'Pages cached', 'cachetop' )
		);

		return $items;
	}

	/**
	 * Add clear cache button to admin bar menu.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ) {
		$url   = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$url   = parse_url( $url, PHP_URL_HOST ) . parse_url( $url, PHP_URL_PATH );
		$color = $this->store->exists( $this->generate_hash( $url ) ) ? 'green' : 'red';
		$count = $this->store->count();
		$url   = $url . '?';

		// Generate main title html.
		$title = sprintf(
			'<span style="%s%s">%s</span> %s', 'height: 18px;width: 18px;border-radius: 50%;background: #ccc;display: inline-block;vertical-align: middle;margin-right: 7px;position: relative;bottom: 2px;color: #fff;text-align: center;vertical-align: middle;line-height: 19px;',
			'background: ' . $color . ';',
			$count,
			__( 'Site cache', 'cachetop' )
		);

		// Add site cache menu.
		$wp_admin_bar->add_menu( [
			'id'    => 'cachetop',
			'title' => $title,
			'href'  => $url . 'cachetop=flush'
		] );

		// Add flush cache menu.
		$wp_admin_bar->add_menu( [
			'id'     => 'cachetop-flush',
			'parent' => 'cachetop',
			'title'  => __( 'Flush cache', 'cachetop' ),
			'href'   => $url . 'cachetop=flush'
		] );

		// Add flush all caches menu.
		$wp_admin_bar->add_menu( [
			'id'     => 'cachetop-flush-all',
			'parent' => 'cachetop',
			'title'  => __( 'Flush all caches', 'cachetop' ),
			'href'   => $url . 'cachetop=flush-all'
		] );
	}

	/**
	 * Flush all posts and delete all Cachetop keys.
	 *
	 * @return bool
	 */
	public function flush_all_posts() {
		global $wpdb;

		// Flush the store.
		$this->store->flush();

		// Delete all Cachetop keys from post meta.
		$sql = "DELETE FROM $wpdb->postmeta WHERE `meta_key` LIKE '%s';";
		$sql = $wpdb->prepare( $sql, '%_cachetop_%' );

		return $wpdb->query( $sql ) > 0;
	}

	/**
	 * Clean post meta fields.
	 *
	 * @param int $post_id
	 */
	private function clean_post( $post_id = 0 ) {
		if ( empty( $post_id ) && get_the_ID() !== 0 ) {
			$post_id = get_the_ID();
		}

		// Delete related Cachetop keys from the post.
		delete_post_meta( $post_id, '_cachetop_hash' );
		delete_post_meta( $post_id, '_cachetop_time' );
	}

	/**
	 * Flush cached html by hash.
	 *
	 * @param  string $hash
	 *
	 * @return bool
	 */
	public function flush_hash( $hash ) {
		$this->clean_post();

		return $this->store->delete( $hash );
	}

	/**
	 * Flush post cache.
	 *
	 * @param  int $post_id
	 *
	 * @return bool
	 */
	public function flush_post( $post_id = 0 ) {
		if ( empty( $post_id ) && get_the_ID() !== 0 ) {
			$post_id = get_the_ID();
		}

		if ( $hash = get_post_meta( $post_id, '_cachetop_hash', true ) ) {
			// Clean post fields.
			$this->clean_post( $post_id );

			// Delete the hash from the store.
			return $this->store->delete( $hash );
		}

		return false;
	}

	/**
	 * Flush the current url or the given url if it exists.
	 *
	 * @param  string $url
	 *
	 * @return bool
	 */
	public function flush_url( $url = '' ) {
		$hash = $this->generate_hash( $url );

		return $this->flush_hash( $hash );
	}

	/**
	 * Handle cached or uncached pages.
	 *
	 * @param bool $exit
	 */
	public function handle_cache( $exit = true ) {
		// Handle cache action.
		if ( $this->handle_cache_action() ) {
			return;
		}

		// Check if the given url should be bypassed or not.
		if ( $this->should_bypass() ) {
			if ( ! headers_sent() ) {
				header( 'Cache-Control: no-cache' );
			}

			return;
		}

		// Generate a hash based on the url.
		$hash = $this->generate_hash();

		// Try to find the cached html for the hash.
		$cache = $this->store->get( $hash );

		// If the cache is empty, it should create a new.
		if ( empty( $cache ) ) {
			ob_start( [$this, 'set_cache'] );
			return;
		}

		// Add a constant that can be used to know if the function
		// is called from the cachetop plugin or not.
		if ( ! defined( 'DOING_CACHETOP' ) ) {
			define( 'DOING_CACHETOP', true );
		}

		// Header timestamp.
		// Should be false if not modified otherwise null.
		$timestamp = null;

		// 1. Action
		// 2. Data (action(:data))
		// 3. Content
		$reg = '/<\!\-\-\s*cachetop\:\s*(\w+(?:(?:\:)([\s\S]*?)|))\s*\-\-\>([\s\S]*?)\<\!\-\-\s*cachetop\:\s*end\s*\-\-\>/';
		preg_match_all( $reg, $cache, $matches );

		// Modify the cached content.
		if ( ! empty( $matches ) ) {
			$len = count( $matches[0] );

			for ( $i = 0, $l = count( $matches[0] ); $i < $l; $i++ ) {
				$parts  = explode( ':', $matches[1][$i] );
				$action = strtolower( isset( $parts[0] ) ? $parts[0] : '' );
				$data   = trim( isset( $parts[1] ) ? $parts[1] : '' );

				// Replace html that should be unfragment with new data.
				if ( $action === 'unfragment' && ! empty( $data ) ) {
					$data = base64_decode( $data );
					$data = json_decode( $data );
					$data = (array) $data;

					// A function to call is required.
					if ( empty( $data['fn'] ) ) {
						continue;
					}

					// Check so the function exists before
					// using it.
					if ( ! function_exists( $data['fn'] ) ) {
						continue;
					}

					// Should it be passed as a array or not?
					// Default is true.
					$args = (array) $data['args'];
					$args = $data['arr_arg'] ? [$args] : $args;

					// Fetch unfragment value from function.
					ob_start();
					echo call_user_func_array( $data['fn'], $args );
					$unfragment = ob_get_clean();

					// If the unfragment value don't match the data we can replace it.
					if ( $unfragment !== $data ) {
						$cache     = str_replace( $matches[0][$i], $unfragment, $cache );
						$timestamp = false;
						continue;
					}
				}
			}
		}

		// Set cache headers.
		$this->set_headers( $hash, $timestamp );

		// Render cached html.
		echo sprintf(
			'%s %s',
			$cache,
			sprintf( '<!-- cached by cachetop - %s - hash: %s -->', date_i18n( 'd.m.Y H:i:s', current_time( 'timestamp' ) ), $hash )
		);

		// Since exit breaks unit tests we need to check
		// if we should exit or not.
		if ( $exit !== false ) {
			exit;
		}
	}

	/**
	 * Handle cache action, both on the frontend and WordPress admin.
	 *
	 * @return bool
	 */
	public function handle_cache_action() {
		$action = isset( $_GET['cachetop'] ) ? $_GET['cachetop'] : '';

		// If a query string action exists
		// it should be handle.
		switch ( $action ) {
			case 'flush':
				$this->flush_post();

				return true;
			case 'flush-all':
				$this->flush_all_posts();

				return true;
			default:
				break;
		}

		return false;
	}

	/**
	 * Get the store instance.
	 *
	 * @param  array $args
	 *
	 * @return Store
	 */
	public static function instance( array $args = [] ) {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static( $args );
		}

		return self::$instance;
	}

	/**
	 * Check if user is logged in or not.
	 *
	 * @return bool
	 */
	private function is_user_logged_in() {
		// Try to check if user is logged in with
		// build in function.
		if ( is_user_logged_in() ) {
			return true;
		}

		// No cookies means not logged in.
		if ( empty( $_COOKIE ) ) {
			return false;
		}

		// Check for WordPress cookies for logged in users.
		foreach ( array_keys( $_COOKIE ) as $key ) {
			if ( preg_match( '/^(wp-postpass|wordpress_logged_in|comment_author)_/', $key ) ) {
				return true;
			}
		}
	}

	/**
	 * Load Localisation files.
	 *
	 * Locales found in:
	 * - WP_LANG_DIR/cachetop/cachetop-LOCALE.mo
	 * - WP_CONTENT_DIR/[mu-]plugins/cachetop/languages/cachetop-LOCALE.mo
	 */
	private function load_textdomain() {
		// Find plugins path.
		$dir    = plugin_dir_path( __FILE__ );
		$locale = apply_filters( 'plugin_locale', get_locale(), 'cachetop' );

		load_textdomain( 'cachetop', WP_LANG_DIR . '/cachetop/cachetop-' . $locale . '.mo' );
		load_textdomain( 'cachetop', $dir . '../languages/cachetop-' . $locale . '.mo' );
	}

	/**
	 * Genrate hash based on the current or given url.
	 *
	 * @param  string $url
	 * @param  bool   $qs
	 * @param  string $algo
	 *
	 * @return string
	 */
	private function generate_hash( $url = '', $qs = false, $algo = 'sha256' ) {
		$url = $this->get_url( $url, $qs );

		return hash( $algo, $url );
	}

	/**
	 * Get url with or without query strings.
	 *
	 * @param  string $url
	 * @param  bool   $qs
	 *
	 * @return string
	 */
	private function get_url( $url = '', $qs = false ) {
		if ( empty( $url ) ) {
			$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		if ( ! $qs ) {
			$url = parse_url( $url, PHP_URL_HOST ) . parse_url( $url, PHP_URL_PATH );
		}

		return $url;
	}

	/**
	 * Set cache data.
	 *
	 * @param string $data
	 */
	public function set_cache( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$hash = $this->generate_hash();

		try {
			$this->store->set( $hash, $data );
		} catch ( Exception $e ) {

		}

		// Save hash on the post for later use,
		// e.g deleting cached html from the store.
		if ( $id = get_the_ID() ) {
			update_post_meta( $id, '_cachetop_hash', $hash );
			update_post_meta( $id, '_cachetop_time', time() );
		}

		return $data;
	}

	/**
	 * Set http headers.
	 *
	 * @param string $hash
	 * @param mixed  $timestamp
	 */
	private function set_headers( $hash, $timestamp = null ) {
		if ( headers_sent() ) {
			return;
		}

		// Sen cache control headers with public and max age values.
		header( 'Cache-Control: public, max-age=' . HOUR_IN_SECONDS * 1 );

		// If no timestamp, fetch it from the post.
		if ( empty( $timestamp ) && $timestamp !== false ) {
			$timestamp = get_post_meta( get_the_ID(), '_cachetop_time', true );
		}

		// Send last modified and etag headers if we have a timestamp.
		if ( $timestamp ) {
			$time = gmdate( 'D, d M Y H:i:s ', $timestamp ) . 'GMT';

			header( 'Last-Modified: ' . $time );
			header( 'ETag: ' . md5( $timestamp . $hash ) );

			$time_check = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] === $time;
			$etag_check = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $_SERVER['HTTP_IF_NONE_MATCH'] === md5( $timestamp . $hash );

			if ( $etag_check || $time_check ) {
				header( 'HTTP/1.1 304 Not Modified' );
			}
		}
	}

	/**
	 * Setup actions.
	 */
	private function setup_actions() {
		if ( is_admin() ) {
			add_action( 'admin_init', [$this, 'handle_cache_action'], 0 );
			add_action( 'dashboard_glance_items', [$this, 'add_dashboard_count'] );
			add_action( 'save_post', [$this, 'clear_post_cache'] );
			add_action( 'wp_trash_post', [$this, 'clear_post_cache'] );
		} else {
			add_action( 'template_redirect', [$this, 'handle_cache'], 0 );
		}

		add_action( 'admin_bar_menu', [$this, 'admin_bar_menu'], 999 );
		add_action( 'switch_theme', [$this, 'flush_cache'] );
	}

	/**
	 * Setup options.
	 */
	private function setup_options() {
		// Modify options with a filter.
		$options = apply_filters( 'cachetop/options', $this->default_options );
		$options = is_array( $options ) ? $options : $this->default_options;

		// Use a object instead of a array for options internally.
		$this->options = (object) $options;

		// Make store option lowercase.
		$this->options->store = strtolower( $this->options->store );
	}

	/**
	 * Setup store.
	 */
	private function setup_store() {
		$options = [
			'expires' => $this->options->expires
		];

		$this->store = $this->options->store === 'redis' ?
			Redis::instance( $options ) :
			Filesystem::instance( $options );
	}

	/**
	 * Check if cache should be bypass or not.
	 *
	 * @return bool
	 */
	private function should_bypass() {
		// Bypass cache easy with a filter.
		if ( apply_filters( 'cachetop/bypass', false ) ) {
			return true;
		}

		// Bypass by exclude a url.
		if ( apply_filters( 'cachetop/exclude_url', $this->get_url() ) === true ) {
			return true;
		}

		// Don't cache:
		// - Search page.
		// - 404 page.
		// - Feed page.
		// - Trackback page.
		// - Robots file.
		// - If preview page.
		// - If post requires password.
		if ( is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() ) {
			return true;
		}

		// Check if user is logged in or not, ca be bypassed with a filter.
		if ( apply_filters( 'cachetop/bypass_logged_in', $this->is_user_logged_in() ) ) {
			return true;
		}

		// Plugins like WooCommerce can have this constant.
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return true;
		}

		// Don't cache WooCommerce pages:
		// - Cart page.
		// - Checkout page.
		// - Account page.
		if ( function_exists( 'is_woocommerce' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}

		// Only cache get requests.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || strtolower( $_SERVER['REQUEST_METHOD'] ) !== 'get' ) {
			return true;
		}

		// Don't cache query string urls.
		if ( ! empty( $_GET ) && ! isset( $_GET['utm_source'], $_GET['utm_medium'], $_GET['utm_campaign'] ) && get_option( 'permalink_structure' ) ) {
			return true;
		}

		return false;
	}
}
