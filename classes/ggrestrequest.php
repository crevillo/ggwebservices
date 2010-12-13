<?php
/**
 * Class used to wrap 'REST' requests.
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009-2010
 */

class ggRESTRequest extends ggWebservicesRequest
{
    /**
    * No request body by default
    */
    function payload()
    {
        return '';
    }

    /**
    * Final part of url that is built REST style: /methodName?p1=val1&p2=val2
    * Flickr uses ?method=methodName&p1=val1&p2=val2
    */
    function requestURI( $uri )
    {
        $parsed = parse_url( $uri );

        $return = '';
        if ( isset( $parsed['user'] ) )
        {
            $return  .= $parsed['user'] . '@' . $parsed['pass'];
        }
        $return  .= rtrim( $parsed['path'], '/' ) . '/' . $this->Name;
        if ( isset( $parsed['query'] ) )
        {
            $return  .= '?' . $parsed['query'];
            $next = '&';
        }
        else
        {
            $next = '?';
        }

        if ( count( $this->Parameters ) )
        {
            $return .= $next;
            foreach( $this->Parameters as $key => $val )
            {
                if ( is_array( $val ) )
                {
                    foreach ( $val as $vkey => $vval )
                    {
                        $return .= urlencode( $key ) . '[' . urlencode( $vkey ) . ']=' . urlencode( $vval ) . '&';
                    }
                }
                else
                {
                    $return .= urlencode( $key ) . '=' . urlencode( $val ) . '&';
                }
            }
            $return = substr( $return, 0, -1 );
        }
        if ( isset( $parsed['fragment'] ) )
        {
            $return .= '#' . $parsed['fragment'];
        }
        return $return;
    }

    /**
    * Unlike other requests, $rawRequest here is not used, as GET params are used, not POST.
    * This is a small break of the encapsulation principle of the API, but is
    * faster than having to push this into a specific server class.
    * While at it, we examine request headers also to determine response type for later
    */
    function decodeStream( $rawRequest )
    {
        /// recover method name from the last fragment in the URL
        if( isset( $_SERVER["PATH_INFO"] ) )
        {
            $this->Name = $_SERVER["PATH_INFO"];
        }
        else
        {
            /// @todo test if this is the good var to use for both cgi mode and when rewrite rules are in effect
            $this->Name = strrchr( $_SERVER["PHP_SELF"], '/' );
        }
        $this->Name = ltrim( $this->Name, '/' );
        $this->Parameters = $_GET;
        $this->ResponseType = $this->getHttpAccept();
        if ( isset( $_GET[$this->JsonpVar] ) && preg_match( $this->JsonpRegexp, $_GET[$this->JsonpVar] ) )
        {
            $this->JsonpCallback = $_GET[$this->JsonpVar];
        }
        else
        {
            $this->JsonpCallback = false;
        }
        return true;
    }

    /**
     * Same logic as used by ezjscore for the 'call' view, but tries to respect
     * weights in http Accept header
     *
     * @todo (!important) recover $aliasList form the response class
     */
    function getHttpAccept()
    {
        if ( isset( $_GET[$this->FormatVar] ) )
        {
            return $_GET[$this->FormatVar];
        }
        else
        {
            if ( isset( $_POST['http_accept'] ) )
                $acceptList = explode( ',', $_POST['http_accept'] );
            else if ( isset( $_POST['HTTP_ACCEPT'] ) )
                $acceptList = explode( ',', $_POST['HTTP_ACCEPT'] );
            else if ( isset( $_GET['http_accept'] ) )
                $acceptList = explode( ',', $_GET['http_accept'] );
            else if ( isset( $_GET['HTTP_ACCEPT'] ) )
                $acceptList = explode( ',', $_GET['HTTP_ACCEPT'] );
            else if ( isset( $_SERVER['HTTP_ACCEPT'] ) )
                $acceptList = explode( ',', $_SERVER['HTTP_ACCEPT'] );
            else
                $acceptList = false;

            if ( !$acceptList ) // works for false && for empty arrays too
            {
                return '';
            }

            $weightedList = array();
            foreach( $acceptList as $accept )
            {
                if ( preg_match( '/; *q *= *([0-9.]+) *$/', $accept, $matches ) )
                {
                    $accept = explode( ';', $accept );
                    $accept = trim( $accept[0] );
                    $weightedList[$accept] = (float)$matches[1];
                }
                else
                {
                    $weightedList[$accept] = 1.0;
                }
            }
            arsort( $weightedList );
            $weightedList = array_keys( $weightedList );

            // try first we the types that responses can serialize to
            /// @todo add txt, html, php, phps to the list
            $aliasList = array( 'json' => 'application/json', 'javascript' => 'application/json', 'xml' => 'text/xml', /*'html' => 'text/xhtml', 'text' => 'text'*/ );
            foreach( $weightedList as $accept )
            {
                foreach( $aliasList as $alias => $returnType )
                {
                    if ( strpos( $accept, $alias ) !== false )
                    {
                        return $returnType;
                    }
                }
            }

            // request said what it wants, but we cannot give it back as it is not supported by response
            return $weightedList[0];
        }
    }

    /// New method in this subclass
    function requestHeaders()
    {
        /// shall we declare support for insecure stuff such as php and serialized php?
        /// NB: this must be accompanied by code that can decode the format in ggRESTResponse
        return array( 'Accept' => 'application/json, text/xml; q=0.5' );
    }

    /// New method in this subclass
    function responseType()
    {
        return $this->ResponseType;
    }

    function jsonpCallback()
    {
        return $this->JsonpCallback;
    }

    protected $Verb = 'GET';
    protected $ResponseType = '';

    /// name of GET variable used to specify output format.
    /// ContentType comes from ezjszcore. flickr uses 'format'
    protected $FormatVar = 'ContentType';
    /// name of GET variable used for jsonp output
    /// 'callback' is used by Yahoo, possibly google too. Flickr uses 'jsoncallback'
    protected $JsonpVar = 'callback';
    /// Regexp used to avoid XSS attacks on jsonp: only callbacks matching this
    /// expression are accepted.
    /// \w = letters, digits, underscore. See also an alternative here: http://www.json-p.org/
    protected $JsonpRegexp = '/^\w+$/';
    /// where we store the callback received in the request
    protected $JsonpCallback = false;
}

?>