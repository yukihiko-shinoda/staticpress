<?php
/**
 * Class Static_Press_Model_Url_Fetched
 *
 * @package static_press\includes
 */

namespace static_press\includes;

if ( ! class_exists( 'static_press\includes\Static_Press_Model_Url' ) ) {
	require dirname( __FILE__ ) . '/class-static-press-model-url.php';
}
if ( ! class_exists( 'static_press\includes\Static_Press_Repository' ) ) {
	require dirname( __FILE__ ) . '/class-static-press-repository.php';
}

use static_press\includes\Static_Press_Model_Url;
use static_press\includes\Static_Press_Repository;

/**
 * Model URL other.
 */
class Static_Press_Model_Url_Fetched extends Static_Press_Model_Url {
	/**
	 * Constructor.
	 * 
	 * @param Object $result Result.
	 */
	public function __construct( $result ) {
		parent::__construct( $result->ID, $result->type, $result->url, null, null, null, $result->pages );
	}

	/**
	 * Getter.
	 */
	public function get_id_fetched() {
		return $this->get_id();
	}

	/**
	 * Getter.
	 */
	public function get_type_fetched() {
		return $this->get_type();
	}

	/**
	 * Getter.
	 */
	public function get_pages_fetched() {
		return $this->get_pages();
	}

	/**
	 * Returns whether has multiple pages or not.
	 * 
	 * @return bool true: has multiple pages, false: doesn't have multiple pages.
	 */
	public function has_multiple_page() {
		return $this->get_pages() > 1;
	}

	/**
	 * Returns whether is static file or not.
	 * 
	 * @return bool true: is static file, false: is not static file.
	 */
	public function is_static_file() {
		return self::TYPE_STATIC_FILE === $this->get_type();
	}
	/**
	 * Converts to array.
	 * 
	 * @throws \LogicException This function should not be called.
	 */
	public function to_array() {
		throw new \LogicException();
	}
}