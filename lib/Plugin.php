<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use TwentySixB\WP\Plugin\Unbabble\Integrations\AdvancedCustomFieldsPro;
use TwentySixB\WP\Plugin\Unbabble\Integrations\YoastDuplicatePost;
use TwentySixB\WP\Plugin\Unbabble\Integrations;
use TwentySixB\WP\Plugin\Unbabble\CLI;
use WP_CLI;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since 0.0.1
 */
class Plugin {

	// TODO: move to a more appropriate place.
	const API_V1 = 'unbabble/v1';

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  0.0.1
	 * @access protected
	 * @var    string
	 */
	protected $name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  0.0.1
	 * @access protected
	 * @var    string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
	 */
	public function run() {
		$this->set_locale();
		$this->define_plugin_hooks();
		$this->define_api_routes();
		$this->define_commands();
		$this->define_integrations();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since  0.0.1
	 * @return string The name of the plugin.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns the version number of the plugin.
	 *
	 * @since  0.0.1
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
	 * @since  0.0.1
	 * @access private
	 */
	private function set_locale() {
		$i18n = new I18n();
		$i18n->set_domain( $this->get_name() );
		$i18n->load_plugin_textdomain();
	}

	/**
	 * Register all of the hooks related to the plugin's base functionality.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function define_plugin_hooks() {
		$components = [
			'admin'             => Admin\Admin::class,
			'lang_cookie'       => Admin\LangCookie::class,
			'options_page'      => Admin\OptionsPage::class,
			'language_switcher' => Admin\LanguageSwitcher::class,
			'redirector'        => Admin\Redirector::class,
			'customize'         => Admin\Customize::class,

			'api_header'     => API\Header::class,
			'api_query_vars' => API\QueryVar::class,

			'attachments_set_language' => Attachments\SetLanguage::class,
			'attachments_delete_file'  => Attachments\DeleteFile::class,

			'posts_create_translation' => Posts\CreateTranslation::class,
			'posts_link_translation'   => Posts\LinkTranslation::class,
			'posts_language_filter'    => Posts\LangFilter::class,
			'posts_change_language'    => Posts\ChangeLanguage::class,
			'posts_language_metabox'   => Posts\LangMetaBox::class,
			'posts_admin_notices'      => Posts\AdminNotices::class,

			'terms_language_metabox'   => Terms\LangMetaBox::class,
			'terms_create_translation' => Terms\CreateTranslation::class,
			'terms_link_translation'   => Terms\LinkTranslation::class,
			'terms_change_language'    => Terms\ChangeLanguage::class,
			'terms_language_filter'    => Terms\LangFilter::class,
			'terms_admin_notices'      => Terms\AdminNotices::class,
			'terms_new_term'           => Terms\NewTerm::class,

			'router_resolver'  => Router\RoutingResolver::class,
			'router_routing'   => Router\Routing::class,

			'lang_frontend' => Language\Frontend::class,
			'lang_packages' => Language\LanguagePacks::class,

			'options' => Options::class,

			// TODO: Filter the query for attaching an attachment.
		];

		// TODO: add filter to remove components.

		if ( ! Options::should_run_unbabble() ) {
			\add_action( 'admin_notices', [ ( new Admin\Admin() ), 'idle_notice' ], PHP_INT_MAX );
			$components = [ 'options' => Options::class ];
		}

		foreach ( $components as $component ) {
			( new $component() )->register();
		}
	}

	private function define_api_routes() : void {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}

		add_action( 'rest_api_init', function () {
			$namespace = self::API_V1;
			( new API\Actions\HiddenContent( $this, $namespace ) )->register();
			( new API\Gutenberg\Post( $this, $namespace ) )->register();
		} );
	}

	private function define_commands() : void {
		$commands = [
			CLI\Post::class        => 'ubb post',
			CLI\Term::class        => 'ubb term',
			CLI\Options::class     => 'ubb options',
			CLI\Hidden\Post::class => 'ubb post hidden',
			CLI\Hidden\Term::class => 'ubb term hidden',
		];
		\add_action( 'init', function () use ( $commands ) {
			foreach ( $commands as $cli_class => $command_name ) {
				if ( class_exists( 'WP_CLI' ) ) {
					WP_CLI::add_command( $command_name, $cli_class );
				}
			}
		} );
	}

	private function define_integrations() : void {
		$this->define_integration_migrators();
		$integrations = [
			YoastDuplicatePost::class      => 'duplicate-post/duplicate-post.php',
			AdvancedCustomFieldsPro::class => 'advanced-custom-fields-pro/acf.php',
		];
		\add_action( 'admin_init', function() use ( $integrations ) {
			foreach ( $integrations as $integration_class => $plugin_name ) {
				if ( \is_plugin_active( $plugin_name ) ) {
					( new $integration_class() )->register();
				}
			}
		} );
	}

	private function define_integration_migrators() : void {
		$integration_migrators = [
			Integrations\WPML\Migrator::class
		];
		\add_action( 'init', function() use ( $integration_migrators ) {
			foreach ( $integration_migrators as $integration_class ) {
				( new $integration_class() )->register();
			}
		} );
	}
}
