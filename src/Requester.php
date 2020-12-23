<?php
/**
 * ProSupl - Backend
 * © 2020 Václav Maroušek
 *
 * requester.php
 */

namespace OpenSOS;

class Requester {
    /** @var array $cookies */
    private $cookies = [];

    /** @var array $requestHeaders */
    private $requestHeaders = [
        //Default headers
        'Host'                      => NULL,
        'Connection'                => 'keep-alive',
        'Pragma'                    => 'no-cache',
        'Cache-Control'             => 'no-cache',
        'DNT'                       => '1',
        'Upgrade-Insecure-Requests' => '1',
        'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36',
        'Sec-Fetch-User'            => '?1',
        'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
        'Sec-Fetch-Site'            => 'same-site',
        'Sec-Fetch-Mode'            => 'navigate',
        'Referer'                   => NULL,
        'Accept-Language'           => 'cs-CZ,cs;q=0.9,en;q=0.8'
    ];

    /** @var int $responseCode */
    private $responseCode;

    /** @var array $responseHeaders */
    private $responseHeaders = [];

    /** @var string $responseBody */
    private $responseBody;


    /** @var string $followLocation */
    private $followLocation = true;

    /** @var string $lastURI */
    private $lastURI;

    /** @var resource $curlHandle */
    private $curlHandle;

    /**
     * Requester constructor.
     */
    public function __construct() {
        //Initialize cURL
        $this->curlHandle = curl_init();
    }

    /**
     * Requester destructor.
     */
    public function __destruct() {
        //Destroy cURL
        curl_close($this->curlHandle);
    }

    /**
     * Performs a requests and parses the response
     * @param string $uri
     * @param string $method
     * @param string|null $post
     * @param bool $isAJAX
     * @return string
     */
    public function Request(string $uri, string $method = 'GET', string $post = NULL, bool $isAJAX = false): string {
        //Set URI
        if (empty($this->htProxy))
            curl_setopt($this->curlHandle, CURLOPT_URL, $uri);
        else
            curl_setopt($this->curlHandle, CURLOPT_URL, $this->htProxy . '?url=' . urlencode($uri));

        //Headers
        $this->SetRequestHeader('Referer', $uri);
        $this->SetRequestHeader('Host', parse_url($uri, PHP_URL_HOST));

        $rootPos = strpos($uri, "/", strpos($uri, "/") + 2);
        $this->SetRequestHeader('Origin', substr($uri, 0, $rootPos));

        //cURL settings
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->curlHandle, CURLOPT_HEADER, true);

