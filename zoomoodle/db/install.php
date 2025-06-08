<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     mod_zoomoodle
 * @category    upgrade
 * @copyright   2020 Fast Video Produzioni
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_zoomoodle_install() {
    global $DB;
    
    // Forza l'uso di collation non UTF-8 per le tabelle del plugin
    $dbman = $DB->get_manager();
    $tables = ['zoomoodle', 'zoomoodle_urls', 'zoomoodle_participants', 'zoomoodle_registrants'];
    
    foreach ($tables as $tablename) {
        $table = new xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $DB->execute("ALTER TABLE {" . $tablename . "} CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci");
        }
    }

    return true;
}
