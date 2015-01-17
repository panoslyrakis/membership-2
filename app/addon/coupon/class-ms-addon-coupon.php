<?php
/**
 * An Addon controller.
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
 * Add-On controller for: Coupons
 *
 * @since 1.1.0
 *
 * @package Membership
 * @subpackage Controller
 */
class MS_Addon_Coupon extends MS_Addon {

	/**
	 * The Add-on ID
	 *
	 * @since 1.1.0
	 */
	const ID = 'coupon';

	/**
	 * Saves a reference to the currently processed coupon in the registration
	 * form.
	 *
	 * @since 1.1.0
	 *
	 * @var MS_Addon_Coupon_Model
	 */
	private static $the_coupon = null;

	/**
	 * Initializes the Add-on. Always executed.
	 *
	 * @since  1.1.0
	 */
	public function init() {

	}

	/**
	 * Activates the Add-on logic, only executed when add-on is active.
	 *
	 * @since  1.1.0
	 */
	public function activate() {
		$hook = 'protect-content_page_protected-content-coupons';
		$this->add_action( 'load-' . $hook, 'admin_coupon_manager' );

		$this->add_action( 'admin_print_scripts-' . $hook, 'enqueue_scripts' );
		$this->add_action( 'admin_print_styles-' . $hook, 'enqueue_styles' );

		// Add Coupon menu item to Protected Content menu (Admin)
		$this->add_filter(
			'ms_plugin_menu_pages',
			'menu_item',
			10, 3
		);

		// Tell Protected Content about the Coupon Post Type
		$this->add_filter(
			'ms_plugin_register_custom_post_types',
			'register_ms_posttypes'
		);

		$this->add_filter(
			'ms_model_rule_custom_post_type_group_get_ms_post_types',
			'update_ms_posttypes'
		);

		// Show Coupon columns in the billing list (Admin)
		$this->add_filter(
			'ms_helper_list_table_billing_get_columns',
			'billing_columns',
			10, 2
		);

		$this->add_filter(
			'ms_helper_list_table_billing-column_amount',
			'billing_column_value',
			10, 3
		);

		$this->add_filter(
			'ms_helper_list_table_billing-column_discount',
			'billing_column_value',
			10, 3
		);

		// Show Coupon form in the payment-form (Frontend)
		$this->add_action(
			'ms_view_frontend_payment_after',
			'payment_coupon_form'
		);

		// Update Coupon-Counter when invoice is paid
		$this->add_action(
			'ms_gateway_process_transaction-paid',
			'invoice_paid',
			10, 2
		);

		// Apply Coupon-Discount to invoice
		$this->add_filter(
			'ms_model_invoice_create_before_save',
			'apply_discount',
			10, 2
		);

		// Add/Remove coupon discount in the payment table frontend.
		$this->add_filter(
			'ms_view_frontend_payment_data',
			'process_payment_table',
			10, 4
		);
	}

	/**
	 * Sets or gets the coupon model that is processed in the current
	 * registration form.
	 *
	 * @since  1.1.0
	 * @param  MS_Addon_Coupon_Model $new_value
	 * @return MS_Addon_Coupon_Model
	 */
	static private function the_coupon( $new_value = null ) {
		if ( $new_value !== null ) {
			self::$the_coupon = $new_value;
		} else {
			if ( null === self::$the_coupon ) {
				self::$the_coupon = MS_Factory::load( 'MS_Addon_Coupon_Model' );
			}
		}

		return self::$the_coupon;
	}

	/**
	 * Registers the Add-On
	 *
	 * @since  1.1.0
	 * @param  array $list The Add-Ons list.
	 * @return array The updated Add-Ons list.
	 */
	public function register( $list ) {
		$list[ self::ID ] = (object) array(
			'name' => __( 'Coupon', MS_TEXT_DOMAIN ),
			'description' => __( 'Enable discount coupons.', MS_TEXT_DOMAIN ),
			'icon' => 'wpmui-fa wpmui-fa-ticket',
		);

		return $list;
	}

