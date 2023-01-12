<?php

/**
 * Class cURLy - A basic cURL wrapper for PHP.
 * @author Timo Cingöz <timo-cingoez@hotmail.de>
 */

class cURLy {
    protected $url;
    private $header;

    /**
     * @var array All of the cURL options that will be used for requests.
     */
    private $curlOpts;

    /**
     * @var false|string
     */
    private $responseHeader;

    /**
     * @var false|resource
     */
    private $verbose;

    /**
     * @var bool
     */
    private $logEnabled;
    private $logDirectory;

    /**
     * @var string The format in which the request data should be sent.
     */
    private $format;

    public function __construct($url) {
        if (trim($url) === '') {
            throw new RuntimeException('cURLy - Missing URL.');
        }

        $this->url = $url;
        // Set the baseline cURL options.
        $this->curlOpts = [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 1
        ];
    }

    public function execute(string $url = '') {
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

        return $response;
    }

    /**
     * Set the cURL options.
     * @param $curl
     * @param string $url
     */
    private function setCurlOpts(&$curl, string $url = '') {
        if (empty($url) === false) {
            $this->curlOpts[CURLOPT_URL] = $url;
        }
        if (curl_setopt_array($curl, $this->curlOpts) === false) {
            throw new RuntimeException('cURLy - Error while setting cURL options.');
        }
        if ($this->logEnabled) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
            $this->verbose = fopen('php://temp', 'rwb+');
            curl_setopt($curl, CURLOPT_STDERR, $this->verbose);
        }
    }

    public function setLog(bool $bool, $directory = 'log') {
        $this->logEnabled = $bool;
        $this->logDirectory = $directory;
    }

    private function writeLog($response) {
        if (mkdir($concurrentDirectory = $this->logDirectory, 0777, true) === false && is_dir($concurrentDirectory) === false) {
            throw new RuntimeException("cURLy - Log directory $concurrentDirectory was not created.");
        }
        clearstatcache();

        rewind($this->verbose);

        $log = stream_get_contents($this->verbose)."\r\n";
        $log .= 'cURLy Request:: '.$this->curlOpts[CURLOPT_POSTFIELDS]."\r\n";
        $log .= "\r\n".'Response:'."\r\n".$response;

        $fileName = 'cURLy_'.date("d_m_Y_H_i_s_").substr((string)microtime(), 2, 7).'txt';

        return file_put_contents($this->logDirectory.'/'.$fileName, $log);
    }

    private function analyzeResponse(string $response, int $httpCode) {
        if (in_array($httpCode, range(200, 207), true) === false) {
            throw new RunTimeException("cURLy - HTTP Error - Code: $httpCode Response: $response", $httpCode, null);
        }
    }

    /**
     * Send a GET Request to the configured URL.
     * @param string $url
     * @return bool|false|string
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

    /**
     * Set the format in which the post fields should be encoded.
     * @param string $format
     */
    public function setFormat(string $format) {
        $this->format = $format;
    }
}