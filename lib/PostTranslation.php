<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use WP_Post;

/**
 * @since 0.0.0
 */
class PostTranslation {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		// Gutenberg.
		\add_action( 'current_screen', [ $this, 'handle_post_edit' ] );

		foreach ( Options::get_allowed_post_types() as $post_type ) {
			// TODO: Attachments are bit different.
			\add_filter( "rest_pre_insert_{$post_type}", [ $this, 'rest_pre_insert' ], 10, 2 );
		}

		// Redirect on create save.
		\add_action( 'save_post', [ $this, 'redirect_on_create' ], PHP_INT_MAX, 3 );

		// Apply translation to post.
		\add_action( 'the_post', [ $this, 'apply_translation' ] );
		\add_action( 'posts_selection', [ $this, 'apply_global_translation' ] );
	}

	// Classic Editor.
	public function apply_global_translation() : void {
		global $post;
		if ( ! $post instanceof \WP_Post || property_exists( $post, 'ubb_lang' ) ) {
			return;
		}
		$this->apply_translation( $post );
	}

	// General translation.
	public function apply_translation( \WP_Post &$post_object ) {
		if ( property_exists( $post_object, 'ubb_lang' ) ) {
			return;
		}
		$options = Options::get();
		if ( ! in_array( $post_object->post_type, $options['post_types'], true ) ) {
			return;
		}

		// TODO: function to get lang.
		$lang = $_GET['lang'] ?? $_COOKIE['ubb_lang'] ?? $options['default_language'];

		// TODO: Check if post's default language is lang. If so then exit.

		// modify post object here
		$this->apply_post_translation( $post_object, $lang, false, true );
	}

	public function redirect_on_create( $post_id, $post, bool $update ) : void {

		// Initial post save.
		if ( ! $update ) {
			return;
		}
		if ( ! isset( $_GET['ubb_create'] ) ) {
			return;
		}

		$query_args = $_GET;
		unset( $query_args['ubb_create'], $query_args['ubb_copy'] );
		$redirect = \add_query_arg( $query_args, $_SERVER['PHP_SELF'] );
		\wp_safe_redirect( $redirect, '302', 'WordPress - Unbabble' );
	}

	public function handle_post_edit( \WP_Screen $screen ) : void {
		// TODO: Handle terms.
		if ( ! is_admin() || $screen->base !== 'post' || ! isset( $_GET['post'] ) ) {
			return;
		}

		$options = Options::get();

		if ( ! in_array( $screen->post_type, $options['post_types'], true ) ) {
			return;
		}

		$lang           = $_GET['lang'] ?? $_COOKIE['ubb_lang'] ?? $options['default_language'];
		$post_id        = $_GET['post'];
		$lang_create    = isset( $_GET['ubb_create'] );
		$copy_from_lang = $_GET['ubb_copy'] ?? null;

		if ( ! empty( $copy_from_lang ) ) {
			// TODO: check if it exists for the current post.
		}

		$post_type = get_post_type( $post_id );
		\add_filter(
			"rest_prepare_{$post_type}",
			function ( \WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request ) use ( $lang, $lang_create, $copy_from_lang ) {
				return $this->rest_prepare_post( $response, $post, $request, $lang, [ 'create' => $lang_create, 'copy_from' => $copy_from_lang ] );
			},
			10,
			3
		);
	}

	public function rest_pre_insert( object $prepared_post, \WP_REST_Request $request ) : object {
		$post_id       = $prepared_post->ID;
		$options       = Options::get();
		$lang          = $_COOKIE['ubb_lang'] ?? $options['default_language'];                //TODO: how to get from request.
		$original_post = \get_post( $post_id );
		$previous_post = $this->apply_post_translation( $original_post, $lang, true );
		$post_langs    = \get_post_meta( $post_id, 'ubb_lang' );                              //TODO: find out if this lang is the original.
		$is_original   = empty( $post_langs ) ? true : current( $post_langs ) === $lang;
		array_shift( $post_langs ); // Keep the rest of the languages.

		// TODO: Add ubb_lang meta when create is on url and redirect to link without create/copy args.
		//       Right now being added on save via the metabox.
		$meta_data_to_save   = [];
		$meta_data_to_delete = [];

		foreach ( get_object_vars( $prepared_post ) as $property => $value ) {
			if ( in_array( $property, [ 'ID', 'post_type', 'page_template' ], true ) ) {
				continue;
			}

			// If not original, then we need to unset the property so it doesn't overwrite the original's.
			if ( ! $is_original ) {

				// Delete unnecessary meta since value is the same as original.
				if ( $value === $original_post->$property ) {
					$meta_data_to_delete[] = "{$property}_ubb_{$lang}";
					unset( $prepared_post->$property );
					continue;
				}

				// TODO: what if property is not set.
				if ( $previous_post->$property === $value ) {
					unset( $prepared_post->$property );
					continue;
				}

				if ( $previous_post->$property !== $value ) {
					$meta_data_to_save[ "{$property}_ubb_{$lang}" ] = $value;
					unset( $prepared_post->$property );
					continue;
				}
			}

			if ( $is_original ) {
				if ( $previous_post->$property === $value ) {
					continue;
				}
				if ( ! isset( $all_meta ) ) {
					$all_meta = \get_post_meta( $post_id );
				}
				/**
				 * When original changes, we need to add ubb metas for translations with the previous
				 * value, if they don't already have a value for that property.
				 */
				foreach ( $post_langs as $other_lang ) {
					if ( ! isset( $all_meta[ "{$property}_ubb_{$other_lang}" ] ) ) {
						$meta_data_to_save[ "{$property}_ubb_{$other_lang}" ] = $previous_post->$property;
					}
				}
				/**
				 * Similarly, we need to remove ubb metas for translations if their value is the
				 * same as the new value.
				 */
				foreach ( $post_langs as $other_lang ) {
					if (
						isset( $all_meta[ "{$property}_ubb_{$other_lang}" ] )
						&& current( $all_meta[ "{$property}_ubb_{$other_lang}" ] ) === $value
					) {
						$meta_data_to_delete[] = "{$property}_ubb_{$other_lang}";
					}
				}
			}
		}

		// TODO: we should save this and then only update after the insert is successful.
		// Save meta data.
		foreach ( $meta_data_to_save as $meta_key => $meta_value ) {
			\update_metadata( 'post', $post_id, $meta_key, $meta_value );
		}

		// Delete meta data.
		foreach ( $meta_data_to_delete as $meta_key ) {
			\delete_post_meta( $post_id, $meta_key );
		}

		// Need to handle the refetch/response that happens when you save, to maintain the same translated content.
		$post_type = $prepared_post->post_type ?? get_post( $post_id )->post_type;
		\add_filter(
			"rest_prepare_{$post_type}",
			function ( \WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request ) use ( $lang ) {
				return $this->rest_prepare_post( $response, $post, $request, $lang, [ 'create' => '', 'copy_from' => '' ] );
			},
			10,
			3
		);

		return $prepared_post;
	}

	// This solution forces prepare_item_for_response, once to call this hook and then again to correct the response with new data.
	public function rest_prepare_post( \WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request, string $lang, array $args ) : \WP_REST_Response {
		if ( property_exists( $post, 'ubb_lang' ) ) {
			return $response;
		}

		// Lang in the request that takes precedence.
		if ( isset( $request['lang'] ) ) {
			$lang = $request['lang'];
		}

		if ( $args['create'] ) {
			//TODO:
			if ( empty( $args['copy_from'] ) ) {
				$post = $this->apply_post_empty_content( $post );
			} else {
				$post = $this->apply_post_translation( $post, $args['copy_from'], true );
			}
		} else {
			$post = $this->apply_post_translation( $post, $lang, true );
		}

		$post->ubb_lang = $lang;

		// TODO: If post is not changed, we should not call prepare_item_for_response again.
		return ( new \WP_REST_Posts_Controller( $post->post_type ) )->prepare_item_for_response( $post, $request );
	}

	public function apply_post_translation( \WP_Post $post, string $lang, $skip_verify_meta = false, bool $apply_in_place = false ) : \WP_Post {
		global $wpdb;

		// Verify if lang is allowed.
		if ( ! in_array( $lang, Options::get()['allowed_languages'], true ) ) {
			return $post;
		}

		// Check if post has this language translation.
		if ( ! $skip_verify_meta && ! in_array( $lang, \get_post_meta( $post->ID, 'ubb_lang', false ), true ) ) {
			return $post;
		}

		$post_data_fields = [
			"post_author_ubb_{$lang}"           => 'post_author',
			"post_date_ubb_{$lang}"             => 'post_date',
			"post_date_gmt_ubb_{$lang}"         => 'post_date_gmt',
			"post_content_ubb_{$lang}"          => 'post_content',
			"post_title_ubb_{$lang}"            => 'post_title',
			"post_excerpt_ubb_{$lang}"          => 'post_excerpt',
			"post_status_ubb_{$lang}"           => 'post_status',
			"comment_status_ubb_{$lang}"        => 'comment_status',
			"ping_status_ubb_{$lang}"           => 'ping_status',
			"post_password_ubb_{$lang}"         => 'post_password',
			"post_name_ubb_{$lang}"             => 'post_name',
			"to_ping_ubb_{$lang}"               => 'to_ping',
			"pinged_ubb_{$lang}"                => 'pinged',
			"post_modified_ubb_{$lang}"         => 'post_modified',
			"post_modified_gmt_ubb_{$lang}"     => 'post_modified_gmt',
			"post_content_filtered_ubb_{$lang}" => 'post_content_filtered',
			"post_parent_ubb_{$lang}"           => 'post_parent',
			"guid_ubb_{$lang}"                  => 'guid',
			"menu_order_ubb_{$lang}"            => 'menu_order',
			"post_type_ubb_{$lang}"             => 'post_type',
			"post_mime_type_ubb_{$lang}"        => 'post_mime_type',
			"comment_count_ubb_{$lang}"         => 'comment_count',
		];

		$post_data_fields_in = implode( "','", array_keys( $post_data_fields ) );

		// Apply translations where matters.
		$translation_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value  FROM {$wpdb->postmeta} WHERE meta_key IN ('{$post_data_fields_in}') and post_id = %s",
				$post->ID
			),
			OBJECT_K
		);

		$new_post = $post;
		if ( ! $apply_in_place ) {
			// Don't want to alter received object.
			$new_post = clone $post;
		}

		foreach ( $translation_data as $meta_key => $meta_data ) {
			$property            = $post_data_fields[ $meta_key ];
			$new_post->$property = $meta_data->meta_value;
		}

		// Add ubb_lang property to know if post is already translated.
		$new_post->ubb_lang = $lang;

		return $new_post;
	}

	public function apply_post_empty_content( \WP_Post $post ) : \WP_Post {
		$post_data_fields = [
			// 'post_author'           => '',
			// 'post_date'             => '', //TODO: current date
			// 'post_date_gmt'         => '',
			'post_content'          => '',
			'post_title'            => '',
			'post_excerpt'          => '',
			'post_status'           => 'draft',
			// 'comment_status'        => '',
			// 'ping_status'           => '',
			// 'post_password'         => '',
			'post_name'             => '',
			// 'to_ping'               => '',
			// 'pinged'                => '',
			// 'post_modified'         => '', //TODO: dates
			// 'post_modified_gmt'     => '',
			// 'post_content_filtered' => '',
			// 'post_parent'           => '',
			// 'guid'                  => '',
			// 'menu_order'            => '',
			// 'post_type'             => '',
			// 'post_mime_type'        => '',
			// 'comment_count'         => '',
		];

		// Don't want to alter received object.
		$new_post = clone $post;

		foreach ( $post_data_fields as $field => $value ) {
			$new_post->$field = $value;
		}

		return $new_post;
	}
}
