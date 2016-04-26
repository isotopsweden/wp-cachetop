<?php

namespace Cachetop\Tests\Stores;

use Cachetop\Stores\Filesystem;

class Filesystem_Test extends \WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->store = new Filesystem();
	}

	public function tearDown() {
		parent::tearDown();
		unset( $this->store );
	}

	public function test_count() {
		$this->assertEmpty( $this->store->count() );

		$this->store->set( 'test1', 'Hello' );
		$this->assertSame( 1, $this->store->count() );
		$this->store->delete( 'test1' );
	}

	public function test_delete() {
		$this->store->set( 'test2', 'Hello' );
		$this->assertSame( 'Hello', $this->store->get( 'test2' ) );

		$this->store->delete( 'test2' );
		$this->assertEmpty( $this->store->get( 'test2' ) );
	}

	public function test_exists() {
		$this->store->set( 'test3', 'Hello' );
		$this->assertTrue( $this->store->exists( 'test3' ) );
		$this->store->delete( 'test3' );
	}

	public function test_get() {
		$this->store->set( 'test4', 'Hello' );
		$this->assertSame( 'Hello', $this->store->get( 'test4' ) );
		$this->store->delete( 'test4' );
	}

	public function test_set() {
		$this->store->set( 'test5', 'Hello' );
		$this->assertSame( 'Hello', $this->store->get( 'test5' ) );
		$this->store->delete( 'test5' );
	}
}
