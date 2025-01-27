<?php

namespace TwentySixB\WP\Plugin\Unbabble\Tests;

use PHPUnit\Framework\TestCase;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Mock;

/**
 * Unit tests for LangInterface.
 *
 * @since 0.0.12
 */
class LangInterfaceTest extends TestCase {

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
	 * Tear down tests.
	 *
	 * @since 0.0.12
	 *
	 * @return void
	 */
	public function tearDown() : void {
		\WP_Mock::tearDown();
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
		WP_Mock::expectFilterAdded( 'ubb_stop_switch_locale', '__return_true' );

		mock_user_function( 'get_locale', [], null, $default_locale );
		// No expectFilterRemoved exists, so we need to do it manually.
		mock_user_function( 'remove_filter', [ 'ubb_stop_switch_locale', '__return_true' ], null );
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
		WP_Mock::onFilter( 'ubb_do_hidden_languages_filter' )
			->with( true, $options )
			->reply( false );

		$this->assertSame(
			$options['allowed_languages'],
			LangInterface::get_languages()
		);

		// Test languages filtered by hidden languages.
		WP_Mock::onFilter( 'ubb_do_hidden_languages_filter' )
			->with( true, $options )
			->reply( true );

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
