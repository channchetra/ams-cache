<?php
/**
 * Class DriverSettingsTest
 *
 * @package Cache_Master
 */

/**
 * Coverage for the shared driver-settings defaults.
 */
class DriverSettingsTest extends WP_UnitTestCase {

	/**
	 * File storage is anchored to the plugin upload directory.
	 *
	 * @return void
	 */
	public function test_file_setting_matches_upload_dir() {
		$this->assertSame(
			array( 'storage' => scm_get_upload_dir() . '/file_driver' ),
			scm_get_driver_setting( 'file' )
		);
	}

	/**
	 * Redis defaults are shared by the factory, test, and Expert Mode.
	 *
	 * @return void
	 */
	public function test_redis_setting_defaults() {
		$this->assertSame(
			array(
				'host'     => '127.0.0.1',
				'port'     => 6379,
				'database' => 0,
			),
			scm_get_driver_setting( 'redis' )
		);
	}

	/**
	 * Mongo defaults exist; Expert Mode previously fell through to an empty array.
	 *
	 * @return void
	 */
	public function test_mongo_setting_defaults() {
		$this->assertSame(
			array(
				'host' => '127.0.0.1',
				'port' => 27017,
			),
			scm_get_driver_setting( 'mongo' )
		);
	}

	/**
	 * Unknown driver types fall back to the file driver.
	 *
	 * @return void
	 */
	public function test_unknown_driver_falls_back_to_file() {
		$this->assertSame(
			scm_get_driver_setting( 'file' ),
			scm_get_driver_setting( 'unknown-driver' )
		);
	}

	/**
	 * MySQL settings come from the WordPress database constants.
	 *
	 * @return void
	 */
	public function test_mysql_setting_uses_wp_database_constants() {
		$this->assertSame(
			array(
				'host'    => DB_HOST,
				'dbname'  => DB_NAME,
				'user'    => DB_USER,
				'pass'    => DB_PASSWORD,
				'charset' => DB_CHARSET,
			),
			scm_get_driver_setting( 'mysql' )
		);
	}

	/**
	 * Advanced settings are merged and normalized by the resolver.
	 *
	 * @return void
	 */
	public function test_driver_settings_merge_advanced_options() {
		$resolved = scm_get_driver_settings(
			'redis',
			array(
				'host' => '10.0.0.5',
				'port' => '6380',
			),
			'tcp'
		);

		$this->assertSame( '10.0.0.5', $resolved['setting']['host'] );
		$this->assertSame( 6380, $resolved['setting']['port'] );
		$this->assertSame( 'tcp', $resolved['connection_type'] );
	}

	/**
	 * An explicit socket connection type is preserved.
	 *
	 * @return void
	 */
	public function test_driver_settings_preserve_connection_type() {
		$resolved = scm_get_driver_settings( 'redis', array(), 'socket' );

		$this->assertSame( 'socket', $resolved['connection_type'] );
	}
}
