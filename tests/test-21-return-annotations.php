<?php
/**
 * Class ReturnAnnotationsTest
 *
 * @package KHSE_Cache
 */

class ReturnAnnotationsTest extends WP_UnitTestCase {

	/**
	 * Every debug-trait method must declare the type it actually returns.
	 *
	 * @return void
	 */
	public function test_debug_trait_return_annotations() {
		$trait    = new ReflectionClass( KHSE_Cache_Debug::class );
		$expected = array(
			'get_front_enqueue_styles' => 'string',
			'debug_message'            => 'string',
			'msg'                      => 'void|string',
			'wp_ob_start'              => 'void',
			'wp_ob_end_flush_all'      => 'void',
			'wp_timer_stop'            => 'string',
			'get_date'                 => 'string',
			'get_memory_usage'         => 'float',
			'log'                      => 'void',
			'get_string_bytes'         => 'int',
			'footer_html'              => 'string',
		);

		foreach ( $expected as $method_name => $return_type ) {
			$docblock = (string) $trait->getMethod( $method_name )->getDocComment();

			$this->assertMatchesRegularExpression(
				'/@return\s+' . preg_quote( $return_type, '/' ) . '\b/',
				$docblock,
				$method_name
			);
		}
	}

	/**
	 * The easy-to-invoke private helpers must match their annotations at runtime.
	 *
	 * @return void
	 */
	public function test_debug_trait_runtime_return_types() {
		$cm         = new KHSE_Cache();
		$reflection = new ReflectionClass( $cm );
		$invoke     = function ( $name ) use ( $reflection, $cm ) {
			$method = $reflection->getMethod( $name );
			$method->setAccessible( true );

			return $method->invoke( $cm );
		};

		$this->assertIsFloat( $invoke( 'get_memory_usage' ) );
		$this->assertIsString( $invoke( 'wp_timer_stop' ) );
		$this->assertIsString( $invoke( 'footer_html' ) );
		$this->assertIsString( $invoke( 'get_date' ) );
	}

	/**
	 * The post-update filter returns the messages array and says so.
	 *
	 * @return void
	 */
	public function test_post_update_filter_returns_messages_array() {
		$function = new ReflectionFunction( 'khse_notice_after_update_post' );
		$docblock = (string) $function->getDocComment();

		$this->assertMatchesRegularExpression( '/@return\s+array\b/', $docblock );

		$messages = khse_notice_after_update_post(
			array(
				'post' => array( 'Post updated.' ),
				'page' => array( 'Page updated.' ),
			)
		);

		$this->assertIsArray( $messages );
		$this->assertArrayHasKey( 'post', $messages );
		$this->assertArrayHasKey( 'page', $messages );
	}
}
