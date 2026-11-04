<?php
/**
 * Class DashboardSettingsSchemaTest
 *
 * @package Cache_Master
 */

/**
 * The declarative dashboard option schema must sanitize, clamp, and default values.
 */
class DashboardSettingsSchemaTest extends WP_UnitTestCase {

	/**
	 * Values are sanitized per rule and clamped to the declared min/max bounds.
	 *
	 * @return void
	 */
	public function test_schema_sanitizes_and_bounds_values() {
		$schema = array(
			'enabled' => array( 'option' => 'scm_test_schema_enabled', 'sanitize' => 'yes_no', 'default' => 'no', 'fallback' => 'no' ),
			'limit'   => array( 'option' => 'scm_test_schema_limit', 'sanitize' => 'absint', 'default' => 50, 'min' => 1, 'max' => 100 ),
			'mode'    => array( 'option' => 'scm_test_schema_mode', 'sanitize' => 'enum', 'values' => array( 'a', 'b' ), 'fallback' => 'a', 'default' => 'a' ),
		);

		scm_ajax_dashboard_apply_option_schema(
			array( 'enabled' => true, 'limit' => '999', 'mode' => 'z' ),
			$schema
		);

		$this->assertSame( 'yes', get_option( 'scm_test_schema_enabled' ) );
		$this->assertSame( 100, get_option( 'scm_test_schema_limit' ) );
		$this->assertSame( 'a', get_option( 'scm_test_schema_mode' ) );
	}

	/**
	 * Fields absent from the incoming payload fall back to their declared default.
	 *
	 * @return void
	 */
	public function test_schema_uses_defaults_for_missing_fields() {
		$schema = array(
			'enabled' => array( 'option' => 'scm_test_schema_enabled2', 'sanitize' => 'yes_no', 'default' => 'yes', 'fallback' => 'no' ),
		);

		scm_ajax_dashboard_apply_option_schema( array(), $schema );

		$this->assertSame( 'yes', get_option( 'scm_test_schema_enabled2' ) );
	}

	/**
	 * Saving Expert Mode off must disable the option and remove the runtime lock
	 * that the save callback syncs afterwards.
	 *
	 * @return void
	 */
	public function test_expert_mode_toggle_controls_runtime_lock() {
		$lock = scm_get_expert_lock_path();

		update_option( 'scm_option_expert_mode_status', 'enable' );
		wp_mkdir_p( dirname( $lock ) );
		file_put_contents( $lock, 'VOTE!' );

		scm_ajax_dashboard_apply_option_schema(
			array( 'expertModeStatus' => false ),
			array(
				'expertModeStatus' => array(
					'option'   => 'scm_option_expert_mode_status',
					'sanitize' => 'enable_disable',
					'default'  => 'enable',
					'fallback' => 'disable',
				),
			)
		);
		scm_sync_expert_mode_runtime();

		$this->assertSame( 'disable', get_option( 'scm_option_expert_mode_status' ) );
		$this->assertFileDoesNotExist( $lock );

		scm_ajax_dashboard_apply_option_schema(
			array( 'expertModeStatus' => true ),
			array(
				'expertModeStatus' => array(
					'option'   => 'scm_option_expert_mode_status',
					'sanitize' => 'enable_disable',
					'default'  => 'disable',
					'fallback' => 'disable',
				),
			)
		);
		scm_sync_expert_mode_runtime();

		$this->assertSame( 'enable', get_option( 'scm_option_expert_mode_status' ) );
		$this->assertFileExists( $lock );

		// Leave the test environment disabled; the DB transaction rolls back.
		update_option( 'scm_option_expert_mode_status', 'disable' );
		@unlink( $lock );
	}

	/**
	 * Textarea values keep their line breaks while tags are stripped; keys are normalized.
	 *
	 * @return void
	 */
	public function test_schema_sanitizes_textareas_and_keys() {
		$schema = array(
			'text' => array( 'option' => 'scm_test_schema_text', 'sanitize' => 'textarea', 'default' => '' ),
			'key'  => array( 'option' => 'scm_test_schema_key', 'sanitize' => 'key', 'default' => '' ),
		);

		scm_ajax_dashboard_apply_option_schema(
			array( 'text' => "<b>keep</b>\nline", 'key' => 'A B!' ),
			$schema
		);

		$this->assertSame( "keep\nline", get_option( 'scm_test_schema_text' ) );
		$this->assertSame( 'ab', get_option( 'scm_test_schema_key' ) );
	}
}
