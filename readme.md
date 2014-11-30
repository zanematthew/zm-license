Usage
==

1. Require/include the file: `require_once( dirname( __FILE__ ) . '/lib/zm-license/zm-license.php');
1. Require/include the settings: `require_once( dirname( __FILE__ ) . '/lib/zm-settings/zm-settings.php');`
`
1. Add a setting type "license" to the settings: `'type' => 'license'`
1. Instantiate the object at a good time, and pass in the needed params: `$_zm_license = new ZM_License( $params );`

Snippet:
```
// include the license class
// include the settings class

function carrier_constant_setup(){

    // load settings
    // At least one setting should be:
    $settings = array(
        'id' => 'license_key',
        'title' => __('License Key','zm_alr_pro'),
        'type' => 'license'
        );

    global $_zm_license;
    $_zm_license = new ZM_License( array(
        'namespace' => CARRIER_NAMESPACE,
        'version'   => '1',
        'download'  => 'Carrier', // Must match download title in EDD store!
        'store'     => 'http://zanematthew-ve.dev/store',
        'plugin_file' => __FILE__,
        'author' => 'Zane Matthew, Inc.',
        'license' =>  empty( $carrier_settings['license_key'] ) ? null : $carrier_settings['license_key'] ) );
}
add_action( 'plugins_loaded', 'carrier_constant_setup' );
```