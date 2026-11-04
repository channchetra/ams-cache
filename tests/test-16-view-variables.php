<?php
/**
 * Class ViewVariablesTest
 *
 * @package Cache_Master
 */

class ViewVariablesTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		scm_drain_view_variables();
	}

	public function test_set_and_drain_preserves_before_after() {
		scm_set_view_variable( 'now', '2026-01-01', 'before' );
		scm_set_view_variable( 'sql_queries', 3, 'after' );

		$data = json_decode( scm_drain_view_variables(), true );

		$this->assertSame( '2026-01-01', $data['before']['now'] );
		$this->assertSame( 3, $data['after']['sql_queries'] );
		$this->assertSame( '[]', scm_drain_view_variables() );
	}

	public function test_default_position_is_before() {
		scm_set_view_variable( 'now', '2026-01-01' );

		$data = json_decode( scm_drain_view_variables(), true );

		$this->assertArrayHasKey( 'before', $data );
		$this->assertArrayNotHasKey( 'after', $data );
	}

	public function test_svg_icon_returns_string_for_known_and_unknown_types() {
		$status = scm_get_svg_icon( 'status' );

		$this->assertIsString( $status );
		$this->assertStringContainsString( '<svg', $status );

		$this->assertSame( '', scm_get_svg_icon( 'unknown-type' ) );
	}
}
