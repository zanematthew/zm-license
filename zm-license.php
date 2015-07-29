<?php

// Handles checking of a remote license

if ( ! class_exists( 'ZMLicense' ) ) :
Class ZMLicense {

    /**
     * Various actions and filters to be ran during init.
     *
     * @param
     *
     * @return
     */
    public function __construct( $params=null ){


        $this->namespace = sanitize_key( $params['namespace'] );
        $this->settings_id = $params['settings_id'];
        $this->version = '1.0.0';


        add_action( 'admin_enqueue_scripts', array( &$this, 'adminScripts' ) );
        add_action( 'wp_ajax_zmLicenseAjaxValidate', array( &$this, 'zmLicenseAjaxValidate' ) );


        // Rather than filter the entire settings,
        // lets just filter the single license field
        add_action( 'quilt_' . $this->settings_id . '_below_license', array( &$this, 'belowLicenseSetting' ) );

        // This is ran to check our license
        // This has that double admin notice bug, leaving commented out for now.
        // add_filter( 'quilt_' . $this->namespace . '_sanitize_license', array( &$this, 'sanitizeLicenseSetting'), 10, 2 );

        // Change the button status
        add_filter( 'quilt_' . $this->settings_id . '_license_args', array( &$this, 'extraLicenseArgs') );
    }


    /**
     * Load needed css/js based on the screen id and setting namespace
     *
     * @param
     *
     * @return
     */
    public function adminScripts(){

        $screen = get_current_screen();

        if ( $screen->id == 'settings_page_' . $this->namespace ){
            wp_enqueue_script( 'zm-license-verify', plugin_dir_url( __FILE__ ) .'assets/javascripts/admin-verify.js', array('jquery'), $this->version );
        }
    }


    /**
     * Deactivates the license via remote (updates the remote server),
     * as well as the db entry.
     *
     * @param $license
     *
     * @return Full license data returned from remote
     */
    public function deactivateLicense( $license_info=null ){

        $data = $this->getLicenseRemoteData( 'deactivate_license', $license_info );
        delete_option( $license_info['settings_id'] . '_data' );

        return $data;
    }


    /**
     * Activates the license via remote (updates the remote server),
     * as well as the db entry.
     *
     * @param $license
     *
     * @return Full license data returned from remote
     */
    public function activateLicense( $license_info=null ){

        $license_remote_data = $this->getLicenseRemoteData( 'activate_license', $license_info );
        update_option( $license_info['settings_id'] . '_data', $license_remote_data );

        return $license_remote_data;

    }


    /**
     * Sends the request to the "store" to retrieve the license data
     *
     * @param $action One of the following: activate_license, deactivate_license, check_license
     * @param $license The license to check
     *
     * @return Response(?) Of "stuff", zomg, yeah, f-ing stuff?
     */
    public function getLicenseRemoteData( $action=null, $license_info=null ){

        $api_params = array(
            'edd_action'=> $action,
            'license'   => $license_info['license_key'],
            'item_name' => urlencode( $license_info['download_name'] ), // the name of our product in EDD
            'url'       => home_url()
        );

        $response = wp_remote_get( add_query_arg( $api_params, $license_info['store'] ), array( 'timeout' => 15, 'sslverify' => false ) );

        // make sure the response came back okay
        if ( is_wp_error( $response ) )
            return false;

        // decode the license data
        $json = json_decode( wp_remote_retrieve_body( $response ) );

        return $json;
    }


    /**
     * Retrieves the license data from the $_optoins table. Valid keys are
     * in the following array:
     *
     * Array
     * (
     *     [success] => 1
     *     [license_limit] => 0
     *     [site_count] => 1
     *     [expires] => 2015-09-04 15:40:27
     *     [activations_left] => unlimited
     *     [license] => valid
     *     [item_name] => zM AJAX Login & Register Pro
     *     [payment_id] => 216
     *     [customer_name] => testbuyer
     *     [customer_email] => zane-test-pp-buyer@zanematthew.com
     * )
     *
     * @param $key (string) The key to return
     *
     * @return False or specific key
     */
    public function getLicenseData( $settings_id=null ){

        $data = get_option( $settings_id . '_data' );

        if ( empty( $data ) )
            return false;

        $data = get_object_vars( $data );

        return $data;
    }


    /**
     * Prints a message below the license type input field as seen in the settings.
     *
     * @param
     *
     * @return Prints HTML
     */
    public function belowLicenseSetting( $settings_id ){

        $data = $this->getLicenseData( $settings_id );

        if ( $data ){
            $expires = date( 'D M j H:i', strtotime( $data['expires'] ) );

            $html = sprintf( '%s %s, %s %s, %s %s, %s %s, %s %s',
                __( 'Registered to', $this->namespace ),
                $data['customer_name'],

                __( 'Expires on', $this->namespace ),
                $expires,

                __( 'Site count', $this->namespace ),
                $data['site_count'],

                __( 'Activations left', $this->namespace ),
                $data['activations_left'],

                __( 'For', $this->namespace ),
                $data['item_name']
            );

            // $data['license_limit']
            // $data['customer_email']
        } else {
            $html = __( 'Please activate your license to keep your products up to date.', $this->namespace );
        }

        echo '<p class="description">' . $html . '</p>';
    }


    /**
     *
     *
     * @param $license (string) The license to check
     *
     * @todo This should really return (bool)
     *
     * @return The full license object
     */
    public function isLicenseActive( $license_info=null ){

        return $this->getLicenseRemoteData( 'check_license', $license_info );

    }


    /**
     *
     * @param   $license_action=null,
     * @param   $license_key=null,
     * @param   $previous_license=null
     */
    public function validateLicense( $args=null ){

        $params = wp_parse_args( $args, array(
            'license_action'   => $_POST['license_action'],
            'license_key'      => sanitize_key( $_POST['license_key'] ),
            'previous_license' => sanitize_key( $_POST['previous_license'] ),
            'namespace'        => $this->namespace,
            'download_name'    => $_POST['download_name'],
            'settings_id'      => $_POST['settings_id'],
            'store'            => $_POST['store']
        ) );

        // Activated
        if ( $params['license_action'] && $params['license_action'] == 'license_activate' ){

            $license_obj = $this->activateLicense( $params );

        // Deactivate
        } elseif ( $params['license_action'] && $params['license_action'] == 'license_deactivate' ){

            $license_obj = $this->deactivateLicense( $params );

        }

        // License key has since changed
        elseif ( $params['license_key'] != $params['previous_license'] ) {

            $license_obj = $this->deactivateLicense( $params );

        }

        // Default
        else {

            $license_obj = false;

        }

        return $license_obj;

    }


    /**
     * This is where we intercept our form submission, and handle checking if the license
     * is active, inactive, etc. and we return the status and description ready to be displayed
     * in the WordPress admin.
     *
     * Ensure that the license is active if the user has already activated a license.
     *
     * @param $input The license being passed in via the filter, default is from $_POST
     *
     * @return On error adds settings error, $input
     */
    public function sanitizeLicenseSetting( $input, $field_id ){

        $data = $this->getLicenseData( $field_id );

        if ( empty( $data ) ){

            $message = array(
                'desc' => "Please activate: {$field_id}\n",
                'type' => 'error'
                );

        } else {
            $message = array(
                'desc' => "{$field_id} is active.",
                'type' => 'updated'
                );
        }

        if ( $message )
            add_settings_error( $this->namespace, 'zm_validate_license_error', $message['desc'], $message['type'] );

        return $input;
    }


    public function zmLicenseAjaxValidate(){

        // check ajax refer

        $validated = $this->validateLicense();

        if ( ! $validated ){

            $message = array(
                'type' => 'invalid',
                'button_text' => __( 'Invalid', $this->namespace ),
                'description' => __( '', $this->namespace )
                );

        }

        // deactivated
        elseif ( $validated->license == 'deactivated' ) {

            $message = array(
                'type' => 'deactivated',
                'button_text' => __( 'Successfully Deactivated!', $this->namespace ),
                'description' => __( '', $this->namespace )
                );

        }

        // Valid and activated
        elseif ( $validated->license == 'valid' ) {

            $message = array(
                'type' => 'valid',
                'button_text' => __( 'Successfully Activated!', $this->namespace ),
                'description' => __( '', $this->namespace )
                );

        } elseif ( ! empty( $validated->error ) && $validated->error == 'expired' ) {

            $message = array(
                'type' => 'failure',
                'button_text' => __( 'Expired!', $this->namespace ),
                'description' => __( '', $this->namespace )
                );

        } else {

            $message = array(
                'type' => 'failure',
                'button_text' => __( 'Failure!', $this->namespace ),
                'description' => __( 'An unexpected error occurred.', $this->namespace )
                );

        }

        wp_send_json( $message );

    }


    /**
     * This adds the license data found in the *_options table to
     * the $argument passed via the filter 'zm_settings_license_args'.
     *
     * @param $args     (array) An array of arguments as seen in the filter 'zm_settings_license_args'
     *
     * @return $args    (array) Array of arguments with the extra license data passed in.
     */
    public function extraLicenseArgs( $args ){

        $args['extra']['license_data'] = $this->getLicenseData( $args['settings_id'] );

        return $args;

    }

}
endif;