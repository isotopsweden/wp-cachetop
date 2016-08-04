<?php

namespace Isotop\Tests\Cachetop;

use Isotop\Cachetop\Minify;

class Minify_Test extends \WP_UnitTestCase {

	public function setUp() {
		$this->minify = new Minify();
	}

	public function tearDown() {
		unset( $this->minify );
	}

	public function test_compress() {
		$expected = $this->minify->compress( '<p>Hello</p>' );
		$this->assertSame( '<p>Hello</p>', $expected );
	}

	public function test_compress_2() {
		$expected = $this->minify->compress( '<p>             Hello</p>' );
		$this->assertSame( '<p> Hello</p>', $expected );
	}

	public function test_compress_3() {
		$input    = '<pre> a { background: black; } </pre>';
		$expected = $this->minify->compress( $input );
		$this->assertSame( $input, $expected );
	}
}
