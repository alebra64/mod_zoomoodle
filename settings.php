<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// Plugin administration pages are defined here.
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Main settings page.
    $settings = new admin_settingpage(
        'modsettingzoomoodle',
        get_string('pluginname', 'mod_zoomoodle')
    );

    // API URL (base endpoint, e.g. https://api.zoom.us/v2).
    $apiurl = new admin_setting_configtext(
        'mod_zoomoodle/apiurl',
        get_string('apiurl', 'mod_zoomoodle'),
        get_string('apiurl_desc', 'mod_zoomoodle'),
        ''
    );
    $settings->add($apiurl);

    // API Token (static, legacy).
    $apitoken = new admin_setting_configpasswordunmask(
        'mod_zoomoodle/apitoken',
        get_string('apitoken', 'mod_zoomoodle'),
        get_string('apitoken_desc', 'mod_zoomoodle'),
        ''
    );
    $settings->add($apitoken);

    // ----------------------------------------------------
    // Server-to-Server OAuth credentials
    // ----------------------------------------------------

    // Client ID.
    $clientid = new admin_setting_configtext(
        'mod_zoomoodle/clientid',
        get_string('clientid', 'mod_zoomoodle'),
        get_string('clientid_desc', 'mod_zoomoodle'),
        '',
        PARAM_RAW_TRIMMED
    );
    $settings->add($clientid);

    // Client Secret.
    $clientsecret = new admin_setting_configpasswordunmask(
        'mod_zoomoodle/clientsecret',
        get_string('clientsecret', 'mod_zoomoodle'),
        get_string('clientsecret_desc', 'mod_zoomoodle'),
        ''
    );
    $settings->add($clientsecret);

    // Account ID.
    $accountid = new admin_setting_configtext(
        'mod_zoomoodle/accountid',
        get_string('accountid', 'mod_zoomoodle'),
        get_string('accountid_desc', 'mod_zoomoodle'),
        '',
        PARAM_RAW_TRIMMED
    );
    $settings->add($accountid);

    // ----------------------------------------------------
    // Finally register this settings page under "Modules"
    // ----------------------------------------------------
    $ADMIN->add('modsettings', $settings);
}
