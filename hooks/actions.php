<?php

add_action('update_availability', 'update_availability_fn', 10, 1);

add_action('update_rates', 'update_rates_fn', 10, 1);
    
function update_availability_fn ($data) {

    if(
        isset($data['start_date']) && 
        $data['start_date'] &&
        isset($data['end_date']) && 
        $data['end_date']
    ){
        $start_date = date('Y-m-d', strtotime($data['start_date']));
        $end_date = date('Y-m-d', strtotime($data['end_date']));
    } else {
        $start_date = date("Y-m-d");
        $end_date = Date("Y-m-d", strtotime("+500 days", strtotime($start_date)));
    }

    $date1 = date_create($start_date);
    $date2 = date_create($end_date);
    $diff = date_diff($date1, $date2);
    $date_diff = (int) $diff->format("%a");

    if ($date_diff > 90) {

        for($date = $start_date; $date <= $end_date; $date = date("Y-m-d", strtotime("+90 days", strtotime($date)))) {
            update_availability_batch($date, date("Y-m-d", strtotime("+90 days", strtotime($date))), $data);
        }

        if (date("Y-m-d", strtotime("-90 days", strtotime($date))) < $end_date) {
            update_availability_batch(date("Y-m-d", strtotime("-90 days", strtotime($date))), $end_date, $data);
        }

    } else {
        update_availability_batch($start_date, $end_date, $data);
    }
}

function update_availability_batch ($start_date, $end_date, $data) {
   
    $CI = &get_instance();
    $CI->load->model('../extensions/channex_integration/models/Channex_int_model');
    $CI->load->model('../extensions/channex_integration/models/Room_type_model');
    $CI->load->model('../extensions/channex_integration/models/Companies_model');
    $CI->load->library('../extensions/channex_integration/libraries/ChannexIntegration');

    $end_date = date("Y-m-d", strtotime("+1 day", strtotime($end_date)));
    
    if(isset($data['room_type_id']) && $data['room_type_id'])
        $room_type_id = explode(',', $data['room_type_id']);
    else
        $room_type_id = null;

    $update_from = isset($data['update_from']) && $data['update_from'] ? $data['update_from'] : null;

    //if($update_from == 'extension')
    $CI->company_id = isset($data['company_id']) && $data['company_id'] ? $data['company_id'] : $this->session->userdata('anonymous_company_id');

    $channex_x_company = $CI->Channex_int_model->get_channex_x_company(null, $CI->company_id, 'channex');

    $property_id = $channex_x_company['ota_property_id'];
    $ota_x_company_id = $channex_x_company['ota_x_company_id'];

    if($room_type_id){
        $room_type_data = $CI->Channex_int_model->get_channex_room_types_by_id($room_type_id, $CI->company_id, $ota_x_company_id);
    }
    else{
        $room_type_data = $CI->Channex_int_model->get_channex_room_types_by_id(null, $CI->company_id, $ota_x_company_id);
    }

    $avail_array["values"] = $availability_data = array();
    if($room_type_data){

        $get_ota = $CI->Channex_int_model->get_channex_data($CI->company_id, 'channex');

        //if($update_from == 'extension'){

            $company_key_data = $CI->Companies_model->get_company_api_permission($CI->company_id);
            $company_access_key = isset($company_key_data[0]['key']) && $company_key_data[0]['key'] ? $company_key_data[0]['key'] : null;

            $room_types_avail_array = $CI->Room_type_model->get_room_type_availability(
                $CI->company_id,
                $get_ota['ota_id'],
                $start_date,
                $end_date,
                null,
                null,
                true,
                null,
                true,
                true,
                true,
                true,
                $company_access_key
            );
        // } else {
        //     $room_types_avail_array = $CI->Room_type_model->get_room_type_availability(
        //         $CI->company_id,
        //         $get_ota['ota_id'],
        //         $start_date,
        //         $end_date,
        //         null,
        //         null,
        //         true
        //     );
        // }

        foreach ($room_types_avail_array as $key => $value) {
            foreach ($room_type_data as $key1 => $value1) {
                if($key == $value1['minical_room_type_id']){
                    $room_types_avail_array[$key]['ota_room_type_id'] = $value1['ota_room_type_id'];
                }
            }
        }

        foreach ($room_types_avail_array as $key => $value) {
            if(isset($value['availability']) && $value['availability']){
                foreach ($value['availability'] as $key1 => $avail) {
                    if(isset($value['ota_room_type_id']) && $value['ota_room_type_id']){
                        $availability_data[] =  array(
                            "availability" => (int)$avail['availability'],
                            "date_from" => $avail['date_start'],
                            
                            "date_to" => strtotime($avail['date_start']) > strtotime("-1 day", strtotime($avail['date_end'])) ? $avail['date_end'] : date("Y-m-d", strtotime("-1 day", strtotime($avail['date_end']))),
                            "property_id" => $property_id,
                            "room_type_id" => $value['ota_room_type_id']
                        );
                    }
                }
            }
        }
    
        $get_token_data = $CI->Channex_int_model->get_token(null, $CI->company_id, 'channex');
        
        // if(channex_refresh_token()){
        //     $get_token_data = $CI->Channex_int_model->get_token(null, $CI->company_id, 'channex');
        // }

        $token_data = json_decode($get_token_data['meta_data']);
        $token = isset($token_data->channex->api_key) && $token_data->channex->api_key ? $token_data->channex->api_key : null;

        if($token){
            $avail_array["values"] = $availability_data;
            echo 'availability request = ';prx($avail_array, 1);
            $response = $CI->channexintegration->update_availability($avail_array, $token);

            save_logs($property_id, 0, 0, json_encode($avail_array), $response);

            $response = json_decode($response, true);

            echo 'availability resp = ';prx($response, 1);
        }
    }
}