	/**
	 * Add the Coupons menu item to the protected-content menu.
	 *
	 * @since 1.1.0
	 *
	 * @param array $tabs The current tabs.
	 * @param int $membership_id The membership id to edit
	 * @return array The filtered tabs.
	 */
	public function menu_item( $items, $is_wizard, $controller ) {
		if ( ! $is_wizard ) {
			$menu_item = array(
				'coupons' => array(
					'parent_slug' => $controller::MENU_SLUG,
					'page_title' => __( 'Coupons', MS_TEXT_DOMAIN ),
					'menu_title' => __( 'Coupons', MS_TEXT_DOMAIN ),
					'menu_slug' => $controller::MENU_SLUG . '-coupons',
					'function' => array( $this, 'admin_coupon' ),
				)
			);
			WDev()->array_insert( $items, 'before', 'addon', $menu_item );
		}

		return $items;
	}

	/**
	 * Register the Coupon Post-Type; this is done in MS_Plugin.
	 *
	 * @since  1.1.0
	 * @param  array $cpts
	 * @return array
	 */
	public function register_ms_posttypes( $cpts ) {
		$cpts[MS_Addon_Coupon_Model::$POST_TYPE] = MS_Addon_Coupon_Model::get_register_post_type_args();

		return $cpts;
	}

	/**
	 * Add the Coupon Post-Type to the list of internal post-types
	 *
	 * @since  1.1.0
	 * @param  array $cpts
	 * @return array
	 */
	public function update_ms_posttypes( $cpts ) {
		$cpts[] = MS_Addon_Coupon_Model::$POST_TYPE;

		return $cpts;
	}

