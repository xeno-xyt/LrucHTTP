<?php
class LrucHTTPClient {
    private $method = 'GET';
    private $url = '';
    private $headers = [];
    private $body = '';
    private $timeout = null; // null = no timeout
    private $cookies = [];
    private $followRedirects = true;
    private $maxRedirects = 5;
    private $verifySSL = true;
    private $responseFormat = 'auto'; // 'auto', 'json', 'xml', 'text'
    private $userAgent = 'LrucHTTPClient/2.0';
    private $proxy = null;
    private $retries = 0;
    private $retryDelay = 1000; // milliseconds
    private $logger = null;
    
    /**
     * Initialize a new HTTP request
     * 
     * @param string $method HTTP method
     * @param string $url Request URL
     * @return $this
     */
    public function prepareReq($method, $url) {
        $this->method = strtoupper($method);
        $this->url = $url;
        return $this;
    }
    
    /**
     * Set request body
     * 
     * @param mixed $body String or array (automatically converted to query string)
     * @return $this
     */
    public function setBody($body) {
        $this->body = is_array($body) ? http_build_query($body) : $body;
        return $this;
    }
    
    /**
     * Set JSON request body
     * 
     * @param mixed $data Data to be JSON encoded
     * @return $this
     */
    public function setJsonBody($data) {
        $this->headers[] = "Content-Type: application/json";
        $this->body = json_encode($data);
        return $this;
    }
    
    /**
     * Set form data (multipart/form-data)
     * 
     * @param array $formData Associative array of form fields
     * @return $this
     */
    public function setFormData($formData) {
        // This is a simplified implementation; a complete one would handle file uploads
        $this->headers[] = "Content-Type: application/x-www-form-urlencoded";
        $this->body = http_build_query($formData);
        return $this;
    }
    
    /**
     * Set request headers
     * 
     * @param array $headers Associative array of headers
     * @return $this
     */
    public function setHeader($headers) {
        foreach ($headers as $key => $value) {
            $this->headers[] = "$key: $value";
        }
        return $this;
    }
    
    /**
     * Set a single header
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return $this
     */
    public function addHeader($name, $value) {
        $this->headers[] = "$name: $value";
        return $this;
    }
    
    /**
     * Set request cookies
     * 
     * @param array $cookies Associative array of cookies
     * @return $this
     */
    public function setCookie($cookies) {
        foreach ($cookies as $key => $value) {
            $this->cookies[] = "$key=$value";
        }
        return $this;
    }
    
    /**
     * Add a single cookie
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @return $this
     */
    public function addCookie($name, $value) {
        $this->cookies[] = "$name=$value";
        return $this;
    }
    
    /**
     * Set authorization header
     * 
     * @param string $type Auth type (Bearer, Basic, etc)
     * @param string $credentials Auth credentials
     * @return $this
     */
    public function setAuthorization($type, $credentials) {
        $this->headers[] = "Authorization: $type $credentials";
        return $this;
    }
    
    /**
     * Set basic auth credentials
     * 
     * @param string $username Username
     * @param string $password Password
     * @return $this
     */
    public function setBasicAuth($username, $password) {
        $credentials = base64_encode("$username:$password");
        return $this->setAuthorization('Basic', $credentials);
    }
    
    /**
     * Set request timeout
     * 
     * @param int $milliseconds Timeout in milliseconds
     * @return $this
     */
    public function timeout($milliseconds) {
        $this->timeout = $milliseconds;
        return $this;
    }
    
    /**
     * Set whether to follow redirects
     * 
     * @param bool $follow Whether to follow redirects
     * @param int $max Maximum number of redirects to follow
     * @return $this
     */
    public function followRedirects($follow = true, $max = 5) {
        $this->followRedirects = $follow;
        $this->maxRedirects = $max;
        return $this;
    }
    
    /**
     * Set whether to verify SSL certificates
     * 
     * @param bool $verify Whether to verify SSL certificates
     * @return $this
     */
    public function verifySSL($verify = true) {
        $this->verifySSL = $verify;
        return $this;
    }
    
    /**
     * Set user agent
     * 
     * @param string $userAgent User agent string
     * @return $this
     */
    public function setUserAgent($userAgent) {
        $this->userAgent = $userAgent;
        return $this;
    }
    
    /**
     * Set proxy configuration
     * 
     * @param string $proxy Proxy address (e.g., 'tcp://proxy.example.com:5100')
     * @return $this
     */
    public function setProxy($proxy) {
        $this->proxy = $proxy;
        return $this;
    }
    
    /**
     * Set retry configuration
     * 
     * @param int $retries Number of retries
     * @param int $delay Delay between retries in milliseconds
     * @return $this
     */
    public function retry($retries, $delay = 1000) {
        $this->retries = max(0, $retries);
        $this->retryDelay = max(0, $delay);
        return $this;
    }
    
    /**
     * Set expected response format
     * 
     * @param string $format Response format ('auto', 'json', 'xml', 'text')
     * @return $this
     */
    public function expectFormat($format) {
        $this->responseFormat = strtolower($format);
        return $this;
    }
    
