<?php
/**
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
 * Membership Metabox
 *
 * @since 4.0.0
 *
 */
class MS_Controller_Membership_Metabox extends MS_Controller {
	
	private $post_types;
	
	private $metabox_id = 'membership_access';
	
	private $metabox_title;
	
	private $context = 'side';
	
	private $priority = 'default';
	
	private $capability = 'manage_options';
		
	public function __construct() {		
		$this->metabox_title = __( 'Membership Access', MS_TEXT_DOMAIN );
		$this->post_types = apply_filters( 'ms_controller_membership_metabox_add_meta_boxes_post_types', array( 'page', 'post' ) );
		
		$this->add_action( 'add_meta_boxes', 'add_meta_boxes', 10 );
		$this->add_action( 'save_post', 'save_metabox_data', 10, 2 );
		$this->add_action( 'admin_enqueue_scripts', 'admin_enqueue_scripts' );
	}
	
	public function add_meta_boxes() {
		foreach ($this->post_types as $post_type) {
			add_meta_box( $this->metabox_id, $this->metabox_title, array( $this, 'membership_metabox' ), $post_type, $this->context, $this->priority );
		}
	
	}
	
	public function membership_metabox( $post ) {	
		$view = apply_filters( 'ms_view_membership_metabox', new MS_View_Membership_Metabox() );
		
		$settings = MS_Plugin::instance()->settings;
		$data = array();

		$memberships = MS_Model_Membership::get_memberships();
		foreach( $memberships as $membership ) {
			if( 'post' == $post->post_type ) {
				$data[ $membership->id ]['has_access'] =  $membership->rules['post']->has_access( $post->ID ) || $membership->rules['category']->has_access( $post->ID );
				$data[ $membership->id ]['dripped'] = $membership->rules['post']->has_dripped_rules( $post->ID );
			}
			else {
				$data[ $membership->id ]['has_access'] = $membership->rules['page']->has_access( $post->ID );
				$data[ $membership->id ]['dripped'] = $membership->rules['page']->has_dripped_rules( $post->ID );				
			}
			$data[ $membership->id ]['name'] = $membership->name;
		}
		$view->data = $data;
		$view->read_only = $this->is_read_only( $post->post_type );
		
		$view->render();
	}
	
	public function save_metabox_data( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( is_int( wp_is_post_revision( $post ) ) ) return;
		if ( is_int( wp_is_post_autosave( $post ) ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id )) return;
		if ( ! in_array($post->post_type, $this->post_types) ) return;
		$nonce = MS_View_Membership_Metabox::MEMBERSHIP_METABOX_NONCE;
		if ( empty( $_POST[ $nonce ]) || ! wp_verify_nonce( $_POST[ $nonce ], $nonce ) ) return;
		
		$rule_type = $post->post_type;
		if( ! empty( $_POST['ms_access'] ) && in_array( $post->post_type, $this->post_types ) ) {
			foreach( $_POST['ms_access'] as $membership_id => $has_access ) {
				$membership = MS_Model_Membership::load( $membership_id );
				$rule = $membership->get_rule( $rule_type );
				if( $has_access ) {
					$rule->add_rule_value( $post_id );
				}
				else {
					$rule->remove_rule_value( $post_id );
				}
				
				$membership->set_rule( $rule_type, $rule );
				$membership->save();
			}
			
		}
	}
	
	public function is_read_only( $post_type ) {
		if( 'post' == $post_type && ! MS_Plugin::instance()->addon->post_by_post ) {
			$read_only = true;
		}
		else {
			$read_only = false;
		}
		return $read_only;
	}
	
	public function admin_enqueue_scripts() {
		global $post_type;
		if( in_array( $post_type, $this->post_types ) && ! $this->is_read_only( $post_type ) ) {
			wp_register_script( 'membership-metabox', MS_Plugin::instance()->url. 'app/assets/js/ms-view-membership-metabox.js' );
			wp_enqueue_script( 'membership-metabox' );
		}
		
	}
}

?>