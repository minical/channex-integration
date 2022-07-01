<?php
class Channex_integration extends MY_Controller
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
        $this->load->model('../extensions/'.$this->module_name.'/models/Invoice_model');
        $this->load->model('../extensions/'.$this->module_name.'/models/OTA_model');
        
        $this->load->library('../extensions/'.$this->module_name.'/libraries/ChannexIntegration');
        
		$view_data['menu_on'] = true;

		$this->load->vars($view_data);
	}	

	function index() {
		$this->channex();
	}

	function channex()
	{
		$data['company_id'] = $this->company_id;
		
        $data['main_content'] = '../extensions/'.$this->module_name.'/views/channex_authentication';
        $data['channex_data'] = $this->Channex_int_model->get_channex_data($this->company_id, 'channex');
        
        if(
        	isset($data['channex_data']) && 
        	$data['channex_data'] &&
        	isset($data['channex_data']['meta_data']) &&
        	$data['channex_data']['meta_data']
        ){
        	$token_data = json_decode($data['channex_data']['meta_data']);
        }
        
        if(isset($token_data) && $token_data){
        	$data['channex_data']['api_key'] = $token_data->channex->api_key;	
        }
        
        $this->template->load('bootstrapped_template', null , $data['main_content'], $data);
	}

	function signin_channex(){
		$user_api_key = $this->input->post('user_api_key');

		$authentication = $this->channexintegration->signin_channex($user_api_key);

		$response = json_decode($authentication, true);

		$is_valid_creds = false;
		if(isset($response['data']) && $response['data']){

			$meta['channex']['api_key'] = $user_api_key;
			$get_ota_data = $this->Channex_int_model->get_otas('channex');

			$data = array(
							'company_id' => $this->company_id,
							'meta_data' => json_encode($meta),
							'created_date' => date('Y-m-d H:i:s'),
							'ota_id' => isset($get_ota_data['id']) && $get_ota_data['id'] ? $get_ota_data['id'] : null
						);

			$channex_data = $this->Channex_int_model->get_channex_data($this->company_id, 'channex');

			if($channex_data){
				unset($data['email']);
				unset($data['password']);
				$this->Channex_int_model->update_token($data);
				$channex_id = $channex_data['id'];
			} else {
				$channex_id = $this->Channex_int_model->save_token($data);
			}
			
			$is_valid_creds = true;
		}

		if($is_valid_creds){
			$msg = l('channex_integration/Authenticated successfully.', true);
			echo json_encode(array('success' => true, 'msg' => $msg, 'channex_id' => $channex_id));
		} else {
			$msg = l('channex_integration/Unauthorized.', true);
			echo json_encode(array('success' => false, 'msg' => $msg));
		}
	}

	function channex_properties($channex_id)
	{
		$data['company_id'] = $this->company_id;
        $data['main_content'] = '../extensions/'.$this->module_name.'/views/channex_properties';

        $channex_prop_data = $this->Channex_int_model->get_properties_by_company_id($this->company_id, $channex_id);

        $data['properties'] = array();
        $get_token_data = $this->Channex_int_model->get_token($channex_id, $this->company_id, 'channex');

        if($channex_prop_data){
        	
        	$data['properties'] = json_decode($channex_prop_data['channex_property_data'], true);
        } else {
        	
	        if($get_token_data){

	        	$token_data = json_decode($get_token_data['meta_data']);

	        	$token = $token_data->channex->api_key;

	        	$properties = $this->channexintegration->get_properties($token);

	        	$properties_data = json_decode($properties, true);

	        	if(isset($properties_data['data']) && count($properties_data['data']) > 0){

					$ch_prop_data = array(
							'ota_manager_id' => $channex_id,
							'company_id' => $this->company_id,
							'channex_property_data' => $properties,
						);
					$this->Channex_int_model->save_properties($ch_prop_data);
				}
	        	
	        	$data['properties'] = $properties_data;
	        }
        }

        $data['channex_room_types'] = $this->Channex_int_model->get_channex_room_types($this->company_id, $channex_id);
        $data['channex_rate_plans'] = $this->Channex_int_model->get_channex_rate_plans($this->company_id, $channex_id);

        $data['channex_room_types_data'] = array();
        $is_mapping = false;

        if($data['channex_room_types'] && $data['channex_rate_plans']){
        	$property_id = $data['channex_room_types'][0]['ota_property_id'];

    		$get_token_data = $this->Channex_int_model->get_token($channex_id, $this->company_id, 'channex');
          
        	$token_data = json_decode($get_token_data['meta_data']);
        
        	$token = $token_data->channex->api_key;
        	$is_mapping = true;

        	$room_types_data = $this->channexintegration->get_room_types($property_id, $token);
        	$channex_room_types = json_decode($room_types_data, true);

        	$rate_plans_data = $this->channexintegration->get_rate_plans($property_id, $token);
        	$channex_rate_plans = json_decode($rate_plans_data, true);

        	if (isset($channex_rate_plans['data']) && count($channex_rate_plans['data']) > 0) {
	        	foreach ($channex_rate_plans['data'] as $key => $value) {
	        		if(
	        			isset($value['relationships']) &&
	        			isset($value['relationships']['parent_rate_plan'])
	        		) {
	        			unset($channex_rate_plans['data'][$key]);
	        		}
	        	}
	    	}

        	$data['channex_room_types_rate_plans'] = array();

			if (isset($channex_room_types['data']) && count($channex_room_types['data']) > 0) {
				foreach ($channex_room_types['data'] as $key => $room_type) {
					$data['channex_room_types_rate_plans'][$key]['room_type_id'] = $room_type['attributes']['id'];
					$data['channex_room_types_rate_plans'][$key]['room_type_name'] = $room_type['attributes']['title'];

					if (isset($channex_rate_plans['data']) && count($channex_rate_plans['data']) > 0) {
						foreach ($channex_rate_plans['data'] as $key1 => $rate_plan) {
							if(
								$rate_plan['relationships']['room_type']['data']['id'] == $room_type['attributes']['id']
							){
								$data['channex_room_types_rate_plans'][$key]['rate_plans'][$key1]['rate_plan_id'] = $rate_plan['attributes']['id'];
								$data['channex_room_types_rate_plans'][$key]['rate_plans'][$key1]['rate_plan_name'] = $rate_plan['attributes']['title'];
							}
						}
					}
				}
			}

        	$data['minical_room_types'] = $this->Room_type_model->get_room_types($this->company_id);
        	$data['minical_rate_plans'] = $this->Rate_plan_model->get_rate_plans($this->company_id);

        	foreach ($data['channex_room_types'] as $key => $value) {
        		foreach ($data['channex_room_types_rate_plans'] as $key1 => $value1) {
        			if($value['ota_room_type_id'] == $value1['room_type_id']){
	        			$data['channex_room_types_rate_plans'][$key1]['minical_room_type_id'] = $value['minical_room_type_id'];
	        		}
	        	}
	        }

	        foreach ($data['channex_rate_plans'] as $key => $value) {
        		foreach ($data['channex_room_types_rate_plans'] as $key1 => $value1) {
        			if($value['ota_room_type_id'] == $value1['room_type_id']){
        				foreach ($value1['rate_plans'] as $key2 => $value2) {
        					if($value['ota_rate_plan_id'] == $value2['rate_plan_id']){
        						$data['channex_room_types_rate_plans'][$key1]['rate_plans'][$key2]['minical_rate_plan_id'] = $value['minical_rate_plan_id'];
        						$data['channex_room_types_rate_plans'][$key1]['rate_plans'][$key2]['rate_update_type'] = $value['rate_type'];
        					}
        				}
        			}
	        	}
	        }
        }

        $data['is_mapping'] = $is_mapping;
        $data['channex_id'] = $channex_id;

        $get_key = $this->Companies_model->get_key_from_company_id($this->company_id);
        $data['key'] = $get_key;

        $import_extra_charge = $this->Channex_int_model->get_channex_x_company(null, $this->company_id);
        if($import_extra_charge && count($import_extra_charge) > 0){
        	$data['is_extra_charge'] = $import_extra_charge['is_extra_charge'];
        }

        $this->template->load('bootstrapped_template', null , $data['main_content'], $data);
	}

	function get_room_types()
	{
		$property_id = $this->input->post('property_id');
		$channex_id = $this->input->post('channex_id');

		$get_token_data = $this->Channex_int_model->get_token($channex_id, $this->company_id, 'channex');

        if($get_token_data){
         
        	$token_data = json_decode($get_token_data['meta_data']);
        	$token = $token_data->channex->api_key;

        	$room_types_data = $this->channexintegration->get_room_types($property_id, $token);
        	$channex_room_types = json_decode($room_types_data, true);
      
        	$rate_plans_data = $this->channexintegration->get_rate_plans($property_id, $token);
        	$channex_rate_plans = json_decode($rate_plans_data, true);
       
        	if (isset($channex_rate_plans['data']) && count($channex_rate_plans['data']) > 0) {
	        	foreach ($channex_rate_plans['data'] as $key => $value) {
	        		if(
	        			isset($value['relationships']) &&
	        			isset($value['relationships']['parent_rate_plan'])
	        		) {
	        			unset($channex_rate_plans['data'][$key]);
	        		}
	        	}
	    	}

        	$data['channex_room_types_rate_plans'] = array();

			if (isset($channex_room_types['data']) && count($channex_room_types['data']) > 0) {
				foreach ($channex_room_types['data'] as $key => $room_type) {
					$data['channex_room_types_rate_plans'][$key]['room_type_id'] = $room_type['attributes']['id'];
					$data['channex_room_types_rate_plans'][$key]['room_type_name'] = $room_type['attributes']['title'];

					if (isset($channex_rate_plans['data']) && count($channex_rate_plans['data']) > 0) {
						foreach ($channex_rate_plans['data'] as $key1 => $rate_plan) {
							if(
								$rate_plan['relationships']['room_type']['data']['id'] == $room_type['attributes']['id']
							){
								$data['channex_room_types_rate_plans'][$key]['rate_plans'][$key1]['rate_plan_id'] = $rate_plan['attributes']['id'];
								$data['channex_room_types_rate_plans'][$key]['rate_plans'][$key1]['rate_plan_name'] = $rate_plan['attributes']['title'];
							}
						}
					}
				}
			}

        	$data['minical_room_types'] = $this->Room_type_model->get_room_types($this->company_id);
            $data['minical_rate_plans'] = $this->Rate_plan_model->get_rate_plans($this->company_id);

            $xml_out = 	json_encode($data['channex_room_types_rate_plans']);

            $this->save_logs($property_id, 3, 0 , null, $xml_out);

            $this->load->view('../extensions/'.$this->module_name.'/views/room_rate_mapping_view', $data);
        }
	}

	function save_channex_mapping_AJAX(){

        $channex_id = $this->input->post('channex_id');
        $property_id = $this->input->post('property_id');
        $mapping_data = $this->input->post('mapping_data');
        $mapping_data_rp = $this->input->post('mapping_data_rp');
        $rate_type = $this->input->post('rate_type');

        $channex_x_company = $this->Channex_int_model->get_channex_x_company($property_id, $this->company_id, 'channex');

        if($channex_x_company){
        	$channex_x_company_id = $channex_x_company['ota_x_company_id'];

        	$channex_company_data = array(
        							'company_id' => $this->company_id,
        							'ota_property_id' => $property_id,
        							// 'rate_update_type' => $rate_type
        						);
        
        	$this->Channex_int_model->save_channex_company($channex_company_data, true);
        } else {
        	$channex_company_data = array(
        							'company_id' => $this->company_id,
        							'ota_manager_id' => $channex_id,
        							'ota_property_id' => $property_id,
        							'is_active' => 1,
        							// 'rate_update_type' => $rate_type
        						);
        
        	$channex_x_company_id = $this->Channex_int_model->save_channex_company($channex_company_data);
        }

        foreach ($mapping_data as $key => $value) {
        	foreach ($mapping_data_rp as $key1 => $val) {

        		$rtrp_id = $val['channex_rate_plan_id'];
        		$rt_rp_id = explode('_', $rtrp_id);
        		if($value['channex_room_type_id'] == $rt_rp_id[0]){
        			$mapping_data[$key]['rate_plan'][$key1]['channex_rate_plan_id'] = $rt_rp_id[1];
        			$mapping_data[$key]['rate_plan'][$key1]['minical_rate_plan_id'] = isset($val['minical_rate_plan_id']) && $val['minical_rate_plan_id'] ? $val['minical_rate_plan_id'] : null;
        			$mapping_data[$key]['rate_plan'][$key1]['rate_update_type'] = isset($val['rate_update_type']) && $val['rate_update_type'] ? $val['rate_update_type'] : null;
        		}
        	}
        }

        foreach ($mapping_data as $mapping) {
        	$channex_room_type_id = isset($mapping['channex_room_type_id']) ? $mapping['channex_room_type_id'] : null;

        	$minical_room_type_id = isset($mapping['minical_room_type_id']) ? $mapping['minical_room_type_id'] : null;

        	$this->Channex_int_model->create_or_update_room_type($channex_x_company_id, $channex_room_type_id, $minical_room_type_id, $this->company_id);

        	if(isset($mapping['rate_plan']) && count($mapping['rate_plan']) > 0){
        		foreach ($mapping['rate_plan'] as $key => $value) {
	        		$minical_rate_plan_id = isset($value['minical_rate_plan_id']) ? $value['minical_rate_plan_id'] : null;
	        		$channex_rate_plan_id = isset($value['channex_rate_plan_id']) ? $value['channex_rate_plan_id'] : null;
	        		$rate_update_type = isset($value['rate_update_type']) ? $value['rate_update_type'] : null;

	        		$this->Channex_int_model->create_or_update_rate_plan($channex_x_company_id, $channex_room_type_id, $minical_rate_plan_id, $channex_rate_plan_id, $this->company_id, $rate_update_type);
	        	}
        	}
        }

        $this->update_full_refresh();
		echo json_encode(array('success' => true));
	}

	function channex_update_availability($start_date = null, $end_date = null){

		if(!$start_date && !$end_date){
			$start_date = $this->input->post('check_in_date');
        	$end_date = $this->input->post('check_out_date');
        	$booking_id = $this->input->post('booking_id');
			$room_type_id = $this->input->post('room_type_id');
		} else {
			$room_type_id = false;
		}

		$data = array(
						'start_date' => $start_date,
						'end_date' => $end_date,
						'room_type_id' => $room_type_id,
						'company_id' => $this->company_id,
						'update_from' => 'extension'
					);

		do_action('update_availability', $data);
	}

	function channex_update_restrictions($start_date = null, $end_date = null){

        if(!$start_date && !$end_date){
			$start_date = $this->input->post('date_start');
        	$end_date = $this->input->post('date_end');
			$rate_plan_id = $this->input->post('rate_plan_id');
		} else {
			$rate_plan_id = false;
		}

		$data = array(
						'start_date' => $start_date,
						'end_date' => $end_date,
						'rate_plan_id' => $rate_plan_id,
						'company_id' => $this->company_id,
						'update_from' => 'extension',
						'adult_1_rate' => $this->input->post('adult_1_rate'),
        				'adult_2_rate' => $this->input->post('adult_2_rate'),
        				'adult_3_rate' => $this->input->post('adult_3_rate'),
        				'adult_4_rate' => $this->input->post('adult_4_rate'),
        				'additional_adult_rate' => $this->input->post('additional_adult_rate'),
        				'closed_to_arrival' => $this->input->post('closed_to_arrival'),
        				'closed_to_departure' => $this->input->post('closed_to_departure'),
        				'minimum_length_of_stay' => $this->input->post('minimum_length_of_stay'),
        				'maximum_length_of_stay' => $this->input->post('maximum_length_of_stay'),
        				'can_be_sold_online' => $this->input->post('can_be_sold_online')						
					);

		do_action('update_rates', $data);
	}

    function deconfigure_channex_AJAX(){
    	$channex_id = $this->input->post('channex_id');

    	$this->Channex_int_model->deconfigure_channex($channex_id);
    	echo json_encode(array('success' => true));
    }

    function update_full_refresh(){
        $start_date = date("Y-m-d");
        $end_date = Date("Y-m-d", strtotime("+365 days", strtotime($start_date)));
        
        $this->channex_update_availability($start_date, $end_date);
        
        $this->channex_update_restrictions($start_date, $end_date);
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

    function update_import_extra_charge(){
    	$company_id = $this->company_id;

    	$is_extra_charge = $this->input->post('is_extra_charge');

        $data['is_extra_charge'] = $is_extra_charge;

    	$this->Channex_int_model->update_import_extra_charge($company_id, $data);
    	echo json_encode(array('success' => true));
    }
}