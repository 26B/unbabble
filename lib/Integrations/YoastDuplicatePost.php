<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Error;
use WP_Post;

class YoastDuplicatePost {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		// TODO: Move post lang metabox input here.

		// Use Yoast's duplicate-post plugin to duplicate post before redirect.
		\add_action( 'save_post', [ $this, 'copy_and_redirect' ], PHP_INT_MAX );
	}

	public function copy_and_redirect( int $post_id ) : void {
		$post_type = get_post_type( $post_id );
		if ( $post_type === 'revision' || ! in_array( $post_type, Options::get_allowed_post_types(), true ) ) {
			return;
		}
		if ( ! ( $_POST['ubb_copy_new'] ?? false ) ) {
			return;
		}
		$_POST['ubb_copy_new'] = false; // Used to stop recursion and stop saving in the LangMetaBox.php.

		// Language to set to the new post.
		$lang_create = $_POST['ubb_create'] ?? '';
		if (
			empty( $lang_create )
			|| ! in_array( $lang_create, Options::get()['allowed_languages'] )
			// TODO: check if post_id has this language already
		) {
			// TODO: What else to do when this happens.
			error_log( print_r( 'CreateTranslation - lang create failed', true ) );
			return;
		}

		$post_duplicator = new \Yoast\WP\Duplicate_Post\Post_Duplicator();
		$new_post_id     = $post_duplicator->create_duplicate( get_post( $post_id ), [] );

		if ( $new_post_id instanceof WP_Error ) {
			error_log( print_r( 'CreateTranslation - New post error', true ) );
			// TODO: How to show error.
			return;
		}

		\delete_post_meta( $new_post_id, '_dp_original' );

		$options = array_merge(
			$post_duplicator->get_default_options(),
			[ 'meta_excludelist' => [ 'ubb_source' ] ]
		);
		// TODO: filter to get meta values for changing stuff.
		$this->set_filters_for_meta( $post_id, $lang_create );
		$post_duplicator->copy_post_meta_info( $new_post_id, get_post( $post_id ), $options );

		// Set language in the custom post lang table.
		if ( ! LangInterface::set_post_language( $new_post_id, $lang_create ) ) {
			error_log( print_r( 'CreateTranslation - language set failed', true ) );
			wp_delete_post( $new_post_id, true );
			// TODO: What else to do when this happens.
			return;
		}

		$source_id = LangInterface::get_post_source( $post_id );

		// If first translations. set source on the original post.
		if ( ! $source_id ) {
			$source_id = $post_id;
			if ( ! LangInterface::set_post_source( $post_id, $post_id ) ) {
				error_log( print_r( 'CreateTranslation - set source original failed', true ) );
				wp_delete_post( $new_post_id, true );
				// TODO: What to do when this happens.
				return;
			}
		}

		if ( ! LangInterface::set_post_source( $new_post_id, $source_id ) ) {
			error_log( print_r( 'CreateTranslation - set source on translation failed', true ) );
			wp_delete_post( $new_post_id, true );
			// TODO: What to do when this happens.
			return;
		}

		// Set terms for translation.
		if ( ! $this->set_translation_terms( $new_post_id, $post_id, $lang_create ) ) {
			error_log( print_r( 'CreateTranslation - set translation terms failed', true ) );
			wp_delete_post( $new_post_id, true );
			// TODO: What to do when this happens.
			return;
		}

		wp_safe_redirect( get_edit_post_link( $new_post_id, '&' ) . "&lang={$lang_create}", 302, 'Unbabble' );
		exit;
	}

	private function set_filters_for_meta( int $post_id, string $new_lang ) : void {
		$default_meta = [];
		if ( in_array( 'attachment', Options::get_allowed_post_types(), true ) ) {
			$default_meta['_thumbnail_id'] = 'post';
			$default_meta['acf_image']     = 'post';
		}
		// TODO: Handle wildcards.
		$meta_to_translate = \apply_filters( 'ubb_yoast_duplicate_post_meta_translate_keys', $default_meta, $post_id, $new_lang );

		$self = $this;
		\add_filter( 'add_post_metadata',
			function( $check, $new_post_id, $meta_key, $meta_value, $unique ) use ( $self, $meta_to_translate, $post_id, $new_lang ) {
				if ( ! isset( $meta_to_translate[ $meta_key ] ) ) {
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
			// TODO:
			return $check;
		}

		return $check;
	}

	private function translate_post_meta( $check, $new_post_id, $meta_key, $meta_value, $new_lang ) {
		$new_meta_value = null;

		if ( is_array( $meta_value ) ) {

			// Verify all numeric.
			if ( count( array_filter( $meta_value, 'is_numeric' ) ) === count( $meta_value ) ) {
				return $check;
			}

			$new_meta_value = [];
			foreach ( $meta_value as $meta_post_id ) {
				$post = get_post( $meta_post_id );
				if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, Options::get_allowed_post_types(), true )  ) {
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
			if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, Options::get_allowed_post_types(), true )  ) {
				return $check;
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


	private function insert_meta( $post_id, $meta_key, $meta_value ) {
		global $wpdb;
		return $wpdb->insert(
			$wpdb->postmeta,
			[
				'post_id'    => $post_id,
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
			]
		);
	}

	private function set_translation_terms( int $new_post_id, int $post_id, string $new_lang ) : bool {
		$allowed_taxonomies = Options::get_allowed_taxonomies();
		$og_terms           = wp_get_object_terms( $post_id, get_post_taxonomies( $post_id ) );
		$new_terms          = [];
		foreach ( $og_terms as $term ) {
			if ( ! in_array( $term->taxonomy, $allowed_taxonomies, true ) ) {
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
}
