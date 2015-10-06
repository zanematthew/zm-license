Description
==
Handles the functionality for activating, and deactivating the license via AJAX.

Note this takes advantage of [Quilt](github.com/zanematthew/quilt)

Usage
==

1. Init the class
```
$a = new ZMLicense( array(
    'namespace'   => MY_PLUGIN_NAMESPACE,
    'settings_id' => 'my_plugin_license' // Where in the settings to get the license from?
) );
```


2. Filter the params for the button that activates/deactivates the license

```
/**
 * Adds the params to the button that checks the license.
 *
 * @since 1.0.0
 */
public function my_plugin_license_filter(){

    global $my_plugin_settings;

    $params = array(
        'namespace'   => MY_PLUGIN_NAMESPACE,
        'version'     => MY_PLUGIN_LICENSE_VERSION,
        'download'    => MY_PLUGIN_LICENSE_PRODUCT_NAME, // Must match download title in EDD store!
        'store'       => MY_PLUGIN_LICENSE_STORE_URL,
        'license'     => $my_plugin_settings['my_plugin_license'],
        'settings_id' => 'my_plugin_license',
        'field_id'    => MY_PLUGIN_NAMESPACE . '_my_plugin_license'
    );

    return $params;
}
add_filter( 'quilt_my_plugin_license_data', array( &$this, 'my_plugin_license_filter' ) );
```