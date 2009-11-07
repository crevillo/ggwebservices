<?php
/**
 *
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright 2009
 *
 * @bug for xmlrpc, datetime and base64 parameters will be sent to remote server as strings...
 */

// decode input params

$module = $Params['Module'];
$protocol = strtoupper( $Params['protocol'] );
$remoteserver = $Params['remoteServerName'];

switch( $protocol )
{
    //case 'REST':
    case 'JSONRPC':
    //case 'SOAP':
    case 'XMLRPC':
        $data = file_get_contents( 'php://input' );
        break;
    default:
        /// @todo return an http error 500 or something like that ?
        echo 'Unsupported protocol : ' . $protocol;
        eZExecution::cleanExit();
        die();
}

// analyze request body

$namespaceURI = '';
$serverClass = 'gg' . $protocol . 'Server';
$server = new $serverClass();
$request = $server->parseRequest( $data );
if ( !is_object( $request ) ) /// @todo use is_a instead
{
    $server->showResponse(
        'unknown_function_name',
        $namespaceURI,
        new ggWebservicesFault( ggWebservicesServer::INVALIDREQUESTERROR, ggWebservicesServer::INVALIDREQUESTSTRING ) );
    eZExecution::cleanExit();
    die();
}

// check perms
$user = eZUser::currentUser();
$accessResult = $user->hasAccessTo( 'webservices' , 'proxy' );
$accessWord = $accessResult['accessWord'];
$access = false;
if ( $accessWord == 'yes' )
{
    $access = true;
}
else if ( $accessWord != 'no' ) // with limitation
{
    //$policies = $accessResult['policies'];
    foreach ( $accessResult['policies'] as $key => $policy )
    {
        if ( isset( $policy['RemoteServers'] ) && in_array( $remoteserver, $policy['RemoteServers'] ) )
        {
            $access = true;
            break;
        }
    }
}
if ( !$access )
{
    // Error access denied
    return $module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
}

// execute method, return response as object
// this also does validation of server name
$response = ggeZWebservicesClient::send( $remoteserver, $request->name(), $request->parameters(), true );
$response = reset( $response );
if ( !is_object( $response ) )
{
    $server->showResponse(
        $request->name(),
        $namespaceURI,
        new ggWebservicesFault( ggWebservicesServer::GENERICRESPONSEERROR, ggWebservicesServer::GENERICRESPONSESTRING ) );
}
else
{
    $server->showResponse( $request->name(), $namespaceURI, $response->value() );
}

eZExecution::cleanExit();

?>