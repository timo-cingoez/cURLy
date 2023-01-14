<?php

/**
 * Class cURLy - A basic cURL wrapper for PHP.
 * @author Timo Cingöz <timo-cingoez@hotmail.de>
 */

class cURLy {
    /**
     * The initial target URL.
     * @var string
     */
    protected string $url;

    /**
     * The request headers.
     * @var array
     */
    private array $header;

    /**
     * All of the cURL options that will be used for requests.
     * @var array
     */
    private array $curlOpts;

    /**
     * The response headers. Used for logs.
     * @var false|string
     */
    private $responseHeader;

    /**
     * Detailed request information.
     * @var false|resource
     */
    private $verbose;

    /**
     * Toggles whether logs are created for requests.
     * @var bool
     */
    private bool $logEnabled = false;

    /**
     * The directory in which the logs are saved.
     * @var string
     */
    private string $logDirectory;

    /**
     * The format in which the request data should be sent.
     * @var string
     */
    private string $format;

    /**
     * The type in which the response should be returned ARRAY|OBJECT.
     * @var string
     */
    private string $responseType = 'ARRAY';

    /**
     * Optional settings for json_decode(); depth|options
     * @var array
     */
    private array $decodeOptions;

    /**
     * cURLy constructor.
     * @param string $url The initial URL that will be used for the cURL operations.
     * @param array $curlOpts Optional cURL options. Not necessary for basic usage.
     */
    public function __construct(string $url, array $curlOpts = []) {
        if (trim($url) === '') {
            throw new RuntimeException('cURLy - Missing URL.');
        }

        $this->url = $url;

        $this->curlOpts = $curlOpts;

        // Set the baseline cURL options.
        $this->curlOpts[CURLOPT_URL] = $this->url;
        $this->curlOpts[CURLOPT_RETURNTRANSFER] = 1;
        $this->curlOpts[CURLOPT_HEADER] = 1;
    }

    /**
     * Create and return a instance of cURLy for easy method chaining.
     * @param string $url The initial URL that will be used for the cURL operations.
     * @param array $curlOpts Optional cURL options. Not necessary for basic usage.
     * @return cURLy
     */
    public static function instance(string $url = '', array $curlOpts = []): cURLy {
        return new self($url, $curlOpts);
    }

    /**
     * Toggle whether logs should be created for requests.
     * @param bool $logEnabled Whether logs should be created or not.
     * @param string $directory The directory in which the logs should be created in.
     * @return cURLy
     */
    public function setLog(bool $logEnabled, string $directory = 'log'): cURLy {
        $this->logEnabled = $logEnabled;
        $this->logDirectory = $directory;
        return $this;
    }

    /**
     * Send a GET Request to the configured URL.
     * @param string $url Optional URL if the endpoint is different from the initial one.
     * @return mixed
     */
    public function GET(string $url = '') {
        return $this->execute($url);
    }

    /**
     * Send a POST Request to the configured URL.
     * @param array $postFields
     * @param string $url
     * @return bool|false|string
     */
    public function POST(array $postFields, string $url = '') {
        $this->curlOpts[CURLOPT_POST] = true;
        $this->curlOpts[CURLOPT_POSTFIELDS] = $this->preparePostFields($postFields);
        return $this->execute($url);
    }

    /**
     * Send a PATCH Request to the configured URL.
     * @param array $patchFields
     * @param string $url
     * @return mixed
     */
    public function PATCH(array $patchFields, string $url = '') {
        $this->curlOpts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        $this->curlOpts[CURLOPT_POSTFIELDS] = $this->preparePostFields($patchFields);
        return $this->execute($url);
    }

    /**
     * Send a PUT Request to the configured URL.
     * @param array $putFields
     * @param string $url
     * @return bool|false|string
     */
    public function PUT(array $putFields, string $url) {
        $this->curlOpts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        $this->curlOpts[CURLOPT_POSTFIELDS] = $this->preparePostFields($putFields);
        return $this->execute($url);
    }

    /**
     * Set the format in which the post fields should be encoded.
     * @param string $format
     * @return cURLy
     */
    public function setFormat(string $format): cURLy {
        $this->format = $format;
        return $this;
    }

    /**
     * Set the type of the response.
     * @param string $responseType ARRAY (default)|OBJECT
     * @param array $decodeOptions depth|options
     * @return cURLy
     */
    public function setResponseType($responseType, $decodeOptions = []): cURLy {
        $this->responseType = $responseType;
        $this->decodeOptions = $decodeOptions;
        return $this;
    }

