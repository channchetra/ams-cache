<?php
/**
 * Class BrandMigrationTest
 *
 * @package KHSE_Cache
 */

class BrandMigrationTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'khse_brand_migrated' );
		delete_option( 'khse_test_migration_value' );
		delete_option( 'scm_test_migration_value' );
		parent::tear_down();
	}

	public function test_legacy_options_are_copied_and_removed() {
		delete_option( 'khse_brand_migrated' );
		delete_option( 'khse_test_migration_value' );
		update_option( 'scm_test_migration_value', 12345 );

		khse_maybe_migrate_legacy_brand();

		$this->assertSame( 12345, (int) get_option( 'khse_test_migration_value' ) );
		$this->assertFalse( get_option( 'scm_test_migration_value', false ) );
		$this->assertNotEmpty( get_option( 'khse_brand_migrated' ) );
	}

	public function test_migration_is_idempotent() {
		delete_option( 'khse_brand_migrated' );
		update_option( 'scm_test_migration_value', 999 );
		khse_maybe_migrate_legacy_brand();

		update_option( 'scm_test_migration_value', 111 );
		khse_maybe_migrate_legacy_brand();

		$this->assertSame( 999, (int) get_option( 'khse_test_migration_value' ) );
		$this->assertSame( 111, (int) get_option( 'scm_test_migration_value' ) );
	}
}
