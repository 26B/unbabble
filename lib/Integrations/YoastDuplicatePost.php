<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Error;
use WP_Post;
use WP_Term;

class YoastDuplicatePost {
	public function register() {

		// Remove ubb_source from rewrite republish copies.
		\add_filter( 'duplicate_post_excludelist_filter', [ $this, 'exclude_meta_keys' ] );

		// Set language of rewrite republish copy to be the original's language.
		\add_action( 'add_post_meta', [ $this, 'set_language_on_copy' ], 10, 3 );

		// Use Yoast's duplicate-post plugin to duplicate post before redirect.
		\add_action( 'save_post', [ $this, 'copy_and_redirect' ], PHP_INT_MAX - 10 );
		\add_action( 'edit_attachment', [ $this, 'copy_and_redirect' ], PHP_INT_MAX - 10 );

		// Don't allow post metabox actions on rewrite republish copies.
		\add_action( 'ubb_allow_metabox_actions', [ $this, 'allow_metabox_actions' ] );

		// Skip WPML to Unbabble migration of ubb_source to meta for rewrite republish copies.
		\add_filter( 'ubb_wpml_migrate_skip_source', [ $this, 'skip_wpml_migrate_source' ], 10, 3 );

		// Hide rewrite republish posts from being linked in other posts.
		\add_filter( 'ubb_possible_links_filter_sql', [ $this, 'hide_rewrite_republish_from_linking' ], 10, 2 );

		// Remove ubb_source from rewrite republish copies when saved.
		\add_action( 'wp_insert_post', [ $this, 'clean_republish_copies' ], 10, 2 );
	}

	public function exclude_meta_keys( array $meta_keys ) : array {
		return array_merge( $meta_keys, [ 'ubb_source' ] );
	}

