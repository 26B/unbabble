<?php

namespace TwentySixB\WP\Plugin\Unbabble\Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Unit tests for LangInterface.
 *
 * @since 0.0.12
 */
class LangInterfaceTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * TODO: Functions to be tested in Integration and E2E testing.
	 *
	 * - get_current_language
	 * - set_post_language
	 * - get_post_language
	 * - set_post_source
	 * - get_post_source
	 * - get_post_translation
	 * - get_post_translations
	 * - change_post_language
	 * - get_posts_for_source
	 * - delete_post_source
	 * - get_new_post_source_id
	 * - set_term_language
	 * - get_term_language
	 * - set_term_source
	 * - get_term_source
	 * - get_term_translation
	 * - get_term_translations
	 * - change_term_language
	 * - get_terms_for_source
	 * - get_new_term_source_id
	 * - delete_term_source
	 * - translate_current_url
	 */

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
		$_GET    = [];
		$_COOKIE = [];

		global $wp_query;
		$wp_query = null;

		$property = ( new \ReflectionClass( Options::class ) )->getProperty( 'options' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Tear down tests.
	 *
	 * @since 0.0.12
	 *
	 * @return void
	 */
	public function tearDown() : void {
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
			'allowed_languages' => [ 'en_US', 'pt_PT', 'es_ES' ],
			'default_language'  => 'pt_PT',
			'hidden_languages'  => [ 'es_ES' ],
			'post_types'        => [ 'post', 'page', 'form' ],
			'taxonomies'        => [ 'category', 'post_tag', 'form_tag' ],
			'router'            => 'directory',
			'router_options'    => [],
		];
	}

	/**
	 * Set up hooks for loading options.
	 *
	 * @since 0.0.12
	 *
	 * @param array  $options
	 * @param string $default_locale
	 * @return void
	 */
	public function setUpOptionsHooks( array $options, string $default_locale = 'en_US' ) : void {
		Filters\expectAdded( 'ubb_stop_switch_locale' )
			->twice()
			->with( '__return_true' );

		mock_user_function( 'get_locale', [], null, $default_locale );
		Filters\expectRemoved( 'ubb_stop_switch_locale' )
			->twice()
			->with( '__return_true' );
		mock_user_function( 'get_option', [ 'ubb_options' ], null, $options );

		$default_options = Options::defaults();
		mock_user_function( 'wp_parse_args', [ $options, $default_options ], null, $options );
	}

	/**
	 * Test get_languages.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_languages - return expected filtered and unfiltered languages
	 *
	 * @return void
	 */
	public function testGetLanguages() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		mock_user_function( 'is_admin', null, null, false );
		mock_user_function( 'current_user_can', [ 'manage_options' ], null, false );

		// Test languages unfiltered by hidden languages.
		Filters\expectApplied( 'ubb_do_hidden_languages_filter' )
			->once()
			->with( true, $options )
			->andReturn( false );

		$this->assertSame(
			$options['allowed_languages'],
			LangInterface::get_languages()
		);

		// Test languages filtered by hidden languages.
		Filters\expectApplied( 'ubb_do_hidden_languages_filter' )
			->once()
			->with( true, $options )
			->andReturn( true );

		$this->assertSame(
			array_diff( $options['allowed_languages'], $options['hidden_languages'] ),
			LangInterface::get_languages()
		);
	}

	/**
	 * Test is_language_allowed.
	 *
	 * @since 0.0.12
	 *
	 * @testdox is_language_allowed - returns as expected
	 *
	 * @return void
	 */
	public function testIsLanguageAllowed() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		// Needed for `get_languages`.
		mock_user_function( 'is_admin', null, null, true );

		// Test existing language.
		$lang = current( $options['allowed_languages'] );
		$this->assertTrue( LangInterface::is_language_allowed( $lang ) );

		// Test unknown language.
		$lang = 'unknown_language';
		$this->assertFalse( LangInterface::is_language_allowed( $lang ) );
	}

	/**
	 * Test get_default_language.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_default_language - returns as expected
	 *
	 * @return void
	 */
	public function testGetDefaultLanguage() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		// Needed for `get_languages`.
		mock_user_function( 'is_admin', null, null, true );

		$this->assertSame( $options['default_language'], LangInterface::get_default_language() );
	}

	/**
	 * Test get_current_language with a query var.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_current_language - reads a valid query var before other sources
	 *
	 * @return void
	 */
	public function testGetCurrentLanguageReadsQueryVar() : void {
		global $wp_query;
		$wp_query = new \stdClass();

		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		mock_user_function( 'get_query_var', [ 'lang', null ], 1, 'en_US' );
		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'sanitize_text_field', [ 'en_US' ], 1, 'en_US' );
		Filters\expectApplied( 'ubb_current_lang' )
			->once()
			->with( 'en_US' )
			->andReturn( 'en_US' );

		$this->assertSame( 'en_US', LangInterface::get_current_language() );
	}

	/**
	 * Test get_current_language with GET data.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_current_language - falls back to a valid lang request value
	 *
	 * @return void
	 */
	public function testGetCurrentLanguageReadsGetValue() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		$_GET['lang'] = 'pt_PT';

		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'sanitize_text_field', [ 'pt_PT' ], 1, 'pt_PT' );
		Filters\expectApplied( 'ubb_current_lang' )
			->once()
			->with( 'pt_PT' )
			->andReturn( 'pt_PT' );

		$this->assertSame( 'pt_PT', LangInterface::get_current_language() );
	}

	/**
	 * Test get_current_language with admin cookie.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_current_language - falls back to a valid admin language cookie
	 *
	 * @return void
	 */
	public function testGetCurrentLanguageReadsAdminCookie() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		$_COOKIE['ubb_lang'] = 'pt_PT';

		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'sanitize_text_field', [ 'pt_PT' ], 1, 'pt_PT' );
		Filters\expectApplied( 'ubb_current_lang' )
			->once()
			->with( 'pt_PT' )
			->andReturn( 'pt_PT' );

		$this->assertSame( 'pt_PT', LangInterface::get_current_language() );
	}

	/**
	 * Test get_current_language with an invalid value.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_current_language - falls back to default language when value is invalid
	 *
	 * @return void
	 */
	public function testGetCurrentLanguageFallsBackToDefaultWhenInvalid() : void {
		global $wp_query;
		$wp_query = new \stdClass();

		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		mock_user_function( 'get_query_var', [ 'lang', null ], 1, 'unknown_language' );
		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'sanitize_text_field', [ $options['default_language'] ], 1, $options['default_language'] );
		Filters\expectApplied( 'ubb_current_lang' )
			->once()
			->with( $options['default_language'] )
			->andReturn( $options['default_language'] );

		$this->assertSame( $options['default_language'], LangInterface::get_current_language() );
	}

	/**
	 * Test set_current_language.
	 *
	 * @since 0.0.12
	 *
	 * @testdox set_current_language - functions as expected
	 *
	 * @return void
	 */
	public function testSetCurrentLanguage() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		// Needed for `get_languages`.
		mock_user_function( 'is_admin', null, null, true );

		// Test unknown language.
		$lang = 'unknown_language';
		$this->assertFalse( LangInterface::set_current_language( $lang ) );

		// Test allowed language.
		$lang = current( $options['allowed_languages'] );
		mock_user_function( 'set_query_var', [ 'lang', $lang ], 1, null );
		$this->assertTrue( LangInterface::set_current_language( $lang ) );
	}

	/**
	 * Test get_post_language from database.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_post_language - returns database value and caches it when cache misses
	 *
	 * @return void
	 */
	public function testGetPostLanguageReturnsDatabaseValueOnCacheMiss() : void {
		global $wpdb;
		$wpdb = new class() {
			public string $prefix = 'wp_';
			public string $last_prepare_query = '';
			public array $last_prepare_args = [];

			public function prepare( string $query, ...$args ) : string {
				$this->last_prepare_query = $query;
				$this->last_prepare_args  = $args;
				return 'prepared post language query';
			}

			public function get_var( string $query ) : string {
				return 'pt_PT';
			}
		};

		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		mock_user_function( 'get_post_type', [ 123 ], 1, 'post' );
		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'get_current_blog_id', [], 1, 1 );
		mock_user_function( 'wp_cache_get', [ 'ubb_1_123_post_language', 'ubb', false, false ], 1, false );
		mock_user_function( 'wp_cache_set', [ 'ubb_1_123_post_language', 'pt_PT', 'ubb', 30 ], 1, true );

		$this->assertSame( 'pt_PT', LangInterface::get_post_language( 123 ) );
		$this->assertSame( [ 123 ], $wpdb->last_prepare_args );
	}

	/**
	 * Test set_post_language rejects unknown language.
	 *
	 * @since 0.0.12
	 *
	 * @testdox set_post_language - rejects unknown language without writing the database
	 *
	 * @return void
	 */
	public function testSetPostLanguageRejectsUnknownLanguage() : void {
		global $wpdb;
		$wpdb = (object) [ 'prefix' => 'wp_' ];

		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		mock_user_function( 'is_admin', null, null, true );

		$this->assertFalse( LangInterface::set_post_language( 123, 'unknown_language' ) );
	}

	/**
	 * Test set_post_language inserts a new language.
	 *
	 * @since 0.0.12
	 *
	 * @testdox set_post_language - writes a new language, clears cache, and fires action
	 *
	 * @return void
	 */
	public function testSetPostLanguageWritesNewLanguage() : void {
		global $wpdb;
		$wpdb = new class() {
			public string $prefix = 'wp_';
			public array $replace_args = [];

			public function prepare( string $query, ...$args ) : string {
				return 'prepared post language query';
			}

			public function get_var( string $query ) : string {
				return '';
			}

			public function replace( string $table, array $data ) : int {
				$this->replace_args = [ $table, $data ];
				return 1;
			}
		};

		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		mock_user_function( 'is_admin', null, null, true );
		mock_user_function( 'get_post_type', [ 123 ], 1, 'post' );
		mock_user_function( 'get_current_blog_id', [], null, 1 );
		Functions\expect( 'wp_cache_get' )
			->once()
			->withAnyArgs()
			->andReturnUsing(
				function ( string $key, string $group, bool $force, bool &$found ) : string {
					$found = true;
					return '';
				}
			);
		mock_user_function( 'wp_cache_set', [ 'ubb_1_123_post_language', '', 'ubb', 30 ], 1, true );
		mock_user_function( 'wp_cache_delete', [ 'ubb_1_123_post_language', 'ubb' ], 1, true );
		Actions\expectDone( 'ubb_post_language_set' )
			->once()
			->with( 123, 'pt_PT', false, null );

		$this->assertTrue( LangInterface::set_post_language( 123, 'pt_PT' ) );
		$this->assertSame(
			[
				'wp_ubb_post_translations',
				[
					'post_id' => 123,
					'locale'  => 'pt_PT',
				],
			],
			$wpdb->replace_args
		);
	}

	/**
	 * Test get_post_source from transient.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_post_source - returns non-empty transient value without reading meta
	 *
	 * @return void
	 */
	public function testGetPostSourceReturnsTransientValue() : void {
		mock_user_function( 'get_transient', [ 'ubb_123_post_source' ], 1, 'source-1' );
		Functions\expect( 'get_post_meta' )->never();

		$this->assertSame( 'source-1', LangInterface::get_post_source( 123 ) );
	}

	/**
	 * Test get_post_source normalizes empty meta.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_post_source - normalizes empty meta values to null and caches null
	 *
	 * @return void
	 */
	public function testGetPostSourceNormalizesEmptyMetaValue() : void {
		mock_user_function( 'get_transient', [ 'ubb_123_post_source' ], 1, false );
		mock_user_function( 'get_post_meta', [ 123, 'ubb_source', true ], 1, '' );
		mock_user_function( 'set_transient', [ 'ubb_123_post_source', null, 30 ], 1, true );

		$this->assertNull( LangInterface::get_post_source( 123 ) );
	}

	/**
	 * Test set_post_source adds a new source.
	 *
	 * @since 0.0.12
	 *
	 * @testdox set_post_source - adds a new source, updates transients, and fires action
	 *
	 * @return void
	 */
	public function testSetPostSourceAddsNewSource() : void {
		mock_user_function( 'get_transient', [ 'ubb_123_post_source' ], 1, false );
		mock_user_function( 'get_post_meta', [ 123, 'ubb_source', true ], 1, '' );
		mock_user_function( 'set_transient', [ 'ubb_123_post_source', null, 30 ], 1, true );
		mock_user_function( 'add_post_meta', [ 123, 'ubb_source', 'source-1', true ], 1, 456 );
		mock_user_function( 'delete_transient', [ 'ubb_source-1_source_posts' ], 1, true );
		mock_user_function( 'set_transient', [ 'ubb_123_post_source', 'source-1', 30 ], 1, true );
		Actions\expectDone( 'ubb_post_source_set' )
			->once()
			->with( 123, 'source-1', null, false );

		$this->assertTrue( LangInterface::set_post_source( 123, 'source-1' ) );
	}

	/**
	 * Test set_post_source force with same source.
	 *
	 * @since 0.0.12
	 *
	 * @testdox set_post_source - forced same source returns true without writing meta
	 *
	 * @return void
	 */
	public function testSetPostSourceForceSameSourceSkipsMetaWrite() : void {
		mock_user_function( 'get_transient', [ 'ubb_123_post_source' ], 1, 'source-1' );
		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'add_post_meta' )->never();
		Functions\expect( 'delete_transient' )->never();

		$this->assertTrue( LangInterface::set_post_source( 123, 'source-1', true ) );
	}

	/**
	 * Test get_translatable_post_types.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_translatable_post_types - returns as expected
	 *
	 * @return void
	 */
	public function testGetTranslatablePostTypes() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		// Needed for `get_languages`.
		mock_user_function( 'is_admin', null, null, true );

		$this->assertSame( $options['post_types'], LangInterface::get_translatable_post_types() );
	}

	/**
	 * Test is_post_type_translatable.
	 *
	 * @since 0.0.12
	 *
	 * @testdox is_post_type_translatable - returns as expected
	 *
	 * @return void
	 */
	public function testIsPostTypeTranslatable() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		// Needed for `get_languages`.
		mock_user_function( 'is_admin', null, null, true );

		// Unknown post type.
		$post_type = 'unknown_post_type';
		$this->assertFalse( LangInterface::is_post_type_translatable( $post_type ) );

		// Translatable post type.
		$post_type = current( $options['post_types'] );
		$this->assertTrue( LangInterface::is_post_type_translatable( $post_type ) );
	}

	/**
	 * Test get_translatable_taxonomies.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_translatable_taxonomies - returns as expected
	 *
	 * @return void
	 */
	public function testGetTranslatableTaxonomies() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		// Needed for `get_languages`.
		mock_user_function( 'is_admin', null, null, true );

		$this->assertSame( $options['taxonomies'], LangInterface::get_translatable_taxonomies() );
	}

	/**
	 * Test get_term_source from transient.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_term_source - returns non-empty transient value without reading meta
	 *
	 * @return void
	 */
	public function testGetTermSourceReturnsTransientValue() : void {
		mock_user_function( 'get_transient', [ 'ubb_321_term_source' ], 1, 'source-2' );
		Functions\expect( 'get_term_meta' )->never();

		$this->assertSame( 'source-2', LangInterface::get_term_source( 321 ) );
	}

	/**
	 * Test get_term_source normalizes empty meta.
	 *
	 * @since 0.0.12
	 *
	 * @testdox get_term_source - normalizes empty meta values to null and caches null
	 *
	 * @return void
	 */
	public function testGetTermSourceNormalizesEmptyMetaValue() : void {
		mock_user_function( 'get_transient', [ 'ubb_321_term_source' ], 1, false );
		mock_user_function( 'get_term_meta', [ 321, 'ubb_source', true ], 1, '' );
		mock_user_function( 'set_transient', [ 'ubb_321_term_source', null, 30 ], 1, true );

		$this->assertNull( LangInterface::get_term_source( 321 ) );
	}

	/**
	 * Test set_term_source adds a new source.
	 *
	 * @since 0.0.12
	 *
	 * @testdox set_term_source - adds a new source, updates transients, and fires action
	 *
	 * @return void
	 */
	public function testSetTermSourceAddsNewSource() : void {
		mock_user_function( 'get_transient', [ 'ubb_321_term_source' ], 1, false );
		mock_user_function( 'get_term_meta', [ 321, 'ubb_source', true ], 1, '' );
		mock_user_function( 'set_transient', [ 'ubb_321_term_source', null, 30 ], 1, true );
		mock_user_function( 'add_term_meta', [ 321, 'ubb_source', 'source-2', true ], 1, 654 );
		mock_user_function( 'delete_transient', [ 'ubb_source-2_source_terms' ], 1, true );
		mock_user_function( 'set_transient', [ 'ubb_321_term_source', 'source-2', 30 ], 1, true );
		Actions\expectDone( 'ubb_term_source_set' )
			->once()
			->with( 321, 'source-2', null, false );

		$this->assertTrue( LangInterface::set_term_source( 321, 'source-2' ) );
	}

	/**
	 * Test set_term_source force with same source.
	 *
	 * @since 0.0.12
	 *
	 * @testdox set_term_source - forced same source returns true without writing meta
	 *
	 * @return void
	 */
	public function testSetTermSourceForceSameSourceSkipsMetaWrite() : void {
		mock_user_function( 'get_transient', [ 'ubb_321_term_source' ], 1, 'source-2' );
		Functions\expect( 'update_term_meta' )->never();
		Functions\expect( 'add_term_meta' )->never();
		Functions\expect( 'delete_transient' )->never();

		$this->assertTrue( LangInterface::set_term_source( 321, 'source-2', true ) );
	}

	/**
	 * Test is_taxonomy_translatable.
	 *
	 * @since 0.0.12
	 *
	 * @testdox is_taxonomy_translatable - returns as expected
	 *
	 * @return void
	 */
	public function testIsTaxonomyTranslatable() : void {
		$options = $this->options();
		$this->setUpOptionsHooks( $options );

		// Needed for `get_languages`.
		mock_user_function( 'is_admin', null, null, true );

		// Unknown taxonomy.
		$taxonomy = 'unknown_taxonomy';
		$this->assertFalse( LangInterface::is_taxonomy_translatable( $taxonomy ) );

		// Translatable taxonomy.
		$taxonomy = current( $options['taxonomies'] );
		$this->assertTrue( LangInterface::is_taxonomy_translatable( $taxonomy ) );
	}
}
