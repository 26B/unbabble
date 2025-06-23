<?php

namespace TwentySixB\WP\Plugin\Unbabble\Meta\Translations;

/**
 * Class to help with translating metas that have regex rules.
 *
 * This class is used to store the regex key, type of a translation.
 *
 * @package TwentySixB\WP\Plugin\Unbabble\Meta\Translations
 * @since 0.5.14
 */
class RegexTranslationKey extends TranslationKey {

	/**
	 * @param string $key
	 * @param string $type
	 * @since 0.5.14
	 */
	public function __construct( string $key, string $type ) {

		// Store the key without the delimiters if they are both present.
		$regex_key = $key;
		if ( str_starts_with( $regex_key, '/' ) && str_ends_with( $regex_key, '/' ) ) {
			$regex_key = substr( substr( $regex_key, 0, -1 ), 1 );
		}

		parent::__construct( $regex_key, $type );
	}

	/**
	 * Check if the regex key matches the given key.
	 *
	 * @param string $key_to_match
	 * @return bool
	 */
	public function matches( string $key_to_match ) : bool {
		$regex = $this->get_regex_key();
		return preg_match( $regex, $key_to_match ) === 1;
	}

	/**
	 * Get the regex key for SQL queries, without the '/' delimiters.
	 *
	 * @return string
	 */
	public function get_sql_key() : string {
		return $this->key;
	}

	/**
	 * Get the regex key for SQL queries, with the '/' delimiters.
	 *
	 * @return string
	 */
	public function get_regex_key() : string {
		return '/' . $this->key . '/';
	}
}
