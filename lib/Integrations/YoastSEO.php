<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use Yoast\WP\Lib\ORM;
use Yoast\WP\SEO\Models\Indexable;

class YoastSEO {

	/**
	 * Archive post type.
	 *
	 * @since 0.5.13
	 * @var ?string
	 */
	private $post_type_archive = null;

	/**
	 * Query to match Yoast SEO's query in find_for_post_type_archive.
	 *
	 * @since 0.5.13
	 *
	 * @var ?string
	 */
	private $post_type_archive_indexable_query = null;

	public function register() {
		add_filter( 'ubb_proxy_options', fn ( $options ) => array_merge(
			$options,
			[ 'wpseo_titles' ]
		) );

		// Handle saving multilingual post type archive indexables.
		add_filter( 'wpseo_should_save_indexable', [ $this, 'save_post_type_archive_indexable' ], 10, 2 );

		/**
		 * Handle loading multilingual post type archive indexables.
		 *
		 * On `template_redirect` we check if the current page is a post type archive and if so,
		 * we try to register the filter to load the indexable.
		 */
		add_action( 'template_redirect', function () {
			if ( is_post_type_archive( LangInterface::get_translatable_post_types() ) ) {
				$this->register_load_indexable_filter();
			}
		});
	}

	/**
	 * Handle saving post type archive indexables for each language.
	 *
	 * This is a workaround for Yoast SEO not handling post type archives correctly when using
	 * Unbabble.
	 *
	 * Yoast SEO creates an indexable for each post type archive, but when creating/updating the
	 * indexable, it only checks for the object type and the object sub type, not the permalink.
	 * This leads to a single indexable being created for all archive languages.
	 *
	 * @since 0.5.13
	 * @param bool $intend_to_save
	 * @param Indexable $indexable
	 * @return bool
	 */
	public function save_post_type_archive_indexable( $intend_to_save, $indexable ) {
		global $wpdb;

		/**
		 * We could check if $intend_to_save is false and exit early, but it might be changed
		 * later in the hook process.
		 *
		 * We could move this hook function's priority to the end of the hook process, but then
		 * the indexable would only be changed at the end of the process, and there might be
		 * other hooked functions which depend on the indexable and its values.
		 *
		 * Due to this, its better to change the indexable if needed, even if it will not be
		 * saved.
		 */

		// Ignore if first save.
		if ( empty( $indexable->id ) ) {
			return $intend_to_save;
		}

		// Check if the indexable object_type is a post type archive.
		if ( empty( $indexable->object_type ) || $indexable->object_type !== 'post-type-archive' ) {
			return $intend_to_save;
		}

		// Check if the indexalbe object_sub_type is a translatable post type.
		if ( empty( $indexable->object_sub_type ) || ! LangInterface::is_post_type_translatable( $indexable->object_sub_type ) ) {
			return $intend_to_save;
		}

		// Get the id of the indexable from the yoast indexable table.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}yoast_indexable WHERE permalink = %s AND object_type = %s AND object_sub_type = %s LIMIT 1",
				$indexable->permalink,
				'post-type-archive',
				$indexable->object_sub_type
			)
		);

		// Indexable does not exist, force Yoast SEO to create a new indexable.
		if ( empty( $id ) ) {

			/**
			 * In order for the ORM to insert a new indexable, we need to set its `is_new`
			 * property to true.
			 *
			 * In order to do this, we need to call create() on the ORM, which will
			 * set the `is_new` property to true.
			 *
			 * The class_name is needed to create() and it might not be set, so we need to set
			 * it manually.
			 */
			$indexable->orm->set_class_name( Indexable::class );
			$indexable->orm->create();

			// Set the indexable id to null.
			$indexable->id = null;

		// Indexable is different, set the new id.
		} else if ( $indexable->id !== (int) $id ) {
			$indexable->id = $id;
		}

		// Proceed as normal.
		return $intend_to_save;
	}

	/**
	 * Register the filter to load the post type archive indexable.
	 *
	 * Pre-load the query to match Yoast SEO's query in `find_for_post_type_archive`, so it's not
	 * built every time the 'query' hook is called since it's a very used hook in WordPress.
	 *
	 * Pre-loaded query is kept in a class property and then used in the filter.
	 *
	 * @since 0.5.13
	 *
	 * @return void
	 */
	private function register_load_indexable_filter() : void {
		global $wpdb;

		// Get the archive post type.
		$post_type = $this->get_archive_post_type();
		if ( ! $post_type ) {
			return;
		}

		// Set the class variable to the custom value based on the WPDB prefix.
		$table_name = Indexable::get_table_name( 'Indexable', true );
		$orm        = ORM::for_table( $table_name );
		$orm->where( 'object_type', 'post-type-archive' )
			->where( 'object_sub_type', $post_type )
			->limit( 1 );

		// Build query in wp-content/plugins/wordpress-seo/src/repositories/indexable-repository.php on find_for_post_type_archive method.
		$this->post_type_archive                 = $post_type;
		$this->post_type_archive_indexable_query = $wpdb->prepare(
			$orm->get_sql(),
			'post-type-archive',
			$post_type
		);

		// Add the filter to load the indexable.
		add_filter( 'query', [ $this, 'load_post_type_archive_indexable' ], 10, 2 );
	}

	/**
	 * Alter YoastSEO's find_one post type archive indexable query to include the permalink so
	 * the correct language indexable is returned.
	 *
	 * This is a workaround for YoastSEO not loading indexables using the archive permalink and
	 * there being no other apparent way via hooks to alter the query or the indexable object
	 * after it has been loaded.
	 *
	 * @since 0.5.13
	 *
	 * @param string $query The query to load the indexable.
	 * @return string The altered query.
	 */
	public function load_post_type_archive_indexable( $query ) {
		global $wpdb;

		// Check if the query is for the Yoast indexable table.
		if ( $this->post_type_archive_indexable_query !== $query ) {
			return $query;
		}

		// Add the permalink condition to the query.
		$query = str_replace(
			'WHERE ',
			$wpdb->prepare(
				'WHERE `permalink` = %s AND ',
				get_post_type_archive_link( $this->post_type_archive)
			),
			$query
		);

		// Remove the filter to prevent it from being called again.
		remove_filter( 'query', [ $this, 'load_post_type_archive_indexable' ] );

		return $query;
	}

	/**
	 * Get the post type archive from the query.
	 *
	 * @since 0.5.13
	 *
	 * @return ?string The post type archive.
	 */
	private function get_archive_post_type() : ?string {
		$post_types = get_query_var( 'post_type' );
		if ( ! $post_types ) {
			return null;
		}

		if ( is_array( $post_types ) ) {
			if ( count( $post_types ) > 1 ) {
				return null;
			} else {
				return $post_types[0];
			}
		}

		if ( is_string( $post_types ) ) {
			return $post_types;
		}

		return null;
	}
}
