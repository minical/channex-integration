<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title><?php echo $company_name; ?> <?php echo l('Error Alert', true); ?>: <?php echo $property_id;?></title>
		<style type="text/css">			
			h2 {font: bold 16px Arial, Helvetica, sans-serif; margin: 0; padding: 0 0 18px; color: black;}
			h3 {font: 16px Arial, Helvetica, sans-serif; margin: 0; color: black;}
			.group {border: 1px solid black; padding: 10px; }			
			#customer-name, #company-name {font-weight: bold; }
		</style>
	</head>
	<body>
		<div style="max-width: 600px;">

			<?php if($error_cause != 'minical_room_type_id_not_found'){ ?>
				<?php echo l('You have received an error alert', true); ?> : 
				<br /><br />

				<b>	<?php echo l('Error Cause', true); ?>: </b><?php echo $error_cause; ?><br/><br/>
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				<b> <?php echo l('Company Name', true); ?>: </b><?php echo $company_name;?><br />

				<?php if(isset($ota_x_company_id) && $ota_x_company_id){ ?>
					<b> <?php echo l('OTA x company ID', true); ?>: </b><?php echo $ota_x_company_id; ?><br />
				<?php } ?>
				
				<br/>
			<?php } ?>
			
			<?php if($error_cause == 'availability_not_found'){ ?>
				<?php if($room_type_id){ ?>
				<b> <?php echo l('Room Type ID', true); ?>: </b><?php echo $room_type_id[0]; ?><br />
				<?php } ?>
				<b> <?php echo l('Request', true).' => '; ?> </b>
				<br/><br/>
				<b>	<?php echo l('OTA ID', true); ?>: </b><?php echo $ota_id; ?><br />
				<b>	<?php echo l('OTA Key', true); ?>: </b><?php echo 'channex'; ?><br />
				<b>	<?php echo l('Start Date', true); ?>: </b><?php echo $start_date; ?><br />
				<b>	<?php echo l('End Date', true); ?>: </b><?php echo $end_date; ?><br />
				<b>	<?php echo l('Company Access Key', true); ?>: </b><?php echo $company_access_key; ?><br />
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />

				<b> <?php echo l('Response', true).' => '; ?> </b><br />
				<?php prx($room_types_avail_array, 1); ?><br />

			<?php } elseif($error_cause == 'availability_token_not_found'){ ?>
				<b> <?php echo l('Request', true).' => '; ?> </b>
				<br/><br/>
				<b>	<?php echo l('OTA Key', true); ?>: </b><?php echo 'channex'; ?><br />
				<b> <?php echo l('Company Name', true); ?>: </b><?php echo $company_name;?><br />
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />

				<b> <?php echo l('Response', true).' => '; ?> </b><br />
				<?php prx($get_token_data, 1); ?><br />

			<?php } elseif($error_cause == 'rates_token_not_found'){ ?>
				<b> <?php echo l('Request', true).' => '; ?> </b>
				<br/><br/>
				<b>	<?php echo l('OTA Key', true); ?>: </b><?php echo 'channex'; ?><br />
				<b> <?php echo l('Company Name', true); ?>: </b><?php echo $company_name;?><br />
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />

				<b> <?php echo l('Response', true).' => '; ?> </b><br />
				<?php prx($get_token_data, 1); ?><br />
				
			<?php } elseif($error_cause == 'rates_not_found'){ ?>
				<b> <?php echo l('Request', true).' => '; ?> </b>
				<br/><br/>
				<b>	<?php echo l('OTA ID', true); ?>: </b><?php echo $ota_id; ?><br />
				<b>	<?php echo l('OTA Key', true); ?>: </b><?php echo 'channex'; ?><br />
				<b>	<?php echo l('Start Date', true); ?>: </b><?php echo $start_date; ?><br />
				<b>	<?php echo l('End Date', true); ?>: </b><?php echo $end_date; ?><br />
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />

				<b> <?php echo l('Response', true).' => '; ?> </b><br />
				<?php prx($minical_rates, 1); ?><br />
				
			<?php } elseif($error_cause == 'property_not_found'){ ?>
				<b> <?php echo l('Request', true).' => '; ?> </b>
				<br/><br/>
				<b>	<?php echo l('OTA ID', true); ?>: </b><?php echo $ota_id; ?><br />
				<b>	<?php echo l('OTA Key', true); ?>: </b><?php echo 'channex'; ?><br />
				<b>	<?php echo l('Company ID', true); ?>: </b><?php echo $company_id; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />

				<b> <?php echo l('Response', true).' => '; ?> </b><br />
				<?php prx($channex_x_company, 1); ?><br />
				
			<?php } elseif($error_cause == 'ota_x_company_id_not_found'){ ?>
				<b> <?php echo l('Request', true).' => '; ?> </b>
				<br/><br/>
				<b>	<?php echo l('OTA ID', true); ?>: </b><?php echo $ota_id; ?><br />
				<b>	<?php echo l('OTA Key', true); ?>: </b><?php echo 'channex'; ?><br />
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />

				<b> <?php echo l('Response', true).' => '; ?> </b><br />
				<?php prx($channex_x_company, 1); ?><br />
				
			<?php } elseif($error_cause == 'rooms_not_found'){ ?>
				<b> <?php echo l('Request', true).' => '; ?> </b>
				<br/><br/>
				<b>	<?php echo l('OTA ID', true); ?>: </b><?php echo $ota_id; ?><br />
				<b>	<?php echo l('OTA Key', true); ?>: </b><?php echo 'channex'; ?><br />
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				<b>	<?php echo l('Company ID', true); ?>: </b><?php echo $company_id; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />

				<b> <?php echo l('Response', true).' => '; ?> </b><br />
				<?php prx($reservation, 1); ?><br />
				
			<?php } elseif($error_cause == 'minical_room_type_id_not_found'){ ?>
				
				<?php echo l('Dear user', true); ?>, 
				<br/><br/>
				<?php if($content) {
					echo $content; 
				} ?>
				<br/><br/>
				<?php echo l('Please find the details Below', true); ?>:
				<br/>
				<b>	<?php echo l('Channex Room Type ID', true); ?>: </b><?php echo $channex_room_type_id; ?><br />
				<b>	<?php echo l('Channex Booking ID', true); ?>: </b><?php echo $channex_booking_id; ?><br />
				<b>	<?php echo l('Property ID', true); ?>: </b><?php echo $property_id; ?><br />
				
				<b>	<?php echo l('Source', true); ?>: </b><?php echo $source; ?><br />
				<b> <?php echo l('Date', true); ?>: </b><?php echo $datetime; ?><br />
				<br />

				<b><?php echo l('Once the mapping is fixed, Follow these steps', true); ?></b>:<br />
				> <?php echo l('Login to Channex app', true); ?><br />
				> <?php echo l('Go to Bookings', true); ?><br />
				> <?php echo l('View Booking (Select Booking that was missed)', true); ?><br />
				> <?php echo l('Go to Subscribers Tab', true); ?><br />
				> <?php echo l('Click Resend latest revision', true); ?><br/><br />

				<img style="width: 300px; height: 159px;" src="https://app.minical.io/images/channex_booking.png"> / 

				<br/><br/>


			<?php } ?>
			<br /><br />
			<?php echo l('Any questions please let us know', true); ?><br />
			<?php echo l('Regards', true); ?><br />
			<?php echo isset($whitelabel_username) && $whitelabel_username ? $whitelabel_username : 'PMS Admin'; ?>

		</div>
	</body>
</html>