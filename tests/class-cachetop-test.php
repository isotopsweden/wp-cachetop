<?php

namespace Cachetop\Tests;

use Cachetop\Cachetop;

class Cachetop_Test extends \WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->cachetop = new Cachetop;		
	}

	public function tearDown() {
		parent::tearDown();
		unset( $this->cacehtop );
	}

	public function test_admin_bar_menu() {
		if ( ! class_exists( '\WP_Admin_Bar' ) ) {
			require_once ABSPATH . '/wp-includes/class-wp-admin-bar.php';
		}

		$admin_bar = new \WP_Admin_Bar;

		$cachetop = new Cachetop;
		$cachetop->admin_bar_menu( $admin_bar );

		$nodes = $admin_bar->get_nodes();

		$this->assertArrayHasKey( 'cachetop', $nodes );
		$this->assertArrayHasKey( 'cachetop-flush', $nodes );
		$this->assertArrayHasKey( 'cachetop-flush-all', $nodes );
	}

	public function test_clear_post_cache_without_store() {
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, '_cachetop_hash', uniqid() );
		$this->cachetop->clear_post_cache( $post_id );
		$this->assertEmpty( get_post_meta( $post_id, '_cachetop_hash', true ) );
	}

	public function test_clear_post_cache_with_store() {
		$post_id = $this->factory->post->create();

		global $post;
		$post = get_post( $post_id );

		$this->cachetop->set_cache( 'Hello' );

		$this->cachetop->handle_cache( false );
		$this->expectOutputRegex( '/Hello/' );
		$this->expectOutputRegex( '/cachetop/' );

		$this->cachetop->clear_post_cache( $post_id );
		$this->assertEmpty( get_post_meta( $post_id, '_cachetop_hash', true ) );
	}

	public function test_handle_cache_action_flush() {
		$post_id = $this->factory->post->create();

		global $post;
		$post = get_post( $post_id );

		$this->cachetop->set_cache( 'Hello' );

		$_GET['cachetop'] = 'flush';
		$this->cachetop->handle_cache_action();
		$this->assertEmpty( get_post_meta( $post_id, '_cachetop_hash', true ) );
	}

	public function test_handle_cache_action_flush_all() {
		$post_id = $this->factory->post->create();

		global $post;
		$post = get_post( $post_id );

		$this->cachetop->set_cache( 'Hello' );

		$_GET['cachetop'] = 'flush-all';
		$this->cachetop->handle_cache_action();
		$this->cachetop->handle_cache( false );
		$this->expectOutputRegex( '//' );
	}
}
