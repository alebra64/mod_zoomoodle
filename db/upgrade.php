<?php
// File: mod/zoomoodle/db/upgrade.php

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

    return true;
}