        // Request method
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, $method);

        //Post fields
        if ($method === 'POST' AND !empty($post)) {
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $post);
        }

        /*
         * Headers
         * */
        $requestHeaders = [];

        if (!empty($this->requestHeaders)) {
            foreach ($this->requestHeaders as $h => $header) {
                $requestHeaders[] = $h . ': ' . $header;
            }
        }

        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $requestHeaders);

        /*
         * Cookies
         * */
        $requestCookies = array();

        if (!empty($this->cookies)) {
            foreach ($this->cookies as $c => $cookie) {
                $requestCookies[] = $c . '=' . $cookie;
            }
        }

        if (!empty($this->cookies))
            curl_setopt($this->curlHandle, CURLOPT_COOKIE, implode('; ', $requestCookies));

        /*
         * Perform request
         * */
        $response = curl_exec($this->curlHandle);

        $this->lastURI = $uri;

        /*
         * Parse response
         * */
        $responseHeaderSize = curl_getinfo($this->curlHandle, CURLINFO_HEADER_SIZE);

        $this->responseCode    = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
        $responseHeadersString = substr($response, 0, $responseHeaderSize);
        $this->responseBody    = substr($response, $responseHeaderSize);

        //Parse header
        $this->responseHeaders = [];
        $responseHeaders = explode("\n", $responseHeadersString);

        foreach ($responseHeaders as $responseHeader) {
            if (!empty($responseHeader)) {

                $colonPos = strpos($responseHeader, ':');

                if ($colonPos !== false) {
                    $this->responseHeaders[trim(substr($responseHeader, 0, $colonPos))] = trim(substr($responseHeader, $colonPos + 1));
                }
            }
        }

        //Parse cookies
        $responseCookiesString = $this->GetResponseHeader('set-cookie');

        if (!empty($responseCookiesString)) {
            $responseCookies = explode(';', $responseCookiesString);

            foreach ($responseCookies as $responseCookie) {
                if (!empty($responseCookie)) {
                    $equalPos = strpos($responseCookie, '=');

                    if ($equalPos !== false) { //ignore attributes
                        $cookieName = trim(substr($responseCookie, 0, $equalPos));
                        $cookieValue = trim(substr($responseCookie, $equalPos + 1));

                        $cookieNameLowercase = strtolower($cookieName);

                        if (
                            $cookieNameLowercase != 'expires' AND
                            $cookieNameLowercase != 'max-age' AND
                            $cookieNameLowercase != 'domain' AND
                            $cookieNameLowercase != 'path' AND
                            $cookieNameLowercase != 'samesite' AND
                            $cookieNameLowercase != 'path'
                        )
                            $this->cookies[$cookieName] = $cookieValue;
                    }
                }
            }
        }

        /*
         * Handle redirects
         * */
        $locationHeader = $this->GetResponseHeader('location');

        if (
            $this->followLocation AND
            $this->responseCode >= 300 AND
            $this->responseCode < 400 AND
            $locationHeader
        ) {
            $location = $locationHeader;

            if ($locationHeader[0] == '?' OR $locationHeader[0] == '#') { //fixme: # není potřeba?! jelikož se při requestu neodesílá
                //Document relative query (https://example.com/test/hello.html + ?argument=1#test = https://example.com/test/hello.html?argument=1#test)
                $location = $uri . $location;
            } else if ($locationHeader[0] == '/') {
                if (isset($locationHeader[1]) AND $locationHeader[1]  == '/') {
                    //Protocol relative path (https://example.com/test/hello.html + //hello/goodbye.html = https://hello/goodbye.html)
                    $protocol = substr($uri, 0, 5);

                    if ($protocol == "https") {
                        $location = "https:" . $locationHeader;
                    } else if ($protocol == "http:") {
                        $location = "http:" . $locationHeader;
                    }
                } else {
                    //Root relative path (https://example.com/test/hello.html + /hello/goodbye.html = https://example.com/hello/goodbye.html)
                    $rootPos = strpos($uri, "/", strpos($uri, "/") + 2);

                    $location = substr($uri, 0, $rootPos) . $locationHeader;
                }
            } else {
                if (substr($locationHeader, 0, 4) == "http") {
                    //Absolute path (https://example.com/test/hello.html + http://hello/goodbye.html = http://hello/goodbye.html)
                    $location = $locationHeader;
                } else {
                    //Folder relative path (https://example.com/test/hello.html + hello/goodbye.html = https://example.com/test/hello/goodbye.html)
                    $location = substr($uri, 0, strrpos($uri, '/')) . '/' . $locationHeader;
                }
            }

            /**
             * HTTP Status Code  HTTP Version	Temporary / Permanent	Cacheable	    Request Method Subsequent Request
             * 301	             HTTP/1.0       Permanent	            Yes         	GET / POST may change
             * 302	             HTTP/1.0       Temporary	            not by default	GET / POST may change
             * 303	             HTTP/1.1	    Temporary	            never	        always GET
             * 307	             HTTP/1.1	    Temporary	            not by default	may not change
             * 308	             HTTP/1.1	    Permanent	            by default	    may not change
             */

            //Perform redirect
            if ($this->responseCode == 301 OR
                $this->responseCode == 302 OR
                $this->responseCode == 303) {
                return $this->Request($location, METHOD_GET);
            } else {
                return $this->Request($location, $method, $post);
            }
        }

        return $this->responseBody;
    }


    public function GetCookies() {
        return $this->cookies;
    }

    public function GetCookie($key) {
        if (!empty($this->cookies[$key]))
            return $this->cookies[$key];
        else
            return NULL;
    }

    public function SetCookie($key, $value) {
        $this->cookies[$key] = $value;
    }

    public function SetCookies($cookies) {
        if (!empty($cookies))
            foreach ($cookies as $key => $value)
                $this->cookies[$key] = $value;
    }


    public function GetRequestHeaders() {
        return $this->requestHeaders;
    }

    public function GetRequestHeader($key) {
        $return = null;

        foreach ($this->requestHeaders as $h => $requestHeader) {
            if (strtolower($h) == strtolower($key)) {
                $return = $this->requestHeaders[$h];
            }
        }

        return $return;
    }

    public function SetRequestHeader($key, $value) {
        $this->requestHeaders[$key] = $value;
    }

    public function SetRequestHeaders($headers) {
        if (!empty($headers))
            foreach ($headers as $key => $value)
                $this->requestHeaders[$key] = $value;
    }

    /**
     * @return int
     */
    public function GetResponseCode(): int {
        return $this->responseCode;
    }

    /**
     * @return array
     */
    public function GetResponseHeaders(): array {
        return $this->responseHeaders;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function GetResponseHeader(string $key) {
        $return = null;

        foreach ($this->responseHeaders as $h => $responseHeader) {
            if (strtolower($h) == strtolower($key)) {
                $return = $this->responseHeaders[$h];
            }
        }

        return $return;
    }

    /**
     * @return string
     */
    public function GetResponseBody(): string {
        return $this->responseBody;
    }

    /**
     * @param bool $bool
     * @return void
     */
    public function FollowLocation(bool $bool): void {
        $this->followLocation = $bool;
    }

    /**
     * @return string
     */
    public function GetLastURI(): string {
        return $this->lastURI;
    }
}
