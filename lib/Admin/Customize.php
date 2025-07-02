<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use TwentySixB\WP\Plugin\Unbabble\Terms;
use WP_Query;

/**
 * Hooks for the customize part of Wordpress, menus and homepages.
 *
 * @since 0.0.3
 */
class Customize {

	/**
	 * Register hooks.
	 *
	 * @since 0.4.5 Add check for translatable nav_menu.
	 * @since 0.0.3
	 */
	public function register() {

		// Fix bad customize action URL that contains bad query args (e.g. `?lang=en_US?theme=...`).
		add_filter( 'wp_prepare_themes_for_js', [ $this, 'fix_customize_action_url' ] );

		// Make widget blocks translatable.
		add_filter( 'ubb_proxy_options', fn ( $options ) => array_merge( $options, [ 'widget_block' ] ) );

		// Only register customize hooks if nav_menu is translatable.
		if ( ! LangInterface::is_taxonomy_translatable( 'nav_menu' ) ) {
			return;
		}

		// FIXME: Lang cookie usage in customize.php can lead to lang desync.

		// Filter dropdown pages.
		add_filter( 'wp_dropdown_pages', [ $this, 'wp_dropdown_pages' ], 10, 2 );

		// Options saving and loading.
		$theme   = get_option( 'stylesheet' );
		add_filter( 'ubb_proxy_options', fn ( $options ) => array_merge(
			$options,
			[ 'page_on_front','show_on_front','page_for_posts', "theme_mods_$theme" ]
		) );

		// Set menu language when created.
		add_action( 'create_nav_menu', [ $this, 'set_menu_lang' ], 10, 2 );

		// Add script for language metabox for nav menu.
		add_action( 'init', function () {
			global $pagenow;
			if ( $pagenow === 'nav-menus.php' && ( $_GET['action'] ?? '' ) !== 'locations' ) {
				add_action( 'admin_footer', array( $this, 'nav_menu_lang_metaboxes' ), 10 );
			}
		} );
	}

	/**
	 * Fix the customize action URL that contains bad query args (e.g. `?lang=en_US?theme=...`).
	 *
	 * TODO: Remove after WordPress 6.9. This should be fixed in that version.
	 *
	 * @since 0.5.15
	 *
	 * @param array $prepared_themes
	 * @return array
	 */
	public function fix_customize_action_url( array $prepared_themes ) : array {
		foreach ( $prepared_themes as $slug => $theme_data ) {
			// If the theme does not have a customize action, skip it.
			if ( empty( $theme_data['actions']['customize'] ) ) {
				continue;
			}

			// Decode action url to match correctly.
			$action_url = urldecode( $theme_data['actions']['customize'] );

			// Get the base URL for the customize action.
			$base_url = admin_url( 'customize.php' );

			// If the action URL does not start with the base URL, skip it.
			if ( ! str_starts_with( $action_url, $base_url ) ) {
				continue;
			}

			// If the base URL does not contain the problematic `?lang=` query arg, skip it.
			if ( ! str_contains( $base_url, '?lang=' ) ) {
				continue;
			}

			// Split the action URL into parts to extract the query args.
			$parts = explode( $base_url, $action_url, 2 );
			if ( count( $parts ) < 2 ) {
				continue;
			}

			// Get the query args from the second part.
			$args = $parts[1];

			// If the query args do not start with a `?`, skip it.
			if ( ! str_starts_with( $args, '?' ) ) {
				continue;
			}

			// Remove the '?' from the '?theme=...' part.
			$args = substr( $args, 1 );

			// Rebuild url correctly and set to the action.
			$prepared_themes[ $slug ]['actions']['customize'] = $base_url . '&' . urlencode( $args );
		}

		return $prepared_themes;
	}

	/**
	 * Add lang metaboxes to nav menu edit.
	 *
	 * @since 0.5.7 Fetch menu id from $_REQUEST only if the action is not delete.
	 * @since 0.4.5 Add hidden input `ubb_source` for new menu's when linking.
	 * @since 0.4.2 Added surrounding <table> and <tbody> to the term meta box. Changed ob_get_flush to ob_get_clean.
	 * @since 0.0.3
	 *
	 * @return void
	 */
	public function nav_menu_lang_metaboxes() : void {
		if ( isset( $_REQUEST['menu'] ) && ( $_REQUEST['action'] ?? '' ) !== 'delete') {
			$menu_id = $_REQUEST['menu'];
		} else {
			$menu_id = get_user_option( 'nav_menu_recently_edited' );
		}

		// If menu id is empty and ubb_source is not set, return.
		if ( empty( $_GET['ubb_source'] ?? false ) && empty( $menu_id ) ) {
			return;
		}

		// Add hidden ubb_source for new menu's.
		if ( empty( $menu_id ) ) {
			$html = sprintf(
				'<input type="hidden" id="ubb_source" name="ubb_source" value="%s">',
				esc_sql( $_GET['ubb_source'] )
			);

		// Otherwise add normal language metabox.
		} else {
			// TODO: Using term meta box has its problems, refactor into a better system of metaboxing.
			ob_start();
			( new Terms\LangMetaBox() )->edit_term_language_metabox( get_term( $menu_id ) );
			$term_meta_box = ob_get_clean();

			$html  = '<div class="ubb-menu-settings">';
			$html .= '<h3>' . __( 'Language' ) . '</h3>';
			$html .= '<table><tbody>';
			$html .= $term_meta_box;
			$html .= '</tbody></table>';
			$html .= '</div>';

			$html = str_replace( [ "\t", "\n" ], '', $html );
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready(function () {
				addLoadEvent(function () {
					jQuery('#update-nav-menu').find('.menu-settings:first').after('<?php echo addslashes_gpc( $html ); ?>');
				})
			});
		</script>
		<?php
	}

	/**
	 * Set a nav menu's language.
	 *
	 * @since 0.0.3
	 *
	 * @param int $term_id
	 * @return void
	 */
	public function set_menu_lang( int $term_id ) : void {
		if ( ! LangInterface::is_taxonomy_translatable( 'nav_menu' ) ) {
			return;
		}

		$curr_lang = LangInterface::get_current_language();
		LangInterface::set_term_language( $term_id, $curr_lang );
	}

	/**
	 * Filter dropdown pages by language.
	 *
	 * @since 0.0.3
	 *
	 * @param string $output
	 * @param array $parsed_args
	 * @return string
	 */
	public function wp_dropdown_pages( string $output, array $parsed_args ) : string {
		if (
			! in_array(
				$parsed_args['name'],
				[
					'page_on_front',
					'page_for_posts',
					'_customize-dropdown-pages-page_on_front',
					'_customize-dropdown-pages-page_for_posts'
				]
			) || ( $parsed_args['__ubb_filtering'] ?? false )
			|| ! LangInterface::is_post_type_translatable( 'page' )
		) {
			return $output;
		}


		// Get pages filtered by current language.
		$query = new WP_Query(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1
			]
		);
		$parsed_args['include']         = $query->posts ?? [];
		$parsed_args['__ubb_filtering'] = true;
		return wp_dropdown_pages( $parsed_args );
	}
}
