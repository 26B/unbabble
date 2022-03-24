<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * @since 0.0.0
 */
class PostMetaTranslation {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {

		// Post meta translations.
		\add_action( 'add_post_metadata', [ $this, 'add_post_metadata' ], 10, 5 );
		\add_action( 'update_post_metadata', [ $this, 'update_post_metadata' ], 10, 5 );
		\add_action( 'get_post_metadata', [ $this, 'get_post_metadata' ], 10, 4 );
		\add_action( 'delete_post_metadata', [ $this, 'delete_post_metadata' ], 10, 5 );

		// TODO: See hook 'default_{$meta_type}_metadata'

		// TODO: Term meta.
	}

	public function delete_post_metadata( $check, $post_id, $meta_key, $meta_value, $delete_all ) {
		if ( $check !== null ) {
			return $check;
		}

		list( $lang, $is_original ) = $this->get_language_for_meta_handling( $post_id, $meta_key );
		if ( empty( $lang ) || $is_original ) {
			return $check;
		}

		/**
		 * TODO: Docs.
		 */
		$delete = apply_filters( 'ubb_delete_post_metadata', true, $post_id, $meta_key, $meta_value, $lang, $delete_all );
		if ( $delete === null ) {
			return $check;
		}

		// TODO: Handle $delete_all

		return delete_post_meta( $post_id, "{$meta_key}_ubb_{$lang}", $meta_value );
	}

	// TODO: Isn't used right now by base WordPress with our plugin.
	public function add_post_metadata( $check, $post_id, $meta_key, $meta_value, $unique ) {
		if ( $check !== null ) {
			return $check;
		}

		list( $lang, $is_original ) = $this->get_language_for_meta_handling( $post_id, $meta_key );
		if ( empty( $lang ) || $is_original ) {
			return $check;
		}

		/**
		 * TODO: Docs.
		 */
		$meta_value = apply_filters( 'ubb_add_post_metadata', $meta_value, $post_id, $meta_key, $lang );
		if ( $meta_value === null ) {
			return $check;
		}

		return add_post_meta( $post_id, "{$meta_key}_ubb_{$lang}", $meta_value, $unique );
	}

	public function get_post_metadata( $check, $post_id, $meta_key, $single ) {
		if ( $check !== null ) {
			return $check;
		}

		list( $lang, $is_original ) = $this->get_language_for_meta_handling( $post_id, $meta_key );
		if ( empty( $lang ) || $is_original ) {
			return $check;
		}

		/**
		 * TODO: Docs.
		 */
		if ( ! apply_filters( 'ubb_get_post_metadata', true, $post_id, $meta_key, $lang ) ) {
			return $check;
		}

		// Check if post has meta key with lang. If not then let WordPress get base value.
		if ( ! metadata_exists( 'post', $post_id, "{$meta_key}_ubb_{$lang}" ) ) {
			return $check;
		}

		return get_post_meta( $post_id, "{$meta_key}_ubb_{$lang}", $single );
	}

	public function update_post_metadata( $check, $post_id, $meta_key, $meta_value, $prev_value ) : ?bool {
		if ( $check !== null ) {
			return $check;
		}

		// TODO: Use this $is_original for the saving space part below.
		list( $lang, $is_original ) = $this->get_language_for_meta_handling( $post_id, $meta_key );
		if ( empty( $lang ) || $is_original ) {
			return $check;
		}

		/**
		 * TODO: Docs.
		 */
		$meta_value = apply_filters( 'ubb_update_post_metadata', $meta_value, $post_id, $meta_key, $lang, $prev_value );
		if ( $meta_value === null ) {
			return $check;
		}

		/**
		 * TODO: Saving space:
		 *  - Remove lang meta if it will have same value as the original meta
		 *  - Add lang meta if it will no longer have same value as original meta
		 * Problems to consider: Multiple entries, value of $prev_value vs value in actual meta.
		 */

		update_post_meta( $post_id, "{$meta_key}_ubb_{$lang}", $meta_value, $prev_value );
		return true;
	}

	// Returns [ $lang, $is_original ]
	private function get_language_for_meta_handling( $post_id, $meta_key ) : array {

		// Check blacklisted keys.
		if ( in_array( $meta_key, $this->get_blacklisted_meta_keys(), true ) ) {
			return [ '', false ];
		}

		$options = Options::get();

		// Check if post $post_id is a translatable post_type.
		if ( ! in_array( get_post_type( $post_id ), $options['post_types'], true ) ) {
			return [ '', false ];
		}

		// Get current language. TODO: function to get lang
		$lang = $_GET['lang'] ?? $_COOKIE['ubb_lang'] ?? $options['default_language'];

		// If the meta_key has 'ubb_{lang}' at the end, do nothing. Prevent loops when we update the correct key below.
		if ( str_ends_with( $meta_key, "_ubb_{$lang}" ) ) {
			return [ '', false ];
		}

		$post_langs = \get_post_meta( $post_id, 'ubb_lang' );

		if ( empty( $post_langs ) ) {
			return [ '', false ];
		}

		return [ $lang, current( $post_langs ) === $lang ];
	}

	private function get_blacklisted_meta_keys() : array {
		// TODO: What other WordPress keys should be added here.
		return apply_filters( // TODO: Maybe we should do a general one (instead of specific for post/term).
			'ubb_blacklisted_post_meta_keys',
			[
				'_edit_lock',
				'_pingme',
				'_encloseme',
				'_edit_last',
				'_wp_trash_meta_status',
				'_wp_trash_meta_time',
				'ubb_lang',
			]
		);
	}
}