    /**
     * Set logger function
     * 
     * @param callable $logger Function that accepts log message and level
     * @return $this
     */
    public function setLogger($logger) {
        if (is_callable($logger)) {
            $this->logger = $logger;
        }
        return $this;
    }
    
    /**
     * Send the HTTP request
     * 
     * @return array Response data
     */
    public function sendReq() {
        // Add cookies to headers
        if (!empty($this->cookies)) {
            $this->headers[] = "Cookie: " . implode('; ', $this->cookies);
        }
        
        // Add user agent if not already set
        $hasUserAgent = false;
        foreach ($this->headers as $header) {
            if (stripos($header, 'User-Agent:') === 0) {
                $hasUserAgent = true;
                break;
            }
        }
        
        if (!$hasUserAgent && $this->userAgent) {
            $this->headers[] = "User-Agent: {$this->userAgent}";
        }
        
        $opts = [
            'http' => [
                'method' => $this->method,
                'header' => implode("\r\n", $this->headers),
                'follow_location' => $this->followRedirects ? 1 : 0,
                'max_redirects' => $this->maxRedirects,
                'ignore_errors' => true, // Don't throw on HTTP error codes
            ],
            'ssl' => [
                'verify_peer' => $this->verifySSL,
                'verify_peer_name' => $this->verifySSL,
            ]
        ];
        
        // Set body for non-GET requests
        if (!in_array($this->method, ['GET', 'HEAD', 'OPTIONS']) && !empty($this->body)) {
            $opts['http']['content'] = $this->body;
        }
        
        // Set timeout if specified
        if ($this->timeout !== null) {
            $opts['http']['timeout'] = $this->timeout / 1000;
        }
        
        // Set proxy if specified
        if ($this->proxy !== null) {
            $opts['http']['proxy'] = $this->proxy;
        }
        
        $this->log("Preparing request to {$this->url} ({$this->method})", 'info');
        
        // Perform request with retries
        $attempt = 0;
        $result = false;
        $lastError = null;
        
        do {
            if ($attempt > 0) {
                $this->log("Retrying request (attempt {$attempt} of {$this->retries})", 'info');
                usleep($this->retryDelay * 1000); // Convert to microseconds
            }
            
            $context = stream_context_create($opts);
            $result = @file_get_contents($this->url, false, $context);
            $lastError = error_get_last();
            
            $attempt++;
        } while ($result === false && $attempt <= $this->retries);
        
        // Process response
        $response = [
            'success' => $result !== false,
            'status_code' => $this->getStatusCode($http_response_header ?? []),
            'body' => $result,
            'headers' => $this->parseHeaders($http_response_header ?? []),
            'error' => $result === false ? $lastError : null,
            'attempts' => $attempt
        ];
        
        // Process response body based on format
        if ($result !== false) {
            $response['parsed'] = $this->parseResponse($result, $response['headers']);
        }
        
        $this->log("Request completed with status code: " . ($response['status_code'] ?? 'unknown'), 'info');
        
        return $response;
    }
    
    /**
     * Make request using a configuration array
     * 
     * @param array $config Request configuration
     * @return array Response data
     */
    public function req($config = []) {
        // Reset instance for new request
        $this->method = 'GET';
        $this->url = '';
        $this->headers = [];
        $this->body = '';
        $this->cookies = [];
        
        // Apply configuration
        $this->prepareReq($config['Method'] ?? 'GET', $config['URL'] ?? '');
        
        if (isset($config['Header'])) {
            $this->setHeader($config['Header']);
        }
        
        if (isset($config['Body'])) {
            $this->setBody($config['Body']);
        }
        
        if (isset($config['JsonBody'])) {
            $this->setJsonBody($config['JsonBody']);
        }
        
        if (isset($config['FormData'])) {
            $this->setFormData($config['FormData']);
        }
        
        if (isset($config['Cookie'])) {
            $this->setCookie($config['Cookie']);
        }
        
        if (isset($config['Auth'])) {
            $this->setAuthorization($config['Auth']['Type'], $config['Auth']['Credentials']);
        }
        
        if (isset($config['BasicAuth'])) {
            $this->setBasicAuth($config['BasicAuth']['Username'], $config['BasicAuth']['Password']);
        }
        
        if (isset($config['Timeout'])) {
            $this->timeout($config['Timeout']);
        }
        
        if (isset($config['FollowRedirects'])) {
            $this->followRedirects($config['FollowRedirects'], $config['MaxRedirects'] ?? 5);
        }
        
        if (isset($config['VerifySSL'])) {
            $this->verifySSL($config['VerifySSL']);
        }
        
        if (isset($config['UserAgent'])) {
            $this->setUserAgent($config['UserAgent']);
        }
        
        if (isset($config['Proxy'])) {
            $this->setProxy($config['Proxy']);
        }
        
        if (isset($config['Retry'])) {
            $this->retry($config['Retry'], $config['RetryDelay'] ?? 1000);
        }
        
        if (isset($config['ResponseFormat'])) {
            $this->expectFormat($config['ResponseFormat']);
        }
        
        return $this->sendReq();
    }
    
