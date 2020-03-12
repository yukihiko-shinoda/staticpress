<?php
/**
 * Class Static_Press_File_System_Utility
 *
 * @package static_press\includes
 */

namespace static_press\includes;

/**
 * File scanner.
 */
class Static_Press_File_System_Utility {
	/**
	 * Makes subdirectries.
	 * 
	 * @param string $file File.
	 */
	public static function make_subdirectories( $file ) {
		$dir_sep     = self::dir_sep();
		$subdir      = $dir_sep;
		$directories = explode( $dir_sep, dirname( $file ) );
		foreach ( $directories as $dir ) {
			if ( empty( $dir ) ) {
				continue;
			}
			$subdir .= trailingslashit( $dir );
			if ( ! file_exists( $subdir ) ) {
				mkdir( $subdir, 0755 );
			}
		}
	}

	/**
	 * Returns directory seperator.
	 * 
	 * @return string Directory seperator.
	 */
	private static function dir_sep() {
		return defined( 'DIRECTORY_SEPARATOR' ) ? DIRECTORY_SEPARATOR : '/';
	}
}
