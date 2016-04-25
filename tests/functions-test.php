<?php

namespace Cachetop\Tests;

class Functions_Test extends \WP_UnitTestCase {

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
