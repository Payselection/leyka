<?php

namespace Payselection;

class Api
{
    protected $options;

    /**
     * request Send request to API server
     *
     * @param  string $path - API path
     * @param  array|bool $data - Request DATA
     * @return WP_Error|string
     */
    protected function request(string $host, string $path, $data = false, $method = "GET")
    {
        $bodyJSON = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : "";

        $requestID = self::guidv4();

        $signBody = $method . PHP_EOL . "/" . $path . PHP_EOL . leyka_options()->opt('payselection_site_id') . PHP_EOL . $requestID . PHP_EOL . $bodyJSON;

        $headers = [
            "X-SITE-ID" => leyka_options()->opt('payselection_site_id'),
            "X-REQUEST-ID" => $requestID,
            "X-REQUEST-SIGNATURE" => self::getSignature($signBody, leyka_options()->opt('payselection_key')),
        ];

        $url = $host . "/" . $path;
        $params = [
            "timeout" => 30,
            "redirection" => 5,
            "httpversion" => "1.0",
            "blocking" => true,
            "headers" => $headers,
            "body" => $bodyJSON,
        ];

        $response = $method === 'POST' ? wp_remote_post($url, $params) : wp_remote_get($url, $params);

        if (is_wp_error($response)) {
            return $response;
        }

        // Decode response
        $response["body"] = json_decode($response["body"], true);

        $code = $response["response"]["code"];

        if ($code === 200 || $code === 201) {
            return $response["body"];
        }

        return new \WP_Error("payselection", $response["body"]["Code"] . ($response["body"]["Description"] ? " " . $response["body"]["Description"] : ""));
    }

    /**
     * guidv4 Create uuid unique id
     * Ref: https://www.uuidgenerator.net/dev-corner/php
     *
     * @param  array|null $data - Random 16 bytes
     * @return string
     */
    protected static function guidv4($data = null)
    {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));
    }

    /**
     * getSignature Get signature by request body and key
     *
     * @param  string $body
     * @param  string $secretKey
     * @return string
     */
    protected static function getSignature(string $body, string $secretKey)
    {
        if (empty($body)) {
            return ";";
        }

        $hash = hash_hmac("sha256", $body, $secretKey, false);
        return $hash;
    }
    
    /**
     * getPaymentLink Get payment link
     *
     * @param  array $data - Request params
     * @return WP_Error|string
     */
    public function getPaymentLink(array $data = [])
    {
        $host = leyka_options()->opt('payselection_create_host') ?: leyka_options()->opt('payselection_host');
        return $this->request($host, 'webpayments/create', $data, 'POST');
    }
    
    /**
     * charge Charge payment
     *
     * @param  array $data - Request params
     * @return WP_Error|string
     */
    public function charge(array $data = [])
    {
        return $this->request(leyka_options()->opt('payselection_host'), 'payments/charge', $data, 'POST');
    }
    
    /**
     * cancel Cancel payment
     *
     * @param  array $data - Request params
     * @return WP_Error|string
     */
    public function cancel(array $data = [])
    {
        return $this->request(leyka_options()->opt('payselection_host'), 'payments/cancellation', $data, 'POST');
    }

    /**
     * Unsubscribe payment
     *
     * @param  array $data - Request params
     * @return WP_Error|string
     */
    public function unsubscribe(array $data = [])
    {
        return $this->request(leyka_options()->opt('payselection_host'), 'payments/unsubscribe', $data, 'POST');
    }
}
