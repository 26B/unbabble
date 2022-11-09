<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use TwentySixB\WP\Plugin\Unbabble\Refactor;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since 0.0.0
 */
class Plugin {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  0.0.0
	 * @access protected
	 * @var    string
	 */
	protected $name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  0.0.0
	 * @access protected
	 * @var    string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 0.0.0
	 * @param string $name    The plugin identifier.
	 * @param string $version Current version of the plugin.
	 */
	public function __construct( $name, $version ) {
		$this->name    = $name;
		$this->version = $version;
	}

	/**
	 * Run the loader to execute all the hooks with WordPress.
	 *
	 * Load the dependencies, define the locale, and set the hooks for the
	 * Dashboard and the public-facing side of the site.
	 *
	 * @since 0.0.0
	 */
	public function run() {
		$this->set_locale();
		$this->define_admin_hooks();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since  0.0.0
	 * @return string The name of the plugin.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns the version number of the plugin.
	 *
	 * @since  0.0.0
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since  0.0.0
	 * @access private
	 */
	private function set_locale() {
		$i18n = new I18n();
		$i18n->set_domain( $this->get_name() );
		$i18n->load_plugin_textdomain();
	}

	/**
	 * Register all of the hooks related to the dashboard functionality
	 * of the plugin.
	 *
	 * @since  0.0.0
	 * @access private
	 */
	private function define_admin_hooks() {
		$components = [
			'admin'             => new Admin\Admin( $this ),
			'options_page'      => new Admin\OptionsPage( $this ),
			'language_switcher' => new Admin\LanguageSwitcher( $this ),
			'redirector'        => new Admin\Redirector( $this ),

			'api_query_vars' => new API\QueryVar( $this ),

			'attachments_set_language' => new Attachments\SetLanguage( $this ),

			'posts_create_translation' => new Posts\CreateTranslation( $this ),
			'posts_language_filter'    => new Posts\LangFilter( $this ),
			'posts_change_language'    => new Posts\ChangeLanguage( $this ),
			'posts_language_metabox'   => new Posts\LangMetaBox( $this ),
			'posts_admin_notices'      => new Posts\AdminNotices( $this ),

			'terms_language_metabox'   => new Terms\LangMetaBox( $this ),
			'terms_create_translation' => new Terms\CreateTranslation( $this ),
			'terms_change_language'    => new Terms\ChangeLanguage( $this ),
			'terms_language_filter'    => new Terms\LangFilter( $this ),
			'terms_admin_notices'      => new Terms\AdminNotices( $this ),

			'router_query_var' => new Router\QueryVar( $this ),
			'router_directory' => new Router\Directory( $this ),

			// TODO: Terms
			// TODO: Disconnect from translations.
			// TODO: Filter the query for attaching an attachment.
		];

		foreach ( $components as $component ) {
			$component->register();
		}
	}
}
