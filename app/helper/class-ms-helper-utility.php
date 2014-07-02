<?php
/**
 * This file defines the MS_Helper_Utility class.
 * 
 * @copyright Incsub (http://incsub.com/)
 *
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 * 
 * This program is free software; you can redistribute it and/or modify 
 * it under the terms of the GNU General Public License, version 2, as  
 * published by the Free Software Foundation.                           
 *
 * This program is distributed in the hope that it will be useful,      
 * but WITHOUT ANY WARRANTY; without even the implied warranty of       
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        
 * GNU General Public License for more details.                         
 *
 * You should have received a copy of the GNU General Public License    
 * along with this program; if not, write to the Free Software          
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               
 * MA 02110-1301 USA                                                    
 *
 */

/**
 * This Helper creates additional utility functions.
 *
 * @since 4.0.0
 * @package Membership
 * @subpackage Helper
 */
class MS_Helper_Utility extends MS_Helper {
	
	/**
	 * Implements a multi-dimensional array_intersect_assoc.
	 *
	 * The standard array_intersect_assoc does the intersection based on string values of the keys.
	 * We need to find a way to recursively check multi-dimensional arrays.
	 *
	 * Note that we are not passing values here but references.
	 *
	 * @since 4.0.0
	 * @param  mixed $arr1 First array to intersect.
	 * @param  mixed $arr2 Second array to intersect.	
	 */
	public static function array_intersect_assoc_deep(&$arr1, &$arr2) {
		
		// If not arrays, at least associate the strings this gives the recursive answer
		// If 1 argument is an array and the other not throw error.
		if ( ! is_array( $arr1 ) && ! is_array( $arr2 ) ) {
	        return (string) $arr1 == (string) $arr2 ? (string) $arr1 : false;
		} elseif ( ! is_array( $arr1 ) && is_array( $arr2 ) ) {
			MS_Helper_Debug::log( __( "WARNING: MS_Helper_Utility::array_intersect_assoc_deep() Expected parameter 1 to be an array.", MS_TEXT_DOMAIN ), true );
			return false;
		} elseif ( ! is_array( $arr2 ) && is_array( $arr1 ) ) {
			MS_Helper_Debug::log( __( "WARNING: MS_Helper_Utility::array_intersect_assoc_deep() Expected parameter 2 to be an array.", MS_TEXT_DOMAIN ), true );
			return false;			
		}
		
	    $intersections = array_intersect( array_keys( $arr1 ), array_keys( $arr2 ) );
	    
		$assoc_array = array();
		
		// Time to recursively run through the arrays
	    foreach ( $intersections as $key ) {
			$result = MS_Helper_Utility::array_intersect_assoc_deep( $arr1[ $key ], $arr2[ $key ] );
			if ( $result ) {
				$assoc_array[ $key ] = $result;
			}
	    }
		
	    return $assoc_array;
	}
	
}
