jQuery( document ).ready(function( $ ){

    $('.zm_license_button').on('click', function(){

        $this = $(this);

        $store_info = $this.data('zm_license_store_info');

        if ( $store_info.length == 0 ){

            console.log( 'use form submission' );
            // var action = $this.data('zm_license_action');

            // $('#zm_license_action').val( action );
            // $this.parents('form').submit();

            // return false;
        } else {

            // add extra params to $_POST via JS?
            console.log( $store_info );
            $.ajax({
                url: ajaxurl,
                dataType: 'json',
                type: 'POST',
                data: {
                    action: 'zmLicenseAjaxValidate'
                    // license_action: $store_info.
                    // license_key: $store_info.
                    // previous_license: $store_info.

                    // namespace: $this.data('namespace'),
                    // _wpnonce: $this.data('quilt_restore_default_nonce')
                },
                success: function( msg ){
                    console.log( msg );
                }
            });
        }
    });
});