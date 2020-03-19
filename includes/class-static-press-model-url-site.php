<?php
/**
 * Class Static_Press_Model_Url_Site
 *
 * @package static_press\includes
 */

namespace static_press\includes;

if ( ! class_exists( 'static_press\includes\Static_Press_Site_Dependency' ) ) {
	require dirname( __FILE__ ) . '/class-static-press-site-dependency.php';
}
use static_press\includes\Static_Press_Site_Dependency;
/**
 * Model URL site.
 */
class Static_Press_Model_Url_Site extends Static_Press_Model_Url {
	/**
	 * Constructor.
	 * 
	 * @param Static_Press_Date_Time_Factory $date_time_factory Date.
	 */
	public function __construct( $date_time_factory ) {
		parent::__construct(
			null,
			Static_Press_Model_Url::TYPE_FRONT_PAGE,
			apply_filters( 'StaticPress::get_url', Static_Press_Site_Dependency::get_site_url() ),
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$date_time_factory->create_date( 'Y-m-d h:i:s' )
		);
	}

	/**
	 * Converts to array.
	 * 
	 * @return array
	 */
	public function to_array() {
		return array(
			Static_Press_Repository::FIELD_NAME_TYPE => $this->get_type(),
			Static_Press_Repository::FIELD_NAME_URL  => $this->get_url(),
			Static_Press_Repository::FIELD_NAME_LAST_MODIFIED => $this->get_last_modified(),
		);
	}
}
