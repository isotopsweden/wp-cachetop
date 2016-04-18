<?php

namespace Cachetop;

use Cachetop\Stores\Filesystem;
use Cachetop\Stores\Redis;

final class Cachetop {

	/**
	 * Query string action.
	 *
	 * @var null|string
	 */
	private $action;

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
		$this->action = isset( $_GET['cachetop'] ) ? $_GET['cachetop'] : null;
		$this->store  = Redis::instance();
		$this->setup_actions();
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

		$count = $this->store->count();

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
	 * @param object $wp_admin_bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
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
			'href'  => $url . 'cachetop=clear'
		] );

		// Add clear cache menu.
		$wp_admin_bar->add_menu( [
			'id'     => 'cachetop-clear',
			'parent' => 'cachetop',
			'title'  => __( 'Clear cache', 'cachetop' ),
			'href'   => $url . 'cachetop=clear'
		] );

		// Add flush all caches menu.
		$wp_admin_bar->add_menu( [
			'id'     => 'cachetop-flush',
			'parent' => 'cachetop',
			'title'  => __( 'Flush all caches', 'cachetop' ),
			'href'   => $url . 'cachetop=flush'
		] );
	}

	/**
	 * Clear post cache.
	 *
	 * @param int $post_id
	 */
	public function clear_post_cache( $post_id = 0 ) {
		if ( empty( $post_id ) && get_the_ID() !== 0 ) {
			$post_id = get_the_ID();
		}

		if ( $hash = get_post_meta( $post_id, '_cachetop_hash', true ) ) {
			$this->store->delete( $hash );
		}
	}

	/**
	 * Handle cache action, both on the frontend and WordPress admin.
	 *
	 * @return bool
	 */
	public function handle_cache_action() {
		// If a query string action exists
		// it should be handle.
		switch ( $this->action ) {
			case 'clear':
				$this->store->delete( $this->generate_hash() );
				$this->clear_post_cache();
				return true;
			case 'flush':
				$this->store->flush();
				return true;
			default:
				break;
		}

		return false;
	}

	/**
	 * Handle cached or uncached pages.
	 */
	public function handle_cache() {
		// Handle cache action.
		if ( $this->handle_cache_action() ) {
			return;
		}

		// Check if the given url should be bypassed or not.
		if ( $this->should_bypass() ) {
			header( 'Cache-Control: no-cache' );
			return;
		}

		// Generate a hash based on the url.
		$hash = $this->generate_hash();

		// Try to find the cached html for the hash.
		$cache = $this->store->read( $hash );

		// If the cache is empty, it should create a new.
		if ( empty( $cache ) ) {
			ob_start( [$this, 'set_cache'] );
			return;
		}

		// Send cache headers.
		$this->set_headers( $hash );

		// Render cached html.
		echo sprintf(
			'%s %s',
			$cache,
			sprintf( '<!-- cached by cachetop - %s - hash: %s -->', date_i18n( 'd.m.Y H:i:s', current_time( 'timestamp' ) ), $hash )
		);

		exit;
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
		if ( empty( $url ) ) {
			$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		if ( ! $qs ) {
			$url = parse_url( $url, PHP_URL_HOST ) . parse_url( $url, PHP_URL_PATH );
		}

		return hash( $algo, $url );
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

		// Save hash on the post for later use, e.g deleting cache file.
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
	 * @param int    $timestamp
	 */
	private function set_headers( $hash, $timestamp = null ) {
		// Sen cache control headers with public and max age values.
		header( 'Cache-Control: public, max-age=' . HOUR_IN_SECONDS * 1 );

		// If no timestamp, fetch it from the post.
		if ( empty( $timestamp ) ) {
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
	 * Should bypass cache?
	 *
	 * @return bool
	 */
	protected function should_bypass() {
		if ( apply_filters( 'cachetop/bypass', false ) ) {
			return true;
		}

		// Don't cache:
		// - Search page.
		// - 404 page.
		// - Feed page.
		// - Trackback page.
		// - Robots file.
		// - If preview.
		// - If post requires password.
		// - If user is logged in.
		if ( is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() || is_user_logged_in() ) {
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
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
			return true;
		}

		// Don't cache query string urls.
		if ( ! empty( $_GET ) && ! isset( $_GET['utm_source'], $_GET['utm_medium'], $_GET['utm_campaign'] ) && get_option( 'permalink_structure' ) ) {
			return true;
		}

		return false;
	}
}
