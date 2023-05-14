<?php

class SCB_API
{

    public $request_u_id = null;
    public $resource_owner_id = null;
    public $endpoint = null;
    public $access_token = null;

    public function __construct($testmode = true, $request_u_id, $resource_owner_id)
    {
        $this->request_u_id = $request_u_id;
        $this->resource_owner_id = $resource_owner_id;

        if ($testmode) {
            $this->endpoint = "https://api-sandbox.partners.scb";
        } else {
            $this->endpoint = "https://api.partners.scb";
        }
    }

    function make_request($method = 'POST',  $body = [], $path)
    {
        $headers = [
            'requestUId' => $this->request_u_id,
            'resourceOwnerId' => $this->resource_owner_id,
            'Content-Type' => 'application/json',
            'accept-language' => 'EN'
        ];

        if ($this->access_token) {
            $headers['Authorization'] = 'Bearer ' . $this->access_token;
        }

        $response = wp_remote_post(
            $this->endpoint . $path,
            [
                'method' => strtoupper($method),
                // 'timeout'     => 45,
                // 'redirection' => 5,
                // 'httpversion' => '1.0',
                // 'blocking'    => true,
                'headers' => $headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]
        );

        if (is_wp_error($response)) {
            return $response->get_error_message();
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body;
        }
    }

    function gen_auth($application_key, $application_secret)
    {
        $data = [
            'applicationKey' => $application_key,
            'applicationSecret' => $application_secret
        ];
        $response = $this->make_request('post', $data, "/partners/sandbox/v1/oauth/token");
        if (is_array($response) && $response['status']['code'] === 1000) {
            return $response['data']['accessToken'];
        } else {
            return false;
        }
    }

    function gen_qrcode($access_token, $qr_type, $biller_id, $ref3, $order)
    {
        $data = [
            "qrType" => $qr_type,
            "ppType" => "BILLERID",
            "ppId" => strval($biller_id),
            "amount" => number_format($order->get_total(), 2),
            "ref1" => "WOOCOMMERCE",
            "ref2" => "ORDER" . strval($order->get_id()),
            "ref3" => strval($ref3)
        ];

        $this->access_token = $access_token;
        $response = $this->make_request('post', $data, "/partners/sandbox/v1/payment/qrcode/create");

        if (is_array($response) && $response['status']['code'] === 1000) {
            $order->add_meta_data('_scb_request_log', $data);
            $order->save();

            return [
                'status' => true,
                'data' => $response['data']['qrImage']
            ];
        } else {
            return [
                'status' => false,
                'data' => $response['status']['description']
            ];
        }
    }
}