	public function set_language_on_copy( $object_id, $meta_key, $_meta_value ) : void {
		if ( $meta_key !== '_dp_original' ) {
			return;
		}

		$post = get_post( $object_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$original = get_post( $_meta_value );

		if ( ! $original instanceof WP_Post ) {
			return;
		}

		$original_lang = LangInterface::get_post_language( $original->ID );

		if ( empty( $original_lang ) ) {
			return;
		}

		LangInterface::set_post_language( $post->ID, $original_lang, true);
	}

	public function copy_and_redirect( int $post_id, bool $redirect = true ) {
		$post_type = get_post_type( $post_id );

		// Sometimes ACF saves attachments (uses `save_post`) before the actual post, so we need to account for it.
		if ( ( $_POST['post_type'] ?? '' ) !== $post_type || $post_id !== (int) $_POST['post_ID'] ) {
			return;
		}

		if ( ! ( $_POST['ubb_copy_new'] ?? false ) ) {
			return;
		}
		$_POST['ubb_copy_new'] = false; // Used to stop recursion and stop saving in the LangMetaBox.php.

		// Language to set to the new post.
		$lang_create = $_POST['ubb_create'] ?? '';

		$new_post_id = $this->copy( $post_id, $lang_create );

		if ( $new_post_id === null ) {
			return;
		}

		if ( $redirect ) {
			wp_safe_redirect( get_edit_post_link( $new_post_id, '&' ) . "&lang={$lang_create}", 302, 'Unbabble' );
			exit;
		}

		return $new_post_id;
	}

	public function copy( int $post_id, string $target_language ) : ?int {
		$post_type = get_post_type( $post_id );

		if ( $post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return null;
		}

		// Language to set to the new post.
		if (
			empty( $target_language )
			|| ! LangInterface::is_language_allowed( $target_language )
			// TODO: check if post_id has this language already
		) {
			// TODO: What else to do when this happens.
			error_log( print_r( 'CreateTranslation - lang create failed', true ) );
			return null;
		}

		// Check if post already has translation for that language.
		if ( ! empty( LangInterface::get_post_translation( $post_id, $target_language ) ) ) {
			return null;
		}

		// Attachment language is set on 'add_attachment' via get_current_language.
		if ( $post_type === 'attachment' ) {
			$curr_lang = LangInterface::get_current_language();
			LangInterface::set_current_language( $target_language );
			$fix_attachment_title = function ( array $new_post, WP_Post $post ) {
				$new_post['post_title'] = $post->post_title;
				return $new_post;
			};
			\add_filter( 'duplicate_post_new_post', $fix_attachment_title, 10, 2 );
		}

		$post_duplicator = new \Yoast\WP\Duplicate_Post\Post_Duplicator();
		$new_post_id     = $post_duplicator->create_duplicate( get_post( $post_id ), [] );

		// Set language back to the correct current one.
		if ( $post_type === 'attachment' ) {
			LangInterface::set_current_language( $curr_lang );
			\remove_filter( 'duplicate_post_new_post', $fix_attachment_title );
		}

		if ( $new_post_id instanceof WP_Error ) {
			error_log( print_r( 'CreateTranslation - New post error', true ) );
			// TODO: How to show error.
			return null;
		}

		\delete_post_meta( $new_post_id, '_dp_original' );

		$options = array_merge(
			$post_duplicator->get_default_options(),
			[ 'meta_excludelist' => [ 'ubb_source' ] ]
		);

		// Set filters to translate meta values before they're saved if needed.
		$this->set_filters_for_meta( $post_id, $target_language );
		$this->filter_post_meta = true;
		$post_duplicator->copy_post_meta_info( $new_post_id, get_post( $post_id ), $options );
		$this->filter_post_meta = false;

		// Set language in the custom post lang table.
		if ( ! LangInterface::set_post_language( $new_post_id, $target_language, true ) ) {
			error_log( print_r( 'CreateTranslation - language set failed', true ) );
			wp_delete_post( $new_post_id, true );
			// TODO: What else to do when this happens.
			return null;
		}

		$source_id = LangInterface::get_post_source( $post_id );

		// If first translations. set source on the original post.
		if ( ! $source_id ) {
			$source_id = LangInterface::get_new_post_source_id();
			if ( ! LangInterface::set_post_source( $post_id, $source_id ) ) {
				error_log( print_r( 'CreateTranslation - set source original failed', true ) );
				wp_delete_post( $new_post_id, true );
				// TODO: What to do when this happens.
				return null;
			}
		}

		if ( ! LangInterface::set_post_source( $new_post_id, $source_id ) ) {
			error_log( print_r( 'CreateTranslation - set source on translation failed', true ) );
			wp_delete_post( $new_post_id, true );
			// TODO: What to do when this happens.
			return null;
		}

		// Set terms for translation.
		if ( ! $this->set_translation_terms( $new_post_id, $post_id, $target_language ) ) {
			error_log( print_r( 'CreateTranslation - set translation terms failed', true ) );
			wp_delete_post( $new_post_id, true );
			// TODO: What to do when this happens.
			return null;
		}

		return $new_post_id;
	}

	private function set_filters_for_meta( int $post_id, string $new_lang ) : void {
		$default_meta = [];
		if ( LangInterface::is_post_type_translatable( 'attachment' ) ) {
			$default_meta['_thumbnail_id'] = 'post';
		}

		// TODO: Handle wildcards/regex.
		// Similar to `ubb_change_language_post_meta_translate_keys` in lib\LangInterface.php.
		$meta_to_translate = \apply_filters( 'ubb_yoast_duplicate_post_meta_translate_keys', $default_meta, $post_id, $new_lang );

		$self = $this;
		\add_filter( 'add_post_metadata',
			function( $check, $new_post_id, $meta_key, $meta_value, $unique ) use ( $self, $meta_to_translate, $post_id, $new_lang ) {
				if ( ! isset( $meta_to_translate[ $meta_key ] ) ) {
					return $check;
				}

				// Prevent filter from messing with other post meta saves.
				if ( ! $self->filter_post_meta ) {
					return $check;
				}

				return $self->translate_meta_value( $check, $new_post_id, $meta_key, $meta_value, $unique, $meta_to_translate, $post_id, $new_lang );
			},
			10,
			5
		);
	}

	public function translate_meta_value( $check, $new_post_id, $meta_key, $meta_value, $unique, $meta_to_translate, $post_id, $new_lang ) {

		// TODO: Filter docs. Might need more arguments.
		// Similar to `ubb_change_language_post_meta_translate_value` in lib\LangInterface.php.
		$return = \apply_filters( 'ubb_yoast_duplicate_post_meta_translate_value', null, $meta_value, $new_post_id, $post_id, $new_lang );
		if ( $return !== null ) {
			$result = $this->insert_meta( $new_post_id, $meta_key, $return );
			if ( $result === false ) {
				// TODO: Failure state.
				return $check;
			}
			return $return;
		}

		if ( $meta_to_translate[ $meta_key ] === 'post' ) {
			return $this->translate_post_meta( $check, $new_post_id, $meta_key, $meta_value, $new_lang );
		}

		if ( $meta_to_translate[ $meta_key ] === 'term' ) {
			return $this->translate_term_meta( $check, $new_post_id, $meta_key, $meta_value, $new_lang );
		}

		return $check;
	}

	private function translate_post_meta( $check, $new_post_id, $meta_key, $meta_value, $new_lang ) {
		$new_meta_value = null;

		if ( is_array( $meta_value ) ) {

			// Verify all numeric.
			if ( count( array_filter( $meta_value, 'is_numeric' ) ) !== count( $meta_value ) ) {
				return $check;
			}

			$new_meta_value = [];
			foreach ( $meta_value as $meta_post_id ) {
				$post = get_post( $meta_post_id );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				// Keep same ID for non translatable post types.
				if ( ! LangInterface::is_post_type_translatable( $post->post_type ) ) {
					$new_meta_value[] = $meta_post_id;
					continue;
				}

				$meta_post_translation = LangInterface::get_post_translation( $meta_post_id, $new_lang );
				if ( $meta_post_translation === null ) {
					continue;
				}
				$new_meta_value[] = $meta_post_translation;
			}
		}

		if ( is_numeric( $meta_value ) ) {
			$post = get_post( $meta_value );
			if ( ! $post instanceof WP_Post ) {
				return $check;
			}

			// Keep same ID for non translatable post types.
			if ( ! LangInterface::is_post_type_translatable( $post->post_type ) ) {
				return $meta_value;
			}

			$new_meta_value = LangInterface::get_post_translation( $meta_value, $new_lang );
			if ( $new_meta_value === null ) {
				return ''; // Save nothing to meta_value.
			}
		}

		if ( $new_meta_value === null ) {
			return $check;
		}

		$result = $this->insert_meta( $new_post_id, $meta_key, $new_meta_value );
		if ( $result === false ) {
			// TODO: Failure state.
			return $check;
		}
		return $new_meta_value;
	}

	// Copy of translate_post_meta
	private function translate_term_meta( $check, $new_post_id, $meta_key, $meta_value, $new_lang ) {
		$new_meta_value = null;

		if ( is_array( $meta_value ) ) {

			// Verify all numeric.
			if ( count( array_filter( $meta_value, 'is_numeric' ) ) !== count( $meta_value ) ) {
				return $check;
			}

			$new_meta_value = [];
			foreach ( $meta_value as $meta_term_id ) {
				$term = \get_term( $meta_term_id );
				if ( ! $term instanceof WP_Term ) {
					continue;
				}

				// Keep same ID for non translatable taxonomies.
				if ( ! LangInterface::is_taxonomy_translatable( $term->taxonomy ) ) {
					$new_meta_value[] = $meta_term_id;
					continue;
				}

				$meta_term_translation = LangInterface::get_term_translation( $meta_term_id, $new_lang );
				if ( $meta_term_translation === null ) {
					continue;
				}
				$new_meta_value[] = $meta_term_translation;
			}
		}

		if ( is_numeric( $meta_value ) ) {
			$term = \get_term( $meta_value );
			if ( ! $term instanceof WP_Term ) {
				return $check;
			}

			// Keep same ID for non translatable taxonomies.
			if ( ! LangInterface::is_taxonomy_translatable( $term->taxonomy ) ) {
				return $meta_value;
			}

			$new_meta_value = LangInterface::get_term_translation( $meta_value, $new_lang );
			if ( $new_meta_value === null ) {
				return ''; // Save nothing to meta_value.
			}
		}

		if ( $new_meta_value === null ) {
			return $check;
		}

		$result = $this->insert_meta( $new_post_id, $meta_key, $new_meta_value );
		if ( $result === false ) {
			// TODO: Failure state.
			return $check;
		}
		return $new_meta_value;
	}

	private function insert_meta( $post_id, $meta_key, $meta_value ) {
		global $wpdb;
		return $wpdb->insert(
			$wpdb->postmeta,
			[
				'post_id'    => $post_id,
				'meta_key'   => $meta_key,
				'meta_value' => \maybe_serialize( $meta_value ),
			]
		);
	}

	private function set_translation_terms( int $new_post_id, int $post_id, string $new_lang ) : bool {
		$og_terms  = wp_get_object_terms( $post_id, get_post_taxonomies( $post_id ) );
		$new_terms = [];
		foreach ( $og_terms as $term ) {
			if ( ! LangInterface::is_taxonomy_translatable( $term->taxonomy ) ) {
				$new_terms[ $term->taxonomy ][] = $term->term_id;
				continue;
			}
			$term_translation = LangInterface::get_term_translation( $term->term_id, $new_lang );
			if ( $term_translation === null ) {
				continue;
			}
			$new_terms[ $term->taxonomy ][] = $term_translation;
		}

		add_filter( 'ubb_use_term_lang_filter', '__return_false' );
		foreach ( $new_terms as $taxonomy => $term_ids ) {
			$return = wp_set_post_terms( $new_post_id, $term_ids, $taxonomy );
			if ( is_wp_error( $return ) || $return === false ) {
				// TODO: What to do here?
				return false;
			}
		}
		remove_filter( 'ubb_use_term_lang_filter', '__return_false' );

		return true;
	}

	/**
	 * Don't allow metabox actions when the post is a rewrite republish copy.
	 *
	 * @since 0.5.0
	 *
	 * @param bool $allow
	 * @return bool
	 */
	public function allow_metabox_actions( bool $allow ) : bool {
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen ) {
			return $allow;
		}

		if ( $screen->base !== 'post' ) {
			return $allow;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return $allow;
		}

		$dp_meta = get_post_meta( $post->ID, '_dp_is_rewrite_republish_copy', true );
		if ( $dp_meta === '1' ) {
			return false;
		}

		return $allow;
	}