function channex_refresh_token(){
    $CI = &get_instance();
    $CI->load->model(array('Channex_int_model','Room_type_model'));

    $channex_data = $CI->Channex_int_model->get_channex_data($CI->company_id, 'channex');

    if($channex_data){
        if(isset($channex_data['created_date']) && $channex_data['created_date']){
            
            $timestamp = strtotime($channex_data['created_date']); //1373673600

            // getting current date 
            $cDate = strtotime(date('Y-m-d H:i:s'));

            // Getting the value of old date + 24 hours
            $oldDate = $timestamp + 86400; // 86400 seconds in 24 hrs

            if($oldDate < $cDate)
            {
                $token_data = json_decode($channex_data['meta_data']);

                $refresh_token = $token_data->data->attributes->refresh_token;

                $get_refresh_token_data = $CI->channexintegration->refresh_token($refresh_token);
                $response = json_decode($get_refresh_token_data);

                if(isset($response->data) && $response->data){

                    $data = array(
                                    'meta_data' => $get_refresh_token_data,
                                    'created_date' => date('Y-m-d H:i:s'),
                                    'email' => $response->data->relationships->user->data->attributes->email,
                                    'company_id' => $CI->company_id,
                                );

                    $CI->Channex_int_model->update_token($data);
                    return true;
                }
            } else {
                return false;
            }
        }
    }
}

function save_logs($ota_property_id = null, $request_type = null, $response_type = null, $xml_in = null, $xml_out = null) {

    $CI = &get_instance();
    $CI->load->model(array('Channex_int_model'));

    $data = array(
                    'ota_property_id' => $ota_property_id ? $ota_property_id : null,
                    'request_type' => ($request_type || $request_type == 0) ? $request_type : null,
                    'response_type' => ($response_type || $response_type == 0) ? $response_type : null,
                    'xml_in' => $xml_in ? $xml_in : null,
                    'xml_out' => $xml_out ? $xml_out : null,
                );
    $CI->Channex_int_model->save_logs($data);
}