	/**
	 * Manages coupon actions.
	 *
	 * Verifies GET and POST requests to manage billing.
	 *
	 * @since 1.0.0
	 */
	public function admin_coupon_manager() {
		$isset = array( 'submit', 'membership_id' );
		$redirect = false;

		if ( self::validate_required( $isset, 'POST', false )
			&& $this->verify_nonce()
			&& $this->is_admin_user()
		) {
			// Save coupon add/edit
			$msg = $this->save_coupon( $_POST );
			$redirect =	add_query_arg(
				array( 'msg' => $msg ),
				remove_query_arg( array( 'coupon_id') )
			);
		} elseif ( self::validate_required( array( 'coupon_id', 'action' ), 'GET' )
			&& $this->verify_nonce( $_GET['action'], 'GET' )
			&& $this->is_admin_user()
		) {
			// Execute table single action.
			$msg = $this->coupon_do_action( $_GET['action'], array( $_GET['coupon_id'] ) );
			$redirect = add_query_arg(
				array( 'msg' => $msg ),
				remove_query_arg( array( 'coupon_id', 'action', '_wpnonce' ) )
			);
		} elseif ( self::validate_required( array( 'coupon_id' ) )
			&& $this->verify_nonce( 'bulk-coupons' )
			&& $this->is_admin_user()
		) {
			// Execute bulk actions.
			$action = $_POST['action'] != -1 ? $_POST['action'] : $_POST['action2'];
			$msg = $this->coupon_do_action( $action, $_POST['coupon_id'] );
			$redirect = add_query_arg( array( 'msg' => $msg ) );
		}

		if ( $redirect ) {
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Perform actions for each coupon.
	 *
	 *
	 * @since 1.0.0
	 * @param string $action The action to perform on selected coupons
	 * @param int[] $coupons The list of coupons ids to process.
	 */
	public function coupon_do_action( $action, $coupon_ids ) {
		if ( ! $this->is_admin_user() ) {
			return;
		}

		if ( is_array( $coupon_ids ) ) {
			foreach ( $coupon_ids as $coupon_id ) {
				switch ( $action ) {
					case 'delete':
						$coupon = MS_Factory::load( 'MS_Addon_Coupon_Model', $coupon_id );
						$coupon->delete();
						break;
				}
			}
		}
	}

	/**
	 * Render the Coupon admin manager.
	 *
	 * @since 1.0.0
	 */
	public function admin_coupon() {
		$isset = array( 'action', 'coupon_id' );

		if ( self::validate_required( $isset, 'GET', false )
			&& 'edit' == $_GET['action']
		) {
			// Edit action view page request
			$coupon_id = ! empty( $_GET['coupon_id'] ) ? $_GET['coupon_id'] : 0;
			$data['coupon'] = MS_Factory::load( 'MS_Addon_Coupon_Model', $coupon_id );
			$data['memberships'] = array( __( 'Any', MS_TEXT_DOMAIN ) );
			$data['memberships'] += MS_Model_Membership::get_membership_names();
			$data['action'] = $_GET['action'];

			$view = MS_Factory::create( 'MS_Addon_Coupon_View_Edit' );
			$view->data = apply_filters( 'ms_addon_coupon_view_edit_data', $data );
			$view->render();
		} else {
			// Coupon admin list page
			$view = MS_Factory::create( 'MS_Addon_Coupon_View_List' );
			$view->render();
		}
	}

	/**
	 * Save coupon using the coupon model.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $fields Coupon fields
	 * @return boolean True in success saving.
	 */
	private function save_coupon( $fields ) {
		$coupon = null;
		$msg = false;

		if ( $this->is_admin_user() ) {
			if ( is_array( $fields ) ) {
				$coupon_id = ( $fields['coupon_id'] ) ? $fields['coupon_id'] : 0;
				$coupon = MS_Factory::load( 'MS_Addon_Coupon_Model', $coupon_id );

				foreach ( $fields as $field => $value ) {
					$coupon->$field = $value;
				}

				$coupon->save();
				$msg = true;
			}
		}

		return apply_filters(
			'ms_addon_coupon_model_save_coupon',
			$msg,
			$fields,
			$coupon,
			$this
		);
	}

	/**
	 * Load Coupon specific styles.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		if ( isset( $_GET['action'] ) && 'edit' == $_GET['action'] ) {
			WDev()->add_ui( 'jquery-ui' );
		}

		do_action( 'ms_addon_coupon_enqueue_styles', $this );
	}

	/**
	 * Load Coupon specific scripts.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( isset( $_GET['action'] ) && 'edit' == $_GET['action'] ) {
			$plugin_url = MS_Plugin::instance()->url;

			wp_enqueue_script( 'jquery-validate' );
			WDev()->add_ui( 'jquery-ui' );

			wp_enqueue_script(
				'ms-view-coupon-edit',
				$plugin_url . '/app/addon/coupon/assets/js/edit.js',
				array( 'jquery' ), $version
			);
		}

		do_action( 'ms_addon_coupon_enqueue_scripts', $this );
	}

	/**
	 * Insert Discount columns in the invoice table.
	 *
	 * @since  1.1.0
	 * @param  array $columns
	 * @param  string $currency
	 * @return array
	 */
	public function billing_columns( $columns, $currency ) {
		$new_columns = array(
			'amount' => sprintf( '%1$s (%2$s)', __( 'Amount', MS_TEXT_DOMAIN ), $currency ),
			'discount' => sprintf( '%1$s (%2$s)', __( 'Discount', MS_TEXT_DOMAIN ), $currency ),
		);

		WDev()->array_insert( $columns, 'after', 'status', $new_columns );

		return $columns;
	}

	/**
	 * Return the column value for the custom billing columns.
	 *
	 * @since  1.1.0
	 * @param  MS_Model $item List item that is parsed.
	 * @param  string $column_name Column that is parsed.
	 * @return string HTML code to display in the cell.
	 */
	public function billing_column_value( $default, $item, $column_name ) {
		$value = $item->$column_name;

		if ( empty( $value ) ) {
			if ( $column_name == 'discount' && empty( $value ) ) {
				$html = '-';
			}
		} else {
			$html = number_format( $value, 2 );
		}

		return $html;
	}

	/**
	 * Output a form where the member can enter a coupon code
	 *
	 * @since  1.0.0
	 * @return string HTML code
	 */
	public function payment_coupon_form( $data ) {
		$coupon = $data['coupon'];
		$coupon_message = '';
		$fields = array();

		if ( ! empty( $data['coupon_valid'] ) ) {
			$fields = array(
				'coupon_code' => array(
					'id' => 'coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
					'value' => $coupon->code,
				),
				'remove_coupon_code' => array(
					'id' => 'remove_coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_SUBMIT,
					'value' => __( 'Remove Coupon', MS_TEXT_DOMAIN ),
					'button_value' => 1,
				),
			);
		} else {
			$fields = array(
				'coupon_code' => array(
					'id' => 'coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_TEXT,
					'value' => $coupon->code,
				),
				'apply_coupon_code' => array(
					'id' => 'apply_coupon_code',
					'type' => MS_Helper_Html::INPUT_TYPE_SUBMIT,
					'value' => __( 'Apply Coupon', MS_TEXT_DOMAIN ),
				),
			);
		}

		$coupon_message = $coupon->coupon_message;

		$fields['membership_id'] = array(
			'id' => 'membership_id',
			'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
			'value' => $data['membership']->id,
		);
		$fields['move_from_id'] = array(
			'id' => 'move_from_id',
			'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
			'value' => $data['ms_relationship']->move_from_id,
		);
		$fields['step'] = array(
			'id' => 'step',
			'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
			'value' => MS_Controller_Frontend::STEP_PAYMENT_TABLE,
		);

		if ( ! empty( $data['coupon_valid'] ) ) {
			$class = 'ms-alert-success';
		} else {
			$class = 'ms-alert-error';
		}

		?>
		<div class="membership-coupon">
			<div class="membership_coupon_form couponbar">
				<form method="post">
					<?php if ( $coupon_message ) : ?>
						<p class="ms-alert-box <?php echo esc_attr( $class ); ?>"><?php
							echo '' . $coupon_message;
						?></p>
					<?php endif; ?>
					<div class="coupon-entry">
						<?php if ( ! isset( $data['coupon_valid'] ) ) : ?>
							<div class="coupon-question"><?php
							_e( 'Have a coupon code?', MS_TEXT_DOMAIN );
							?></div>
						<?php endif;

						foreach ( $fields as $field ) {
							MS_Helper_Html::html_element( $field );
						}
						?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * When an invoice is paid, check if it did use a coupon. If yes, then update
	 * the coupon counter.
	 *
	 * @since  1.1.0
	 * @param  MS_Model_Invoice $invoice
	 */
	public function invoice_paid( $invoice, $member ) {
		if ( $invoice->coupon_id ) {
			$coupon = MS_Factory::load( 'MS_Addon_Coupon_Model', $invoice->coupon_id );
			$coupon->remove_coupon_application( $member->id, $invoice->membership_id );
			$coupon->used++;
			$coupon->save();
		}
	}

	/**
	 * Called by MS_Model_Invoice before a new invoice is saved. We apply the
	 * coupon discount to the total amount, if a coupon was used.
	 *
	 * @since  1.1.0
	 * @param  MS_Model_Invoice $invoice
	 * @param  MS_Model_Membership_Relationship $ms_relationship
	 * @return MS_Model_Invoice
	 */
	public function apply_discount( $invoice, $ms_relationship ) {
		$membership = $ms_relationship->get_membership();
		$member = MS_Factory::load( 'MS_Model_Member', $ms_relationship->user_id );

		if ( isset( $_POST['apply_coupon_code'] ) ) {
			$coupon = apply_filters(
				'ms_addon_coupon_model',
				MS_Addon_Coupon_Model::load_by_coupon_code( $_POST['coupon_code'] )
			);

			if ( $coupon->is_valid_coupon( $membership->id ) ) {
				$coupon->save_coupon_application( $ms_relationship );
			}
		} else {
			$coupon = MS_Addon_Coupon_Model::get_coupon_application(
				$member->id,
				$membership->id
			);

			if ( ! empty( $_POST['remove_coupon_code'] ) ) {
				$coupon->remove_coupon_application( $member->id, $membership->id );
				$coupon = false;
			}
		}
		self::the_coupon( $coupon );

		if ( $coupon ) {
			$invoice->coupon_id = $coupon->id;
			$discount = $coupon->get_discount_value( $ms_relationship );
			$invoice->discount = $discount;

			$note = sprintf(
				__( 'Coupon %s, discount: %s %s. ', MS_TEXT_DOMAIN ),
				$coupon->code,
				$invoice->currency,
				$discount
			);

			$invoice->add_notes( $note );
		}

		return $invoice;
	}

	/**
	 * Add/Remove Coupon from the membership price in the frontend payment table.
	 *
	 * @since  1.1.0
	 * @param  array $data
	 * @param  int $membership_id
	 * @param  MS_Model_Membership_Relationship $ms_relationship
	 * @param  MS_Model_Member $member
	 */
	public function process_payment_table( $data, $membership_id, $ms_relationship, $member ) {
		$data['coupon'] = self::the_coupon();
		$data['coupon_valid'] = false;

		if ( ! empty( $_POST['coupon_code'] ) ) {
			$coupon = MS_Addon_Coupon_Model::get_coupon_application(
				$member->id,
				$membership_id
			);
			self::the_coupon( $coupon );

			if ( $coupon ) {
				$data['coupon_valid'] = $coupon->was_valid();
				$data['coupon'] = $coupon;
			}
		}

		return $data;
	}

}