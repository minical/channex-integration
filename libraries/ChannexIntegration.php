<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class ChannexIntegration
{
    function __construct()
    {   
        $this->ci =& get_instance();
        $this->ci->load->model('Channex_int_model');

        $this->channex_url = ($this->ci->config->item('app_environment') == "development") ? "https://staging.channex.io" : "https://app.channex.io";
        $this->channex_host = ($this->ci->config->item('app_environment') == "development") ? "staging.channex.io" : "app.channex.io";
    }

    public function call_api($api_url, $method, $data, $headers, $method_type = 'POST'){

        $url = $api_url . $method;
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            
        if($method_type == 'GET'){

        } else {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
               
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($curl);
        
        curl_close($curl);
        
        return $response;
    }

    public function signin_channex($token){

        $api_url = $this->channex_url;
        $method = '/api/v1/properties';
        $method_type = 'GET';
        $data = array();

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key:".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }

    public function get_properties($token){

        $api_url = $this->channex_url;
        $method = '/api/v1/properties?pagination[limit]=100&order[inserted_at]=desc';
        $method_type = 'GET';

        $data = array();

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key: ".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }

    public function get_room_types($property_id, $token){

        $api_url = $this->channex_url;
        $method = '/api/v1/room_types?pagination[limit]=100&filter[property_id]='.$property_id;
        $method_type = 'GET';

        $data = array();

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key: ".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }

    public function get_rate_plans($property_id, $token){

        $final_arr = array();
        for ($i = 1; $i <= 10; $i++) { 
            $api_url = $this->channex_url;

            $method = '/api/v1/rate_plans?pagination[page]='.$i.'&pagination[limit]=100&filter[property_id]='.$property_id;
            $method_type = 'GET';

            $data = array();

            $headers = array(
                "Host: ".$this->channex_host,
                "Content-Type: application/json",
                "user-api-key: ".$token
            );

            $response = $this->call_api($api_url, $method, $data, $headers, $method_type);
            $response = json_decode($response);

            $final_arr[] = $response;

            if($response->meta->total > $response->meta->limit){
                continue;
            } else {
                break;
            }
        }

        $rate_plans = array();
        foreach ($final_arr as $key => $value) {
            if($value->data && count($value->data) > 0){
                foreach ($value->data as $k => $val) {
                    $rate_plans['data'][] = $val;
                }
            }
        }

        $rate_plans = json_encode($rate_plans);

        return $rate_plans;
    }

    public function update_availability($data, $token){

        $api_url = $this->channex_url;
        $method = '/api/v1/availability';
        $method_type = 'POST';

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key: ".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }

    public function update_restrictions($data, $token){

        $api_url = $this->channex_url;
        $method = '/api/v1/restrictions';
        $method_type = 'POST';

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key: ".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }

    public function get_bookings($property_id, $token){

        $api_url = $this->channex_url;
        $method = '/api/v1/booking_revisions/feed?filter[property_id]='.$property_id.'&pagination[page]=1&pagination[limit]=10&order[inserted_at]=desc';

        $method_type = 'GET';

        $data = array();

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key: ".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }

    public function acknowledge_bookings($booking_id, $token){

        $api_url = $this->channex_url;
        $method = '/api/v1/booking_revisions/'.$booking_id.'/ack';
        $method_type = 'POST';

        $data = array();

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key: ".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }

    public function revision_bookings($booking_id, $token){

        $api_url = $this->channex_url;
        $method = '/api/v1/booking_revisions/'.$booking_id;
        $method_type = 'GET';

        $data = array();

        $headers = array(
            "Host: ".$this->channex_host,
            "Content-Type: application/json",
            "user-api-key: ".$token
        );

        $response = $this->call_api($api_url, $method, $data, $headers, $method_type);

        return $response;
    }
}