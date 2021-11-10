<?php

add_filter('get_inventory_channel_keys', 'get_inventory_channel_keys', 10, 1);
    
function get_inventory_channel_keys ($channels_keys) {

    $channels_keys[] = 'channex';

    return $channels_keys;
}