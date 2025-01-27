<?php

namespace TwentySixB\WP\Plugin\Unbabble\Validation;

class Validator {

	private array $config;
	private array $errors = [];

	public function __construct( array $config ) {
		$this->config = $config;
	}

	public function errors() : array {
		return $this->errors;
	}

	public function validate( array $values ) : bool {
		$this->errors = [];
		foreach ( $this->config as $key => $validations ) {
			if ( ! isset( $values[ $key ] ) ) {
				$this->errors[ $key ] = \__( 'Missing option.', 'unbabble' );
				continue;
			}

			$value = $values[ $key ];
			foreach ( $validations as $validation ) {
				if ( method_exists( $this, $validation ) ) {
					$this->$validation( $key, $value );
					continue;
				}

				$parts = explode( ':', $validation, 2 );
				if ( count( $parts ) > 1 && method_exists( $this, $parts[0] ) ) {
					$method_name = $parts[0];
					$this->$method_name( $key, $value, ...explode( ',', $parts[1] ) );
				}
			}
		}
		return empty( $this->errors );
	}

	public function array( string $key, $value ) : void {
		if ( is_array( $value ) ) {
			return;
		}
		$this->errors[ $key ][] = \__( 'Value is not an array.', 'unbabble' );
	}

	public function not_empty( string $key, $value ) : void {
		if ( ! empty( $value ) ) {
			return;
		}
		$this->errors[ $key ][] = \__( 'Value is empty.', 'unbabble' );
	}

	public function string( string $key, $value ) : void {
		if ( is_string( $value ) ) {
			return;
		}
		$this->errors[ $key ][] = \__( 'Value is not a string.', 'unbabble' );
	}

	public function string_array( string $key, $value ) : void {
		if ( ! is_array( $value ) ) {
			$this->errors[ $key ][] = \__( 'Value is not an array.', 'unbabble' );
			return;
		}

		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) ) {
				$this->errors[ $key ][] = \__( 'At least one of it\'s array values is not a string.', 'unbabble' );
				return;
			}
		}
	}

	public function in( string $key, $value, ...$accepted_values ) : void {
		if ( in_array( $value, $accepted_values ) ) {
			return;
		}
		$this->errors[ $key ][] = sprintf(
			/* translators: %s: list of accepted values */
			\__( 'Value is unknown. Accepted values: %s.', 'unbabble' ),
			implode( ', ', $accepted_values )
		);
	}
}
