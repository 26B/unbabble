<?php

namespace TwentySixB\WP\Plugin\Unbabble\Meta\Translations;

/**
 * Class to help with translating metas.
 *
 * This class is used to store the key and type of a translation.
 *
 * @package TwentySixB\WP\Plugin\Unbabble\Meta\Translations
 * @since Unreleased
 */
class TranslationKey {

	/**
	 * @var string
	 * @since Unreleased
	 */
	protected $key;

	/**
	 * @var string
	 * @since Unreleased
	 */
	protected $type;

	/**
	 * @param string $key
	 * @param string $type
	 * @since Unreleased
	 */
	public function __construct( string $key, string $type ) {
		$this->key  = $key;
		$this->type = $type;
	}

	/**
	 * Get the meta key.
	 *
	 * @return string
	 * @since Unreleased
	 */
	public function get_key() : string {
		return $this->key;
	}

	/**
	 * Get the type of the meta key.
	 *
	 * @return string
	 * @since Unreleased
	 */
	public function get_type() : string {
		return $this->type;
	}

	/**
	 * Check if the key matches the given key.
	 *
	 * @param string $key_to_match
	 * @return bool
	 * @since Unreleased
	 */
	public function matches( string $key_to_match ) : bool {
		return $this->key === $key_to_match;
	}
}
