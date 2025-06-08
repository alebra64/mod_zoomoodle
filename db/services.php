<?php
$functions = array(
    'zoomoodle_webinar_graduation' => array(
            'classname'   => 'mod_zoomoodle_external',
            'methodname'  => 'webinar_graduation',
            'classpath'   => 'mod/zoomoodle/classes/external.php',
            'description' => 'Get Webinar Attended Report ',
            'type'        => 'read'
    )
);

$services = array(
        'Get Webinar Report' => array(
                'functions' => array ('zoomoodle_webinar_graduation'),
                'restrictedusers' => 0,
                'enabled'=> 1
        )
);