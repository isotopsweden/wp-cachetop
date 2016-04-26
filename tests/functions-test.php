<?php

namespace Cachetop\Tests;

class Functions_Test extends \WP_UnitTestCase {

	public function test_cachetop() {
		$this->assertTrue( cachetop() instanceof \Cachetop\Cachetop );
	}

	public function test_cachetop_flush_hash_without_store() {
		$this->assertFalse( cachetop_flush_hash( '' ) );
	}

	public function test_cachetop_flush_hash_with_store() {
		$post_id = $this->factory->post->create();

		global $post;
		$post = get_post( $post_id );

		cachetop()->set_cache( 'Hello' );
		$this->assertNotEmpty( get_post_meta( $post_id, '_cachetop_hash', true ) );

		$hash = get_post_meta( $post_id, '_cachetop_hash', true );
		$this->assertTrue( cachetop_flush_hash( $hash ) );
	}

	public function test_cachetop_flush_url() {
		$this->set_permalink_structure( '%postname%' );

		$post_id = $this->factory->post->create();

		global $post;
		$post = get_post( $post_id );
		$url = get_permalink( $post );

		cachetop()->set_cache( 'Hello' );
		$this->assertNotEmpty( get_post_meta( $post_id, '_cachetop_hash', true ) );

		cachetop_flush_url( $url );
		$this->assertEmpty( get_post_meta( $post_id, '_cachetop_hash', true ) );
	}

	public function test_cachetop_unfragment_non_string() {
		cachetop_unfragment( false );
		$this->expectOutputString( '' );
	}

	public function test_cachetop_unfragment_empty_function() {
		cachetop_unfragment( 'fake' );
		$this->expectOutputString( '' );
	}

	public function test_cachetop_unfragment() {
		$data = [
			'fn'      => 'uniqid',
			'args'    => [],
			'arr_arg' => false
		];
		$data = json_encode( $data );
		$data = base64_encode( $data );

		cachetop_unfragment( 'uniqid', [], false );

		$expected = sprintf( '/\<\!\-\-\scachetop\:\sunfragment\:%s\s\-\-\>/', $data );
		$this->expectOutputRegex( $expected );
	}
}
