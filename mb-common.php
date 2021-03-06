<?php
  // Prevent direct script access
  if ( ! defined( 'MEMBUCKET' ) ) exit;

  $err_OS_NOT_SUPPORTED    = "Your OS is not yet supported, please report this bug to Membucket.io !";
  $err_USER_NOT_AUTHORIZED = "Membucket is available on your system, but not enabled for your account!
Please ask your hosting provider to enable Membucket for your account.";

  function CallAPI($method = "GET", $path = "", $data = false) {
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, "http://127.0.0.1:9999/wells{$path}" );

    $data_string = json_encode( $data );
    if ( "GET" != $method ) {
      curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
      curl_setopt( $curl, CURLOPT_HTTPHEADER,
        array(
          "Content-Type: application/json",
          "Content-Length: " . strlen( $data_string )
        )
      );

      if ( $data ) {
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $data_string );
      }
    } else {
      curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Content-Type: application/json" ) );
      curl_setopt( $curl, CURLOPT_HTTPGET, true );
    }

    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 3 );
    curl_setopt( $curl, CURLOPT_TIMEOUT,        10 );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    $result = curl_exec( $curl );
    curl_close( $curl );
    return $result;
  }

  function _Get_User() {
    $user = posix_getpwuid( posix_geteuid() );
    return $user['name'];
  }

  function MB_Get_User_Key() {
    global $err_OS_NOT_SUPPORTED;
    global $err_USER_NOT_AUTHORIZED;
  
    // Get script directory without trailing slash
    $home = realpath( get_home_path() );

    // On CentOS under default (supported) configuration, the home Directory
    // contains the username, therefore we need the username.
    $user = _Get_User();

    // Currently the username must be in the path
    if ( -1 === strpos( $home, $user ) )
      die( $err_OS_NOT_SUPPORTED );
      
    // Try at most 10 directories
    for ( $i = 0; 10 > $i; $i++ ) {
      if ( "/" === $home ) {
        die( $err_USER_NOT_AUTHORIZED );
        break;
      }

      if ( file_exists( "{$home}/.membucket" ) ) {
        $key = trim( file_get_contents( "{$home}/.membucket" ) );
        break;
      }

      // Traverse up one directory
      $home = realpath ( "{$home}/../" );
    }

    // Key must be set and exactly 128 characters long
    if ( ! isset( $key ) || 128 !== strlen( $key ) ) {
      die( $err_USER_NOT_AUTHORIZED );
    }

    return $key;
  }
  
  require( 'Well.class.php' );

  // List all wells our user has access to
  function MB_Get_System_Wells() {
    $key  = MB_Get_User_Key();
    $user = _Get_User();

    $wells = array();

    $response = CallAPI("GET", "?key={$key}&keyUser={$user}");
    foreach ( json_decode( $response, true ) as $well ) {
      // Server didn't like our key
      if ( "Bad Arguments" == $well ) {
        return "Not Authorized";
      }

      // Skip empty or expired records
      if ( ! $well['ID'] ) continue;

      $wells[] = new Well(
        $well['ID'],
        $well['Name'],
        $well['Running']
      );
    }

    return $wells;
  }
