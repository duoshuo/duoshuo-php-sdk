<?php
/**
 * WordPress HTTP Class for managing HTTP Transports and making HTTP requests.
 *
 * This class is called for the functionality of making HTTP requests and replaces Snoopy
 * functionality. There is no available functionality to add HTTP transport implementations, since
 * most of the HTTP transports are added and available for use.
 *
 * There are no properties, because none are needed and for performance reasons. Some of the
 * functions are static and while they do have some overhead over functions in PHP4, the purpose is
 * maintainability. When PHP5 is finally the requirement, it will be easy to add the static keyword
 * to the code. It is not as easy to convert a function to a method after enough code uses the old
 * way.
 *
 * Debugging includes several actions, which pass different variables for debugging the HTTP API.
 *
 * @package WordPress
 * @subpackage HTTP
 * @since 2.7.0
 */
class EasyHttp {

	const DEBUG		= false;
	
	static $version	= '0.1';
	static $blockExternal;
	static $accessibleHosts;
	
	static $headerToDesc = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',

		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		226 => 'IM Used',

		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => 'Reserved',
		307 => 'Temporary Redirect',

		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		426 => 'Upgrade Required',

		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		510 => 'Not Extended'
	);

	/**
	 * Send a HTTP request to a URI.
	 *
	 * The body and headers are part of the arguments. The 'body' argument is for the body and will
	 * accept either a string or an array. The 'headers' argument should be an array, but a string
	 * is acceptable. If the 'body' argument is an array, then it will automatically be escaped
	 * using http_build_query().
	 *
	 * The only URI that are supported in the HTTP Transport implementation are the HTTP and HTTPS
	 * protocols. HTTP and HTTPS are assumed so the server might not know how to handle the send
	 * headers. Other protocols are unsupported and most likely will fail.
	 *
	 * The defaults are 'method', 'timeout', 'redirection', 'httpversion', 'blocking' and
	 * 'user-agent'.
	 *
	 * Accepted 'method' values are 'GET', 'POST', and 'HEAD', some transports technically allow
	 * others, but should not be assumed. The 'timeout' is used to sent how long the connection
	 * should stay open before failing when no response. 'redirection' is used to track how many
	 * redirects were taken and used to sent the amount for other transports, but not all transports
	 * accept setting that value.
	 *
	 * The 'httpversion' option is used to sent the HTTP version and accepted values are '1.0', and
	 * '1.1' and should be a string. Version 1.1 is not supported, because of chunk response. The
	 * 'user-agent' option is the user-agent and is used to replace the default user-agent, which is
	 * 'WordPress/WP_Version', where WP_Version is the value from $wp_version.
	 *
	 * 'blocking' is the default, which is used to tell the transport, whether it should halt PHP
	 * while it performs the request or continue regardless. Actually, that isn't entirely correct.
	 * Blocking mode really just means whether the fread should just pull what it can whenever it
	 * gets bytes or if it should wait until it has enough in the buffer to read or finishes reading
	 * the entire content. It doesn't actually always mean that PHP will continue going after making
	 * the request.
	 *
	 * @access public
	 * @since 2.7.0
	 * @todo Refactor this code. The code in this method extends the scope of its original purpose
	 *		and should be refactored to allow for cleaner abstraction and reduce duplication of the
	 *		code. One suggestion is to create a class specifically for the arguments, however
	 *		preliminary refactoring to this affect has affect more than just the scope of the
	 *		arguments. Something to ponder at least.
	 *
	 * @param string $url URI resource.
	 * @param str|array $args Optional. Override the defaults.
	 * @return array|object Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A EasyHttp_Error instance upon error
	 */
	function request( $url, $args = array() ) {
		$defaults = array(
			'method' => 'GET',
			'timeout' => EasyHttp::applyFilters( 'http_request_timeout', 5),
			'redirection' => EasyHttp::applyFilters( 'http_request_redirection_count', 5),
			'httpversion' => EasyHttp::applyFilters( 'http_request_version', '1.0'),
			'user-agent' => EasyHttp::applyFilters( 'http_headers_useragent', 'EasyHttp/' . EasyHttp::$version . '; ' . EasyHttp::getOption( 'siteurl' )  ),
			'blocking' => true,
			'headers' => array(),
			'cookies' => array(),
			'body' => null,
			'compress' => false,
			'decompress' => true,
			'sslverify' => true,
			'stream' => false,
			'filename' => null
		);

		// Pre-parse for the HEAD checks.
		$args = EasyHttp::parseArgs( $args );

		// By default, Head requests do not cause redirections.
		if ( isset($args['method']) && 'HEAD' == $args['method'] )
			$defaults['redirection'] = 0;

		$r = EasyHttp::parseArgs( $args, $defaults );
		$r = EasyHttp::applyFilters( 'http_request_args', $r, $url );

		// Certain classes decrement this, store a copy of the original value for loop purposes.
		$r['_redirection'] = $r['redirection'];

		// Allow plugins to short-circuit the request
		$pre = EasyHttp::applyFilters( 'pre_http_request', false, $r, $url );
		if ( false !== $pre )
			return $pre;

		$arrURL = parse_url( $url );

		if ( empty( $url ) || empty( $arrURL['scheme'] ) )
			return new EasyHttp_Error('http_request_failed', __('A valid URL was not provided.'));

		if ( $this->block_request( $url ) )
			return new EasyHttp_Error( 'http_request_failed', __( 'User has blocked requests through HTTP.' ) );

		// Determine if this is a https call and pass that on to the transport functions
		// so that we can blacklist the transports that do not support ssl verification
		$r['ssl'] = $arrURL['scheme'] == 'https' || $arrURL['scheme'] == 'ssl';

		// Determine if this request is to OUR install of WordPress
		$r['local'] = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] == $arrURL['host'] || 'localhost' == $arrURL['host'];
		
		// If we are streaming to a file but no filename was given drop it in the WP temp dir
		// and pick it's name using the basename of the $url
		// 如果$r['stream'] 必须指定一个$r['filename']
		//if ( $r['stream']  && empty( $r['filename'] ) )
		//	$r['filename'] = get_temp_dir() . basename( $url );
		
		// Force some settings if we are streaming to a file and check for existence and perms of destination directory
		if ( $r['stream'] ) {
			$r['blocking'] = true;
			if ( ! is_writable( dirname( $r['filename'] ) ) )
				return new EasyHttp_Error( 'http_request_failed', __( 'Destination directory for file streaming does not exist or is not writable.' ) );
		}

		if ( $r['headers'] === null )
			$r['headers'] = array();

		if ( ! is_array( $r['headers'] ) ) {
			$processedHeaders = EasyHttp::processHeaders( $r['headers'] );
			$r['headers'] = $processedHeaders['headers'];
		}

		if ( isset( $r['headers']['User-Agent'] ) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset( $r['headers']['User-Agent'] );
		}

		if ( isset( $r['headers']['user-agent'] ) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset( $r['headers']['user-agent'] );
		}

		// Construct Cookie: header if any cookies are set
		EasyHttp::buildCookieHeader( $r );

		if ( EasyHttp_Encoding::is_available() )
			$r['headers']['Accept-Encoding'] = EasyHttp_Encoding::accept_encoding();

		if ( empty($r['body']) ) {
			$r['body'] = null;
			// Some servers fail when sending content without the content-length header being set.
			// Also, to fix another bug, we only send when doing POST and PUT and the content-length
			// header isn't already set.
			if ( ($r['method'] == 'POST' || $r['method'] == 'PUT') && ! isset( $r['headers']['Content-Length'] ) )
				$r['headers']['Content-Length'] = 0;
		} else {
			if ( is_array( $r['body'] ) || is_object( $r['body'] ) ) {
				$r['body'] = http_build_query( $r['body'], null, '&' );
				if ( ! isset( $r['headers']['Content-Type'] ) )
					$r['headers']['Content-Type'] = 'application/x-www-form-urlencoded; charset=' . EasyHttp::getOption( 'blog_charset' );
				$r['headers']['Content-Length'] = strlen( $r['body'] );
			}

			if ( ! isset( $r['headers']['Content-Length'] ) && ! isset( $r['headers']['content-length'] ) )
				$r['headers']['Content-Length'] = strlen( $r['body'] );
		}

		return $this->_dispatch_request($url, $r);
	}

	/**
	 * Tests which transports are capable of supporting the request.
	 *
	 * @since 3.2.0
	 * @access private
	 *
	 * @param array $args Request arguments
	 * @param string $url URL to Request
	 *
	 * @return string|false Class name for the first transport that claims to support the request. False if no transport claims to support the request.
	 */
	public function _get_first_available_transport( $args, $url = null ) {
		$request_order = array( 'curl', 'streams', 'fsockopen' );

		// Loop over each transport on each HTTP request looking for one which will serve this request's needs
		foreach ( $request_order as $transport ) {
			$class = 'EasyHttp_' . ucfirst($transport);

			// Check to see if this transport is a possibility, calls the transport statically
			if ( !call_user_func( array( $class, 'test' ), $args, $url ) )
				continue;

			return $class;
		}

		return false;
	}

	/**
	 * Dispatches a HTTP request to a supporting transport.
	 *
	 * Tests each transport in order to find a transport which matches the request arguments.
	 * Also caches the transport instance to be used later.
	 *
	 * The order for blocking requests is cURL, Streams, and finally Fsockopen.
	 * The order for non-blocking requests is cURL, Streams and Fsockopen().
	 *
	 * There are currently issues with "localhost" not resolving correctly with DNS. This may cause
	 * an error "failed to open stream: A connection attempt failed because the connected party did
	 * not properly respond after a period of time, or established connection failed because [the]
	 * connected host has failed to respond."
	 *
	 * @since 3.2.0
	 * @access private
	 *
	 * @param string $url URL to Request
	 * @param array $args Request arguments
	 * @return array|object Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A EasyHttp_Error instance upon error
	 */
	private function _dispatch_request( $url, $args ) {
		static $transports = array();

		$class = $this->_get_first_available_transport( $args, $url );
		if ( !$class )
			return new EasyHttp_Error( 'http_failure', __( 'There are no HTTP transports available which can complete the requested request.' ) );

		// Transport claims to support request, instantiate it and give it a whirl.
		if ( empty( $transports[$class] ) )
			$transports[$class] = new $class;

		$response = $transports[$class]->request( $url, $args );

		//暂时不支持do_action
		//do_action( 'http_api_debug', $response, 'response', $class, $args, $url );

		if ( $response instanceof EasyHttp_Error )
			return $response;

		return EasyHttp::applyFilters( 'http_response', $response, $args, $url );
	}

	/**
	 * Uses the POST HTTP method.
	 *
	 * Used for sending data that is expected to be in the body.
	 *
	 * @access public
	 * @since 2.7.0
	 *
	 * @param string $url URI resource.
	 * @param str|array $args Optional. Override the defaults.
	 * @return array|object Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A EasyHttp_Error instance upon error
	 */
	function post($url, $args = array()) {
		$defaults = array('method' => 'POST');
		$r = EasyHttp::parseArgs( $args, $defaults );
		return $this->request($url, $r);
	}

	/**
	 * Uses the GET HTTP method.
	 *
	 * Used for sending data that is expected to be in the body.
	 *
	 * @access public
	 * @since 2.7.0
	 *
	 * @param string $url URI resource.
	 * @param str|array $args Optional. Override the defaults.
	 * @return array|object Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A EasyHttp_Error instance upon error
	 */
	function get($url, $args = array()) {
		$defaults = array('method' => 'GET');
		$r = EasyHttp::parseArgs( $args, $defaults );
		return $this->request($url, $r);
	}

	/**
	 * Uses the HEAD HTTP method.
	 *
	 * Used for sending data that is expected to be in the body.
	 *
	 * @access public
	 * @since 2.7.0
	 *
	 * @param string $url URI resource.
	 * @param str|array $args Optional. Override the defaults.
	 * @return array|object Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A EasyHttp_Error instance upon error
	 */
	function head($url, $args = array()) {
		$defaults = array('method' => 'HEAD');
		$r = EasyHttp::parseArgs( $args, $defaults );
		return $this->request($url, $r);
	}

	/**
	 * Parses the responses and splits the parts into headers and body.
	 *
	 * @access public
	 * @static
	 * @since 2.7.0
	 *
	 * @param string $strResponse The full response string
	 * @return array Array with 'headers' and 'body' keys.
	 */
	function processResponse($strResponse) {
		$res = explode("\r\n\r\n", $strResponse, 2);

		return array('headers' => $res[0], 'body' => isset($res[1]) ? $res[1] : '');
	}

	/**
	 * Transform header string into an array.
	 *
	 * If an array is given then it is assumed to be raw header data with numeric keys with the
	 * headers as the values. No headers must be passed that were already processed.
	 *
	 * @access public
	 * @static
	 * @since 2.7.0
	 *
	 * @param string|array $headers
	 * @return array Processed string headers. If duplicate headers are encountered,
	 * 					Then a numbered array is returned as the value of that header-key.
	 */
	public static function processHeaders($headers) {
		// split headers, one per array element
		if ( is_string($headers) ) {
			// tolerate line terminator: CRLF = LF (RFC 2616 19.3)
			$headers = str_replace("\r\n", "\n", $headers);
			// unfold folded header fields. LWS = [CRLF] 1*( SP | HT ) <US-ASCII SP, space (32)>, <US-ASCII HT, horizontal-tab (9)> (RFC 2616 2.2)
			$headers = preg_replace('/\n[ \t]/', ' ', $headers);
			// create the headers array
			$headers = explode("\n", $headers);
		}

		$response = array('code' => 0, 'message' => '');

		// If a redirection has taken place, The headers for each page request may have been passed.
		// In this case, determine the final HTTP header and parse from there.
		for ( $i = count($headers)-1; $i >= 0; $i-- ) {
			if ( !empty($headers[$i]) && false === strpos($headers[$i], ':') ) {
				$headers = array_splice($headers, $i);
				break;
			}
		}

		$cookies = array();
		$newheaders = array();
		foreach ( (array) $headers as $tempheader ) {
			if ( empty($tempheader) )
				continue;

			if ( false === strpos($tempheader, ':') ) {
				$stack = explode(' ', $tempheader, 3);
				$stack[] = '';
				list( , $response['code'], $response['message']) = $stack;
				continue;
			}

			list($key, $value) = explode(':', $tempheader, 2);

			if ( !empty( $value ) ) {
				$key = strtolower( $key );
				if ( isset( $newheaders[$key] ) ) {
					if ( !is_array($newheaders[$key]) )
						$newheaders[$key] = array($newheaders[$key]);
					$newheaders[$key][] = trim( $value );
				} else {
					$newheaders[$key] = trim( $value );
				}
				if ( 'set-cookie' == $key )
					$cookies[] = new EasyHttp_Cookie( $value );
			}
		}

		return array('response' => $response, 'headers' => $newheaders, 'cookies' => $cookies);
	}

	/**
	 * Takes the arguments for a ::request() and checks for the cookie array.
	 *
	 * If it's found, then it's assumed to contain EasyHttp_Cookie objects, which are each parsed
	 * into strings and added to the Cookie: header (within the arguments array). Edits the array by
	 * reference.
	 *
	 * @access public
	 * @version 2.8.0
	 * @static
	 *
	 * @param array $r Full array of args passed into ::request()
	 */
	public static function buildCookieHeader( &$r ) {
		if ( ! empty($r['cookies']) ) {
			$cookies_header = '';
			foreach ( (array) $r['cookies'] as $cookie ) {
				$cookies_header .= $cookie->getHeaderValue() . '; ';
			}
			$cookies_header = substr( $cookies_header, 0, -2 );
			$r['headers']['cookie'] = $cookies_header;
		}
	}

	/**
	 * Decodes chunk transfer-encoding, based off the HTTP 1.1 specification.
	 *
	 * Based off the HTTP http_encoding_dechunk function. Does not support UTF-8. Does not support
	 * returning footer headers. Shouldn't be too difficult to support it though.
	 *
	 * @todo Add support for footer chunked headers.
	 * @access public
	 * @since 2.7.0
	 * @static
	 *
	 * @param string $body Body content
	 * @return string Chunked decoded body on success or raw body on failure.
	 */
	function chunkTransferDecode($body) {
		$body = str_replace(array("\r\n", "\r"), "\n", $body);
		// The body is not chunked encoding or is malformed.
		if ( ! preg_match( '/^[0-9a-f]+(\s|\n)+/mi', trim($body) ) )
			return $body;

		$parsedBody = '';
		//$parsedHeaders = array(); Unsupported

		while ( true ) {
			$hasChunk = (bool) preg_match( '/^([0-9a-f]+)(\s|\n)+/mi', $body, $match );

			if ( $hasChunk ) {
				if ( empty( $match[1] ) )
					return $body;

				$length = hexdec( $match[1] );
				$chunkLength = strlen( $match[0] );

				$strBody = substr($body, $chunkLength, $length);
				$parsedBody .= $strBody;

				$body = ltrim(str_replace(array($match[0], $strBody), '', $body), "\n");

				if ( "0" == trim($body) )
					return $parsedBody; // Ignore footer headers.
			} else {
				return $body;
			}
		}
	}

	/**
	 * Block requests through the proxy.
	 *
	 * Those who are behind a proxy and want to prevent access to certain hosts may do so. This will
	 * prevent plugins from working and core functionality, if you don't include api.wordpress.org.
	 *
	 * You block external URL requests by defining WP_HTTP_BLOCK_EXTERNAL as true in your wp-config.php
	 * file and this will only allow localhost and your blog to make requests. The constant
	 * WP_ACCESSIBLE_HOSTS will allow additional hosts to go through for requests. The format of the
	 * WP_ACCESSIBLE_HOSTS constant is a comma separated list of hostnames to allow, wildcard domains
	 * are supported, eg *.wordpress.org will allow for all subdomains of wordpress.org to be contacted.
	 *
	 * @since 2.8.0
	 * @link http://core.trac.wordpress.org/ticket/8927 Allow preventing external requests.
	 * @link http://core.trac.wordpress.org/ticket/14636 Allow wildcard domains in WP_ACCESSIBLE_HOSTS
	 *
	 * @param string $uri URI of url.
	 * @return bool True to block, false to allow.
	 */
	function block_request($uri) {
		// We don't need to block requests, because nothing is blocked.
		if ( ! isset( self::$blockExternal ) || ! self::$blockExternal )
			return false;

		// parse_url() only handles http, https type URLs, and will emit E_WARNING on failure.
		// This will be displayed on blogs, which is not reasonable.
		$check = @parse_url($uri);

		/* Malformed URL, can not process, but this could mean ssl, so let through anyway.
		 *
		 * This isn't very security sound. There are instances where a hacker might attempt
		 * to bypass the proxy and this check. However, the reason for this behavior is that
		 * WordPress does not do any checking currently for non-proxy requests, so it is keeps with
		 * the default unsecure nature of the HTTP request.
		 */
		if ( $check === false )
			return false;

		$home = parse_url( EasyHttp::getOption('siteurl') );

		// Don't block requests back to ourselves by default
		if ( $check['host'] == 'localhost' || $check['host'] == $home['host'] )
			return EasyHttp::applyFilters('block_local_requests', false);

		if ( !isset(self::$accessibleHosts) )
			return true;

		static $accessible_hosts;
		static $wildcard_regex = false;
		if ( null == $accessible_hosts ) {
			$accessible_hosts = preg_split('|,\s*|', self::$accessibleHosts);

			if ( false !== strpos(self::$accessibleHosts, '*') ) {
				$wildcard_regex = array();
				foreach ( $accessible_hosts as $host )
					$wildcard_regex[] = str_replace('\*', '[\w.]+?', preg_quote($host, '/'));
				$wildcard_regex = '/^(' . implode('|', $wildcard_regex) . ')$/i';
			}
		}

		if ( !empty($wildcard_regex) )
			return !preg_match($wildcard_regex, $check['host']);
		else
			return !in_array( $check['host'], $accessible_hosts ); //Inverse logic, If its in the array, then we can't access it.

	}

	static function make_absolute_url( $maybe_relative_path, $url ) {
		if ( empty( $url ) )
			return $maybe_relative_path;

		// Check for a scheme
		if ( false !== strpos( $maybe_relative_path, '://' ) )
			return $maybe_relative_path;

		if ( ! $url_parts = @parse_url( $url ) )
			return $maybe_relative_path;

		if ( ! $relative_url_parts = @parse_url( $maybe_relative_path ) )
			return $maybe_relative_path;

		$absolute_path = $url_parts['scheme'] . '://' . $url_parts['host'];
		if ( isset( $url_parts['port'] ) )
			$absolute_path .= ':' . $url_parts['port'];

		// Start off with the Absolute URL path
		$path = ! empty( $url_parts['path'] ) ? $url_parts['path'] : '/';

		// If the it's a root-relative path, then great
		if ( ! empty( $relative_url_parts['path'] ) && '/' == $relative_url_parts['path'][0] ) {
			$path = $relative_url_parts['path'];

		// Else it's a relative path
		} elseif ( ! empty( $relative_url_parts['path'] ) ) {
			// Strip off any file components from the absolute path
			$path = substr( $path, 0, strrpos( $path, '/' ) + 1 );

			// Build the new path
			$path .= $relative_url_parts['path'];

			// Strip all /path/../ out of the path
			while ( strpos( $path, '../' ) > 1 ) {
				$path = preg_replace( '![^/]+/\.\./!', '', $path );
			}

			// Strip any final leading ../ from the path
			$path = preg_replace( '!^/(\.\./)+!', '', $path );
		}

		// Add the Query string
		if ( ! empty( $relative_url_parts['query'] ) )
			$path .= '?' . $relative_url_parts['query'];

		return $absolute_path . '/' . ltrim( $path, '/' );
	}
	
	/**
	 * Merge user defined arguments into defaults array.
	 *
	 * This function is used throughout WordPress to allow for both string or array
	 * to be merged into another array.
	 *
	 * @since 2.2.0
	 *
	 * @param string|array $args Value to merge with $defaults
	 * @param array $defaults Array that serves as the defaults.
	 * @return array Merged user defined values with defaults.
	 */
	static function parseArgs( $args, $defaults = '' ) {
		if ( is_object( $args ) )
			$r = get_object_vars( $args );
		elseif ( is_array( $args ) )
			$r =& $args;
		else{
			parse_str( $args, $r );
			if ( get_magic_quotes_gpc() )
				$r = EasyHttp::stripslashesDeep( $r );
			//$r = EasyHttp::applyFilters( 'wp_parse_str', $r );
		}
	
		if ( is_array( $defaults ) )
			return array_merge( $defaults, $r );
		return $r;
	}
	
	/**
	 * Navigates through an array and removes slashes from the values.
	 *
	 * If an array is passed, the array_map() function causes a callback to pass the
	 * value back to the function. The slashes from this value will removed.
	 *
	 * @since 2.0.0
	 *
	 * @param array|string $value The array or string to be stripped.
	 * @return array|string Stripped array (or string in the callback).
	 */
	static function stripslashesDeep($value) {
		if ( is_array($value) ) {
			$value = array_map(array('EasyHttp','stripslashesDeep'), $value);
		} elseif ( is_object($value) ) {
			$vars = get_object_vars( $value );
			foreach ($vars as $key=>$data) {
				$value->{$key} = EasyHttp::stripslashesDeep( $data );
			}
		} else {
			$value = stripslashes($value);
		}
	
		return $value;
	}
	
	static function applyFilters($name, $value){
		return $value;
	}
	
	static function getOption($option){
		switch($option){
			case 'charset':
				return 'utf-8';
			default:
				return null;
		}
	}
}
