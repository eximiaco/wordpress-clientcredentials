<?php

/** Make sure that the WordPress bootstrap has run before continuing. */
require __DIR__ . '/wp-load.php';
require __DIR__ . '/wp-includes/pluggable.php';

$AZUREAD_TOKEN_METADATA = 'https://login.microsoftonline.com/common/v2.0/.well-known/openid-configuration';
$AZUREAD_WORDPRESS_AUDIENCE = 'api://684d1e7b-f1bf-4532-afa6-885ec43f6c8c';
$AZUREAD_WORDPRESS_ROLE = 'Wordpress.Restrict';
$APPLICATION_USER_ID = 1;

// Redirect to HTTPS login if forced to use SSL.
if ( force_ssl_admin() && ! is_ssl() ) {
        if ( 0 === strpos( $_SERVER['REQUEST_URI'], 'http' ) ) {
                wp_safe_redirect( set_url_scheme( $_SERVER['REQUEST_URI'], 'https' ) );
                exit;
        } else {
                wp_safe_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
                exit;
        }
}

if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   
    $full_uri = "https://";   
else  
    $full_uri = "http://";   

$full_uri.= $_SERVER['HTTP_HOST'];
$full_uri.= $_SERVER['REQUEST_URI'];    

$url_components = parse_url($full_uri);
$redirect_uri = $url_components['scheme'] . '://' . $url_components['host'] . '/';

$user = wp_get_current_user();
if($user->ID != 0) {
    header("Location: " . $redirect_uri);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    header("Location: " . $redirect_uri);
    exit;
}

$access_token = $_SERVER['HTTP_AUTHORIZATION'];
if(isset($access_token)) {
    $access_token_parts = explode(' ', $access_token);

    if(count($access_token_parts) > 1) {
        $access_token = $access_token_parts[1];
    }    
}
else {
    parse_str($url_components['query'], $params);
    $access_token = $params['access_token'];
}

if(!isset($access_token)){
    header("Location: " . $redirect_uri);
    exit;
}

try {
    list($token_header, $token_payload, $token_signature) = explode('.', $access_token);

    $token_header_decoded = json_decode(base64_decode($token_header));    
    $token_payload_decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',$token_payload))), true);

    $token_meta = json_decode(file_get_contents($AZUREAD_TOKEN_METADATA), true);
    $keys_url = $token_meta['jwks_uri'];
    
    $keys = (array)json_decode( file_get_contents($keys_url));      
      
    foreach( $keys['keys'] as $key )
    {         
        if( $key->kid === $token_header_decoded->kid )
        {            
            $plainTextKeyCert  = "-----BEGIN CERTIFICATE-----\r\n";
            $plainTextKeyCert .= chunk_split( str_replace( " ", "", $key->x5c[0] ), 64, "\r\n" );
            $plainTextKeyCert .= "-----END CERTIFICATE-----";
            
            $cert              = openssl_x509_read( $plainTextKeyCert );            
            $pubkey = openssl_pkey_get_public( $cert );            
            $token_public_key = openssl_pkey_get_details( $pubkey )['key'];
            break;
        }
    }

    $validation_payload = utf8_decode($token_header . '.' . $token_payload);
    $verified = openssl_verify($validation_payload, base64_decode(strtr($token_signature, '-_', '+/')), $token_public_key,OPENSSL_ALGO_SHA256 );    
    if(!$verified) {
        header("Location: " . $redirect_uri);
        exit;
    }

    $expiration_timestamp = intval($token_payload_decoded['exp']);
    $date_utc = new DateTime("now", new DateTimeZone("UTC"));
    $now_timestamp = $date_utc->getTimestamp();
    if($expiration_timestamp < $now_timestamp){
        header("Location: " . $redirect_uri);
        exit;
    }    

    $audience = $token_payload_decoded['aud'];    
    if($audience !== $AZUREAD_WORDPRESS_AUDIENCE) {
        header("Location: " . $redirect_uri);
        exit;
    }

    $roles = $token_payload_decoded['roles'];
    if (!in_array($AZUREAD_WORDPRESS_ROLE, $roles)) {     
        header("Location: " . $redirect_uri);
        exit;
    }

} catch (Exception $e) { 
    header("Location: " . $redirect_uri);
    exit;
}

wp_set_auth_cookie($APPLICATION_USER_ID, false);

header("Location: " . $redirect_uri);
