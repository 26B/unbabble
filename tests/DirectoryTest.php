<?php

namespace {
	if ( ! class_exists( 'WP_Post' ) ) {
		class WP_Post {
			public int $ID;
			public string $post_type;

			public function __construct( int $id = 0, string $post_type = 'post' ) {
				$this->ID        = $id;
				$this->post_type = $post_type;
			}

			public static function get_instance( int $post_id ) : self {
				throw new \RuntimeException( 'WP_Post::get_instance should not be called.' );
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
use TwentySixB\WP\Plugin\Unbabble\Router\Directory;

/**
 * Unit tests for directory routing.
 *
 * @since Unreleased
 */
class DirectoryTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up tests.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	public function setUp() : void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		$_GET    = [];
		$_COOKIE = [];

		global $wp_query;
		$wp_query = null;

		Options::clear_static_cache();
	}

	/**
	 * Tear down tests.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	public function tearDown() : void {
		Options::clear_static_cache();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Options used in tests.
	 *
	 * @since Unreleased
	 *
	 * @return array
	 */
	private function options() : array {
		return [
			'allowed_languages' => [ 'en_US', 'pt_PT' ],
			'default_language'  => 'en_US',
			'hidden_languages'  => [],
			'post_types'        => [ 'post', 'attachment' ],
			'taxonomies'        => [ 'category' ],
			'router'            => 'directory',
			'router_options'    => [
				'directories' => [
					'pt_PT' => 'pt',
				],
			],
		];
	}

	/**
	 * Set up option loading.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	private function setUpOptionsHooks() : void {
		$options = $this->options();

		Filters\expectAdded( 'ubb_stop_switch_locale' )
			->twice()
			->with( '__return_true' );
		mock_user_function( 'get_locale', [], null, 'en_US' );
		Filters\expectRemoved( 'ubb_stop_switch_locale' )
			->twice()
			->with( '__return_true' );
		mock_user_function( 'get_option', [ 'ubb_options' ], null, $options );

		$default_options = Options::defaults();
		mock_user_function( 'wp_parse_args', [ $options, $default_options ], null, $options );
	}

	/**
	 * Test attachment links that already include the attachment language.
	 *
	 * @since Unreleased
	 *
	 * @testdox apply_lang_to_attachment_url - returns same-language attachment URLs unchanged
	 *
	 * @return void
	 */
	public function testApplyLangToAttachmentUrlReturnsSameLanguageLink() : void {
		$this->setUpOptionsHooks();

		mock_user_function( 'get_post_type', [ 123 ], 1, 'attachment' );
		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'get_current_blog_id', [], 1, 1 );
		mock_user_function( 'wp_cache_get', [ 'ubb_1_123_post_language', 'ubb', false, false ], 1, false );
		mock_user_function( 'wp_cache_set', [ 'ubb_1_123_post_language', 'pt_PT', 'ubb', 30 ], 1, true );

		global $wpdb;
		$wpdb = new class() {
			public string $prefix = 'wp_';

			public function prepare( string $query, ...$args ) : string {
				return 'prepared attachment language query';
			}

			public function get_var( string $query ) : string {
				return 'pt_PT';
			}
		};

		$link = 'https://example.test/pt/uploads/file.pdf';

		$this->assertSame( $link, Directory::apply_lang_to_attachment_url( $link, 123 ) );
	}

	/**
	 * Test REST home URLs.
	 *
	 * @since Unreleased
	 *
	 * @testdox home_url - applies directory routing to REST scheme URLs
	 *
	 * @return void
	 */
	public function testHomeUrlAppliesDirectoryRoutingToRestSchemeUrls() : void {
		$this->setUpOptionsHooks();

		Filters\expectApplied( 'ubb_home_url' )
			->once()
			->with( false, 'https://example.test/wp-json/', '/wp-json/', 'rest' )
			->andReturn( false );
		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'sanitize_text_field', [ 'pt_PT' ], 1, 'pt_PT' );
		$_GET['lang'] = 'pt_PT';

		$this->assertSame(
			'https://example.test/pt/wp-json/',
			Directory::home_url( 'https://example.test/wp-json/', '/wp-json/', 'rest' )
		);
	}

	/**
	 * Test redirect guess pre-filter values.
	 *
	 * @since Unreleased
	 *
	 * @testdox pre_redirect_guess_404_permalink - returns non-null pre-filter values unchanged
	 *
	 * @return void
	 */
	public function testPreRedirectGuess404PermalinkReturnsPreFilterValue() : void {
		Functions\expect( 'get_query_var' )->never();

		$this->assertSame(
			'https://example.test/manual/',
			Directory::pre_redirect_guess_404_permalink( 'https://example.test/manual/' )
		);
	}

	/**
	 * Test network_home_url signature.
	 *
	 * @since Unreleased
	 *
	 * @testdox network_home_url - accepts resolver arguments
	 *
	 * @return void
	 */
	public function testNetworkHomeUrlAcceptsResolverArguments() : void {
		$method = new \ReflectionMethod( Directory::class, 'network_home_url' );

		$this->assertSame( 3, $method->getNumberOfParameters() );
	}
}
}