	/**
	 * Skip WPML to Unbabble migration of ubb_source to meta for rewrite republish copies.
	 *
	 * @since 0.5.0
	 *
	 * @param bool   $skip
	 * @param array  $row
	 * @param string $type
	 * @return bool
	 */
	public function skip_wpml_migrate_source( bool $skip, array $row, string $type ) : bool {

		// If already skipped, ignored.
		if ( $skip ) {
			return $skip;
		}

		// If not a post, ignore.
		if ( $type !== 'post' ) {
			return $skip;
		}

		$post_id = $row['object_id'] ?? 0;
		if ( ! $post_id ) {
			return $skip;
		}

		// If not a rewrite republish copy, ignore.
		$dp_meta = get_post_meta( $post_id, '_dp_is_rewrite_republish_copy', true );
		if ( $dp_meta === '1' ) {
			return true;
		}

		return $skip;
	}

	/**
	 * Hide rewrite republish posts from being linked in other posts.
	 *
	 * @since 0.5.3
	 *
	 * @param string $sql
	 * @return string
	 */
	public function hide_rewrite_republish_from_linking( string $sql ) : string {
		global $wpdb;

		$sql .= " AND P.ID NOT IN (
			SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_dp_is_rewrite_republish_copy'
			AND meta_value = '1'
		)";

		return $sql;
	}

	/**
	 * Remove ubb_source from rewrite republish copies when saved.
	 *
	 * @since 0.5.3
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function clean_republish_copies( $post_id, $post ) : void {
		$permissions_helper = new \Yoast\WP\Duplicate_Post\Permissions_Helper();
		if (
			! $post instanceof WP_Post
			|| ! $permissions_helper->is_rewrite_and_republish_copy( $post )
		) {
			return;
		}

		$meta_keys = $this->exclude_meta_keys( [] );
		foreach ( $meta_keys as $meta_key ) {
			\delete_post_meta( $post_id, $meta_key );
		}
	}
}
