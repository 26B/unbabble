<?php

namespace TwentySixB\WP\Plugin\Unbabble\CLI;

use TwentySixB\WP\Plugin\Unbabble\Options as UbbOptions;

/**
 * CLI commands for Unbabble's options.
 *
 * @since 0.0.5
 */
class Options extends Command {

	/**
	 * Show Unbabble's options.
	 *
	 * @since 0.0.5
	 * @return void
	 */
	public function get() : void {
		$options         = UbbOptions::get();
		$ordered_options = [
			'default_language'  => $options['default_language'],
			'allowed_languages' => $options['allowed_languages'],
			'router'            => $options['router'],
			'router_options'    => $options['router_options'] ?? [],
			'post_types'        => $options['post_types'],
			'taxonomies'        => $options['taxonomies'],
		];

		$data = [];
		foreach ( $ordered_options as $option_name => $option_values ) {
			$header = $option_name;
			switch ( $option_name ) {
				case 'post_types':
					$header = "Translatable post types";
					break;
				case 'taxonomies':
					$header = "Translatable taxonomies";
					break;
				default:
					$header = ucfirst( str_replace( '_', ' ', $option_name ) );
			}
			$header = "%4{$header}:%N";

			$values   = is_array( $option_values ) ? $option_values : [ $option_values ];
			$sub_data = [];
			foreach ( $values as $index => $value ) {
				switch ( $option_name ) {
					case 'allowed_languages':
					case 'default_language':
						$sub_data[] = [ $value,  self::get_lang_info( $value, false ) ];
						break;
					case 'router':
						$sub_data[] = [ ucfirst( $value ) ];
						break;
					case 'post_types':
						$label      = get_post_type_object( $value )->label;
						$sub_data[] = [ $value, "%y{$label}%N" ];
						break;
					case 'taxonomies':
						$label      = get_taxonomy( $value )->label;
						$sub_data[] = [ $value, "%y{$label}%N" ];
						break;
					case 'router_options':
						$sub_header = ucfirst( str_replace( '_', ' ', $index ) );
						$sub_header = "%8{$sub_header}:%N";

						if ( ! is_array( $value ) ) {
							$sub_data[][ $sub_header ] = $value;
						} else {
							$lines = [];
							foreach ( $value as $sub_option_name => $sub_option_value ) {

								// TODO: Ignoring depth 3 options for now.
								if ( is_array( $sub_option_value ) ) {
									continue;
								}

								if ( $index === 'directories' ) {
									$value_str = $sub_option_value;
									if ( empty( $value_str ) ) {
										$value_str = "%rN/A%N";
									}
									$lines[] = [ $sub_option_name, $value_str ];
								} else {
									$lines[] = [ $sub_option_name, $sub_option_value ];
								}
							}
							if ( ! empty( $lines ) ) {
								$sub_data[ $sub_header ] = $lines;
							}
						}

						break;
					default:
						$sub_data[] = [ $value ];
				}
			}

			if ( ! empty( $sub_data ) ) {
				$data[ $header ] = $sub_data;
			}
		}

		self::format_data_and_log( $data );
	}
}
