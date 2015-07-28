<?php

set_site_transient( 'update_plugins', null );

if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
    // load our custom updater
    include plugin_dir_path( __FILE__ ) . 'vendor/edd/EDD_SL_Plugin_Updater.php';
}


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

        // @todo __get, __set
        $this->namespace = $this->sanitizeNamespace( $params['namespace'] );
        $this->license_option = $this->namespace . '_license_data';

        $this->version = $params['version'];
        $this->download_name = $params['download'];
        $this->store_url = $params['store'];
        $this->plugin_file = $params['plugin_file'];
        $this->license = $params['license'];
        $this->author = $params['author'];

        add_action( 'admin_enqueue_scripts', array( &$this, 'adminScripts' ) );
        add_action( 'admin_init', array( &$this, 'zmSlPluginUpdater'), 0 );
        add_action( 'wp_ajax_zmLicenseAjaxValidate', array( &$this, 'zmLicenseAjaxValidate' ) );

        add_action( 'zm_license_deactivate_license', array( &$this, 'deactivateLicense' ) );
        add_action( 'zm_license_activate_license', array( &$this, 'activateLicense' ) );
        add_action( 'zm_license_is_active', array( &$this, 'isLicenseActive' ) );

        add_action( 'quilt_' . $this->namespace . '_below_license', array( &$this, 'belowLicenseSetting' ) );

        // This is ran to check our license
        add_filter( 'quilt_' . $this->namespace . '_sanitize_license', array( &$this, 'sanitizeLicenseSetting') );
        add_filter( 'quilt_' . $this->namespace . '_license_args', array( &$this, 'extraLicenseArgs') );
    }


    /**
     * Instantiate the plugin updater
     *
     * @param
     * @todo This should be a dependency injection?
     * @return
     */
    public function zmSlPluginUpdater() {

        // setup the updater
        $edd_updater = new EDD_SL_Plugin_Updater( $this->store_url, $this->plugin_file, array(
                'version'   => $this->version,
                'license'   => $this->license,
                'item_name' => $this->download_name,
                'author'    => $this->author
            )
        );
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
    public function deactivateLicense( $license=null ){

        $data = $this->getLicenseRemoteData( 'deactivate_license', $license );
        $removed = $this->removeLicenseData();

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
    public function activateLicense( $license=null ){

        $license_remote_data = $this->getLicenseRemoteData( 'activate_license', $license );
        $this->updateLicenseData( $license_remote_data );

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
    public function getLicenseRemoteData( $action=null, $license=null ){

        $api_params = array(
            'edd_action'=> $action,
            'license'   => $license,
            'item_name' => urlencode( $this->download_name ), // the name of our product in EDD
            'url'       => home_url()
        );

        $response = wp_remote_get( add_query_arg( $api_params, $this->store_url ), array( 'timeout' => 15, 'sslverify' => false ) );

        // make sure the response came back okay
        if ( is_wp_error( $response ) )
            return false;

        // decode the license data
        return json_decode( wp_remote_retrieve_body( $response ) );
    }


    /**
     * Updates the entry in the *_options table with the new license data
     *
     * @param $license_data (array) The license data to save in the *_options table
     *
     * @return (bool)
     */
    public function updateLicenseData( $license_data=null ){

        return update_option( $this->license_option, $license_data );

    }


    /**
     * Deletes the entry from the *_options table where the license data is stored
     *
     * @param
     *
     * @return (bool)
     */
    public function removeLicenseData(){

        return delete_option( $this->license_option );

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
    public function getLicenseData( $key=null ){
        $data = get_option( $this->license_option );

        if ( empty( $data ) )
            return false;

        $data = get_object_vars( $data );
        if ( ! empty( $key ) ){
            $data = $data[ $key ];
        }

        return $data;
    }


    /**
     * Prints a message below the license type input field as seen in the settings.
     *
     * @param
     *
     * @return Prints HTML
     */
    public function belowLicenseSetting(){
        $data = $this->getLicenseData();

        if ( $data ){
            $expires = date( 'D M j H:i', strtotime( $data['expires'] ) );

            $html = sprintf( '%s %s, %s %s, %s %s, %s %s',
                __( 'Registered to', $this->namespace ),
                $data['customer_name'],

                __( 'Expires on', $this->namespace ),
                $expires,

                __( 'Site count', $this->namespace ),
                $data['site_count'],

                __( 'Activations left', $this->namespace ),
                $data['activations_left']
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
    public function isLicenseActive( $license=null ){

        return $this->getLicenseRemoteData( 'check_license', $license );

    }


    /**
     * Strips lowercase alphanumeric characters, dashes and
     * underscores are allowed. Uppercase characters will be
     * converted to lowercase.
     *
     * @param $license The license to sanitize
     * @return Sanitized license via 'sanitize_key'
     */
    public function sanitize_license( $license=null ){

        return sanitize_key( $license );

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
            'license_key'      => $this->sanitize_license( $_POST['license_key'] ),
            'previous_license' => $this->sanitize_license( $_POST['previous_license'] ),
            'namespace'        => $this->namespace
        ) );

        print_r( $params );
        die();

        // Activated
        if ( $params['license_action'] && $params['license_action'] == 'license_activate' ){

            $license_obj = $this->activateLicense( $params['license_key'] );

            // Not valid
            if ( isset( $license_obj->error ) ){

                switch( $license_obj->error ){
                    case 'missing' :
                    case 'revoked' :
                    case 'no_activations_left' :
                    case 'key_mismatch' :
                    case 'invalid_item_id' :
                    case 'item_name_mismatch' :

                        $message = array(
                            'desc' => sprintf( '%s: %s',
                                __( 'Unable to activate your license', $params['namespace'] ),
                                $license_obj->error
                            ),
                            'type' => 'error'
                        );

                        break;

                    case 'expired' :

                        $message = array(
                            'desc' => sprintf( '%s <br />%s: %s',
                                __( 'Your license has expired. Please contact us to renew your license.', $params['namespace'] ),
                                __( 'Expired on', $params['namespace'] ),
                                date( 'D M j H:i', strtotime( $license_obj->expires ) )
                                ),
                            'type' => 'error'
                        );

                        break;

                    default :

                        $message = array(
                            'desc' => sprintf( '%s: %s',
                                __( 'An unexpected error occurred ', $params['namespace'] ),
                                $license_obj->error
                                ),
                            'type' => 'updated'
                        );

                        break;

                }
            }

            // Valid
            elseif ( isset( $license_obj->license ) && $license_obj->license = 'valid' ){

                $message = array(
                    'desc' => sprintf( "%s: %s<br />\n\n %s: %s<br /> %s: %s<br /> %s: %s<br /> %s: %s<br /> %s: %s",

                        __( 'Your license is', $params['namespace'] ),
                        $license_obj->license,

                        __( 'Expires', $params['namespace'] ),
                        date( 'D M j H:i', strtotime( $license_obj->expires ) ),

                        __( 'Registered To', $params['namespace'] ),
                        $license_obj->customer_name,

                        __( 'License Limit', $params['namespace'] ),
                        $license_obj->license_limit,

                        __( 'Site Count', $params['namespace'] ),
                        $license_obj->site_count,

                        __( 'Activations left', $params['namespace'] ),
                        $license_obj->activations_left
                        ),
                    'type' => 'updated'
                );

            } else {

                $message = array(
                    'desc' => __( 'An unexpected error occurred.', $params['namespace'] ),
                    'type' => 'error'
                    );

            }

        // Deactivate
        } elseif ( $params['license_action'] && $params['license_action'] == 'license_deactivate' ){

            $license_obj = $this->deactivateLicense( $params['license_key'] );
            if ( isset( $license_obj->license ) && $license_obj->license == 'deactivated' ){

                $message = array(
                    'desc' => __( 'Deactivated license', $params['namespace'] ),
                    'type' => 'updated'
                );

            }

        }

        // License key has since changed
        elseif ( $params['license_key'] != $params['previous_license'] ) {

            $license_obj = $this->deactivateLicense( $params['license_key'] );
            $message = array(
                'desc' => __( 'You changed the license after you activated it. license is invalid and has been deactivated.', $params['namespace'] ),
                'type' => 'error'
            );

        }

        // Default
        else {

            $message = array(
                'desc' => false,
                'type' => false
            );

        }

        return $message;

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
    public function sanitizeLicenseSetting( $input ){

        $message = $this->validateLicense();

        if ( $message )
            add_settings_error( $this->namespace, 'zm_validate_license_error', $message['desc'], $message['type'] );

        return $input;
    }


    public function zmLicenseAjaxValidate(){

        $this->validateLicense();

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

        $args['extra']['license_data'] = $this->getLicenseData();
        return $args;

    }


    /**
     * Should return a string that is safe to be used in function names, as a variable, etc.
     * free of illegal characters.
     *
     * @param $namespace (string)   The namespace to sanitize
     * @return $namespace (string)  The namespace free of illegal characters.
     */
    public function sanitizeNamespace( $namespace=null ){

        return str_replace( array('-', ' ' ), '_', $namespace );

    }
}
endif;