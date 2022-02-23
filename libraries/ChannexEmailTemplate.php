<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class ChannexEmailTemplate {

    public function __construct()
    {
        $this->ci =& get_instance();

        $this->module_name = $this->ci->router->fetch_module();

        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Channex_booking_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Booking_room_history_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Rooms_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Room_type_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Customer_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Companies_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Charge_types_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Rates_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Image_model');
        $this->ci->load->model('../extensions/'.$this->module_name.'/models/Booking_source_model');
        $this->ci->load->model('Whitelabel_partner_model');

        $this->ci->load->library('Email');
        $this->ci->load->helper('date_format_helper');
        $this->ci->load->helper('language_translation_helper');

        log_message('debug', "Email template initialized");
    }

    function set_language($company_language_id) {
        load_translations($company_language_id);
    }

    function reset_language($company_language_id) {
        if(empty($this->ci->session->userdata('language_id')))
        {
            get_language_id($this->ci->session->userdata('language'));
        }
        $language_id = $this->ci->session->userdata('language_id');
        if ($company_language_id != $language_id) {
            load_translations($language_id);
        }
    }

    function send_booking_confirmation_email($booking_id, $booking_type)
    {
        $booking_data = $this->ci->Channex_booking_model->get_booking_detail($booking_id);
        $company_id = $this->ci->Channex_booking_model->get_company_id($booking_id);
        $company = $this->ci->Company_model->get_company($company_id);

        $this->set_language($company['default_language']);

        $booking_room_history_data = $this->ci->Booking_room_history_model->get_booking_detail($booking_id);
        $room_data = $this->ci->Room_model->get_room($booking_room_history_data['room_id']);
        $customer_data['staying_customers'] = $this->ci->Channex_booking_model->get_booking_staying_customers($booking_id, $company_id);

        $number_of_nights = (strtotime($booking_room_history_data['check_out_date']) - strtotime($booking_room_history_data['check_in_date']))/(60*60*24);

        $check_in_date = date('Y-m-d', strtotime($booking_room_history_data['check_in_date']));
        $check_out_date = date('Y-m-d', strtotime($booking_room_history_data['check_out_date']));
        $rate = $rate_with_taxes = $booking_data['rate'];
        $total_charges = $total_charges_with_taxes = 0;

        $charge_type_id = null;
        $rate_plan = array();

        if ($booking_data['use_rate_plan'] == '1')
        {
            $this->ci->load->library('Rate');
            $rate_array = $this->ci->rate->get_rate_array(
                $booking_data['rate_plan_id'],
                date('Y-m-d', strtotime($booking_room_history_data['check_in_date'])),
                date('Y-m-d', strtotime($booking_room_history_data['check_out_date'])),
                $booking_data['adult_count'],
                $booking_data['children_count']
            );

            $rate_plan   = $this->ci->Rate_plan_model->get_rate_plan($booking_data['rate_plan_id']);

            $tax_rates = $this->ci->Charge_type_model->get_taxes($rate_plan['charge_type_id']);

            $charge_type_id = $rate_plan['charge_type_id'];

            foreach ($rate_array as $index => $rate)
            {
                $tax_total = 0;
                if($tax_rates && count($tax_rates) > 0)
                {
                    foreach($tax_rates as $tax){
                        if($tax['is_tax_inclusive'] == 0){
                            $tax_total += (($tax['is_percentage'] == '1') ? ($rate_array[$index]['rate'] * $tax['tax_rate'] / 100) : $tax['tax_rate']);
                        }
                    }
                }
                $total_charges += $rate_array[$index]['rate'];
                $total_charges_with_taxes += $rate_array[$index]['rate'] + $tax_total;
            }
            $rate = $total_charges;
            $rate_with_taxes = $total_charges_with_taxes;
        }
        else
        {
            $charge_type_id = $booking_data['charge_type_id'];

            $tax_rates = $this->ci->Charge_type_model->get_taxes($booking_data['charge_type_id'], $rate);

            if($booking_data['pay_period'] == ONE_TIME)
            {
                $rate = $booking_data['rate'];
                $tax_total = 0;
                if($tax_rates && count($tax_rates) > 0)
                {

                    foreach($tax_rates as $tax){
                        if($tax['is_tax_inclusive'] == 0){
                            $tax_total += (($tax['is_percentage'] == '1') ? ($rate * $tax['tax_rate'] / 100) : $tax['tax_rate']);
                        }
                    }
                }
                $total_charges += $rate;
                $total_charges_with_taxes += $rate + $tax_total;

                $rate = $total_charges;
                $rate_with_taxes = $total_charges_with_taxes;
            }
            else
            {

                $days = 1;
                $date_increment = "+1 day";
                $date_decrement = "-1 day";
                $description = "Daily Room Charge";

                if($booking_data['pay_period'] == WEEKLY)
                {
                    $days = 7;
                    $date_increment = "+7 days";
                    $date_decrement = "-7 days";
                    $description = "Weekly Room Charge";
                }
                if($booking_data['pay_period'] == MONTHLY)
                {
                    $days = 30;
                    $date_increment = "+1 month";
                    $date_decrement = "-1 month";
                }

                for ($charge_start_date = $check_in_date;
                     $charge_start_date < $check_out_date && Date("Y-m-d", strtotime($date_increment, strtotime($charge_start_date))) <= $check_out_date;
                     $charge_start_date = Date("Y-m-d", strtotime($date_increment, strtotime($charge_start_date)))
                ) {
                    $tax_total = 0;
                    if($tax_rates && count($tax_rates) > 0)
                    {
                        foreach($tax_rates as $tax){
                            if($tax['is_tax_inclusive'] == 0){
                                $tax_total += (($tax['is_percentage'] == '1') ? ($rate * $tax['tax_rate'] / 100) : $tax['tax_rate']);
                            }
                        }
                    }
                    $total_charges += $rate;
                    $total_charges_with_taxes += $rate + $tax_total;
                }

                if($charge_start_date < $check_out_date)
                {
                    $daily_rate = round(($rate / $days), 2, PHP_ROUND_HALF_UP);
                    for ($date = $charge_start_date; $date < $check_out_date; $date = Date("Y-m-d", strtotime("+1 day", strtotime($date))) )
                    {
                        $tax_total = 0;
                        if($tax_rates && count($tax_rates) > 0)
                        {
                            foreach($tax_rates as $tax){
                                if($tax['is_tax_inclusive'] == 0){
                                    $tax_total += ($daily_rate * $tax['tax_rate'] / 100);
                                }
                            }
                        }
                        $total_charges += $daily_rate;
                        $total_charges_with_taxes += $daily_rate + $tax_total;
                    }
                }
                $rate = $total_charges;
                $rate_with_taxes = $total_charges_with_taxes;
            }
        }

        $customer_info = $this->ci->Customer_model->get_customer_info($booking_data['booking_customer_id']);

        if (!$customer_info)
        {
            $msg = l('Customer not found in the Booking', true);
            return array(
                "success" => false,
                "message" => $msg
            );
        }

        $room_type = $this->ci->Room_type_model->get_room_type($room_data['room_type_id']);
        $logo_images = $this->ci->Image_model->get_images($company['logo_image_group_id']);


        $booking_hash = $booking_modify_link = "";
        if($company['customer_modify_booking'])
        {
            $booking_hash = $booking_data['invoice_hash'];
            $booking_modify_link = base_url() . "booking/show_booking_information/".$booking_hash;
        }

        $booking_notes = "";
        if($company['send_booking_notes'])
        {
            $booking_notes = $booking_data['booking_notes'];
        }

        $room_instructions = "";
        if(isset($room_data['instructions']) && $room_data['instructions'])
        {
            $room_instructions = $room_data['instructions'];
        }

        $check_in_date = $company['enable_hourly_booking'] ? get_local_formatted_date($booking_room_history_data['check_in_date']).' '.date('h:i A', strtotime($booking_room_history_data['check_in_date'])) : get_local_formatted_date($booking_room_history_data['check_in_date']);

        $check_out_date = $company['enable_hourly_booking'] ? get_local_formatted_date($booking_room_history_data['check_out_date']).' '.date('h:i A', strtotime($booking_room_history_data['check_out_date'])) : get_local_formatted_date($booking_room_history_data['check_out_date']);

        $booking_types = Array(UNCONFIRMED_RESERVATION, RESERVATION, INHOUSE, CHECKOUT, OUT_OF_ORDER);
        $booking_type = "";

        switch($booking_data['state']) {
            case UNCONFIRMED_RESERVATION:
                $booking_type = l('Unconfirmed Reservation', true);
                break;
            case RESERVATION:
                $booking_type = l('Reservation', true);
                break;
            case INHOUSE:
                $booking_type = l('Checked-In', true);
                break;
            case CHECKOUT:
                $booking_type = l('Checked-Out', true);
                break;
            case CANCELLED:
                $booking_type = l('Cancelled', true);
                break;
            case OUT_OF_ORDER:
                $booking_type = l('Out of order');
                break;
        }

        $common_booking_sources = json_decode(COMMON_BOOKING_SOURCES, true);
        $coomon_sources_setting = $this->ci->Booking_source_model->get_common_booking_sources_settings($company_id);
        $sort_order = 0;
        foreach($common_booking_sources as $id => $name)
        {
            if(!(isset($coomon_sources_setting[$id]) && $coomon_sources_setting[$id]['is_hidden'] == 1))
            {
                $source_data[] = array(
                    'id' => $id,
                    'name' => $name,
                    'sort_order' => isset($coomon_sources_setting[$id]) ? $coomon_sources_setting[$id]['sort_order'] : $sort_order
                );
            }
            $sort_order++;
        }

        $booking_sources = $this->ci->Booking_source_model->get_booking_source($company_id);
        if (!empty($booking_sources)) {
            foreach ($booking_sources as $booking_source) {
                if($booking_source['is_hidden'] != 1)
                {
                    $source_data[] = array(
                        'id' => $booking_source['id'],
                        'name' => $booking_source['name'],
                        'sort_order' => $booking_source['sort_order']
                    );
                }
            }
        }
        usort($source_data, function($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        });

        $booking_sources = $source_data;

        $booking_source = '';

        if($booking_sources){
            foreach ($booking_sources as $key => $value) {
                if($value['id'] == $booking_data['source'])
                {
                    $booking_source = $value['name'];
                    break;
                }
            }
        }

        //Send confirmation email
        $email_data = array (
            'booking_id' => $booking_id,

            'customer_name' => $customer_info['customer_name'],

            'customer_address' => $customer_info['address'],
            'customer_city' => $customer_info['city'],
            'customer_region' => $customer_info['region'],
            'customer_country' => $customer_info['country'],
            'customer_postal_code' => $customer_info['postal_code'],

            'customer_phone' => $customer_info['phone'],
            'customer_email' => $customer_info['email'],

            'check_in_date' => $check_in_date,
            'check_out_date' => $check_out_date,

            'room_type' => $room_type['name'],

            'average_daily_rate' => $booking_data['rate'],
            'rate' => $rate,
            'rate_with_taxes' => $rate_with_taxes,
            'charge_type_id' => $charge_type_id,

            'company_name' => $company['name'],

            'company_address' => $company['address'],

            'company_city' => $company['city'],
            'company_region' => $company['region'],
            'company_country' => $company['country'],
            'company_postal_code' => $company['postal_code'],

            'company_phone' => $company['phone'],
            'company_email' => $company['email'],
            'company_website' => $company['website'],
            'company_fax' => $company['fax'],
            'company_room' => $company['default_room_plural'],
            'reservation_policies' => $company['reservation_policies'],
            'date_format' => $company['date_format'],
            'company_room' => $company['default_room_plural'],
            'booking_confirmation_email_header' => $company['booking_confirmation_email_header'],
            'customer_modify_booking' => $company['customer_modify_booking'],
            'booking_modify_link' => $booking_modify_link,
            'room_instructions' => $room_instructions,
            'adult_count' => $booking_data['adult_count'],
            'children_count' => $booking_data['children_count'],
            'logo_images' => $logo_images,
            'company_id' => $company_id,
            'booking_type' => $booking_type,
            'amount_due' => $booking_data['balance'],
            'rate_plan_detail' => $rate_plan,
            'confirmation_email_header' => $company['booking_confirmation_email_header'],
            'booking_source' => $booking_source,
            'booking_notes' => $booking_notes
        );

        if ($email_data['customer_email'] == null || strlen($email_data['customer_email']) <= 1) {
            $msg = l("channex_integration/ERROR: Customer Making Reservation does not have email entered", true);
            return array(
                "success" => false,
                "message" => $msg
            );
        }

        $email_list = $email_data['customer_email'];
        if(isset($company['send_copy_to_additional_emails']) && $company['send_copy_to_additional_emails'])
        {
            $email_list .= ",".$company['additional_company_emails'];
        }

        $email_from = $company['email'];

        $this->ci->email->clear();
        $this->ci->email->from($email_from, $company['name']);
        $this->ci->email->to($email_list);
        $this->ci->email->reply_to($email_data['company_email']);

        $this->ci->email->subject($email_data['company_name'] . ' - '.$booking_type.' Booking Confirmation: ' . $email_data['booking_id']);

        $this->ci->email->message($this->ci->load->view('../extensions/'.$this->module_name.'/views/new_booking_confirm-html', $email_data, true));
        // $this->ci->email->message($this->ci->load->view('email/new_booking_confirm-html', $email_data, true));

        $this->ci->email->send();

        $this->reset_language($company['default_language']);

        $email_msg = l('channex_integration/Email successfully sent to ', true);
        return $email_msg.$email_data['customer_email'];
    }

    function send_booking_alert_email($booking_id, $booking_type)
    {
        $booking_data = $this->ci->Channex_booking_model->get_booking_detail($booking_id);
        $company_id = $this->ci->Channex_booking_model->get_company_id($booking_id);
        $company = $this->ci->Company_model->get_company($company_id);

        $this->set_language($company['default_language']);

        $booking_room_history_data = $this->ci->Booking_room_history_model->get_booking_detail($booking_id);
        $room_data = $this->ci->Room_model->get_room($booking_room_history_data['room_id']);
        $customer_data['staying_customers'] = $this->ci->Channex_booking_model->get_booking_staying_customers($booking_id, $company_id);

        $number_of_nights = (strtotime($booking_room_history_data['check_out_date']) - strtotime($booking_room_history_data['check_in_date']))/(60*60*24);

        $check_in_date = date('Y-m-d', strtotime($booking_room_history_data['check_in_date']));
        $check_out_date = date('Y-m-d', strtotime($booking_room_history_data['check_out_date']));
        $rate = $rate_with_taxes = $booking_data['rate'];
        $total_charges = $total_charges_with_taxes = 0;

        $charge_type_id = null;
        $rate_plan = array();

        if ($booking_data['use_rate_plan'] == '1')
        {
            $this->ci->load->library('Rate');
            $rate_array = $this->ci->rate->get_rate_array(
                $booking_data['rate_plan_id'],
                date('Y-m-d', strtotime($booking_room_history_data['check_in_date'])),
                date('Y-m-d', strtotime($booking_room_history_data['check_out_date'])),
                $booking_data['adult_count'],
                $booking_data['children_count']
            );

            $rate_plan   = $this->ci->Rate_plan_model->get_rate_plan($booking_data['rate_plan_id']);

            $tax_rates = $this->ci->Charge_type_model->get_taxes($rate_plan['charge_type_id']);

            $charge_type_id = $rate_plan['charge_type_id'];

            foreach ($rate_array as $index => $rate)
            {
                $tax_total = 0;
                if($tax_rates && count($tax_rates) > 0)
                {
                    foreach($tax_rates as $tax){
                        if($tax['is_tax_inclusive'] == 0){
                            $tax_total += (($tax['is_percentage'] == '1') ? ($rate_array[$index]['rate'] * $tax['tax_rate'] / 100) : $tax['tax_rate']);
                        }
                    }
                }
                $total_charges += $rate_array[$index]['rate'];
                $total_charges_with_taxes += $rate_array[$index]['rate'] + $tax_total;
            }
            $rate = $total_charges;
            $rate_with_taxes = $total_charges_with_taxes;
        }
        else
        {
            $charge_type_id = $booking_data['charge_type_id'];

            $tax_rates = $this->ci->Charge_type_model->get_taxes($booking_data['charge_type_id'], $rate);

            if($booking_data['pay_period'] == ONE_TIME)
            {
                $rate = $booking_data['rate'];
                $tax_total = 0;
                if($tax_rates && count($tax_rates) > 0)
                {

                    foreach($tax_rates as $tax){
                        if($tax['is_tax_inclusive'] == 0){
                            $tax_total += (($tax['is_percentage'] == '1') ? ($rate * $tax['tax_rate'] / 100) : $tax['tax_rate']);
                        }
                    }
                }
                $total_charges += $rate;
                $total_charges_with_taxes += $rate + $tax_total;

                $rate = $total_charges;
                $rate_with_taxes = $total_charges_with_taxes;
            }
            else
            {

                $days = 1;
                $date_increment = "+1 day";
                $date_decrement = "-1 day";
                $description = "Daily Room Charge";

                if($booking_data['pay_period'] == WEEKLY)
                {
                    $days = 7;
                    $date_increment = "+7 days";
                    $date_decrement = "-7 days";
                    $description = "Weekly Room Charge";
                }
                if($booking_data['pay_period'] == MONTHLY)
                {
                    $days = 30;
                    $date_increment = "+1 month";
                    $date_decrement = "-1 month";
                }

                for ($charge_start_date = $check_in_date;
                     $charge_start_date < $check_out_date && Date("Y-m-d", strtotime($date_increment, strtotime($charge_start_date))) <= $check_out_date;
                     $charge_start_date = Date("Y-m-d", strtotime($date_increment, strtotime($charge_start_date)))
                ) {
                    $tax_total = 0;
                    if($tax_rates && count($tax_rates) > 0)
                    {
                        foreach($tax_rates as $tax){
                            if($tax['is_tax_inclusive'] == 0){
                                $tax_total += (($tax['is_percentage'] == '1') ? ($rate * $tax['tax_rate'] / 100) : $tax['tax_rate']);
                            }
                        }
                    }
                    $total_charges += $rate;
                    $total_charges_with_taxes += $rate + $tax_total;
                }

                if($charge_start_date < $check_out_date)
                {
                    $daily_rate = round(($rate / $days), 2, PHP_ROUND_HALF_UP);
                    for ($date = $charge_start_date; $date < $check_out_date; $date = Date("Y-m-d", strtotime("+1 day", strtotime($date))) )
                    {
                        $tax_total = 0;
                        if($tax_rates && count($tax_rates) > 0)
                        {
                            foreach($tax_rates as $tax){
                                if($tax['is_tax_inclusive'] == 0){
                                    $tax_total += ($daily_rate * $tax['tax_rate'] / 100);
                                }
                            }
                        }
                        $total_charges += $daily_rate;
                        $total_charges_with_taxes += $daily_rate + $tax_total;
                    }
                }
                $rate = $total_charges;
                $rate_with_taxes = $total_charges_with_taxes;
            }
        }

        $customer_info = $this->ci->Customer_model->get_customer_info($booking_data['booking_customer_id']);

        if (!$customer_info)
        {
            $msg = l("channex_integration/Customer not found in the Booking", true);
            return array(
                "success" => false,
                "message" => $msg
            );
        }

        $room_type = $this->ci->Room_type_model->get_room_type($room_data['room_type_id']);
        $logo_images = $this->ci->Image_model->get_images($company['logo_image_group_id']);


        $booking_hash = $booking_modify_link = "";
        if($company['customer_modify_booking'])
        {
            $booking_hash = $booking_data['invoice_hash'];
            $booking_modify_link = base_url() . "booking/show_booking_information/".$booking_hash;
        }

        $booking_notes = "";
        if($company['send_booking_notes'])
        {
            $booking_notes = $booking_data['booking_notes'];
        }

        $room_instructions = "";
        if(isset($room_data['instructions']) && $room_data['instructions'])
        {
            $room_instructions = $room_data['instructions'];
        }

        $check_in_date = $company['enable_hourly_booking'] ? get_local_formatted_date($booking_room_history_data['check_in_date']).' '.date('h:i A', strtotime($booking_room_history_data['check_in_date'])) : get_local_formatted_date($booking_room_history_data['check_in_date']);

        $check_out_date = $company['enable_hourly_booking'] ? get_local_formatted_date($booking_room_history_data['check_out_date']).' '.date('h:i A', strtotime($booking_room_history_data['check_out_date'])) : get_local_formatted_date($booking_room_history_data['check_out_date']);

        $booking_types = Array(UNCONFIRMED_RESERVATION, RESERVATION, INHOUSE, CHECKOUT, OUT_OF_ORDER);
        $booking_type = "";

        switch($booking_data['state']) {
            case UNCONFIRMED_RESERVATION:
                $booking_type = l('Unconfirmed Reservation', true);
                break;
            case RESERVATION:
                $booking_type = l('Reservation', true);
                break;
            case INHOUSE:
                $booking_type = l('Checked-In', true);
                break;
            case CHECKOUT:
                $booking_type = l('Checked-Out', true);
                break;
            case CANCELLED:
                $booking_type = l('Cancelled', true);
                break;
            case OUT_OF_ORDER:
                $booking_type = l('Out of order');
                break;
        }

        $common_booking_sources = json_decode(COMMON_BOOKING_SOURCES, true);
        $coomon_sources_setting = $this->ci->Booking_source_model->get_common_booking_sources_settings($company_id);
        $sort_order = 0;
        foreach($common_booking_sources as $id => $name)
        {
            if(!(isset($coomon_sources_setting[$id]) && $coomon_sources_setting[$id]['is_hidden'] == 1))
            {
                $source_data[] = array(
                    'id' => $id,
                    'name' => $name,
                    'sort_order' => isset($coomon_sources_setting[$id]) ? $coomon_sources_setting[$id]['sort_order'] : $sort_order
                );
            }
            $sort_order++;
        }

        $booking_sources = $this->ci->Booking_source_model->get_booking_source($company_id);
        if (!empty($booking_sources)) {
            foreach ($booking_sources as $booking_source) {
                if($booking_source['is_hidden'] != 1)
                {
                    $source_data[] = array(
                        'id' => $booking_source['id'],
                        'name' => $booking_source['name'],
                        'sort_order' => $booking_source['sort_order']
                    );
                }
            }
        }
        usort($source_data, function($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        });

        $booking_sources = $source_data;

        $booking_source = '';

        if($booking_sources){
            foreach ($booking_sources as $key => $value) {
                if($value['id'] == $booking_data['source'])
                {
                    $booking_source = $value['name'];
                    break;
                }
            }
        }

        //Send confirmation email
        $email_data = array (
            'booking_id' => $booking_id,

            'customer_name' => $customer_info['customer_name'],

            'customer_address' => $customer_info['address'],
            'customer_city' => $customer_info['city'],
            'customer_region' => $customer_info['region'],
            'customer_country' => $customer_info['country'],
            'customer_postal_code' => $customer_info['postal_code'],

            'customer_phone' => $customer_info['phone'],
            'customer_email' => $customer_info['email'],

            'check_in_date' => $check_in_date,
            'check_out_date' => $check_out_date,

            'room_type' => $room_type['name'],

            'average_daily_rate' => $booking_data['rate'],
            'rate' => $rate,
            'rate_with_taxes' => $rate_with_taxes,
            'charge_type_id' => $charge_type_id,

            'company_name' => $company['name'],

            'company_address' => $company['address'],

            'company_city' => $company['city'],
            'company_region' => $company['region'],
            'company_country' => $company['country'],
            'company_postal_code' => $company['postal_code'],

            'company_phone' => $company['phone'],
            'company_email' => $company['email'],
            'company_website' => $company['website'],
            'company_fax' => $company['fax'],
            'company_room' => $company['default_room_plural'],
            'reservation_policies' => $company['reservation_policies'],
            'date_format' => $company['date_format'],
            'company_room' => $company['default_room_plural'],
            'booking_confirmation_email_header' => $company['booking_confirmation_email_header'],
            'customer_modify_booking' => $company['customer_modify_booking'],
            'booking_modify_link' => $booking_modify_link,
            'room_instructions' => $room_instructions,
            'adult_count' => $booking_data['adult_count'],
            'children_count' => $booking_data['children_count'],
            'logo_images' => $logo_images,
            'company_id' => $company_id,
            'booking_type' => $booking_type,
            'amount_due' => $booking_data['balance'],
            'rate_plan_detail' => $rate_plan,
            'confirmation_email_header' => $company['booking_confirmation_email_header'],
            'booking_source' => $booking_source,
            'booking_notes' => $booking_notes
        );

        if ($email_data['company_email'] == null || strlen($email_data['company_email']) <= 1) {
            $msg = l('channex_integration/ERROR: Company does not have email entered', true);
            return array(
                "success" => false,
                "message" => $msg
            );
        }

        $email_from = $company['email'];

        $this->ci->email->from($email_from, $company['name']);
        $this->ci->email->to($email_data['company_email']);
        $this->ci->email->reply_to($email_data['customer_email']);

        $this->ci->email->subject($booking_type." Booking Confirmation: ".$email_data['booking_id']);

        $this->ci->email->message($this->ci->load->view('../extensions/'.$this->module_name.'/views/new_booking_confirm-html', $email_data, true));

        $this->ci->email->send();

        $this->reset_language($company['default_language']);

        $email_msg = l('channex_integration/Email successfully sent to ', true);
        return $email_msg.$email_data['company_email'];
    }

    function send_booking_cancellation_email($booking_id)
    {
        $booking = $this->ci->Channex_booking_model->get_booking_detail($booking_id);

        if (!$booking['booking_customer_email']) {

            return array(
                "success" => false
            );
        }

        $company_id = $this->ci->Channex_booking_model->get_company_id($booking_id);
        $booking_room_history_data = $this->ci->Booking_room_history_model->get_booking_detail($booking_id);
        $room_data = $this->ci->Room_model->get_room($booking_room_history_data['room_id']);
        $room_type = $this->ci->Room_type_model->get_room_type($room_data['room_type_id']);
        $company = $this->ci->Company_model->get_company($company_id);

        $this->set_language($company['default_language']);

        $invoice_hash = $booking['invoice_hash'];
        $invoice_link = base_url() . "invoice/show_invoice_read_only/".$invoice_hash;

        $content =  'Please visit the following link to view your invoice'." :<a href='".$invoice_link."'>".$invoice_link."</a><br/><br/>";
        $content1 = "<br/><br/>".'Thank you for your business'.",<br/><br/>"
            .$company['name']."
                <br/>".$company['email']. "<br/>".$company['phone']."<br/>";


        $config['mailtype'] = 'html';

        $this->ci->email->initialize($config);
        // Company logo
        $logo_url = $this->ci->Image_model
            ->get_company_logo_url($company['company_id'], $company['logo_image_group_id']);

        $email_data = array (
            'booking_id' => $booking_id,
            'customer_name' => $booking['booking_customer_name'],
            'check_in_date' => $booking_room_history_data['check_in_date'],
            'check_out_date' => $booking_room_history_data['check_out_date'],
            'room_type' => $room_type['name'],
            'company_name' => $company['name'],
            'company_email' => $company['email'],
            'content' => $content,
            'content1' => $content1,
            'company_room' => $company['default_room_plural'],
            'company_logo_url' => $logo_url
        );

        $customer_email = $booking['booking_customer_email'];

        $whitelabelinfo = $this->ci->session->userdata('white_label_information');

        $from_email = isset($whitelabelinfo['do_not_reply_email']) && $whitelabelinfo['do_not_reply_email'] ? $whitelabelinfo['do_not_reply_email'] : 'donotreply@minical.io';

        $email_from = $company['email'];

        $this->ci->email->clear();
        $this->ci->email->from($email_from, $company['name']);
        $this->ci->email->to($customer_email);
        $this->ci->email->reply_to($email_data['company_email']);

        $this->ci->email->subject($email_data['company_name'] . ' - Booking Cancellation: ' . $email_data['booking_id']);

        $this->ci->email->message($this->ci->load->view('../extensions/'.$this->module_name.'/views/booking_cancellation-html', $email_data, true));

        $this->ci->email->send();

        $this->set_language($company['default_language']);

        $msg = l('channex_integration/Email successfully sent to ', true);
        return $msg.$customer_email;
    }

    function send_overbooking_email($booking_id, $is_non_continuous_available = true, $room_type_availability = null, $no_rooms_available = false)
    {       

        $booking_data = $this->ci->Channex_booking_model->get_booking($booking_id);
        $company_id = $booking_data['company_id'];
        $company = $this->ci->Company_model->get_company($company_id);

        $whitelabelinfo = null;
        $white_label_detail = $this->ci->Whitelabel_partner_model->get_partners(array('id' => $company['partner_id']));
        
        if($white_label_detail && isset($white_label_detail[0])) {
            $whitelabelinfo = $white_label_detail[0];
        }

        $company_support_email = $whitelabelinfo && isset($whitelabelinfo['support_email']) && $whitelabelinfo['support_email'] ? $whitelabelinfo['support_email'] : 'support@minical.io';

        $this->set_language($company['default_language']);

        $booking_room_history_data = $this->ci->Booking_room_history_model->get_block($booking_id);
        $room_data = $this->ci->Room_model->get_room($booking_room_history_data['room_id']);

        $customer_info = $this->ci->Customer_model->get_customer($booking_data['booking_customer_id']);             
        $room_type = $this->ci->Room_type_model->get_room_type($room_data['room_type_id']);             

        //Send confirmation email
        $email_data = array (                   
            'booking_id' => $booking_id,
            
            'customer_name' => $customer_info['customer_name'],
            
            'customer_address' => $customer_info['address'],
            'customer_city' => $customer_info['city'],
            'customer_region' => $customer_info['region'],
            'customer_country' => $customer_info['country'],
            'customer_postal_code' => $customer_info['postal_code'],
            
            'customer_phone' => $customer_info['phone'],
            'customer_email' => $customer_info['email'],
            
            'check_in_date' => $booking_room_history_data['check_in_date'],
            'check_out_date' => $booking_room_history_data['check_out_date'],
            
            'room_type' => $room_type['name'],
            'room'      => $room_data['room_name'],
            'source'    => $booking_data['source'],
            
            'company_name' => $company['name'],
            'allow_non_continuous_bookings' => $company['allow_non_continuous_bookings'],
            'is_non_continuous_available' => $is_non_continuous_available,
            'room_type_availability' => $room_type_availability,
            'no_rooms_available' => $no_rooms_available
        );

        $this->ci->email->clear();
        $this->ci->email->from($company_support_email);
      
        // don't send emails unless in production environment
        if (strtolower($_SERVER['HTTP_HOST']) == 'app.minical.io')
        {
            if (isset($company['email']))
            {
                $cc_list = 'pankaj@minical.io';
                $this->ci->email->to($company['email']);
                if($whitelabelinfo && isset($whitelabelinfo['overbooking_alert_email']) && $whitelabelinfo['overbooking_alert_email']){
                    $cc_list .= ",".$whitelabelinfo['overbooking_alert_email'];
                    
                }
                $this->ci->email->cc($cc_list);
            }   
        }

        $this->ci->email->reply_to($company_support_email);
        
        $this->ci->email->subject('Room allocation conflict alert | ' . $email_data['company_name']);
        $this->ci->email->message($this->ci->load->view('../extensions/'.$this->module_name.'/views/over_booking_html', $email_data, true));
       
        $this->ci->email->send();
    
        $this->reset_language($company['default_language']);

        return array('success' => true, 'owner_email' => $company['email']);
    }

    function send_error_alert_email($email_data){

        $company = $this->ci->Company_model->get_company($email_data['company_id']);

        $whitelabelinfo = null;
        $white_label_detail = $this->ci->Whitelabel_partner_model->get_partners(array('id' => $company['partner_id']));
        
        if($white_label_detail && isset($white_label_detail[0])) {
            $whitelabelinfo = $white_label_detail[0];
        }

        $company_support_email = $whitelabelinfo && isset($whitelabelinfo['support_email']) && $whitelabelinfo['support_email'] ? $whitelabelinfo['support_email'] : 'support@minical.io';

        $email_data['company_name'] = $company['name'];

        $this->ci->email->clear();
        $this->ci->email->from($company_support_email);

        if (strtolower($_SERVER['HTTP_HOST']) == 'app.minical.io') {

            if($email_data['error_cause'] == 'property_not_found') {
                $this->ci->email->to('pankaj@minical.io');
                $this->ci->email->cc('mradul.jain90@gmail.com');
            } else {
                // $cc_list = 'pankaj@minical.io';
                // $this->ci->email->to($company['email']);

                $this->ci->email->to($company['email']);
                $cc_list = "pankaj@minical.io, mradul.jain90@gmail.com";
                $this->ci->email->cc($cc_list);
            }
        }

        // $this->ci->email->cc('mradul.jain90@gmail.com');

        if(isset($email_data['subject']) && $email_data['subject'])
            $this->ci->email->subject($email_data['subject']);
        else
            $this->ci->email->subject('Error alert | ' . $company['name']);

        $this->ci->email->message($this->ci->load->view('../extensions/'.$this->module_name.'/views/error_alert-html', $email_data, true));
        // $this->ci->email->attach('https://app.minical.io/images/calendar-extension.png');
       
        $this->ci->email->send();
    
        $this->reset_language($company['default_language']);

        return array('success' => true, 'message' => 'Error eamil sent.');
    }
}