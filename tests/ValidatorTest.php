<?php

namespace TwentySixB\WP\Plugin\Unbabble\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use TwentySixB\WP\Plugin\Unbabble\Validation\Validator;

/**
 * Unit tests for Validator.
 *
 * @since 0.0.12
 */
class ValidatorTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up tests.
	 *
	 * @since 0.0.12
	 *
	 * @return void
	 */
	public function setUp() : void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
	}

	/**
	 * Tear down tests.
	 *
	 * @since 0.0.12
	 *
	 * @return void
	 */
	public function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test valid values.
	 *
	 * @since 0.0.12
	 *
	 * @testdox validate - accepts values that satisfy all configured rules
	 *
	 * @return void
	 */
	public function testValidateAcceptsValidValues() : void {
		$validator = new Validator(
			[
				'languages' => [ 'string_array', 'not_empty' ],
				'name'      => [ 'string' ],
				'router'    => [ 'in:query_var,directory' ],
				'options'   => [ 'array' ],
			]
		);

		$this->assertTrue(
			$validator->validate(
				[
					'languages' => [ 'en_US', 'pt_PT' ],
					'name'      => 'Unbabble',
					'router'    => 'directory',
					'options'   => [],
				]
			)
		);
		$this->assertSame( [], $validator->errors() );
	}

	/**
	 * Test missing values.
	 *
	 * @since 0.0.12
	 *
	 * @testdox validate - reports configured keys that are missing
	 *
	 * @return void
	 */
	public function testValidateReportsMissingValues() : void {
		$validator = new Validator( [ 'languages' => [ 'string_array' ] ] );

		$this->assertFalse( $validator->validate( [] ) );
		$this->assertSame(
			[
				'languages' => 'Missing option.',
			],
			$validator->errors()
		);
	}

	/**
	 * Test invalid values.
	 *
	 * @since 0.0.12
	 *
	 * @testdox validate - reports type, emptiness, and allow-list errors
	 *
	 * @return void
	 */
	public function testValidateReportsInvalidValues() : void {
		$validator = new Validator(
			[
				'languages' => [ 'string_array', 'not_empty' ],
				'name'      => [ 'string' ],
				'router'    => [ 'in:query_var,directory' ],
				'options'   => [ 'array' ],
			]
		);

		$this->assertFalse(
			$validator->validate(
				[
					'languages' => [],
					'name'      => 26,
					'router'    => 'path',
					'options'   => 'none',
				]
			)
		);
		$this->assertSame(
			[
				'languages' => [ 'Value is empty.' ],
				'name'      => [ 'Value is not a string.' ],
				'router'    => [ 'Value is unknown. Accepted values: query_var, directory.' ],
				'options'   => [ 'Value is not an array.' ],
			],
			$validator->errors()
		);
	}

	/**
	 * Test invalid string array values.
	 *
	 * @since 0.0.12
	 *
	 * @testdox validate - reports non-string entries in string arrays
	 *
	 * @return void
	 */
	public function testValidateReportsNonStringArrayEntries() : void {
		$validator = new Validator( [ 'languages' => [ 'string_array' ] ] );

		$this->assertFalse( $validator->validate( [ 'languages' => [ 'en_US', 26 ] ] ) );
		$this->assertSame(
			[
				'languages' => [ 'At least one of it\'s array values is not a string.' ],
			],
			$validator->errors()
		);
	}
}
