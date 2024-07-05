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
	 * @since 0.0.3
	 */
	public function register() {
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
	 * Add lang metaboxes to nav menu edit.
	 *
	 * @since 0.0.3
	 *
	 * @return void
	 */
	public function nav_menu_lang_metaboxes() : void {
		if ( isset( $_REQUEST['menu'] ) ) {
			$menu_id = $_REQUEST['menu'];
		} else {
			$menu_id = get_user_option( 'nav_menu_recently_edited' );
		}

		if ( empty( $menu_id ) ) {
			return;
		}

		// TODO: Using term meta box has its problems, refactor into a better system of metaboxing.
		ob_start();
		( new Terms\LangMetaBox() )->edit_term_language_metabox( get_term( $menu_id ) );
		$term_meta_box = ob_get_flush();

		$html  = '<div class="ubb-menu-settings">';
		$html .= '<h3>' . __( 'Language' ) . '</h3>';
		$html .= $term_meta_box;
		$html .= '</div>';

		$html = str_replace( [ "\t", "\n" ], '', $html );
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
		$parsed_args['include']         = $query->get_posts();
		$parsed_args['__ubb_filtering'] = true;
		return wp_dropdown_pages( $parsed_args );
	}
}
