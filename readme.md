Description
==
Handles the functionality for activating, and deactivating the license via AJAX.

Usage
==

1. Init the class
```
$a = new ZMLicense( array(
    'namespace'   => CLIENT_ACCESS_NAMESPACE,
    'settings_id' => 'client_access_license' // Where in the settings to get the license from?
) );
```


2. Filter the params for the button that activates/deactivates the license

```
/**
 * Adds the params to the button that checks the license.
 *
 * @since 1.0.0
 */
public function licenseParams(){

    global $client_access_settings;

    $params = array(
        'namespace'   => CLIENT_ACCESS_NAMESPACE,
        'version'     => CLIENT_ACCESS_LICENSE_VERSION,
        'download'    => CLIENT_ACCESS_LICENSE_PRODUCT_NAME, // Must match download title in EDD store!
        'store'       => CLIENT_ACCESS_LICENSE_STORE_URL,
        'license'     => $client_access_settings['client_access_license'],
        'settings_id' => 'client_access_license',
        'field_id'    => CLIENT_ACCESS_NAMESPACE . '_client_access_license'
    );

    return $params;
}
add_filter( 'client_access_client_access_license_data', array( &$this, 'licenseParams' ) );
```