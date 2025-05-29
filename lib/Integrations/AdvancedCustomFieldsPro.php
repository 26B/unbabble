<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Meta\Translations\RegexTranslationKey;
use TwentySixB\WP\Plugin\Unbabble\Meta\Translations\TranslationKey;

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
	public function set_fields_as_translatable( array $fields, string $prefix = '', string $regex_prefix = '' ) : array {
		$field_keys = [];

		/**
		 * Store layouts for flexible content fields. Some layout types are not stored inside
		 * the flexible content field, but in a separate entry. This is the case for the block
		 * layout type.
		 */
		$layouts_to_look_for = [];

		// Loop all the fields being imported.
		foreach ( $fields as $field ) {
			$prefix_value       = $prefix;
			$regex_prefix_value = $regex_prefix;

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
				$prefix_value       = $layouts_to_look_for[ $field['parent_layout'] ]['simple'];
				$regex_prefix_value = $layouts_to_look_for[ $field['parent_layout'] ]['regex'];
			}

			// If the field is a repeater, check its subfields and add the prefix value.
			if ( $field['type'] === 'repeater' ) {
				// Since we're adding regex for repeater, we need the prefix value (previous regex if defined, otherwise normal prefix).
				$new_regex_prefix_value = empty( $regex_prefix_value ) ? $prefix_value : $regex_prefix_value;

				$this->set_fields_as_translatable(
					$field['sub_fields'] ?? [],
					$prefix_value . $field['name'] . '_%_',
					$new_regex_prefix_value . $field['name'] . '_([0-9]+)_'
				);
				continue;
			}

			// If the field is a block or a group, check it subfields and add the prefix value.
			if ( $field['type'] === 'block' || $field['type'] === 'group' ) {
				// Since we're adding no regex for group or block, we only add regex if there was any defined before.
				$new_regex_prefix_value = empty( $regex_prefix_value ) ? '' : ( $regex_prefix_value . $field['name'] . '_');

				$this->set_fields_as_translatable(
					$field['sub_fields'] ?? [],
					$prefix_value . $field['name'] . '_',
					$new_regex_prefix_value
				);
				continue;
			}

			// If the field is a flexible content, add its layouts to the variable with the correct prefix value to be checked later.
			if ( $field['type'] === 'flexible_content' ) {
				foreach ( $field['layouts'] ?? [] as $layout ) {
					$layouts_to_look_for[ $layout['key'] ]['simple'] = $prefix_value . $field['name'] . '_%_';

					// Since we're adding regex for flexible content, we need the prefix value (previous regex if defined, otherwise normal prefix).
					$new_regex_prefix_value = empty( $regex_prefix_value ) ? $prefix_value : $regex_prefix_value;
					$layouts_to_look_for[ $layout['key'] ]['regex']  = $new_regex_prefix_value . $field['name'] . '_([0-9]+)_';
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
			if ( ! empty( $regex_prefix_value ) ) {
				$field_keys[ $prefix_value . $field['name'] ] = new RegexTranslationKey(
					'/^' . $regex_prefix_value . $field['name'] . '$/',
					$object_type,
					$prefix_value . $field['name']
				);
			} else {
				$field_keys[ $prefix_value . $field['name'] ] = new TranslationKey(
					$prefix_value . $field['name'],
					$object_type,
				);
			}
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
	 * @since 0.5.12 Handle $field['post_type'] not being set.
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_relationship( array $field ) : ?string {
		// TODO: How does ACF handle when only some of these are translatable?
		foreach ( $field['post_type'] ?? [] as $post_types ) {
			if ( LangInterface::is_post_type_translatable( $post_types ) ) {
				return 'post';
			}
		}
		return null;
	}

	/**
	 * Check if a post object field is translatable.
	 *
	 * @since 0.5.12 Handle $field['post_type'] not being set.
	 * @since 0.5.8
	 *
	 * @param array $field
	 *
	 * @return string|null
	 */
	private function check_post_object( array $field ) : ?string {
		// TODO: How does ACF handle when only some of these are translatable?
		foreach ( $field['post_type'] ?? [] as $post_type ) {
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
