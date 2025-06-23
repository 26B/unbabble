<?php

namespace TwentySixB\WP\Plugin\Unbabble\Meta\Translations;

/**
 * Class to help with translating metas.
 *
 * @package TwentySixB\WP\Plugin\Unbabble\Meta\Translations
 * @since Unreleased
 */
class Helper {

	/**
	 * Check if a meta key matches any of the translation keys.
	 *
	 * @param string $meta_key
	 * @param array  $translation_keys
	 * @return string|null
	 *
	 * @since Unreleased
	 */
	public static function match_translation_key( string $meta_key, array $translation_keys ) : ?string {

		// Match simple keys first that are not using TranslationKey's.
		if (
			isset( $translation_keys[ $meta_key ] )
			&& ! $translation_keys[ $meta_key ] instanceof TranslationKey
			&& is_string( $translation_keys[ $meta_key ] )
		) {
			return $translation_keys[ $meta_key ];
		}

		// Match simple keys that are using TranslationKey's.
		if (
			isset( $translation_keys[ $meta_key ] )
			&& $translation_keys[ $meta_key ] instanceof TranslationKey
			&& $translation_keys[ $meta_key ]->matches( $meta_key )
		) {
			return $translation_keys[ $meta_key ]->get_type();
		}

		// Match regex keys.
		foreach ( $translation_keys as $value ) {
			if ( ! $value instanceof RegexTranslationKey ) {
				continue;
			}

			if ( $value->matches( $meta_key ) ) {
				return $value->get_type();
			}
		}

		// None of the keys matched.
		return null;
	}
}
