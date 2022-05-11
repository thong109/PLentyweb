<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Service;

class ZoomService
{
    private $errors;
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $timeout;
    /**
     * @var int
     */
    private $responseCode;

    public function __construct($apiKey, $apiSecret, $options = [])
    {
        $this->apiKey = $apiKey;

        $this->apiSecret = $apiSecret;

        $this->baseUrl = 'https://api.zoom.us/v2';
        $this->timeout = 30;

        // Store any options if they map to valid properties
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public static function urlsafeB64Encode($string)
    {
        return str_replace('=', '', strtr(base64_encode($string), '+/', '-_'));
    }

    private function generateJWT(): string
    {
        $token = [
            'iss' => $this->apiKey,
            'exp' => time() + 60,
        ];
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $toSign =
            self::urlsafeB64Encode(json_encode($header))
            .'.'.
            self::urlsafeB64Encode(json_encode($token))
        ;

        $signature = hash_hmac('SHA256', $toSign, $this->apiSecret, true);

        return $toSign.'.'.self::urlsafeB64Encode($signature);
    }

    private function headers(): array
    {
        return [
            'Authorization: Bearer '.$this->generateJWT(),
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }

    private function pathReplace($path, $requestParams)
    {
        $errors = [];
        $path = preg_replace_callback('/\\{(.*?)\\}/', function ($matches) use ($requestParams,$errors) {
            if (!isset($requestParams[$matches[1]])) {
                $this->errors[] = 'Required path parameter was not specified: '.$matches[1];

                return '';
            }

            return rawurlencode($requestParams[$matches[1]]);
        }, $path);

        if (count($errors)) {
            $this->errors = array_merge($this->errors, $errors);
        }

        return $path;
    }

    public function doRequest($method, $path, $queryParams = [], $pathParams = [], $body = '')
    {
        if (is_array($body)) {
            // Treat an empty array in the body data as if no body data was set
            if (!count($body)) {
                $body = '';
            } else {
                $body = json_encode($body);
            }
        }

        $this->errors = [];
        $this->responseCode = 0;

        $path = $this->pathReplace($path, $pathParams);

        if (count($this->errors)) {
            return false;
        }

        $method = strtoupper($method);
        $url = $this->baseUrl.$path;

        // Add on any query parameters
        if (count($queryParams)) {
            $url .= '?'.http_build_query($queryParams);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (in_array($method, ['DELETE', 'PATCH', 'POST', 'PUT'])) {
            // All except DELETE can have a payload in the body
            if ($method != 'DELETE' && strlen($body)) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $result = curl_exec($ch);

        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $this->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return json_decode($result, true);
    }

    // Returns the errors responseCode returned from the last call to doRequest
    public function requestErrors()
    {
        return $this->errors;
    }

    // Returns the responseCode returned from the last call to doRequest
    public function responseCode(): int
    {
        return $this->responseCode;
    }
}
