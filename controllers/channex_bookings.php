<?php
class Channex_bookings extends MY_Controller
{
    public $module_name;
    function __construct()
    {

        parent::__construct();
        $this->module_name = $this->router->fetch_module();
        $this->load->model('../extensions/'.$this->module_name.'/models/Channex_int_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Room_type_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Rate_plan_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Customer_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Card_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Charge_types_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Currency_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Companies_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Rooms_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Rates_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Date_range_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Booking_room_history_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Booking_log_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Channex_booking_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Invoice_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/OTA_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/Availability_model');
        
        $this->load->library('../extensions/'.$this->module_name.'/libraries/ChannexIntegration');
        $this->load->library('../extensions/'.$this->module_name.'/libraries/ChannexEmailTemplate');        
        $this->load->helper('my_assets_helper');
        
        $view_data['menu_on'] = true;

        $this->channex_url = ($this->config->item('app_environment') == "development") ? "https://secure-staging.channex.io" : "https://secure.channex.io";
        $this->channex_host = ($this->config->item('app_environment') == "development") ? "staging.channex.io" : "app.channex.io";

        $this->load->vars($view_data);
    }   

    function refresh_token($company_id){
        $channex_data = $this->Channex_int_model->get_channex_data($company_id, 'channex');
        
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

                    $get_refresh_token_data = $this->channexintegration->refresh_token($refresh_token); // refresh_token function from channex integration library
                    $response = json_decode($get_refresh_token_data);

                    if(isset($response->data) && $response->data){

                        $data = array(
                                        'meta_data' => $get_refresh_token_data,
                                        'created_date' => date('Y-m-d H:i:s'),
                                        'email' => $response->data->relationships->user->data->attributes->email,
                                        'company_id' => $company_id,
                                    );

                        $this->Channex_int_model->update_token($data);
                        return true;
                    }
                } else {
                    return false;
                }
            }
        }
    }

    function channex_retrieve_booking($ota_booking_id){

        $get_token_data = $this->Channex_int_model->get_token(null, $this->company_id, 'channex');
        $booking_response = array();

        $token_data = json_decode($get_token_data['meta_data']);
        $token = $token_data->channex->api_key;

        $booking = $this->channexintegration->revision_bookings($ota_booking_id, $token);

        $response = json_decode($booking);
        $this->channex_get_bookings($this->company_id, $response);
    }

    function channex_get_bookings($company_id, $booking_revesion = array()){

        $is_error = false;
        $booking_loop = true;
        $error_book_type = '';
        $error_cause = '';
        $email_data = array();
            
        if($booking_revesion){

            $booking_response->data[] = isset($booking_revesion->data) && $booking_revesion->data ? $booking_revesion->data : null;
            $is_pci_booking = true;

            $api_url = $this->channex_url;
            $method = '/api/v1/booking_revisions/:id';

            $get_token_data = $this->Channex_int_model->get_token(null, $company_id, 'channex');

            $token_data = json_decode($get_token_data['meta_data']);
            $token = $token_data->channex->api_key;

        } else {

            $get_token_data = $this->Channex_int_model->get_token(null, $company_id, 'channex');
            $booking_response = array();

            $token_data = null;
            if(isset($get_token_data['meta_data']) && $get_token_data['meta_data']) {
                $token_data = json_decode($get_token_data['meta_data']);
            }

            if($token_data)
            {
                $token = $token_data->channex->api_key;

                $channex_x_company = $this->Channex_int_model->get_channex_x_company(null, $company_id, 'channex');
                $property_id = $channex_x_company['ota_property_id'];

                if($property_id)
                {
                    $api_url = $this->channex_url;
                    $method = '/api/v1/booking_revisions/feed?filter[property_id]='.$property_id.'&pagination[page]=1&pagination[limit]=100&order[inserted_at]=desc';

                    $headers = array(
                        "Host: ".$this->channex_host,
                        "Content-Type: application/json",
                        "user-api-key: ".$token
                    );

                    if(function_exists('retrieve_booking')){ // retrieve_booking function from Channex PCI helper
                        $booking_response = retrieve_booking($api_url.$method, $headers, 'POST', array());
                        $is_pci_booking = true;
                    } else {
                        $response = $this->channexintegration->get_bookings($property_id, $token); // get_bookings function from channex integration library 
                        $booking_response = json_decode($response);
                        $is_pci_booking = false;
                    }

                    $this->save_logs($property_id, 2, 0, $api_url.$method, json_encode($booking_response));
                } else {
                    $is_error = true;
                    $error_cause = 'property_not_found';
                }
            } else {
                $is_error = true;
                $error_cause = 'token_not_found';
            }
        }

        $booking_array = array();
        
        // prx(json_encode($booking_response));
        // $booking_response = file_get_contents("php://input");
        // $booking_response = json_decode($booking_response);

        if (isset($booking_response->data)){

            foreach ($booking_response->data as $key => $book) {
                
                if(isset($book->attributes) && $book->attributes){
                    $reservation = $book->attributes;
                } else {
                    echo 'wrong id'; die;
                }
                
                $property_id = $reservation->property_id;

                $raw_message = isset($reservation->raw_message) ? json_decode($reservation->raw_message, true) : null;
                // $raw_message = null;
                $comment = $CommissionPayableAmount = $CommissionAmount = $CommissionCurrencyCode = $CommissionDecimalPlace = "";

                if($raw_message){
                    if(isset($raw_message['ResGlobalInfo']['TotalCommissions']) && $raw_message['ResGlobalInfo']['TotalCommissions']){
                        $comment = $raw_message['ResGlobalInfo']['TotalCommissions']['Comment'];
                        $CommissionPayableAmount = $raw_message['ResGlobalInfo']['TotalCommissions']['CommissionPayableAmount'];
                        $CommissionAmount = $raw_message['ResGlobalInfo']['TotalCommissions']['CommissionPayableAmount']['amount'];
                        $CommissionCurrencyCode = $raw_message['ResGlobalInfo']['TotalCommissions']['CommissionPayableAmount']['currency_code'];
                        $CommissionDecimalPlace = $raw_message['ResGlobalInfo']['TotalCommissions']['CommissionPayableAmount']['decimal_places'];
                    }
                }

                $channex_x_company = $this->Channex_int_model->get_channex_x_company($property_id, null, 'channex');

                if(!isset($channex_x_company['ota_x_company_id']) && !$channex_x_company['ota_x_company_id']){
                    $is_error = true;
                    $error_cause = 'ota_x_company_id_not_found';
                }

                $channex_x_company_id = isset($channex_x_company['ota_x_company_id']) && $channex_x_company['ota_x_company_id'] ? $channex_x_company['ota_x_company_id'] : null;

                $channex_booking_id = $reservation->ota_reservation_code;
                $channex_booking_ack_id = $reservation->id;
                
                $channex_booking_source = isset($reservation->ota_name) && $reservation->ota_name ? $reservation->ota_name : "";

                $rooms = $reservation->rooms;

                if($rooms){
                    foreach ($rooms as $key => $room) {
                    
                        $channex_room_type_id = $room->room_type_id;
                        $minical_room_type_id = $this->Channex_int_model->get_minical_room_type_id($channex_room_type_id, $channex_x_company_id);

                        if(!$minical_room_type_id){
                            $is_error = true;
                            $booking_loop = false;
                            $error_channex_room_type_id = $channex_room_type_id;
                            $error_cause = 'minical_room_type_id_not_found';
                            $subject = 'Minical | Missed Reservation from Channex!';
                            $content = 'A reservation is missed while importing from Channex. <br/>The Room Types are not mapped correctly Minical. <br/>Please fix the mapping for following room type and go to Channex booking and resend the revision.';
                        }

                        switch ((string)$reservation->status) {
                            case 'new':
                                $booking_type = 'new';
                                break;
                            case 'modified':
                                $booking_type = 'modified';
                                break;
                            case 'cancelled':
                                $booking_type = 'cancelled';
                                break;
                        }

                        if ($booking_type == "cancelled")
                        {
                            $booking_array[] = array(
                                'ota_booking_id' => $channex_booking_id,
                                'booking_type' => $booking_type,
                                'company_id' => $company_id,
                                "source" => SOURCE_CHANNEX,
                                "minical_room_type_id" => $minical_room_type_id
                            );

                            $error_book_type = 'cancelled';
                        }
                        else
                        {
                            if(
                                isset($room->meta) &&
                                $room->meta &&
                                isset($room->meta->parent_rate_plan_id) &&
                                $room->meta->parent_rate_plan_id
                            ) {
                                $channex_rate_plan_id = $room->meta->parent_rate_plan_id;
                            } else {
                                $channex_rate_plan_id = isset($room->rate_plan_id) ? $room->rate_plan_id : '';
                            }

                            $minical_rate_plan_id = $this->Channex_int_model->get_minical_rate_plan_id($channex_rate_plan_id, $channex_room_type_id, $channex_x_company_id);
                            $check_in_date = (string)$room->checkin_date;
                            $check_out_date = (string)$room->checkout_date;
                            $adult_count = (int)$room->occupancy->adults;
                            $child_count = (int)$room->occupancy->children;
                            $currency = $reservation->currency;

                            $primary_guest = $reservation->customer;
                            $per_day_rates = $room->days;

                            $notes = $reservation->notes;

                            if($comment){
                                $notes .= $notes ? "\n".(string)$comment : (string)$comment ;
                            }

                            if($CommissionAmount && $CommissionCurrencyCode){

                                if($CommissionDecimalPlace){
                                    $CommissionAmount = floatval($CommissionAmount / (pow(10,$CommissionDecimalPlace)));
                                }
                                
                                $notes .= "\nCommission: ".(string)$CommissionAmount. " ".$CommissionCurrencyCode;
                            }

                            $rate_array = array();
                            foreach($per_day_rates as $day => $day_rate)
                            {

                                $daily_rate = floatval($day_rate);
                                $base_rate = number_format($daily_rate, 2, ".", "");
                                
                                $date = (string)$day;
                                
                                $rate_array[] = array(
                                    'date' => $date,
                                    'base_rate' => $base_rate
                                );
                            }

                            $guest_name = $primary_guest->name;
                            $guest_name .= isset($primary_guest->surname) && $primary_guest->surname ? ' '.$primary_guest->surname : '';

                            $booking_data = array(
                                'ota_booking_id' => $channex_booking_id,
                                'booking_type' => $booking_type,
                                'company_id' => $company_id,
                                'minical_room_type_id' => $minical_room_type_id,
                                "source" => SOURCE_CHANNEX, // this represents channex
                                "sub_source" => $channex_booking_source,
                                "check_in_date" => $check_in_date,
                                "check_out_date" => $check_out_date,
                                "adult_count" => $adult_count,
                                "children_count" => $child_count,
                                "booking_notes" => "created from OTA (Booking ID: ".$channex_booking_id.")\n".(string)$notes,
                                "rate_plan" => array(
                                    "rate_plan_name" => "ota #".$channex_booking_id,
                                    "number_of_adults_included_for_base_rate" => $adult_count,
                                    "rates" => get_array_with_range_of_dates($rate_array, SOURCE_CHANNEX),
                                    "currency" => array ("currency_code" => (string)$currency),
                                    "minical_rate_plan_id" => $minical_rate_plan_id
                                ),
                                "booking_customer" => array(
                                    'company_id' => $company_id,
                                    'customer_name' => $guest_name,
                                    'phone' => $primary_guest->phone,
                                    'email' => (string)$primary_guest->mail,
                                    'address' => $primary_guest->address ? (string) $primary_guest->address : '', 
                                    'city' => $primary_guest->city ? (string) $primary_guest->city : '', 
                                    'country' => $primary_guest->country ? (string) $primary_guest->country : '', 
                                    'postal_code' => $primary_guest->zip ? (string) $primary_guest->zip : ''
                                )
                            );

                            $payment_card = $reservation->guarantee;
                            // Credit cart info
                            $cc_number = isset($payment_card->card_number) ? (string) $payment_card->card_number : '';
                            $cc_cvc = isset($payment_card->cvv) ? (string) $payment_card->cvv : '';
                            $expiry_date = isset($payment_card->expiration_date) ? (string) $payment_card->expiration_date : '';
                            
                            if($cc_number)
                            {
                                $booking_data += array(
                                    'card' => array(
                                        "number"    => substr($cc_number, -4),
                                        "exp_month" => substr($expiry_date, 0, 2),
                                        "exp_year"  => substr($expiry_date, 5),
                                        "name"      => (string)$payment_card->cardholder_name,
                                        "token"      => (string)$payment_card->token,
                                        "card_type"  => (string)$payment_card->card_type
                                    )
                                );

                                if($cc_cvc){
                                    $booking_data['card']['cvc'] = $cc_cvc;
                                }
                            }

                            $booking_array[] = $booking_data;
                        }
                    }
                } else {
                    $is_error = true;
                    $error_cause = 'rooms_not_found';
                }

                if($is_error){
                    // email to support team
                    $ota_id = $this->Channex_int_model->get_ota_id('channex');
                    $email_data = array(
                                        'property_id'       => isset($property_id) && $property_id ? $property_id : null,
                                        'company_id'        => $company_id,
                                        'channex_room_type_id' => isset($error_channex_room_type_id) && $error_channex_room_type_id ? $error_channex_room_type_id : null,
                                        'error_cause'       => $error_cause,
                                        'ota_x_company_id'  => $channex_x_company_id,
                                        'ota_id'            => $ota_id,
                                        'get_token_data'    => $token_data,
                                        'channex_x_company' => isset($channex_x_company) && $channex_x_company ? $channex_x_company : null,
                                        'reservation'       => isset($reservation) && $reservation ? $reservation : null,
                                        'subject'           => isset($subject) && $subject ? $subject : null,
                                        'content'           => isset($content) && $content ? $content : null,
                                        'source'            => isset($channex_booking_source) && $channex_booking_source ? $channex_booking_source : null,
                                        'channex_booking_id'=> isset($channex_booking_id) && $channex_booking_id ? $channex_booking_id : null,
                                        'datetime'          => gmdate('Y-m-d H:i:s')
                                    );

                    if($error_book_type != 'cancelled'){
                        $error_data = $this->channexemailtemplate->send_error_alert_email($email_data);
                    }
                    
                    $is_error = false;
                    if(!$booking_loop){
                        $booking_array = array();
                    }
                }

                // acknowledgement bookings
                $this->channexintegration->acknowledge_bookings($channex_booking_ack_id, $token);
            }   
        }

        if($is_error){
            // email to support team
            $ota_id = $this->Channex_int_model->get_ota_id('channex');
            $email_data = array(
                                'property_id'       => isset($property_id) && $property_id ? $property_id : null,
                                'company_id'        => $company_id,
                                'error_cause'       => $error_cause,
                                'ota_id'            => $ota_id,
                                'get_token_data'    => $token_data,
                                'channex_x_company' => isset($channex_x_company) && $channex_x_company ? $channex_x_company : null,
                                'datetime'          => gmdate('Y-m-d H:i:s')
                            );

            $error_data = $this->channexemailtemplate->send_error_alert_email($email_data);
            // echo $error_data['message'];
        }

        $bookings_to_be_deleted = array();
        $bookings_to_be_created = array();
        $modified_booking_charges_payments = array();
        $pms_confirmation_number = "";
        $is_booking_inserted = $pms_booking_ids = $booking_arr = array();

        if (count($booking_array) > 0)
        {
            foreach ($booking_array as $index => $booking)
            {
                if(
                    (
                        isset($booking['minical_room_type_id']) &&
                        $booking['minical_room_type_id']
                        // && isset($booking['rate_plan']['minical_rate_plan_id']) && 
                        // $booking['rate_plan']['minical_rate_plan_id']
                    ) || 
                    $booking['booking_type'] == 'cancelled'
                ){
                    if($booking['booking_type'] != 'modified')
                    {
                        if(empty($this->OTA_model->get_booking_by_ota_booking_id($booking['ota_booking_id'], $booking['booking_type'])))
                        {
                            $booking_arr[] = $booking;
                        } else {
                            echo "Booking already exists ID - ".$booking['ota_booking_id']."<br/>";
                            unset($booking_array[$index]); 
                            continue;
                        }
                    } else if($this->OTA_model->get_booking_by_ota_booking_id($booking['ota_booking_id'], $booking['booking_type'], $booking['check_in_date'], $booking['check_out_date'])) {

                        echo "Booking already exists ID - ".$booking['ota_booking_id']."<br/>";
                        unset($booking_array[$index]); 
                        continue;
                    } else {
                        $booking_arr[] = $booking;
                    }
                }
            }

            if($booking_arr){
                foreach ($booking_arr as $index => $booking) {

                    if ($booking['booking_type'] == 'cancelled')
                    {
                        $booking['this_booking_is_being_cancelled'] = true;
                        $bookings_to_be_deleted[] = $booking;
                    }
                    else
                    {
                        if($booking['booking_type'] == 'modified') // modified bookings are deleted, then created
                        {
                            $booking['this_booking_is_being_cancelled'] = false;
                            $bookings_to_be_deleted[] = $booking;
                            $bookings_to_be_created[] = $booking;                   
                        }
                        else // new booking!
                        {
                            $bookings_to_be_created[] = $booking;
                        }
                    }
                }
            }

            // bookings deletion
            foreach($bookings_to_be_deleted as $booking)
            {
                $booking_ids_to_be_deleted = $this->Channex_int_model->get_related_pms_booking_ids(
                                                                                        $booking['ota_booking_id']
                                                                                        );

                $param = Array("booking_ids_to_be_deleted" => $booking_ids_to_be_deleted);
                
                if ($booking['this_booking_is_being_cancelled'])
                {
                    $param = Array("pms_booking_ids" => $booking_ids_to_be_deleted, "action" => "cancel_booking");
                    $this->cancel_bookings($booking_ids_to_be_deleted);
                }
                else
                {
                    $pms_modified_booking_ids = array(
                        "pms_booking_ids" =>  $booking_ids_to_be_deleted,
                        "booking_type"   => $booking['booking_type']
                    );

                    if (!(isset($modified_booking_charges_payments[$booking['ota_booking_id']]) && count($modified_booking_charges_payments[$booking['ota_booking_id']]) > 0)) {
                        
                        $get_modified_booking_charges_and_payments = $this->get_modified_booking_charges_and_payments($pms_modified_booking_ids);
                        $modified_booking_charges_payments[$booking['ota_booking_id']] = $get_modified_booking_charges_and_payments;
                    }
                    
                    $this->delete_bookings($booking_ids_to_be_deleted);
                }
                // if the booking is being cancelled (but not modified), then send confirmation to OTA

                if ($booking['this_booking_is_being_cancelled'] && $booking['ota_booking_id'] && isset($booking_ids_to_be_deleted[0]) && $booking_ids_to_be_deleted[0])
                {
                    $ota_booking = array(
                        'ota_booking_id' => $booking['ota_booking_id'],
                        'pms_booking_id' => $booking_ids_to_be_deleted[0],
                        'create_date_time' => date('Y-m-d H:i:s', time()),
                        'booking_type' => 'cancelled',
                        'xml_out' => json_encode($booking, true)
                    );

                    $pms_booking_ids[] = isset($booking_ids_to_be_deleted[0]) && $booking_ids_to_be_deleted[0] ? $booking_ids_to_be_deleted[0] : null;

                    $existing_booking = $this->Channex_int_model->get_bookings($booking['ota_booking_id']);
                    
                    if (isset($existing_booking[0]['check_in_date']) && isset($existing_booking[0]['check_out_date'])) 
                    {


                        $update_availability_data = array(
                                        'start_date' => $existing_booking[0]['check_in_date'],
                                        'end_date' => $existing_booking[0]['check_out_date'],
                                        'room_type_id' => $booking['minical_room_type_id'],
                                        'company_id' => $company_id,
                                        'update_from' => 'extension'
                                                );

                        do_action('update_availability', $update_availability_data);
                    }

                    $this->OTA_model->insert_booking($ota_booking);

                    echo "Booking cancelled successfully ID - ".$booking['ota_booking_id']."<br/>";
                }
            }

            // bookings creation 
            foreach($bookings_to_be_created as $key => $booking){
                $response = $this->create_bookings($booking);

                $is_booking_inserted[$booking['ota_booking_id']] = true;

                $ota_booking = array(
                        'ota_booking_id' => $response['ota_booking_id'],
                        'booking_type' => $response['booking_type'],
                        'check_in_date' => $booking['check_in_date'],
                        'check_out_date' => $booking['check_out_date'],
                        'pms_booking_id' => $response['minical_booking_id'],
                        'create_date_time' => date('Y-m-d H:i:s', time()),
                        'xml_out' => json_encode($booking, true)
                    );

                $pms_booking_ids[] = isset($response['minical_booking_id']) && $response['minical_booking_id'] ? $response['minical_booking_id'] : null;
                
                $this->OTA_model->insert_booking($ota_booking);

                if(count($modified_booking_charges_payments) > 0 && $response['minical_booking_id']) // set previous charges and payments with modified bookings
                {
                    if(isset($modified_booking_charges_payments[$response['ota_booking_id']]) && $modified_booking_charges_payments[$response['ota_booking_id']])
                    {
                        $prev_charges_payments = $modified_booking_charges_payments[$response['ota_booking_id']];
                       
                        $prev_charges = isset($prev_charges_payments['charges']) ? $prev_charges_payments['charges'] : array();
                        $prev_payments = isset($prev_charges_payments['payments']) ? $prev_charges_payments['payments'] : array();
                        $prev_state = isset($prev_charges_payments['states'][$key]) ? $prev_charges_payments['states'][$key] : '';
                        $prev_color = isset($prev_charges_payments['colors'][$key]) ? $prev_charges_payments['colors'][$key] : '';

                        $new_payments = $new_charges = array();
                        
                        if(!empty($prev_charges))
                        {
                            foreach($prev_charges as $prev_charge)
                            { 
                                if($prev_charge['booking_id'] == $param['booking_ids_to_be_deleted'][$key]){
                                    unset($prev_charge['charge_id']);
                                    $prev_charge['booking_id'] = $response['minical_booking_id']; // set new booking id   
                                    $new_charges[] = $prev_charge;
                                }
                            }
                        }
                        
                        if(!empty($prev_payments))
                        {
                            foreach($prev_payments as $prev_payment)
                            { 
                                if($prev_payment['booking_id'] == $param['booking_ids_to_be_deleted'][$key]){
                                    unset($prev_payment['payment_id']);
                                    $prev_payment['booking_id'] = $response['minical_booking_id'];// set new booking id   
                                    $new_payments[] = $prev_payment;
                                }
                            }
                        }

                        $new_charges_payments = array(
                            'charges' => $new_charges,
                            'payments' => $new_payments,
                            'state' => $prev_state,
                            'color' => $prev_color
                        );

                        $this->modified_booking_charges_and_payments($new_charges_payments, $response['minical_booking_id']);
                    }
                }

                $company_detail = $this->Companies_model->get_company_by_booking($response['minical_booking_id']);

                $this->_send_booking_emails($response['minical_booking_id'], $response['booking_type'], $company_detail, $response['ota_booking_id'], $is_booking_inserted, $booking['check_in_date'], $booking['check_out_date']);
                
                if($is_pci_booking){
                    $xml_in = $api_url.$method;
                    $xml_out = json_encode($booking);
                } else {
                    $xml_in = 'whithout card';
                    $xml_out = json_encode($booking);
                }

                $property_data = $this->Channex_int_model->get_channex_x_company(null, $company_id, 'channex');

                $this->save_logs($property_data['ota_property_id'], 2, 0, $xml_in, $xml_out);
                
                if($response['booking_type'] == 'modified'){
                    $msg = "Booking modified successfully";
                } else {
                    $msg = "Booking created successfully";
                }

                $update_availability_data = array(
                                        'start_date' => $booking['check_in_date'],
                                        'end_date' => $booking['check_out_date'],
                                        'room_type_id' => $booking['minical_room_type_id'],
                                        'company_id' => $company_id,
                                        'update_from' => 'extension'
                                                );

                do_action('update_availability', $update_availability_data);

                $booking_action_data = array(
                        'booking_id' => $response['minical_booking_id'], 
                        'company_id'=> $company_id,
                        'booking_type'=> $response['booking_type'],
                        'booking_from'=> "Channex"

                );

                do_action('post.add.booking', $booking_action_data);

                echo $msg." ID - ".$response['ota_booking_id']."<br/>";
            }
        }
    }

    function get_modified_booking_charges_and_payments($param)
    {
        $charges_payments['payments'] = $charges_payments['charges'] = $charges_payments['states'] = $charges_payments['colors'] = array();
        $charges_total = $payments_total = $booking_details = array();
        $pms_booking_ids = $param['pms_booking_ids'];
        $booking_type = $param['booking_type'];

        if(count($pms_booking_ids) > 0)
        {
            foreach($pms_booking_ids as $pms_booking_id)
            {
                $charges = $this->Channex_int_model->get_charges($pms_booking_id, $exclude_deleted_bookings = true);
                $payments = $this->Channex_int_model->get_payments($pms_booking_id, $exclude_deleted_bookings = true);
                $booking_detail = $this->Channex_int_model->get_booking_detail($pms_booking_id);
                if(!empty($charges))
                    $charges_total[$pms_booking_id] = $charges;
                if(!empty($payments))
                    $payments_total[$pms_booking_id] = $payments;
                if(!empty($booking_detail))
                    $booking_details[$pms_booking_id] = $booking_detail;
            }
        }
        
        if(count($charges_total) > 0)
        {
            foreach($charges_total as $charges)
            {
                foreach($charges as $charge){
                    $charges_payments['charges'][] = $charge;
                }
            }
        }
        
        if(count($payments_total) > 0)
        {
            foreach($payments_total as $payments)
            {
                foreach($payments as $payment){
                    $charges_payments['payments'][] = $payment;
                }
            }
        }
        if(count($booking_details) > 0)
        {
            foreach($booking_details as $booking_detail)
            {
                $charges_payments['states'][] = $booking_detail['state'];
                $charges_payments['colors'][] = $booking_detail['color'];
            }
        }
        
        if(count($charges_payments) > 0)
            return $charges_payments;
        else
            return null;
    }

    function modified_booking_charges_and_payments($new_charge_payments, $new_pms_booking_id)
    {
        $param = array(
            "new_charges" => $new_charge_payments['charges'],
            "new_payments" => $new_charge_payments['payments'],
            "new_state" => $new_charge_payments['state'],
            "new_color" => $new_charge_payments['color'],
            "new_pms_booking_id" => $new_pms_booking_id
        );

        $response =  $this->set_modified_booking_charges_and_payments($param);
    }

    function set_modified_booking_charges_and_payments($param)
    {
        $new_charges = $param['new_charges'];
        $new_payments = $param['new_payments'];
        $new_state = $param['new_state'];
        $new_color = $param['new_color'];
        $new_pms_booking_id = $param['new_pms_booking_id'];
        if(count($new_charges) > 0)
        {
            $this->Channex_int_model->insert_charges($new_charges);

            $post_charge_data = $new_charges;
            do_action('post.create.charge', $post_charge_data);
        }
        
        if(count($new_payments) > 0)
        {
            $this->Channex_int_model->insert_payments($new_payments);
        }

        if($new_state)
        {
            $this->Channex_int_model->update_booking(array('state' => $new_state, 'booking_id' => $new_pms_booking_id));
        }

        $this->Channex_int_model->update_booking(array('color' => $new_color, 'booking_id' => $new_pms_booking_id));
        
        $this->Channex_int_model->update_booking_balance($new_pms_booking_id); 
        
        return array('success' => true);
    }

    function delete_bookings($booking_ids_to_be_deleted)
    {
        if (isset($booking_ids_to_be_deleted))
        {
            if($booking_ids_to_be_deleted && count($booking_ids_to_be_deleted) > 0){
                
                $this->Channex_int_model->delete_bookings($booking_ids_to_be_deleted);

                $post_booking_data['booking_ids'] = $booking_ids_to_be_deleted;
                do_action('post.delete.booking', $post_booking_data);
                
                foreach($booking_ids_to_be_deleted as $booking_id_to_be_deleted) {
                    
                    $booking = $this->Channex_int_model->get_booking($booking_id_to_be_deleted);
                    $company = null;
                    if($booking && isset($booking['company_id'])) {
                        $company = $this->Companies_model->get_company_data($booking['company_id']);
                    }
                    $log_data = array(
                        'selling_date' => $company && isset($company['selling_date']) ? $company['selling_date'] : date('Y-m-d'),
                        'user_id' => 0, // User_id 0 is System user (null null)
                        'booking_id' => $booking_id_to_be_deleted,                  
                        'date_time' => gmdate('Y-m-d H:i:s'),
                        'log' => 'OTA Booking deleted',
                        'log_type' => SYSTEM_LOG
                    );
                    
                    $this->Booking_log_model->insert_log($log_data);
                }
            }
        }
    }

    function cancel_bookings($pms_booking_ids)
    {
        if(count($pms_booking_ids) > 0)
        {
            foreach ($pms_booking_ids as $pms_booking_id)
            {
                $this->Channex_int_model->cancel_booking($pms_booking_id);

                $post_booking_data['booking_id'] = $pms_booking_id;
                do_action('post.update.booking', $post_booking_data);

                $company_detail = $this->Companies_model->get_company_by_booking($pms_booking_id);
                $log_data = array(
                    'selling_date' => $company_detail['selling_date'],
                    'user_id' => 0, // User_id 0 is System user (null null)
                    'booking_id' => $pms_booking_id,                  
                    'date_time' => gmdate('Y-m-d H:i:s'),
                    'log' => 'OTA Booking cancelled',
                    'log_type' => SYSTEM_LOG

                );

                $this->Booking_log_model->insert_log($log_data);

                $this->_send_booking_emails($pms_booking_id, 'cancelled', $company_detail);
            }
        }
    }
    
    function create_bookings($booking){

        if (!empty($booking))

        $minical_booking_id = $this->_create_booking($booking);
        if(is_numeric($minical_booking_id)){
            $response = array(
                    'ota_booking_id' => $booking['ota_booking_id'],
                    'minical_booking_id' => (isset($minical_booking_id)) ? $minical_booking_id:'',
                    'booking_type' => $booking['booking_type']
                );
        }
        else
        {
            $response = array(
                    'error' => $minical_booking_id
                );
        }
        if (ob_get_contents()) ob_end_clean();
        
        return $response;
    }

    function _create_booking($booking)
    {
        try{
            // create customer
            $booking_customer = $booking['booking_customer'];
            $booking_type = $booking['booking_type'];
            $company_id = $booking['company_id'];
            if(isset($booking['card']) && isset($booking['card']['number']) && isset($booking['card']['token'])){
                $cc_tokenex_token = $cc_cvc_encrypted = NULL;

                if(isset($booking['card']['token']))
                {
                    $cc_tokenex_token = $booking['card']['token']; // tokenex token 
                }
                if(isset($booking['card']['cvc']))
                {
                    $cc_cvc_encrypted = $booking['card']['cvc']; // encrypted format
                }
                
                $booking_customer += array(
                    'cc_number'         => 'XXXX XXXX XXXX '.$booking['card']['number'],
                    'cc_expiry_month'   => isset($booking['card']['exp_month']) ? $booking['card']['exp_month'] : null,
                    'cc_expiry_year'    => isset($booking['card']['exp_year']) ? $booking['card']['exp_year'] : null,
                    'cc_tokenex_token'  => null,
                    'cc_cvc_encrypted'  => null,
                    'card_holder_name'  => (isset($booking['card']['name']) ? $booking['card']['name'] : "")
                );
            }

            // check if this customer has stayed in this hotel before. 
            // (by checking email) if so, update the profile
            $booking_customer_id = null;
            if (isset($booking_customer['email']) && $booking_customer['email'])
            {
                $customers = $this->Customer_model->get_customer_by_email($booking_customer['email'], $company_id);
                if($customers && count($customers) > 0)
                {
                    foreach($customers as $customer){
                        if($customer['customer_name'] == $booking_customer['customer_name'])
                        {
                            $customer_info = $customer;
                            $booking_customer_id = $customer['customer_id'];
                        }
                    }
                }
            }
            
            // if customer already exists, update it. otherwise, create new customer
            $card_data = null;
            if(isset($booking['card'])){
                $card_data = array(
                    'customer_name' => isset($booking_customer['customer_name']) ? $booking_customer['customer_name'] : null,
                    'company_id' => isset($booking_customer['company_id']) ? $booking_customer['company_id'] : null,
                    'cc_number' => isset($booking_customer['cc_number']) ? $booking_customer['cc_number'] : null,
                    'cc_expiry_month' => isset($booking_customer['cc_expiry_month']) ? $booking_customer['cc_expiry_month'] : null,
                    'cc_expiry_year' => isset($booking_customer['cc_expiry_year']) ? $booking_customer['cc_expiry_year'] : null,
                    'cc_tokenex_token' => isset($booking_customer['cc_tokenex_token']) ? $booking_customer['cc_tokenex_token'] : null,
                    'cc_cvc_encrypted' => isset($booking_customer['cc_cvc_encrypted']) ? $booking_customer['cc_cvc_encrypted'] : null,
                    'card_name' => isset($booking_customer['card_holder_name']) ? $booking_customer['card_holder_name'] : null,
                    'is_primary' => 1,   
                ); 

                $meta['token'] = $cc_tokenex_token ? $cc_tokenex_token : null;
                $card_data['customer_meta_data'] = json_encode($meta);

            }
                    
            if ($booking_customer_id)
            {
                unset($booking_customer['cc_number']);
                unset($booking_customer['cc_expiry_month']);
                unset($booking_customer['cc_expiry_year']);
                unset($booking_customer['cc_tokenex_token']);
                unset($booking_customer['cc_cvc_encrypted']);
                unset($booking_customer['card_holder_name']);
                
                
                $booking_customer['customer_id'] = $booking_customer_id;
                $this->Customer_model->update_customer($booking_customer);

                $post_customer_data = $booking_customer;
                $post_customer_data['customer_id'] = $booking_customer_id;

                do_action('post.update.customer', $post_customer_data);
                
                if($card_data) {
                    $card_data['customer_id'] = $booking_customer_id;
                    $this->Card_model->update_customer_card($card_data);
                }
            }
            else
            {
                unset($booking_customer['cc_number']);
                unset($booking_customer['cc_expiry_month']);
                unset($booking_customer['cc_expiry_year']);
                unset($booking_customer['cc_tokenex_token']);
                unset($booking_customer['cc_cvc_encrypted']);
                unset($booking_customer['card_holder_name']);
                
                $booking_customer_id = $this->Customer_model->create_customer($booking_customer);

                $post_customer_data = $booking_customer;
                $post_customer_data['customer_id'] = $booking_customer_id;

                do_action('post.create.customer', $post_customer_data);

                if($card_data) {
                    $card_data['customer_id'] = $booking_customer_id;
                    $this->Card_model->create_customer_card($card_data);
                }
            }

                $customer_data = array(
                    "customer_id" => $booking_customer_id,
                    "first_name" =>  $booking_customer['customer_name'] ?? null,
                    "email" => $booking_customer['email'] ?? null,
                    "payment_source" => array(
                        "address_line1" => $booking_customer['address'] ?? null,
                        "address_city" => $booking_customer['city'] ?? null,
                        "address_state" =>  $booking_customer['state'] ?? null,
                        "address_country" => $booking_customer['country'] ?? null,
                        "address_postcode" => $booking_customer['postal_code'] ?? null,
                        "phone" => $booking_customer['phone'] ?? null,
                        "card_name" => "%CARDHOLDER_NAME%",
                        "card_number" => "%CARD_NUMBER%",
                        "expire_month" => "%EXPIRATION_MM%",
                        "expire_year" => "%EXPIRATION_YYYY%",
                        "card_ccv" => "%SERVICE_CODE%",
                    ),
                );

                if(isset($cc_tokenex_token) && $cc_tokenex_token != ''){
                    $customer_data['company_id'] = $company_id;
                    $customer_data['pci_token'] = $cc_tokenex_token;
                    $pci_customer_response = apply_filters('post.add.pci_customer', $customer_data);

                    if (isset($pci_customer_response['customer_response']['success']) && $pci_customer_response['customer_response']['success'] == true)
                    {
                        if(isset($pci_customer_response['customer_response']['customer_id'])){
                            $meta['customer_id'] = $pci_customer_response['customer_response']['customer_id'];
                        } elseif(isset($pci_customer_response['customer_response']['card_token'])){
                            $meta['nexio_token'] = $pci_customer_response['customer_response']['card_token'];
                        }
                        $data['customer_meta_data'] = json_encode($meta);
                        $data['customer_id'] = $booking_customer_id;
                        $this->Card_model->update_customer_card($data);
                    }
                }
               

            // create rate plan, currency_id, rates, date_range, and rate_range_x_rate
            $rate_plan = $booking['rate_plan'];

            // get rate_plan of the hotel that is relevant to this booking

            $rate_plan['charge_type_id'] = $this->Charge_types_model->get_default_charge_type_id($company_id);

            if (isset($rate_plan['minical_rate_plan_id']) && $rate_plan['minical_rate_plan_id'])
            {
                if ($rate_plan['minical_rate_plan_id'])
                {
                    $minical_rate_plan = $this->Rate_plan_model->get_rate_plan($rate_plan['minical_rate_plan_id']);
                    if(isset($minical_rate_plan['charge_type_id']) && $minical_rate_plan['charge_type_id']) {
                        $rate_plan['charge_type_id'] =  $minical_rate_plan['charge_type_id'];
                    }
                }
            }
                unset($rate_plan['minical_rate_plan_id']);

            // get currency_id
            $currency_id = $this->Currency_model->get_currency_id($rate_plan['currency']['currency_code']);
            $rate_plan['currency_id'] = $currency_id;
            $rate_plan['company_id'] = $company_id;
            $rate_plan['is_selectable'] = '0';
            unset($rate_plan['currency']);

            // create rates
            $rates = $rate_plan['rates'];
            unset($rate_plan['rates']);
            $minical_room_type_id = (isset($booking['minical_room_type_id']))?$booking['minical_room_type_id']:'';
            
            // create rate plan
            $rate_plan['room_type_id'] = $minical_room_type_id;
            $rate_plan_id = $this->Rate_plan_model->create_rate_plan($rate_plan);

            $average_daily_rate = 0;
            $average_daily_rate_set = false;

            foreach ($rates as $rate)
            {
                $rate_id = $this->Rates_model->create_rate(
                    Array(
                        'rate_plan_id' => $rate_plan_id,
                        'base_rate' => $rate['base_rate'],
                        'adult_1_rate' => $rate['base_rate'],
                        'adult_2_rate' => $rate['base_rate'],
                        'adult_3_rate' => $rate['base_rate'],
                        'adult_4_rate' => $rate['base_rate']
                        )
                    );

                $date_range_id = $this->Date_range_model->create_date_range(
                    Array(
                        'date_start' => $rate['date_start'],
                        'date_end' => $rate['date_end']
                        )
                    );

                $this->Date_range_model->create_date_range_x_rate(
                    Array(
                        'rate_id' => $rate_id,
                        'date_range_id' => $date_range_id
                        )
                    );

                $average_daily_rate = $average_daily_rate_set ? $average_daily_rate : $rate['base_rate'];
                $average_daily_rate_set = true;
            }
           // if adult count not provided, use default max
            $room_type = $this->Room_type_model->get_room_type($minical_room_type_id);
            if(!isset($booking['adult_count']) || !$booking['adult_count'])
            {
                $booking['adult_count'] = isset($room_type['max_adults']) ? $room_type['max_adults'] : 1;
            }

            $common_booking_sources = json_decode(COMMON_BOOKING_SOURCES, true);
            
            $source_id = $booking['source'];
            $is_new_source = null;
            
            if($booking['source'] == SOURCE_CHANNEX){
                
                $source = isset($booking['sub_source']) && $booking['sub_source'] ? $booking['sub_source'] : "";
                $parent_source = "Channex";
                if($source) {
                    $is_new_source = true;
                    if(strcmp($parent_source, trim($source)) == 0) {
                        $source_id = SOURCE_CHANNEX;
                        $is_new_source = false;
                    } else {
                        $source_ids = $this->Channex_booking_model->get_booking_source_detail($company_id);
                        if($source_ids) {
                            foreach($source_ids as $ids) {
                                if(strcmp($ids['name'], $source) == 0)
                                {   
                                    $source_id = $ids['id'];
                                    $is_new_source = false;
                                }
                            }
                        }
                        if ($common_booking_sources && count($common_booking_sources) > 0) {
                            foreach ($common_booking_sources as $id => $name) {
                                if(strtolower($source) == strtolower($name)) {
                                    $source_id = $id;
                                    $is_new_source = false;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if($is_new_source){
                $source_id = $this->Channex_booking_model->create_booking_source($company_id, $source);
            } 
            
            // create booking
            $booking_data = Array(
                'company_id' => $company_id,
                'state' => '0',
                'rate_plan_id' => $rate_plan_id,
                'booking_customer_id' => $booking_customer_id,
                'adult_count' => $booking['adult_count'],
                'children_count' => isset($booking['children_count']) ? $booking['children_count'] : 0,
                'use_rate_plan' => '1',
                'source' => $source_id,
                'booking_notes' => $booking['booking_notes'],
                'color' => '8EB3DE',
                'rate' => $average_daily_rate,
                'charge_type_id' => $rate_plan['charge_type_id'],
                'is_ota_booking' => 1
            ); 
            $booking_id = $this->Channex_booking_model->create_booking($booking_data);

            $post_booking_data = $booking_data;
            $post_booking_data['booking_id'] = $booking_id;

            do_action('post.create.booking', $post_booking_data);


            // find an available room and get its room_id
            $company_detail = $this->Companies_model->get_company_detail($company_id);
            // $company_detail = array();

            $room_id = $this->Rooms_model->get_available_room_id(
                                                    $booking['check_in_date'],
                                                    $booking['check_out_date'],
                                                    $minical_room_type_id,
                                                    'undefined' ,
                                                    $company_id,
                                                    1, // can be sold online
                                                    null,
                                                    null,
                                                    $company_detail['subscription_level'],
                                                    $company_detail['book_over_unconfirmed_reservations']
                                                );
            //echo "available room_id:";
            //print_r($room_id);
            $is_overbooking = false;
            $is_non_continuous_available = false;
            // if no room is available then get non contiuous room blocks
            // if(is_null($room_id) && isset($company_detail['allow_non_continuous_bookings']) && $company_detail['allow_non_continuous_bookings']){
            //  $is_non_continuous_available = $this->get_non_continuous_available_room_ids($booking_id, $minical_room_type_id, $company_id, $booking['check_in_date'],  $booking['check_out_date'], $company_detail['subscription_level'], $company_detail['book_over_unconfirmed_reservations']);
            // }
            $inventory = $avail_array = array();
            // if no room is available, then just assign the first room of the room_type
            if (is_null($room_id))
            {
                $rooms_available = $this->Room_model->get_rooms_by_room_type_id($minical_room_type_id, $company_detail['subscription_level']);

                if (isset($rooms_available[0]->room_id))
                {
                    if($booking['booking_type'] == "new")
                    {
                        $is_overbooking = true;
                        $inventory[$minical_room_type_id] = $room_type;
                        $avail_array = $this->Availability_model->get_availability(
                                                                $booking['check_in_date'], 
                                                                $booking['check_out_date'], 
                                                                array_keys($inventory), 
                                                                $booking['source'],
                                                                true,
                                                                $booking['adult_count'],
                                                                isset($booking['children_count']) ? $booking['children_count'] : 0,
                                                                false,
                                                                false,
                                                                false,
                                                                false,
                                                                $is_overbooking,
                                                                $company_id
                        );
                    }
                    $room_id = $rooms_available[0]->room_id;
                }
            }

            //echo "chosen room_id:";
            // prx($avail_array);

            if(!is_null($room_id)){
                // create booking_room_history
                $booking_room_history_data = Array(
                    'room_id' => $room_id,
                    'check_in_date' => $company_detail['enable_new_calendar'] ? $booking['check_in_date'].' '.date("H:i:s", strtotime($company_detail['default_checkin_time'])) : $booking['check_in_date'],
                    'check_out_date' => $company_detail['enable_new_calendar'] ? $booking['check_out_date'].' '.date("H:i:s", strtotime($company_detail['default_checkout_time'])) : $booking['check_out_date'],
                    'booking_id' => $booking_id,
                    'room_type_id' => $minical_room_type_id
                );
                $this->Booking_room_history_model->create_booking_room_history($booking_room_history_data);
            }

            $post_booking_data['room_id'] = $room_id;
            $post_booking_data['check_in_date'] =$company_detail['enable_new_calendar'] ? $booking['check_in_date'].' '.date("H:i:s", strtotime($company_detail['default_checkin_time'])) : $booking['check_in_date'] ;
            $post_booking_data['check_out_date'] = $company_detail['enable_new_calendar'] ? $booking['check_out_date'].' '.date("H:i:s", strtotime($company_detail['default_checkout_time'])) : $booking['check_out_date'];
            $post_booking_data['room_type_id'] = $minical_room_type_id;
            do_action('post.create.booking', $post_booking_data);

            // add staying guest if and only if staying guest's name is different from the booking customer
            if (isset($booking['staying_guest']))
            {
                $staying_guest = $booking['staying_guest'];
                if ($staying_guest['guest_name'] != $booking_customer['customer_name'])
                {
                    $staying_guest_data = Array(
                        'company_id' => $company_id,
                        'customer_name' => $staying_guest['guest_name']
                        );
                    $staying_guest_id = $this->Customer_model->create_customer($staying_guest_data);
                    $this->Customer_model->add_staying_customer_to_booking(Array(
                        'customer_id' => $staying_guest_id,
                        'booking_id' => $booking_id,
                        'company_id' => $company_id
                        ));
                }
            }


            // extras
            if (isset($booking['extras']))
            {
                $extras = $booking['extras'];
                foreach ($extras as $extra)
                {
                    // im intentionally not entering $company_id, 
                    // so this extra doesn't show up on the company's innGrid settings.
                    // this extra is for Booking.com only.
                    $extra_data = array(
                                    "extra_name" => $extra['extra_name'],
                                    "extra_type" => $extra['extra_type'],
                                    "charge_type_id" => $this->Charge_type_model->get_default_charge_type_id($company_id),
                                    "charging_scheme" => $extra['charging_scheme']
                                    );
                    $extra_id = $this->Extra_model->create_extra($extra_data);
                    $this->Booking_extra_model->create_booking_extra(
                        $booking_id, 
                        $extra_id,
                        $extra['start_date'],
                        $extra['end_date'],
                        $extra['quantity'],
                        $extra['rate']);

                }
            }

            $log_data = array(
                'selling_date' => $company_detail['selling_date'],
                'user_id' => 0, // User_id 0 is System user (null null)
                'booking_id' => $booking_id,                  
                'date_time' => gmdate('Y-m-d H:i:s'),
                'log' => 'OTA Booking created',
                'log_type' => SYSTEM_LOG

            );

            $this->Booking_log_model->insert_log($log_data);

            //Create a corresponding invoice
            $this->Invoice_model->create_invoice($booking_id);

            
            $this->Channex_booking_model->update_booking_balance($booking_id);

            $rt_availability = array();
            $no_rooms_available = false;
            if($is_overbooking)
            {
                if(isset($avail_array) && $avail_array && count($avail_array) > 0)
                {
                    foreach ($avail_array as $key => $value) {
                        $rt_availability[$value['date']] = $value['availability'];
                    }
                }
                array_pop($rt_availability);

                if(in_array("0", $rt_availability))
                {
                    $no_rooms_available = true;
                }
                
                $this->send_overbooking_email($booking_id, $is_non_continuous_available, $rt_availability, $no_rooms_available, $company_detail);
            }

            return $booking_id;
        }
        catch(Exception $e){
            return $booking_id ? $booking_id : $e->getMessage();
        }
    }

    function send_overbooking_email($booking_id, $is_non_continuous_available = true, $room_type_availability = null, $no_rooms_available = false, $company = null) {
       
        $result_array = $this->channexemailtemplate->send_overbooking_email($booking_id, $is_non_continuous_available, $room_type_availability, $no_rooms_available);
        if ($result_array && $result_array['success']) {
            $log_data = array(
                    'selling_date' => $company['selling_date'],
                    'user_id' => 0, // User_id 0 is System user (null null)
                    'booking_id' => $booking_id,                  
                    'date_time' => gmdate('Y-m-d H:i:s'),
                    'log' => "Room allocation conflict alert email sent to " . $result_array['owner_email'],
                    'log_type' => SYSTEM_LOG
                );

            $this->Booking_log_model->insert_log($log_data);
        }
    }

    function _send_booking_emails($booking_id, $booking_type = null, $company_detail = null, $ota_booking_id = null, $is_booking_inserted = array(), $booking_check_in_date = null, $booking_check_out_date = null) {

        if ($booking_type == "cancelled")
        {
            if(isset($company_detail) && !$company_detail['email_cancellation_for_ota_reservations'])
        {
            $this->channexemailtemplate->send_booking_cancellation_email($booking_id); // to owners
        }
        }
        else
        {
            if($booking_type == "modified")
            {
                $is_send_emails = false;
                $bookings = $this->Channex_int_model->get_bookings($ota_booking_id, null, null, 2);
                
                if(isset($is_booking_inserted[$ota_booking_id])){
                    $booking  = isset($bookings[1]) ? $bookings[1] : null;
                    $prev_check_in_date = (string) ($booking ? $booking['check_in_date'] : null);
                    $prev_check_out_date = (string) ($booking ? $booking['check_out_date'] : null);
                }else{
                    $booking  = isset($bookings[0]) ? $bookings[0] : null;
                    $prev_check_in_date = (string) ($booking ? $booking['check_in_date'] : null);
                    $prev_check_out_date = (string) ($booking ? $booking['check_out_date'] : null);
                }

                if($booking_check_in_date != $prev_check_in_date || $booking_check_out_date != $prev_check_out_date){
                    // don't send emails unless dates are modified
                    $is_send_emails = true;
                }  
                if($is_send_emails)
                {
                    $this->channexemailtemplate->send_booking_alert_email($booking_id, $booking_type); // to owners
                    $this->channexemailtemplate->send_booking_confirmation_email($booking_id, $booking_type); // to customer
                }
            }
            else
            {
                $this->channexemailtemplate->send_booking_alert_email($booking_id, $booking_type); // to owners
                
                if(isset($company_detail) && !$company_detail['email_confirmation_for_ota_reservations'])
                {
                    $this->channexemailtemplate->send_booking_confirmation_email($booking_id, $booking_type); // to owners
                }
            }
        }
    }

    function save_logs($ota_property_id = null, $request_type = null, $response_type = null, $xml_in = null, $xml_out = null) {

        $data = array(
                        'ota_property_id' => $ota_property_id ? $ota_property_id : null,
                        'request_type' => ($request_type || $request_type == 0) ? $request_type : null,
                        'response_type' => ($response_type || $response_type == 0) ? $response_type : null,
                        'xml_in' => $xml_in ? $xml_in : null,
                        'xml_out' => $xml_out ? $xml_out : null,
                    );
        $this->Channex_int_model->save_logs($data);
    }
    
    public function get_cc_cvc_encrypted($cvc = null, $token = null)
    {       
        if($cvc && is_numeric($cvc) && $token)
        {
            $this->load->library('Encrypt');
            $cc_cvc_encrypted = $this->encrypt->encode($cvc, $token); // get encoded cvc
            return $cc_cvc_encrypted;
        }
        return null;
    }
}