    /**
     * Parse the response. Expects JSON format.
     * @param string $response JSON string.
     * @return mixed
     */
    private function parseResponse(string $response) {
        $assoc = $this->responseType === 'ARRAY';
        $depth = empty($this->decodeOptions['options']) ? 512 : $this->decodeOptions['options'];
        $options = empty($this->decodeOptions['options']) ? 0 : $this->decodeOptions['options'];
        $parsedResponse = json_decode($response, $assoc, $depth, JSON_THROW_ON_ERROR | $options);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsedResponse;
        }
        throw new RuntimeException('cURLy - The response is not in a valid JSON format. ('.json_last_error_msg().')');
    }

    /**
     * Set the authentication method and adjust the header accordingly.
     * @param string $authMethod The authentication method. BASIC, OAUTH
     * @param array $authData The authentication data. [httpUsername, httpPassword] | [token]
     * @return cURLy
     */
    private function setAuthentication(string $authMethod, array $authData): cURLy {
        switch ($authMethod) {
            case 'BASIC':
                if (empty($authData['httpUsername']) || empty($authData['httpPassword'])) {
                    throw new RuntimeException('cURLy - Missing data for BASIC authentication. (Expected: httpUsername, httpPassword)');
                }
                $this->curlOpts[CURLOPT_HTTPHEADER] = CURLAUTH_BASIC;
                $this->curlOpts[CURLOPT_USERPWD] = $authData['httpUsername'].':'.$authData['httpPassword'];
                $this->curlOpts[CURLOPT_FOLLOWLOCATION] = 1;
                break;
            case 'OAUTH':
                if (empty($authData['token'])) {
                    throw new RuntimeException('cURLy - Missing data for OAUTH authentication. (Expected: token)');
                }
                $this->header = ['Authentication: Bearer '.$authData['token']];
                break;
        }
        return $this;
    }

    /**
     * Set the configured cURL options, execute the request and handle the response.
     * @param string $url
     * @return bool|false|string
     */
    private function execute(string $url = '') {
        // Initialize a new cURL session.
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('cURLy - Failed to initialize.');
        }

        $this->setCurlOpts($curl, $url);

        // WARNING: Only allow this for debugging purposes and when working in a local environment with safe endpoints. Seriously this is a really dangerous security threat.
        if (stripos("localhost", $_SERVER["SERVER_NAME"]) !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }

        // Send the request and capture the response.
        $response = curl_exec($curl);
        if ($response === false) {
            throw new RuntimeException(curl_error($curl), curl_errno($curl));
        }

        // Handle response based on the set options.
        if ($this->curlOpts[CURLOPT_HEADER]) {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $this->responseHeader = substr($response, 0, $headerSize);
            $response = substr($response, $headerSize);
        }

        if ($this->logEnabled) {
            $this->writeLog($response);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the cURL session.
        curl_close($curl);

        // Analyze if the response was positive.
        $this->analyzeResponse($response, $httpCode);

        return $this->parseResponse($response);
    }

    /**
     * Set the cURL options.
     * @param $curl
     * @param string $url
     */
    private function setCurlOpts(&$curl, string $url = ''): void {
        if (empty($url) === false) {
            $this->curlOpts[CURLOPT_URL] = $url;
        }
        if (curl_setopt_array($curl, $this->curlOpts) === false) {
            throw new RuntimeException('cURLy - Error while setting cURL options.');
        }
        if (empty($this->header) === false) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $this->header);
        }
        if ($this->logEnabled) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
            $this->verbose = fopen('php://temp', 'rwb+');
            curl_setopt($curl, CURLOPT_STDERR, $this->verbose);
        }
    }

    /**
     * Create an individual log file for an executed request.
     * @param string|false $response The cURL response.
     * @return false|int File write result.
     */
    private function writeLog(string $response) {
        if (mkdir($concurrentDirectory = $this->logDirectory, 0777, true) === false && is_dir($concurrentDirectory) === false) {
            throw new RuntimeException("cURLy - Log directory $concurrentDirectory was not created.");
        }
        clearstatcache();

        rewind($this->verbose);

        $log = stream_get_contents($this->verbose)."\r\n";
        $log .= 'cURLy Request'.$this->curlOpts[CURLOPT_POSTFIELDS]."\r\n";
        $log .= "\r\n".'Response:'."\r\n".$response;

        $fileName = 'cURLy_'.date("d_m_Y_H_i_s_").substr((string)microtime(), 2, 7).'.txt';

        return file_put_contents($this->logDirectory.'/'.$fileName, $log);
    }

    /**
     * Check whether the response code was positive.
     * @param string $response
     * @param int $httpCode
     */
    private function analyzeResponse(string $response, int $httpCode): void {
        if (in_array($httpCode, range(200, 207), true) === false) {
            throw new RunTimeException("cURLy - HTTP Error - Code: $httpCode Response: $response", $httpCode, null);
        }
    }

    /**
     * Prepare the post fields by encoding them based on the configuration.
     * @param array $postFields
     * @return string Encoded post fields.
     */
    private function preparePostFields(array $postFields): string {
        if ($this->format === 'JSON' && is_array($postFields)) {
            $postFields = json_encode($postFields);
        } else {
            $postFields = http_build_query($postFields);
        }
        return $postFields;
    }
}