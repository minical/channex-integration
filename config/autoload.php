<?php 
$config['js-files'] = array(
    array(
        "file" => 'assets/js/channex.js',
         "location" => array(
           "channex_integration/channex_properties",
           "channex_integration/index"
        )
    ),
    array(
        "file" => 'assets/js/channex_updates.js',
         "location" => array(
           "*"
        )
    )
);

$config['css-files'] = array(
  array(
        "file" => 'assets/css/channex.css',
         "location" => array(
           "channex_integration/channex_properties"
        )
    )
);