    /**
     * Extract status code from response headers
     * 
     * @param array $headers Response headers
     * @return int|null Status code or null if not found
     */
    private function getStatusCode($headers) {
        if (empty($headers)) {
            return null;
        }
        
        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * Parse response headers into an associative array
     * 
     * @param array $rawHeaders Raw headers array
     * @return array Parsed headers
     */
    private function parseHeaders($rawHeaders) {
        $headers = [];
        $cookies = [];
        
        foreach ($rawHeaders as $header) {
            if (strpos($header, ':') !== false) {
                list($name, $value) = explode(':', $header, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Handle multiple headers with same name
                if (!isset($headers[$name])) {
                    $headers[$name] = $value;
                } else {
                    if (!is_array($headers[$name])) {
                        $headers[$name] = [$headers[$name]];
                    }
                    $headers[$name][] = $value;
                }
                
                // Extract cookies
                if (strtolower($name) === 'set-cookie') {
                    if (preg_match('/^([^=]+)=([^;]+)/', $value, $matches)) {
                        $cookies[$matches[1]] = $matches[2];
                    }
                }
            }
        }
        
        $headers['cookies'] = $cookies;
        return $headers;
    }
    
    /**
     * Parse response body based on content type
     * 
     * @param string $body Response body
     * @param array $headers Response headers
     * @return mixed Parsed response body
     */
    private function parseResponse($body, $headers) {
        $format = $this->responseFormat;
        
        // If format is auto, try to determine from content-type header
        if ($format === 'auto' && isset($headers['Content-Type'])) {
            $contentType = is_array($headers['Content-Type']) 
                ? $headers['Content-Type'][0] 
                : $headers['Content-Type'];
                
            if (stripos($contentType, 'application/json') !== false) {
                $format = 'json';
            } elseif (stripos($contentType, 'application/xml') !== false || 
                     stripos($contentType, 'text/xml') !== false) {
                $format = 'xml';
            } else {
                $format = 'text';
            }
        }
        
        switch ($format) {
            case 'json':
                return json_decode($body, true);
            case 'xml':
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($body);
                if ($xml === false) {
                    return null;
                }
                return $xml;
            case 'text':
            default:
                return $body;
        }
    }
    
    /**
     * Log a message if logger is set
     * 
     * @param string $message Message to log
     * @param string $level Log level
     */
    private function log($message, $level = 'debug') {
        if ($this->logger !== null) {
            call_user_func($this->logger, $message, $level);
        }
    }
    
    /**
     * Convenience method for GET request
     * 
     * @param string $url Request URL
     * @param array $headers Optional headers
     * @return array Response
     */
    public function get($url, $headers = []) {
        return $this->prepareReq('GET', $url)
            ->setHeader($headers)
            ->sendReq();
    }
    
    /**
     * Convenience method for POST request
     * 
     * @param string $url Request URL
     * @param mixed $body Request body
     * @param array $headers Optional headers
     * @return array Response
     */
    public function post($url, $body, $headers = []) {
        return $this->prepareReq('POST', $url)
            ->setHeader($headers)
            ->setBody($body)
            ->sendReq();
    }
    
    /**
     * Convenience method for JSON POST request
     * 
     * @param string $url Request URL
     * @param mixed $data Data to be JSON encoded
     * @param array $headers Optional headers
     * @return array Response
     */
    public function postJson($url, $data, $headers = []) {
        return $this->prepareReq('POST', $url)
            ->setHeader($headers)
            ->setJsonBody($data)
            ->sendReq();
    }
    
    /**
     * Convenience method for PUT request
     * 
     * @param string $url Request URL
     * @param mixed $body Request body
     * @param array $headers Optional headers
     * @return array Response
     */
    public function put($url, $body, $headers = []) {
        return $this->prepareReq('PUT', $url)
            ->setHeader($headers)
            ->setBody($body)
            ->sendReq();
    }
    
    /**
     * Convenience method for DELETE request
     * 
     * @param string $url Request URL
     * @param array $headers Optional headers
     * @return array Response
     */
    public function delete($url, $headers = []) {
        return $this->prepareReq('DELETE', $url)
            ->setHeader($headers)
            ->sendReq();
    }
    
    /**
     * Convenience method for PATCH request
     * 
     * @param string $url Request URL
     * @param mixed $body Request body
     * @param array $headers Optional headers
     * @return array Response
     */
    public function patch($url, $body, $headers = []) {
        return $this->prepareReq('PATCH', $url)
            ->setHeader($headers)
            ->setBody($body)
            ->sendReq();
    }
    
    /**
     * Download a file to a local path
     * 
     * @param string $url URL to download from
     * @param string $localPath Path to save the file
     * @return bool Success status
     */
    public function download($url, $localPath) {
        $response = $this->get($url);
        
        if ($response['success'] && !empty($response['body'])) {
            return file_put_contents($localPath, $response['body']) !== false;
        }
        
        return false;
    }
}
