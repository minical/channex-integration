<?php
	
$extension_route['channex'] = 'channex_integration/index';
$extension_route['signin_channex'] = 'channex_integration/signin_channex';
$extension_route['channex_properties/(:any)'] = 'channex_integration/channex_properties/$1';
$extension_route['get_room_types'] = 'channex_integration/get_room_types';
$extension_route['save_channex_mapping_AJAX'] = 'channex_integration/save_channex_mapping_AJAX';
$extension_route['channex_update_availability'] = 'channex_integration/channex_update_availability';
$extension_route['channex_update_restrictions'] = 'channex_integration/channex_update_restrictions';
$extension_route['deconfigure_channex_AJAX'] = 'channex_integration/deconfigure_channex_AJAX';
$extension_route['update_full_refresh'] = 'channex_integration/update_full_refresh';
$extension_route['channex_refresh_token'] = 'channex_integration/channex_refresh_token';
$extension_route['update_import_extra_charge'] = 'channex_integration/update_import_extra_charge';

$extension_route['cron/channex_get_bookings/(:any)'] = 'channex_bookings/channex_get_bookings/$1';
$extension_route['refresh_token'] = 'channex_bookings/refresh_token';
$extension_route['save_logs'] = 'channex_bookings/save_logs';
$extension_route['cron/channex_retrieve_booking/(:any)'] = 'channex_bookings/channex_retrieve_booking/$1';

