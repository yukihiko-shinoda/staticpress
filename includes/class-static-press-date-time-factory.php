<?php
/**
 * Class Static_Press_Date_Time_Factory
 *
 * @package static_press\includes
 */

namespace static_press\includes;

/**
 * Date time factory.
 */
class Static_Press_Date_Time_Factory {
	/**
	 * Creates GM date.
	 * 
	 * @param string $format Format.
	 */
	public function create_gmdate( $format ) {
		return gmdate( $format );
	}
}