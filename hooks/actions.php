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
    $CI->module_name = $CI->router->fetch_module();

    if($CI->module_name == '') {
        foreach ($CI->all_active_modules as $key => $value) {
            if($value['name'] == 'Channex Integration') {
                $CI->module_name = $key;
                break;
            }
        }
    }

    $CI->load->model('../extensions/'.$CI->module_name.'/models/Channex_int_model');
    $CI->load->model('../extensions/'.$CI->module_name.'/models/Room_type_model');
    $CI->load->model('../extensions/'.$CI->module_name.'/models/Companies_model');
    $CI->load->library('../extensions/'.$CI->module_name.'/libraries/ChannexIntegration');
    $CI->load->library('../extensions/'.$CI->module_name.'/libraries/ChannexEmailTemplate');

    $end_date = date("Y-m-d", strtotime("+1 day", strtotime($end_date)));
    
    if(isset($data['room_type_id']) && $data['room_type_id'])
        $room_type_id = explode(',', $data['room_type_id']);
    else
        $room_type_id = null;

    $update_from = isset($data['update_from']) && $data['update_from'] ? $data['update_from'] : null;

    $CI->company_id = isset($data['company_id']) && $data['company_id'] ? $data['company_id'] : $this->session->userdata('anonymous_company_id');

    $channex_x_company = $CI->Channex_int_model->get_channex_x_company(null, $CI->company_id, 'channex');

    $property_id = $channex_x_company['ota_property_id'];
    $ota_x_company_id = $channex_x_company['ota_x_company_id'];

    $is_error = false;
    $error_cause = '';
    $email_data = array();

    if($room_type_id){
        $room_type_data = $CI->Channex_int_model->get_channex_room_types_by_id($room_type_id, $CI->company_id, $ota_x_company_id);
    }
    else{
        $room_type_data = $CI->Channex_int_model->get_channex_room_types_by_id(null, $CI->company_id, $ota_x_company_id);
    }

    $avail_array["values"] = $availability_data = array();
    if($room_type_data){

        $get_ota = $CI->Channex_int_model->get_channex_data($CI->company_id, 'channex');

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

                        $start_date = $avail['date_start'];
                        $end_date = strtotime($avail['date_start']) > strtotime("-1 day", strtotime($avail['date_end'])) ? $avail['date_end'] : date("Y-m-d", strtotime("-1 day", strtotime($avail['date_end'])));
                    
                        if ($start_date < gmdate('Y-m-d')) {
                            // discard all past dates for availability update
                            $start_date = gmdate('Y-m-d');

                            $start_gmt_minus_one_date = date("Y-m-d", strtotime("-1 day", strtotime($start_date)));
                            if ($start_gmt_minus_one_date <= $end_date) {
                                $availability_data[] =  array(
                                    "availability" => (int)$avail['availability'],
                                    "date_from" => $start_gmt_minus_one_date,
                                    "date_to" => $start_gmt_minus_one_date,
                                    "property_id" => $property_id,
                                    "room_type_id" => $value['ota_room_type_id']
                                );
                            }
                        }

                        if ($start_date > $end_date) {
                            continue;
                        } else if ($start_date == $end_date) {
                            $availability_data[] =  array(
                                "availability" => (int)$avail['availability'],
                                "date_from" => $start_date,
                                "date_to" => $end_date,
                                "property_id" => $property_id,
                                "room_type_id" => $value['ota_room_type_id']
                            );
                        } else {
                            $availability_data[] =  array(
                                "availability" => (int)$avail['availability'],
                                "date_from" => $start_date,
                                "date_to" => $start_date,
                                "property_id" => $property_id,
                                "room_type_id" => $value['ota_room_type_id']
                            );
                            $availability_data[] =  array(
                                "availability" => (int)$avail['availability'],
                                "date_from" => date("Y-m-d", strtotime("+1 day", strtotime($start_date))),
                                "date_to" => $end_date,
                                "property_id" => $property_id,
                                "room_type_id" => $value['ota_room_type_id']
                            );
                        }
                    }
                }
            } else {
                $is_error = true;
                $error_cause = 'availability_not_found';
            }
        }
    
        $get_token_data = $CI->Channex_int_model->get_token(null, $CI->company_id, 'channex');
        
        $token_data = json_decode($get_token_data['meta_data']);
        $token = isset($token_data->channex->api_key) && $token_data->channex->api_key ? $token_data->channex->api_key : null;

        if($token){
            $avail_array["values"] = $availability_data;

            if($data['update_from'] != 'booking_controller') {
            echo 'availability request = ';prx($avail_array, 1);
            }

            $response = $CI->channexintegration->update_availability($avail_array, $token);

            save_logs($property_id, 0, 0, json_encode($avail_array), $response);

            $response = json_decode($response, true);

            if($data['update_from'] != 'booking_controller') {
            echo 'availability resp = ';prx($response, 1);
            }
        } else {
            $is_error = true;
            $error_cause = 'availability_token_not_found';
        }

        if($is_error){
            // email to support team
            $email_data = array(
                                'property_id'       => $property_id,
                                'company_id'        => $CI->company_id,
                                'error_cause'       => $error_cause,
                                // 'xml_in'            => json_encode($avail_array),
                                // 'xml_out'           => $response,
                                'ota_x_company_id'  => $ota_x_company_id,
                                'ota_id'            => $get_ota['ota_id'],
                                'start_date'        => $start_date,
                                'end_date'          => date("Y-m-d", strtotime("-1 day", strtotime($end_date))),
                                'company_access_key'=> $company_access_key,
                                'room_types_avail_array'=> $room_types_avail_array,
                                'get_token_data'    => $get_token_data,
                                'datetime'          => date('Y-m-d H:i:s')
                            );
            if($room_type_id){
                $email_data['room_type_id'] = $room_type_id;
            }

            $CI->channexemailtemplate->send_error_alert_email($email_data);
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
    $CI->module_name = $CI->router->fetch_module();

    if($CI->module_name == '') {
        foreach ($CI->all_active_modules as $key => $value) {
            if($value['name'] == 'Channex Integration') {
                $CI->module_name = $key;
                break;
            }
        }
    }
    
    $CI->load->model('../extensions/'.$CI->module_name.'/models/Channex_int_model');
    $CI->load->model('../extensions/'.$CI->module_name.'/models/Rates_model');
    $CI->load->model('../extensions/'.$CI->module_name.'/models/Companies_model');
    $CI->load->library('../extensions/'.$CI->module_name.'/libraries/ChannexIntegration');
    $CI->load->library('../extensions/'.$CI->module_name.'/libraries/ChannexEmailTemplate');

    $rate_plan_id = isset($data['rate_plan_id']) && $data['rate_plan_id'] ? $data['rate_plan_id'] : null;

    $update_from = isset($data['update_from']) && $data['update_from'] ? $data['update_from'] : null;

    $CI->company_id = isset($data['company_id']) && $data['company_id'] ? $data['company_id'] : null;

    $channex_x_company = $CI->Channex_int_model->get_channex_x_company(null, $CI->company_id, 'channex');
    $property_id = $channex_x_company['ota_property_id'];
    $ota_x_company_id = $channex_x_company['ota_x_company_id'];

    $is_error = false;
    $is_error_send = false;
    $error_cause = '';
    $email_data = array();

    $currency_array = array('JPY', 'VND');

    $get_ota = $CI->Channex_int_model->get_channex_data($CI->company_id, 'channex');

    if($rate_plan_id)
        $rate_plan_data = $CI->Channex_int_model->get_channex_rate_plans_by_id($rate_plan_id, $CI->company_id, $ota_x_company_id);
    else
        $rate_plan_data = $CI->Channex_int_model->get_channex_rate_plans_by_id(null, $CI->company_id, $ota_x_company_id);

    $minical_rates = array();

    if($rate_plan_data){
        foreach($rate_plan_data as $key => $rate_plan){
            if(isset($rate_plan['minical_rate_plan_id']) && $rate_plan['minical_rate_plan_id']){

                $rate_plan_id = $rate_plan['minical_rate_plan_id'];

                if($rate_plan_id){
                    $is_error_send = true;
                }

                $minical_rates[] = $CI->Rates_model->get_rates(
                                                    $rate_plan_id, 
                                                    $get_ota['ota_id'],
                                                    $start_date, 
                                                    $end_date);
            }
        }
    }

    $rate_array['values'] = $rate_data = array();

    $rate_plan_mapping = array();
    $rate_plan_map = array();

    if($rate_plan_data){
        foreach ($rate_plan_data as $rate_plan_item) {
            $rate_plan_mapping[$rate_plan_item['minical_rate_plan_id']] = $rate_plan_item['ota_rate_plan_id'];
            $rate_plan_mapping[$rate_plan_item['minical_rate_plan_id'].'-rate_update_type'] = $rate_plan_item['rate_update_type'];
        
            $rate_plan_map[] = $rate_plan_mapping;
        }
    }

    if($property_id){
        if($minical_rates){
            foreach ($minical_rates as $key => $minical_rate) {
                if($minical_rate){
                    foreach($minical_rate as $key1 => $minical_rate_item){
                        if($minical_rate_item){
                            if($rate_plan_map && count($rate_plan_map) > 0) {
                                foreach($rate_plan_map as $rpm) {
                                    if(isset($rpm[$minical_rate_item['rate_plan_id']]) && $rpm[$minical_rate_item['rate_plan_id']]) {
                                        $rate_data_item =  array(
                                            'date_from' => $minical_rate_item['date_start'],
                                            'date_to' => $minical_rate_item['date'],
                                            'property_id' => $property_id,
                                            'rate_plan_id' => $rpm[$minical_rate_item['rate_plan_id']],
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
                                        
                                        if($rpm[$minical_rate_item['rate_plan_id'].'-rate_update_type'] == 'OBP'){
                                            for ($i = 1; $i <= $minical_rate_item['max_occupancy']; $i++) {

                                                if ($i <= 4) {
                                                    if (isset($minical_rate_item["adult_{$i}_rate"]) && $minical_rate_item["adult_{$i}_rate"]) {
                                                        $rate_data_item['rates'][] = array(
                                                            "occupancy" => $i,
                                                            "rate" => in_array($minical_rate_item['currency_code'], $currency_array) ? intval($minical_rate_item["adult_{$i}_rate"]) : intval($minical_rate_item["adult_{$i}_rate"] * 100)
                                                            
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
                                                        "rate" => in_array($minical_rate_item['currency_code'], $currency_array) ? intval($additional_adult_rate) : intval($additional_adult_rate * 100)
                                                    );
                                                }
                                            }
                                        } else {
                                            unset($rate_data_item['rates']);
                                            $i = 2;
                                            if (isset($minical_rate_item['adult_'.$i.'_rate']) && is_numeric($minical_rate_item['adult_'.$i.'_rate']))
                                            {
                                                $rate_data_item['rate'] = in_array($minical_rate_item['currency_code'], $currency_array) ? intval($minical_rate_item['adult_'.$i.'_rate']) : intval($minical_rate_item['adult_'.$i.'_rate'] * 100);
                                            }
                                        }
                                    }

                                    $rate_data[] = $rate_data_item;
                                }
                            }

                            // add more restrictions.

                        } else {
                            $is_error = true;
                            $error_cause = 'rates_not_found';
                        }
                    }
                } else {
                    $is_error = true;
                    $error_cause = 'rates_not_found';
                }
            }
        } else {
            $is_error = true;
            $error_cause = 'rates_not_found';
        }
    }
    

    $get_token_data = $CI->Channex_int_model->get_token(null, $CI->company_id, 'channex');
    
    $token_data = json_decode($get_token_data['meta_data']);
    $token = isset($token_data->channex->api_key) && $token_data->channex->api_key ? $token_data->channex->api_key : null;

    if($token){
        
        $rate_data = array_map("unserialize", array_unique(array_map("serialize", $rate_data)));
        
        $rate_data = array_values($rate_data);

        $rate_array['values'] = $rate_data;
        echo 'rates request = ';prx($rate_array, 1);
        $response = $CI->channexintegration->update_restrictions($rate_array, $token);

        save_logs($property_id, 1, 0, json_encode($rate_array), $response);

        $response = json_decode($response, true);
        echo 'rates resp = ';prx($response, 1);
    } else {
        $is_error = true;
        $error_cause = 'rates_token_not_found';
    }

    if($is_error && $is_error_send){ 
            // email to support team
            $email_data = array(
                                'property_id'       => $property_id,
                                'company_id'        => $CI->company_id,
                                'error_cause'       => $error_cause,
                                'ota_x_company_id'  => $ota_x_company_id,
                                'ota_id'            => $get_ota['ota_id'],
                                'start_date'        => $start_date,
                                'end_date'          => $end_date,
                                'minical_rates'     => $minical_rates,
                                'get_token_data'    => $get_token_data,
                                'datetime'          => date('Y-m-d H:i:s')
                            );
            if($rate_plan_id){
                $email_data['rate_plan_id'] = $rate_plan_id;
            }

            $CI->channexemailtemplate->send_error_alert_email($email_data);
        }
}