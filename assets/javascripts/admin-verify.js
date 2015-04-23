jQuery( document ).ready(function( $ ){

    $('#zm_license_activate_button').on('click', function(){

        $this = $(this);
        var action = $this.data('zm_license_action');

        $('#zm_license_action').val( action );
        $this.parents('form').submit();

        return false;
    });
});