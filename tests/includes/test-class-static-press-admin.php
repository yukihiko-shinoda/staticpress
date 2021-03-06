<?php
/**
 * Class Static_Press_Admin_Test
 *
 * @package static_press\tests\includes
 */

namespace static_press\tests\includes;

/**
 * StaticPress test case.
 *
 * @noinspection PhpUndefinedClassInspection
 */
class Static_Press_Admin_Test extends \WP_UnitTestCase {
	/**
	 * Sets administrator as current user.
	 *
	 * @see https://wordpress.stackexchange.com/a/207363
	 */
	public function setUp() {
		parent::setUp();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}

	/**
	 * Function admin_menu() should not throw any exception.
	 */
	public function test_admin_menu() {
		$static_press_admin = new \Static_Press_Admin( plugin_basename( __FILE__ ) );
		$static_press_admin->admin_menu();
	}
}
