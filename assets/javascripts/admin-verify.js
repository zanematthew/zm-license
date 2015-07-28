jQuery( document ).ready(function( $ ){

    $('.zm_license_button').on('click', function(){

        var $this = $(this),
            $store_info = $this.data('zm_license_store_info'),
            $action = $this.data('zm_license_action'),
            $field_id = '#' + $store_info.field_id,
            $current_value = $( $field_id ).val();

        console.log( $current_value );

        if ( $store_info.length == 0 ){

            $('#zm_license_action').val( $action );
            $this.parents('form').submit();

            return false;

        } else {

            $this.attr('disabled', true);

            $.ajax({
                url: ajaxurl,
                dataType: 'json',
                type: 'POST',
                data: {
                    action: 'zmLicenseAjaxValidate',
                    license_action: $action,
                    license_key: $store_info.license,
                    previous_license: $current_value,
                    download_name: $store_info.download,
                    settings_id: $store_info.settings_id,
                    store: $store_info.store
                    // namespace: $this.data('namespace'),
                    // _wpnonce: $this.data('quilt_restore_default_nonce')
                },
                success: function( msg ){
                    console.log( msg );
                    $this.val( msg.button_text );

                    if ( msg.type == 'deactivated' ){
                        $this.data( 'zm_license_action', 'license_activate' );
                    }

                    if ( msg.type == 'valid' ){
                        $this.data( 'zm_license_action', 'license_deactivate' );
                    }

                    $this.attr('disabled', false);

                }
            });
        }
    });
});