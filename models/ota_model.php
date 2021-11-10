<?php

class OTA_model extends CI_Model {

	function __construct()
    {        
        parent::__construct(); // Call the Model constructor
    }

    function insert_booking($booking)
    {     
    	$this->db->insert('ota_bookings', $booking);
    	if ($this->db->_error_message())
		{
			show_error($this->db->_error_message());
		}
    }

    function get_booking_by_ota_booking_id($ota_booking_id, $ota_booking_type = null, $check_in_date = null, $check_out_date = null)
    {
        $where_condition = "" ;
        
        if($ota_booking_type){
            $where_condition = "AND booking_type = '$ota_booking_type'"; 
        }

        if($check_in_date && $check_out_date){
            $where_condition .= " AND check_in_date = '$check_in_date' AND check_out_date = '$check_out_date'"; 
        }
        
        $sql =  " 
                SELECT 
                    *
                FROM
                    ota_bookings
                WHERE
                    ota_booking_id = '$ota_booking_id'
                    $where_condition
                ORDER BY 
                    id DESC
                LIMIT 
                    0, 1
            ";
        //echo $sql;
        $query = $this->db->query($sql);
        
        $result = $query->result_array();
        
        if($result)
            return $result[0];
        
        return NULL;
    }

}