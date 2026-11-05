<?php
/**
 * Class DriverFactoryContractTest
 *
 * @package KHSE_Cache
 */

class DriverFactoryContractTest extends WP_UnitTestCase {

	public function test_file_driver_is_returned() {
		$driver = khse_driver_factory( 'file' );

		$this->assertFalse( is_wp_error( $driver ) );
		$this->assertInstanceOf( 'Shieldon\\SimpleCache\\Cache', $driver );
	}

	public function test_unknown_type_falls_back_to_a_driver() {
		$driver = khse_driver_factory( 'not-a-real-driver' );

		$this->assertFalse( is_wp_error( $driver ) );
		$this->assertInstanceOf( 'Shieldon\\SimpleCache\\Cache', $driver );
	}
}
