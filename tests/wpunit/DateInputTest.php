<?php

class DateInputTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		wp_set_current_user( $this->admin );
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Test that DateInput type has hour, minute, and second fields
	 */
	public function testDateInputHasHourMinuteSecondFields() {
		$query = '
		query IntrospectDateInput {
			__type(name: "DateInput") {
				name
				inputFields {
					name
					type {
						name
					}
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$input_fields = $actual['data']['__type']['inputFields'];
		$field_names  = array_column( $input_fields, 'name' );

		// Assert that the existing fields are still present
		$this->assertContains( 'year', $field_names );
		$this->assertContains( 'month', $field_names );
		$this->assertContains( 'day', $field_names );

		// Assert that the new fields are present
		$this->assertContains( 'hour', $field_names );
		$this->assertContains( 'minute', $field_names );
		$this->assertContains( 'second', $field_names );

		// Verify that all these fields are of type Int
		$field_map = [];
		foreach ( $input_fields as $field ) {
			$field_map[ $field['name'] ] = $field['type']['name'];
		}

		$this->assertEquals( 'Int', $field_map['year'] );
		$this->assertEquals( 'Int', $field_map['month'] );
		$this->assertEquals( 'Int', $field_map['day'] );
		$this->assertEquals( 'Int', $field_map['hour'] );
		$this->assertEquals( 'Int', $field_map['minute'] );
		$this->assertEquals( 'Int', $field_map['second'] );
	}

	/**
	 * Test that DateQuery filters work with hour and minute fields
	 */
	public function testDateQueryWithHourAndMinute() {
		// Create posts with specific times
		$morning_post = $this->factory()->post->create(
			[
				'post_title'  => 'Morning Post',
				'post_status' => 'publish',
				'post_date'   => '2024-01-15 08:30:00',
			]
		);

		$afternoon_post = $this->factory()->post->create(
			[
				'post_title'  => 'Afternoon Post',
				'post_status' => 'publish',
				'post_date'   => '2024-01-15 14:45:00',
			]
		);

		$evening_post = $this->factory()->post->create(
			[
				'post_title'  => 'Evening Post',
				'post_status' => 'publish',
				'post_date'   => '2024-01-15 20:15:00',
			]
		);

		// Query for posts after 10:00 AM and before 4:00 PM on the same day
		$query = '
		query GetPostsByTimeRange {
			posts(
				where: {
					dateQuery: {
						after: {
							year: 2024
							month: 1
							day: 15
							hour: 10
							minute: 0
						}
						before: {
							year: 2024
							month: 1
							day: 15
							hour: 16
							minute: 0
						}
					}
				}
			) {
				nodes {
					title
					date
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		// Clean up
		wp_delete_post( $morning_post, true );
		wp_delete_post( $afternoon_post, true );
		wp_delete_post( $evening_post, true );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		// Should only return the afternoon post
		$nodes = $actual['data']['posts']['nodes'];
		$this->assertCount( 1, $nodes );
		$this->assertEquals( 'Afternoon Post', $nodes[0]['title'] );
	}
}
