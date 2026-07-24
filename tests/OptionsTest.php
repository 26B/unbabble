<?php

namespace {
	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			public $error_data;

			public function __construct( $code = '', $message = '', $data = '' ) {
				$this->error_data = $data;
			}
		}
	}

	if ( ! class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {
			private string $body;

			public function __construct( array $body ) {
				$this->body = json_encode( $body );
			}

			public function get_body() : string {
				return $this->body;
			}
		}
	}
}

namespace TwentySixB\WP\Plugin\Unbabble\Tests {

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Unit tests for Options.
 *
 * @since 0.0.12
 */
class OptionsTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up tests.
	 *
	 * @since 0.0.12
	 *
	 * @return void
	 */
	public function setUp() : void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'wp_parse_args' )->alias(
			fn ( array $args, array $defaults ) => array_merge( $defaults, $args )
		);

		Options::clear_static_cache();
	}

	/**
	 * Tear down tests.
	 *
	 * @since 0.0.12
	 *
	 * @return void
	 */
	public function tearDown() : void {
		Options::clear_static_cache();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Options used in the tests.
	 *
	 * @since 0.0.12
	 *
	 * @return array
	 */
	private function options() : array {
		return [
			'allowed_languages' => [ 'en_US', 'pt_PT' ],
			'default_language'  => 'en_US',
			'hidden_languages'  => [],
			'post_types'        => [ 'post', 'page' ],
			'taxonomies'        => [ 'category', 'post_tag' ],
			'router'            => 'query_var',
			'router_options'    => [],
		];
	}

	/**
	 * Test validate accepts valid options.
	 *
	 * @since 0.0.12
	 *
	 * @testdox validate - accepts complete valid options
	 *
	 * @return void
	 */
	public function testValidateAcceptsValidOptions() : void {
		$this->assertSame( [], Options::validate( $this->options() ) );
	}

	/**
	 * Test validate reports schema errors.
	 *
	 * @since 0.0.12
	 *
	 * @testdox validate - reports missing, type, and router errors
	 *
	 * @return void
	 */
	public function testValidateReportsSchemaErrors() : void {
		$options = $this->options();
		unset( $options['taxonomies'] );
		$options['allowed_languages'] = [ 'en_US', 26 ];
		$options['default_language']  = [];
		$options['hidden_languages']  = 'pt_PT';
		$options['post_types']        = 'post';
		$options['router']            = 'path';
		$options['router_options']    = false;

		$this->assertSame(
			[
				'allowed_languages' => [ 'At least one of it\'s array values is not a string.' ],
				'default_language'  => [ 'Value is not a string.', 'Value is empty.' ],
				'hidden_languages'  => [ 'Value is not an array.' ],
				'post_types'        => [ 'Value is not an array.' ],
				'taxonomies'        => 'Missing option.',
				'router'            => [ 'Value is unknown. Accepted values: query_var, directory.' ],
				'router_options'    => [ 'Value is not an array.' ],
			],
			Options::validate( $options )
		);
	}

	/**
	 * Test validate reports language rule errors.
	 *
	 * @since 0.0.12
	 *
	 * @testdox validate - reports default and hidden language conflicts
	 *
	 * @return void
	 */
	public function testValidateReportsLanguageRuleErrors() : void {
		$options = $this->options();
		$options['default_language'] = 'es_ES';

		$this->assertSame(
			[
				'default_language' => [ 'Default not in allowed languages.' ],
			],
			Options::validate( $options )
		);

		$options                     = $this->options();
		$options['hidden_languages'] = [ 'en_US', 'pt_PT' ];

		$this->assertSame(
			[
				'hidden_languages' => [
					'Default in hidden languages.',
					'Hidden languages will remove all the allowed languages.',
				],
			],
			Options::validate( $options )
		);
	}

	/**
	 * Test standardize.
	 *
	 * @since 0.0.12
	 *
	 * @testdox standardize - reindexes post type and taxonomy arrays
	 *
	 * @return void
	 */
	public function testStandardizeReindexesConfiguredArrays() : void {
		$options               = $this->options();
		$options['post_types'] = [ 4 => 'post', 9 => 'page' ];
		$options['taxonomies'] = [ 2 => 'category', 7 => 'post_tag' ];

		$standardized = Options::standardize( $options );

		$this->assertSame( [ 'post', 'page' ], $standardized['post_types'] );
		$this->assertSame( [ 'category', 'post_tag' ], $standardized['taxonomies'] );
	}

	/**
	 * Test get_filter_options with no filter value.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_filter_options - returns false when filter does not provide options
	 *
	 * @return void
	 */
	public function testGetFilterOptionsReturnsFalseWhenFilterDoesNotProvideOptions() : void {
		Filters\expectApplied( 'ubb_options' )
			->once()
			->with( null )
			->andReturn( null );

		$this->assertFalse( Options::get_filter_options() );
	}

	/**
	 * Test get_filter_options with invalid options.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_filter_options - returns WP_Error for invalid filtered options
	 *
	 * @return void
	 */
	public function testGetFilterOptionsReturnsWpErrorForInvalidOptions() : void {
		$options                      = $this->options();
		$options['default_language']  = 'fr_FR';
		$options['allowed_languages'] = [ 'en_US' ];

		Filters\expectApplied( 'ubb_options' )
			->once()
			->with( null )
			->andReturn( $options );

		$result = Options::get_filter_options();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame(
			[
				'default_language' => [ 'Default not in allowed languages.' ],
			],
			$result->error_data
		);
	}

	/**
	 * Test get_filter_options pairs menu translation options.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_filter_options - pairs nav menu post types and taxonomies
	 *
	 * @return void
	 */
	public function testGetFilterOptionsPairsNavMenuOptions() : void {
		$options               = $this->options();
		$options['post_types'] = [ 'post', 'nav_menu_item' ];
		$options['taxonomies'] = [ 'category' ];

		Filters\expectApplied( 'ubb_options' )
			->once()
			->with( null )
			->andReturn( $options );

		$result = Options::get_filter_options();

		$this->assertSame( [ 'category', 'nav_menu' ], $result['taxonomies'] );

		$options               = $this->options();
		$options['post_types'] = [ 'post' ];
		$options['taxonomies'] = [ 'category', 'nav_menu' ];

		Filters\expectApplied( 'ubb_options' )
			->once()
			->with( null )
			->andReturn( $options );

		$result = Options::get_filter_options();

		$this->assertSame( [ 'post', 'nav_menu_item' ], $result['post_types'] );
	}

	/**
	 * Test update_via_api with query var router.
	 *
	 * @since 0.0.12
	 *
	 * @testdox update_via_api - builds normalized options and preserves router options for query var router
	 *
	 * @return void
	 */
	public function testUpdateViaApiBuildsNormalizedOptionsAndPreservesRouterOptions() : void {
		$current_options                   = $this->options();
		$current_options['router_options'] = [ 'directories' => [ 'pt_PT' => 'pt' ] ];

		$new_options = [
			'allowed_languages' => [ 'en_US', 'pt_PT' ],
			'hidden_languages'  => [ 'pt_PT' ],
			'default_language'  => 'en_US',
			'router'            => 'query_var',
			'router_options'    => $current_options['router_options'],
			'post_types'        => [ 'post', 'page' ],
			'taxonomies'        => [ 'category', 'post_tag' ],
		];

		mock_user_function( 'get_option', [ 'ubb_options' ], 1, $current_options );
		mock_user_function( 'update_option', [ 'ubb_options', $new_options ], 1, true );
		mock_user_function( 'update_option', [ 'ubb_settings_manual_changes', true ], 1, true );

		$request = new \WP_REST_Request(
			[
				'languages'       => [
					[ 'language' => 'en_US' ],
					[ 'language' => 'pt_PT', 'hidden' => true ],
					[ 'language' => 'pt_PT', 'hidden' => true ],
				],
				'defaultLanguage' => 'en_US',
				'routing'         => [
					'router'         => 'query_var',
					'router_options' => [ 'directories' => [ 'pt_PT' => 'portuguese' ] ],
				],
				'postTypes'       => [ 'post', 'page', 'page' ],
				'taxonomies'      => [ 'category', 'post_tag', 'post_tag' ],
			]
		);

		$this->assertTrue( Options::update_via_api( $request ) );
	}

	/**
	 * Test update_via_api with directory router.
	 *
	 * @since 0.0.12
	 *
	 * @testdox update_via_api - normalizes directory router options
	 *
	 * @return void
	 */
	public function testUpdateViaApiNormalizesDirectoryRouterOptions() : void {
		$current_options = $this->options();
		$new_options     = [
			'allowed_languages' => [ 'en_US', 'pt_PT' ],
			'hidden_languages'  => [],
			'default_language'  => 'en_US',
			'router'            => 'directory',
			'router_options'    => [ 'directories' => [ 'pt_PT' => '', 'en_US' => '' ] ],
			'post_types'        => [ 'post' ],
			'taxonomies'        => [ 'category' ],
		];

		mock_user_function( 'get_option', [ 'ubb_options' ], 1, $current_options );
		mock_user_function( 'update_option', [ 'ubb_options', $new_options ], 1, true );
		mock_user_function( 'update_option', [ 'ubb_settings_manual_changes', true ], 1, true );

		$request = new \WP_REST_Request(
			[
				'languages'       => [
					[ 'language' => 'en_US' ],
					[ 'language' => 'pt_PT' ],
				],
				'defaultLanguage' => 'en_US',
				'routing'         => [
					'router'         => 'directory',
					'router_options' => [ 'directories' => [ 'pt_PT' => false ] ],
				],
				'postTypes'       => [ 'post' ],
				'taxonomies'      => [ 'category' ],
			]
		);

		$this->assertTrue( Options::update_via_api( $request ) );
	}
}
}
