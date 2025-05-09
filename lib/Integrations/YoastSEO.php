<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use Yoast\WP\SEO\Models\Indexable;

class YoastSEO {
	public function register() {
		add_filter( 'ubb_proxy_options', fn ( $options ) => array_merge(
			$options,
			[ 'wpseo_titles' ]
		) );

		// Handle multilingual post type archive indexables.
		add_filter( 'wpseo_should_save_indexable', [ $this, 'post_type_archive_indexable' ], 10, 2 );
	}

	/**
	 * Handle post type archive indexables for each language.
	 *
	 * This is a workaround for Yoast SEO not handling post type archives correctly when using
	 * Unbabble.
	 *
	 * Yoast SEO creates an indexable for each post type archive, but when creating/updating the
	 * indexable, it only checks for the object type and the object sub type, not the permalink.
	 * This leads to a single indexable being created for all archive languages.
	 *
	 * @since Unreleased
	 * @param bool $intend_to_save
	 * @param Indexable $indexable
	 * @return bool
	 */
	public function post_type_archive_indexable( $intend_to_save, $indexable ) {
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

		// Indexable already exists.
		if ( $id ) {
			// Indexable is correct, do nothing.
			if ( $indexable->id === (int) $id ) {
				return $intend_to_save;
			}

			// Indexable is different, set the new id.
			$indexable->id = $id;
		} else {
			// Indexable does not exist, force Yoast SEO to create a new indexable.

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
		}

		// Proceed as normal.
		return $intend_to_save;
	}
}
