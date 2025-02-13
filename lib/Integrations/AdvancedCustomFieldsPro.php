<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;

class AdvancedCustomFieldsPro {
	public function register() {
		add_filter( 'option_acf_pro_license', [ $this, 'change_home_url' ] );
		add_filter( 'acf/prepare_fields_for_import', [ $this, 'set_fields_as_translatable' ], PHP_INT_MAX - 10, 1 );
	}

	/**
	 * Change the home url in the acf pro license.
	 *
	 * If the home url is not fixed, then the acf deactivates and activates itself which takes a
	 * long time when changing languages in the backoffice.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $pro_license
	 * @return mixed
	 */
	public function change_home_url( $pro_license ) {
		$decoded = maybe_unserialize( base64_decode( $pro_license ) );
		if ( ! is_array( $decoded ) || ! isset( $decoded['url'] ) ) {
			return $pro_license;
		}
		$decoded['url'] = get_home_url();
		return base64_encode( maybe_serialize( $decoded ) );
	}

	/**
	 * Sets ACF fields as translatable according to their type.
	 *
	 * @since 0.5.8
	 *
	 * @param array  $fields
	 * @param string $prefix
	 *
	 * @return array
	 */
	public function set_fields_as_translatable( array $fields, string $prefix = '' ) : array {
		$field_keys = [];

		/**
		 * Store layouts for flexible content fields. Some layout types are not stored inside
		 * the flexible content field, but in a separate entry. This is the case for the block
		 * layout type.
		 */
		$layouts_to_look_for = [];

		// Loop all the fields being imported.
		foreach ( $fields as $field ) {
			$prefix_value = $prefix;

			/**
			 * Ignore sub fields since we deal with top fields only (aggregators included),
			 * unless they are a flexible content layout.
			 */
			if (
				empty( $prefix )
				&& (
					! isset( $field['parent_layout'] )
					|| ! isset( $layouts_to_look_for[ $field['parent_layout'] ] )
				)
				&& (
					! isset( $field['parent' ] )
					|| str_starts_with( $field['parent'], 'field_' )
				)
			) {
				continue;
			}

			// If the field is a layout, fetch the prefix value.
			if ( isset( $field['parent_layout'] ) ) {
				$prefix_value = $layouts_to_look_for[ $field['parent_layout'] ];
			}

			// If the field is a repeater, check it subfields and add the prefix value.
			if ( $field['type'] === 'repeater' ) {
				$this->set_fields_as_translatable( $field['sub_fields'] ?? [], $prefix_value . $field['name'] . '_%_' );
				continue;
			}

			// If the field is a block or a group, check it subfields and add the prefix value.
			if ( $field['type'] === 'block' || $field['type'] === 'group' ) {
				$this->set_fields_as_translatable( $field['sub_fields'] ?? [], $prefix_value . $field['name'] . '_' );
				continue;
			}

			// If the field is a flexible content, add its layouts to the variable with the correct prefix value to be checked later.
			if ( $field['type'] === 'flexible_content' ) {
				foreach ( $field['layouts'] ?? [] as $layout ) {
					$layouts_to_look_for[ $layout['key'] ] = $prefix_value . $field['name'] . '_%_';
				}
				continue;
			}

			// Check for field types that have post/term identifiers, and if they are translatable.
			$object_type = null;
			switch ( $field['type'] ) {
				// case 'page_link': TODO: can contain ids but also archive urls so can break unbabble.
				case 'relationship':
					$object_type = $this->check_relationship( $field );
					break;
				case 'post_object':
					$object_type = $this->check_post_object( $field );
					break;
				case 'image':
					$object_type = $this->check_image( $field );
					break;
				case 'file':
					$object_type = $this->check_file( $field );
					break;
				case 'gallery':
					$object_type = $this->check_gallery( $field );
					break;
				case 'taxonomy':
					$object_type = $this->check_taxonomy( $field );
					break;
			}

			// If the field is not translatable, skip it.
			if ( empty( $object_type ) ) {
				continue;
			}

			// Add to the field keys array.
			$field_keys[ $prefix_value . $field['name'] ] = $object_type;
		}

		// Register the field keys to be translated.
		if ( ! empty( $field_keys ) ) {
			add_filter( 'ubb_change_language_post_meta_translate_keys', fn( $meta_keys ) => array_merge( $meta_keys, $field_keys ) );
			add_filter( 'ubb_yoast_duplicate_post_meta_translate_keys', fn( $meta_keys ) => array_merge( $meta_keys, $field_keys ) );
		}

		return $fields;
	}

	/**
	 * Check if a relationship field is translatable.
	 *
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_relationship( array $field ) : ?string {
		// TODO: How does ACF handle when only some of these are translatable?
		foreach ( $field['post_type'] as $post_types ) {
			if ( LangInterface::is_post_type_translatable( $post_types ) ) {
				return 'post';
			}
		}
		return null;
	}

	/**
	 * Check if a post object field is translatable.
	 *
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_post_object( array $field ) : ?string {
		// TODO: How does ACF handle when only some of these are translatable?
		foreach ( $field['post_type'] as $post_type ) {
			if ( LangInterface::is_post_type_translatable( $post_type ) ) {
				return 'post';
			}
		}
		return null;
	}

	/**
	 * Check if an image field is translatable.
	 *
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_image( array $field ) : ?string {
		if ( LangInterface::is_post_type_translatable( 'attachment' ) ) {
			return 'post';
		}
		return null;
	}

	/**
	 * Check if a file field is translatable.
	 *
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_file( array $field ) : ?string {
		if ( LangInterface::is_post_type_translatable( 'attachment' ) ) {
			return 'post';
		}
		return null;
	}

	/**
	 * Check if a gallery field is translatable.
	 *
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_gallery( array $field ) : ?string {
		if ( LangInterface::is_post_type_translatable( 'attachment' ) ) {
			return 'post';
		}
		return null;
	}

	/**
	 * Check if a taxonomy field is translatable.
	 *
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_taxonomy( array $field ) : ?string {
		if ( LangInterface::is_taxonomy_translatable( $field['taxonomy'] ) ) {
			return 'term';
		}
		return null;
	}
}
