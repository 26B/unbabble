<?php

namespace TwentySixB\WP\Plugin\Unbabble\Meta\Translations;

/**
 * Class to help with translating metas.
 *
 * This class is used to store the key and type of a translation.
 *
 * @package TwentySixB\WP\Plugin\Unbabble\Meta\Translations
 * @since 0.5.14
 */
class TranslationKey {

	/**
	 * @var string
	 * @since 0.5.14
	 */
	protected $key;

	/**
	 * @var string
	 * @since 0.5.14
	 */
	protected $type;

	/**
	 * @param string $key
	 * @param string $type
	 * @since 0.5.14
	 */
	public function __construct( string $key, string $type ) {
		$this->key  = $key;
		$this->type = $type;
	}

	/**
	 * Get the meta key.
	 *
	 * @return string
	 * @since 0.5.14
	 */
	public function get_key() : string {
		return $this->key;
	}

	/**
	 * Get the type of the meta key.
	 *
	 * @return string
	 * @since 0.5.14
	 */
	public function get_type() : string {
		return $this->type;
	}

	/**
	 * Check if the key matches the given key.
	 *
	 * @param string $key_to_match
	 * @return bool
	 * @since 0.5.14
	 */
	public function matches( string $key_to_match ) : bool {
		return $this->key === $key_to_match;
	}
}