function update_rates_fn($data){

    if(
        isset($data['start_date']) && 
        $data['start_date'] &&
        isset($data['end_date']) && 
        $data['end_date']
    ){
        $start_date = date('Y-m-d', strtotime($data['start_date']));
        $end_date = date('Y-m-d', strtotime($data['end_date']));
    } else {
        $start_date = date("Y-m-d");
        $end_date = Date("Y-m-d", strtotime("+500 days", strtotime($start_date)));
    }

    $date1 = date_create($start_date);
    $date2 = date_create($end_date);
    $diff = date_diff($date1, $date2);
    $date_diff = (int) $diff->format("%a");

    if ($date_diff > 90) {

        for($date = $start_date; $date <= $end_date; $date = date("Y-m-d", strtotime("+90 days", strtotime($date)))) {
            update_rates_batch($date, date("Y-m-d", strtotime("+90 days", strtotime($date))), $data);
        }

        if (date("Y-m-d", strtotime("-90 days", strtotime($date))) < $end_date) {
            update_rates_batch(date("Y-m-d", strtotime("-90 days", strtotime($date))), $end_date, $data);
        }

    } else {
        update_rates_batch($start_date, $end_date, $data);
    }
}

function update_rates_batch ($start_date, $end_date, $data) {

    $CI = &get_instance();
    $CI->load->model('../extensions/channex_integration/models/Channex_int_model');
    $CI->load->model('../extensions/channex_integration/models/Rates_model');
    $CI->load->model('../extensions/channex_integration/models/Companies_model');
    $CI->load->library('../extensions/channex_integration/libraries/ChannexIntegration');

    $rate_plan_id = isset($data['rate_plan_id']) && $data['rate_plan_id'] ? $data['rate_plan_id'] : null;

    $update_from = isset($data['update_from']) && $data['update_from'] ? $data['update_from'] : null;

    $CI->company_id = isset($data['company_id']) && $data['company_id'] ? $data['company_id'] : null;

    $channex_x_company = $CI->Channex_int_model->get_channex_x_company(null, $CI->company_id, 'channex');
    $property_id = $channex_x_company['ota_property_id'];
    $ota_x_company_id = $channex_x_company['ota_x_company_id'];

    if($rate_plan_id) {
        $rate_plan_data = $CI->Channex_int_model->get_channex_rate_plans_by_id($rate_plan_id, $CI->company_id, $ota_x_company_id);

        $rates = $data;
        $rate_array['values'] = $rate_data = $restriction_data = array();
        if($property_id && $rate_plan_data){

            foreach ($rate_plan_data as $key => $value) {
                if($value['minical_rate_plan_id'] == $rate_plan_id){
                    $rate_data[$key] =  array(
                                'date_from' => date('Y-m-d', strtotime($start_date)),
                                'date_to' => date('Y-m-d', strtotime($end_date)),
                                'property_id' => $property_id,
                                'rate_plan_id' => $value['ota_rate_plan_id']
                            );
                    if(isset($rates['minimum_length_of_stay']) && $rates['minimum_length_of_stay'] != 'null' && $rates['minimum_length_of_stay'] != '0'){
                        $rate_data[$key]['min_stay_arrival'] = intval($rates['minimum_length_of_stay']);
                    }

                    if(isset($rates['minimum_length_of_stay']) && $rates['minimum_length_of_stay'] != 'null' && $rates['minimum_length_of_stay'] != '0'){
                        $rate_data[$key]['min_stay_through'] = intval($rates['minimum_length_of_stay']);
                    }

                    if(isset($rates['maximum_length_of_stay']) && $rates['maximum_length_of_stay'] != 'null' && $rates['maximum_length_of_stay'] != '0'){
                        $rate_data[$key]['max_stay'] = intval($rates['maximum_length_of_stay']);
                    }
                    if(isset($rates['closed_to_arrival']) && $rates['closed_to_arrival'] == 0){
                        $rate_data[$key]['closed_to_arrival'] = false;
                    } 
                    else if(isset($rates['closed_to_arrival']) && $rates['closed_to_arrival'] == 1){
                        $rate_data[$key]['closed_to_arrival'] = true;
                    }
                    if(isset($rates['closed_to_departure']) && $rates['closed_to_departure'] == 0){
                        $rate_data[$key]['closed_to_departure'] = false;
                    }
                    else if(isset($rates['closed_to_departure']) && $rates['closed_to_departure'] == 1){
                        $rate_data[$key]['closed_to_departure'] = true;
                    }
                    if(isset($rates['can_be_sold_online']) && $rates['can_be_sold_online'] == 1){
                        $rate_data[$key]['stop_sell'] = false;
                    }
                    else if(isset($rates['can_be_sold_online']) && $rates['can_be_sold_online'] == 0){
                        $rate_data[$key]['stop_sell'] = true;
                    }
                } 
            }

            $room_type_detail = $CI->Channex_int_model->get_room_type_by_rate_plan_id($rate_plan_id);

            $room_maximum_occupancy = $room_type_detail['max_occupancy'];

            if($channex_x_company['rate_update_type'] == 'OBP'){
                foreach ($rate_data as $key => $value) {
                    
                    for($i = 1; $i <= $room_maximum_occupancy; $i++)
                    {
                        if (isset($rates['adult_'.$i.'_rate']) && is_numeric($rates['adult_'.$i.'_rate']))
                        {
                            $rate_data[$key]['rates'][] = array(
                                                                "occupancy" => $i,
                                                                "rate" => intval($rates['adult_'.$i.'_rate'] * 100)
                                                                
                                                            );
                        } else if (isset($rates['additional_adult_rate']) && $rates['additional_adult_rate'] > 0 && isset($rates['adult_4_rate']) && $rates['adult_4_rate'] > 0 && $i > 4) {
                            $additional_adult_rate = ($rates['adult_4_rate'] + (($i - 4) * $rates['additional_adult_rate']));
                            $rate_data[$key]['rates'][] = array(
                                                                "occupancy" => $i,
                                                                "rate" => intval($additional_adult_rate * 100)
                                                            );
                        }
                    }
                }
            } else {
                foreach ($rate_data as $key => $value) {
                    $i = 2;
                    if (isset($rates['adult_'.$i.'_rate']) && is_numeric($rates['adult_'.$i.'_rate']))
                    {
                        $rate_data[$key]['rate'] = intval($rates['adult_'.$i.'_rate'] * 100);
                    }
                }
            }
        }
    }
    else {
        $rate_plan_data = $CI->Channex_int_model->get_channex_rate_plans_by_id(null, $CI->company_id, $ota_x_company_id);
    
        $get_ota = $CI->Channex_int_model->get_channex_data($CI->company_id, 'channex');

        $minical_rates = array();

        if($rate_plan_data){
            foreach($rate_plan_data as $key => $rate_plan){
                if(isset($rate_plan['minical_rate_plan_id']) && $rate_plan['minical_rate_plan_id']){

                    $rate_plan_id = $rate_plan['minical_rate_plan_id'];
                    $minical_rates[] = $CI->Rates_model->get_rates(
                                                        $rate_plan_id, 
                                                        $get_ota['ota_id'],
                                                        $start_date, 
                                                        $end_date);
                }
            }
        }

        $rate_array['values'] = $rate_data = array();

        $rate_plan_mapping = [];

        if($rate_plan_data){
            foreach ($rate_plan_data as $rate_plan_item) {
                $rate_plan_mapping[$rate_plan_item['minical_rate_plan_id']] = $rate_plan_item['ota_rate_plan_id'];
            }
        }


        if($property_id){

            foreach ($minical_rates as $key => $minical_rate) {
                foreach($minical_rate as $key1 => $minical_rate_item){
                    $rate_data_item =  array(
                        'date_from' => $minical_rate_item['date_start'],
                        'date_to' => $minical_rate_item['date'],
                        'property_id' => $property_id,
                        'rate_plan_id' => $rate_plan_mapping[$minical_rate_item['rate_plan_id']],
                        'rates' => []
                    );

                    if(isset($minical_rate_item['minimum_length_of_stay']) && $minical_rate_item['minimum_length_of_stay'] != 'null' && $minical_rate_item['minimum_length_of_stay'] != '0'){
                        $rate_data_item['min_stay_arrival'] = intval($minical_rate_item['minimum_length_of_stay']);
                    }
                    if(isset($minical_rate_item['minimum_length_of_stay']) && $minical_rate_item['minimum_length_of_stay'] != 'null' && $minical_rate_item['minimum_length_of_stay'] != '0'){
                        $rate_data_item['min_stay_through'] = intval($minical_rate_item['minimum_length_of_stay']);
                    }
                    if(isset($minical_rate_item['maximum_length_of_stay']) && $minical_rate_item['maximum_length_of_stay'] != 'null' && $minical_rate_item['maximum_length_of_stay'] != '0'){
                        $rate_data_item['max_stay'] = intval($minical_rate_item['maximum_length_of_stay']);
                    }
                    if(isset($minical_rate_item['closed_to_arrival']) && $minical_rate_item['closed_to_arrival'] == 0){
                        $rate_data_item['closed_to_arrival'] = false;
                    } 
                    else if(isset($minical_rate_item['closed_to_arrival']) && $minical_rate_item['closed_to_arrival'] == 1){
                        $rate_data_item['closed_to_arrival'] = true;
                    }
                    if(isset($minical_rate_item['closed_to_departure']) && $minical_rate_item['closed_to_departure'] == 0){
                        $rate_data_item['closed_to_departure'] = false;
                    } 
                    else if(isset($minical_rate_item['closed_to_departure']) && $minical_rate_item['closed_to_departure'] == 1){
                        $rate_data_item['closed_to_departure'] = true;
                    }
                    if(isset($minical_rate_item['can_be_sold_online']) && $minical_rate_item['can_be_sold_online'] == 1){
                        $rate_data_item['stop_sell'] = false;
                    }
                    else if(isset($minical_rate_item['can_be_sold_online']) && $minical_rate_item['can_be_sold_online'] == 0){
                        $rate_data_item['stop_sell'] = true;
                    }

                    if($channex_x_company['rate_update_type'] == 'OBP'){
                        for ($i = 1; $i <= $minical_rate_item['max_occupancy']; $i++) {

                            if ($i <= 4) {
                                if (isset($minical_rate_item["adult_{$i}_rate"]) && $minical_rate_item["adult_{$i}_rate"]) {
                                    $rate_data_item['rates'][] = array(
                                        "occupancy" => $i,
                                        "rate" => intval($minical_rate_item["adult_{$i}_rate"] * 100)
                                        
                                    );
                                }
                            } else if (
                                        isset($minical_rate_item['additional_adult_rate']) && 
                                        $minical_rate_item['additional_adult_rate'] > 0 && 
                                        isset($minical_rate_item['adult_4_rate']) && 
                                        $minical_rate_item['adult_4_rate'] > 0 && 
                                        $i > 4
                                    ) {
                                $additional_adult_rate = ($minical_rate_item['adult_4_rate'] + (($i - 4) * $minical_rate_item['additional_adult_rate']));
                                $rate_data_item['rates'][] = array(
                                    "occupancy" => $i, 
                                    "rate" => intval($additional_adult_rate * 100)
                                );
                            }
                        }
                    } else {
                        unset($rate_data_item['rates']);
                        $i = 2;
                        if (isset($minical_rate_item['adult_'.$i.'_rate']) && is_numeric($minical_rate_item['adult_'.$i.'_rate']))
                        {
                            $rate_data_item['rate'] = intval($minical_rate_item['adult_'.$i.'_rate'] * 100);
                        }
                    }

                    // add more restrictions.

                    $rate_data[] = $rate_data_item;
                }
            }
        }
    }

    $get_token_data = $CI->Channex_int_model->get_token(null, $CI->company_id, 'channex');
    
    // if(channex_refresh_token()){
    //     $get_token_data = $CI->Channex_int_model->get_token(null, $CI->company_id, 'channex');
    // }

    $token_data = json_decode($get_token_data['meta_data']);
    $token = isset($token_data->channex->api_key) && $token_data->channex->api_key ? $token_data->channex->api_key : null;

    if($token){
        $rate_array['values'] = $rate_data;
        echo 'rates request = ';prx($rate_array, 1);
        $response = $CI->channexintegration->update_restrictions($rate_array, $token);

        save_logs($property_id, 1, 0, json_encode($rate_array), $response);

        $response = json_decode($response, true);
        echo 'rates resp = ';prx($response, 1);
    }
}