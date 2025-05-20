<?php

namespace TwentySixB\WP\Plugin\Unbabble\Meta\Translations;

/**
 * Class to help with translating metas that have regex rules.
 *
 * This class is used to store the regex key, type of a translation. Also allow for a sql like
 * key to be used for the sql queries.
 *
 * @package TwentySixB\WP\Plugin\Unbabble\Meta\Translations
 * @since Unreleased
 */
class RegexTranslationKey extends TranslationKey {

	/**
	 * A SQL LIKE key similar to the regex key used for sql queries to match the key.
	 *
	 * @var string
	 * @since Unreleased
	 */
	protected $sql_like_key;

	/**
	 * @param string $key
	 * @param string $type
	 * @param string $sql_like_key
	 * @since Unreleased
	 */
	public function __construct( string $key, string $type, string $sql_like_key = '' ) {
		parent::__construct( $key, $type );
		$this->sql_like_key = $sql_like_key;
	}

	/**
	 * Check if the regex key matches the given key.
	 *
	 * @param string $key_to_match
	 * @return bool
	 */
	public function matches( string $key_to_match ) : bool {
		$regex = $this->key;
		if ( ! str_starts_with( $regex, '/' ) ) {
			$regex = '/' . $regex;
		}
		if ( ! str_ends_with( $regex, '/' ) ) {
			$regex .= '/';
		}

		return preg_match( $this->key, $key_to_match ) === 1;
	}

	/**
	 * Check if the regex key has a SQL LIKE key.
	 *
	 * @return bool
	 */
	public function has_sql_like() : bool {
		return ! empty( $this->sql_like_key );
	}

	/**
	 * Get the SQL LIKE key
	 *
	 * @return string
	 */
	public function get_sql_like() : string {
		return $this->sql_like_key;
	}
}
