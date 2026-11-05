<?php
/**
 * Class LocalSimpleCacheTest
 *
 * @package KHSE_Cache
 */

class LocalSimpleCacheTest extends WP_UnitTestCase {

	public function test_simple_cache_loads_from_the_local_library() {
		$this->assertTrue( class_exists( 'Shieldon\\SimpleCache\\Cache' ) );
		$this->assertTrue( interface_exists( 'Psr\\SimpleCache\\CacheInterface' ) );

		$cache = new ReflectionClass( 'Shieldon\\SimpleCache\\Cache' );
		$this->assertStringContainsString( '/inc/lib/simple-cache/', $cache->getFileName() );

		$psr = new ReflectionClass( 'Psr\\SimpleCache\\CacheInterface' );
		$this->assertStringContainsString( '/inc/lib/psr-simple-cache/', $psr->getFileName() );
	}
}
