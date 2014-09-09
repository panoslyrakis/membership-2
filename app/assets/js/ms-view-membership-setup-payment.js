jQuery( document ).ready( function( $ ) {

	var ms_functions = {
		feedback: function( obj ) {
			var data = [], save_obj_selector = '.ms-save-text-wrapper', processing_class = 'ms-processing', init_class = 'ms-init';
			
			if( ! $( obj ).hasClass( processing_class ) ) {
				$( save_obj_selector ).addClass( processing_class );
				$( save_obj_selector ).removeClass( init_class );

				data = $( obj ).data( 'ms' );
				data.value = $( obj ).val();
				$.post( ajaxurl, data, function( response ) {
					$( save_obj_selector ).removeClass( processing_class );
				});
			}
		},
		payment_type: function( obj ) {
			$( '.ms-membership-type' ).hide();
			payment_type = $( obj ).val();

			if ( 'permanent' == payment_type ) {
				$( '.ms-membership-type' ).parents('#ms-membership-type-wrapper').hide();
			} 
			else {
				$( '.ms-membership-type' ).parents('#ms-membership-type-wrapper').show();
			}
			
			$( '#ms-membership-type-' + payment_type + '-wrapper').show();
			if( 'finite' == payment_type || 'date-range' == payment_type ) {
				$( '#ms-membership-on-end-membership-wrapper' ).show();
			}
			else {
				$( '#ms-membership-on-end-membership-wrapper' ).hide();
			}
		},
		is_free: function() {
			if( 1 == $( 'input[name="is_free"]:checked' ).val() ) {
				$( '#ms-payment-settings-wrapper' ).show();
			}
			else {
				$( '#ms-payment-settings-wrapper' ).hide();
			}
			
		}
	}
	$( 'input[name="is_free"]' ).change( function() { ms_functions.is_free() } );

	$( '.chosen-select' ).chosen({disable_search_threshold: 5});
	
	$( '#currency' ).chosen().change( function() { ms_functions.feedback( this ) } ); 
	
	$( '.ms-ajax-update' ).change( function() { ms_functions.feedback( this ) } );
	
	$( '#payment_type').change( function() { ms_functions.payment_type( this ) } );

	$( '#period_date_start' ).datepicker({
        dateFormat : 'yy-mm-dd' //TODO get wp configured date format
    });
	$( '#period_date_end' ).datepicker({
        dateFormat : 'yy-mm-dd' //TODO get wp configured date format
    });
	
	ms_functions.payment_type( $( '#payment_type' ) );
	ms_functions.is_free();
});