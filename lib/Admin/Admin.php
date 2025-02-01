<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use TwentySixB\WP\Plugin\Unbabble\Plugin;
use TwentySixB\WP\Plugin\Unbabble\Posts;
use WP_Post;
use WP_Query;
use WP_Screen;

/**
 * General hooks for the back-office.
 *
 * @since 0.0.1
 */
class Admin {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {

		// Admin scripts.
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts'] );

		// Whitelist query vars.
		add_filter( 'query_vars', [ $this, 'whitelist_query_vars' ] );
	}

	/**
	 * Prints the admin notice when Unbabble is idling.
	 *
	 * @since 0.0.9
	 *
	 * @return void
	 */
	public function idle_notice() : void {
		$message = sprintf(
			/* translators: %s: Code html with constant name */
			esc_html( __( 'Unbabble is not running due to the constant %s.', 'unbabble' ) ),
			'<code>UNBABBLE_IDLE</code>'
		);
		printf( '<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>', $message );
	}

	/**
	 * Prints the admin notice when the Unbabble options failed to update via the filter.
	 *
	 * @since 0.0.10
	 *
	 * @return void
	 */
	public function options_update_failed_notice() : void {
		$message = sprintf(
			/* translators: %s: Code html with options name */
			esc_html( __( 'Unbabble was not able to update the %s option value following an options value change via the filter.', 'unbabble' ) ),
			'<code>ubb_options</code>'
		);
		printf( '<div class="notice notice-error"><p><b>Unbabble: </b>%s</p></div>', $message );
	}

	/**
	 * Prints the admin notice when the Unbabble options where updated successfully.
	 *
	 * @since 0.0.11
	 *
	 * @return void
	 */
	public function options_updated() : void {
		?>
		<div class="notice notice-success">
			<p> <?php \_e( 'Unbabble options were updated.', 'unbabble' ); ?> </p>
		</div>
		<?php
	}

	/**
	 * Prints the admin notice when the Unbabble options are invalid in a certain context.
	 *
	 * @since 0.0.11
	 *
	 * @param string $context
	 * @param array $errors
	 * @return void
	 */
	public function invalid_options_notice( string $context, array $errors ) : void {
		?>
		<div class="notice notice-error">
			<p>
				<b>Unbabble - </b>
				<?php printf( __( 'Error while %s:', 'unbabble' ), $context ); ?>
				<br>
				<?php /* TODO: Make collapsable lists */ ?>
				<?php foreach ( $errors as $key => $messages ) : ?>
					<p><code><?php print( $key ) ?></code></p>
					<div style='margin-left:2em;'>
						<?php foreach ( $messages as $message ) : ?>
							<p> <?php print( '- ' . $message ) ?> </p>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueues admin scripts.
	 *
	 * @since 0.0.6
	 *
	 * @return void
	 */
	public function enqueue_scripts() : void {
		if ( ! function_exists( 'wp_get_available_translations' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}

		if ( ! $this->should_enqueue_scripts() ) {
			return;
		}

		$wp_languages = array_merge(
			[ [ 'code' => 'en_US', 'label' => 'English (USA) (en_US)' ] ],
			array_values(
				array_map(
					fn ( $translation ) => [
						'code'  => $translation['language'],
						'label' => sprintf( '%s (%s) ', $translation['english_name'], $translation['language'] ),
					],
					\wp_get_available_translations()
				)
			)
		);
		usort( $wp_languages, fn ($a, $b) => $a['label'] <=> $b['label'] );

		$data = [
			// TODO: change to snake case.
			'api_root'                   => \esc_url_raw( \rest_url( Plugin::API_V1 ) ),
			'admin_url'                  => \remove_query_arg( 'lang', \admin_url() ),
			'current_lang'               => LangInterface::get_current_language(),
			'default_lang'               => LangInterface::get_default_language(),
			'languages'                  => LangInterface::get_languages(),
			'languagesInfo'              => Options::get_languages_info(),
			'options'                    => Options::get(),
			'wpLanguages'                => $wp_languages,
			'wpPostTypes'                => array_values( get_post_types() ),
			'wpTaxonomies'               => array_values( get_taxonomies() ),
			'post_type'                  => $this->get_current_post_type(),
			'settings_manual_changes'    => Options::has_manual_changes(),
			'settings_has_filter'        => Options::has_filter_settings(),
			'settings_read_only'         => defined( 'UBB_SETTINGS_READONLY' ) && UBB_SETTINGS_READONLY,
			'post_metabox_allow_actions' => Posts\LangMetaBox::allow_actions(),
		];

		// Information to show when a post's translation is being created.
		if ( $_GET['ubb_source'] ?? '' ) {
			$source_post = get_post( $_GET['ubb_source'] );
			if ( $source_post instanceof WP_Post ) {
				$data['source_title']    = $source_post->post_title;
				$data['source_edit_url'] = get_edit_post_link( $source_post->ID );
			}
		}

		$assets = include dirname( __FILE__, 3 ) . '/build/index.asset.php';

		\wp_enqueue_script(
			'ubb-admin',
			plugin_dir_url( dirname( __FILE__, 2 ) ) . 'build/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		\wp_localize_script(
			'ubb-admin',
			'UBB',
			$data
		);

		if ( ! self::is_block_editor() ) {
			\wp_enqueue_style( 'wp-components' );
		}
	}

	/**
	 * Whitelists Unbabble's query vars.
	 *
	 * @since 0.0.6
	 *
	 * @param  array $query_vars Whitelist of query vars.
	 * @return array
	 */
	public function whitelist_query_vars( array $query_vars ) : array {
		$query_vars[] = 'lang';
		$query_vars[] = 'ubb_source';
		return $query_vars;
	}

	public static function is_block_editor( WP_Screen $screen = null ) : bool {
		if ( $screen === null && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		if ( $screen instanceof WP_Screen && method_exists( $screen, 'is_block_editor' ) ) {
			return $screen->is_block_editor();
		}

		return false;
	}

	/**
	 * Check if the scripts should be enqueued.
	 *
	 * Stop enqueuing scripts if the current screen is for an untranslatable post type.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	private function should_enqueue_scripts() : bool {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}

		if ( $screen->base === 'toplevel_page_' . 'unbabble_options' ) {
			return true;
		}

		if ( $screen->base !== 'post' ) {
			return false;
		}

		$post_type = get_post_type();
		if ( ! $post_type ) {
			return false;
		}

		return LangInterface::is_post_type_translatable( get_post_type() );
	}

	/**
	 * Returns the current post type.
	 *
	 * @since 0.4.6
	 *
	 * @return string|null
	 */
	private function get_current_post_type() : ?string {
		$post_type = get_post_type();
		if ( is_string( $post_type ) && ! empty( $post_type ) ) {
			return $post_type;
		}

		return null;
	}
}
