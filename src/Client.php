<?php

namespace DailyPack\Api;

/**
 * DailyPack API Client
 *
 * @author Hedzer Gelijsteen <hedzer@dailypack.nl>
 */

class Client
{
    protected $username;
    protected $password;

    protected $protocol = 'https';
    protected $host = 'api.dailypack.nl';
    protected $version = '0.2.0';
    protected $agent = 'DailyPack Wrapper';

    protected $skipSslVerification = false;
    protected $timeoutInSeconds = 60;
    protected $debug = false;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';

    protected $response;

    public function __construct($username = '', $password = '')
    {
        $this->username = $username;
        $this->password = $password;
    }

    /*
     * Account
     */
    public function getAccount($filters = [])
    {
        return $this->processAPI('/account', null, null, $filters);
    }

    /*
     * Account
     */
    public function getOrders($filters = [])
    {
        return $this->processAPI('/orders', null, null, $filters);
    }

    
    public function processAPI($endpoint, $params = [], $method = self::METHOD_GET, $filters = [])
    {
        $endpoint = $this->getEndpoint($endpoint, $filters);

        $this->debug('URL: ' . $this->getUrl($endpoint));

        $curlSession = curl_init();

        curl_setopt($curlSession, CURLOPT_HEADER, false);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curlSession, CURLOPT_URL, $this->getUrl($endpoint));
        curl_setopt($curlSession, CURLOPT_TIMEOUT, $this->timeoutInSeconds);

        curl_setopt($curlSession, CURLAUTH_BEARER , base64_encode($this->username . ':' . $this->password));

        curl_setopt($curlSession, CURLOPT_USERAGENT, $this->agent);
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);

        $this->setData($curlSession, $method, $params);
        $this->setSslVerification($curlSession);

        $apiResult = curl_exec($curlSession);
        $headerInfo = curl_getinfo($curlSession);

        $this->debug('Raw result: ' . $apiResult);

        $apiResultJson = json_decode($apiResult, true);

        $result = [];
        $result['success'] = false;

        // CURL failed
        if ($apiResult === false) {
            $result['error'] = true;
            $result['errorcode'] = 0;
            $result['errormessage'] = curl_error($curlSession);
            curl_close($curlSession);
            return $result;
        }

        curl_close($curlSession);

        if (!in_array($headerInfo['http_code'], ['200', '201', '204'])) {
            $result['error'] = true;
            $result['errorcode'] = $headerInfo['http_code'];
            if (isset($apiResult)) {
                $result['errormessage'] = $apiResult;
            }

            return $result;
        }

        // API returns success
        $result['success'] = true;
        $result['data'] = (($apiResultJson === null) ? $apiResult : $apiResultJson);

        return $result;
    }

    protected function getUrl($endpoint)
    {
        return $this->protocol . '://' . $this->host . '/' . $this->version  . '/' . $endpoint;
    }

    protected function prepareData($params)
    {
        return json_encode($params);
    }

    protected function getEndpoint($endpoint, $filters)
    {
        if (!empty($filters)) {
            $i = 0;
            foreach ($filters as $key => $value) {
                if ($i == 0) {
                    $endpoint .= '?';
                } else {
                    $endpoint .= '&';
                }
                $endpoint .= $key . '=' . urlencode($value);
                $i++;
            }
        }

        return $endpoint;
    }


    /**
    *
    */
    public function setDebug()
    {
        $this->debug = true;
    }    
    
    protected function debug($message)
    {
        if ($this->debug) {
            echo 'Debug: ' . $message . PHP_EOL;
        }
    }

    protected function setData($curlSession, $method, $params)
    {
        if (!in_array($method, [self::METHOD_POST, self::METHOD_PUT, self::METHOD_DELETE])) {
            return;
        }

        $data = $this->prepareData($params);
        $this->debug('Data: ' . $data);

        curl_setopt($curlSession, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $data);
    }

    protected function setSslVerification($curlSession)
    {
        if ($this->skipSslVerification) {
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, false);
        }
    }

}
