<?php
// File: mod/zoomoodle/db/upgrade.php

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
 * Plugin upgrade steps are defined here.
 *
 * @package     mod_zoomoodle
 * @category    upgrade
 * @copyright   2023 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for Zoomoodle module.
 * @param int $oldversion
 * @return bool
 */
function xmldb_zoomoodle_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025052101) {
        // Table zoomoodle.
        $table = new xmldb_table('zoomoodle');

        // Field attendance_threshold.
        $field = new xmldb_field('attendance_threshold', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '80', 'grade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Field quiztoleitura.
        $field = new xmldb_field('quiztoleitura', XMLDB_TYPE_INTEGER, '10', null, false, null, '0', 'attendance_threshold');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached.
        upgrade_mod_savepoint(true, 2025052101, 'zoomoodle');
    }

    if ($oldversion < 2023120600) {
        // Definisci la tabella zoomoodle_urls se non esiste.
        $table = new xmldb_table('zoomoodle_urls');

        // Aggiungi i campi.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('module', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('zoom_user', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('zoom_url', XMLDB_TYPE_CHAR, '300', null, null, null, null);

        // Aggiungi le chiavi.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Aggiungi gli indici.
        $table->add_index('user_course_module', XMLDB_INDEX_UNIQUE, ['user', 'course', 'module']);

        // Crea la tabella se non esiste.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2023120600, 'zoomoodle');
    }

    return true;
}
