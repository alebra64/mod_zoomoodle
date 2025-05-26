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
 * Library of interface functions and constants.
 *
 * @package     mod_zoomoodle
 * @copyright   2020 (c) Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true|null True if the feature is supported, null otherwise.
 */
function zoomoodle_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPINGS:
        case FEATURE_GROUPMEMBERSONLY:
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        // Support completion tracking and rules
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_zoomoodle into the database.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_zoomoodle_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function zoomoodle_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    // QUI salvarà anche attendance_threshold e quiztoleitura perché il form li fornisce.
    $moduleinstance->id = $DB->insert_record('zoomoodle', $moduleinstance);

    zoomoodle_grade_item_update($moduleinstance);
    return $moduleinstance->id;
}

/**
 * Updates an instance of the mod_zoomoodle in the database.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_zoomoodle_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function zoomoodle_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    // QUI aggiorna anche attendance_threshold e quiztoleitura.
    $DB->update_record('zoomoodle', $moduleinstance);

    zoomoodle_grade_item_update($moduleinstance);
    return true;
}

/**
 * Removes an instance of the mod_zoomoodle from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function zoomoodle_delete_instance($id) {
    global $DB;

    if (!$moduleinstance = $DB->get_record('zoomoodle', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('zoomoodle', ['id' => $moduleinstance->id]);
    zoomoodle_grade_item_delete($moduleinstance);
    return true;
}

/**
 * Is a given scale used by the instance of mod_zoomoodle?
 *
 * @param int $moduleinstanceid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool
 */
function zoomoodle_scale_used($moduleinstanceid, $scaleid) {
    global $DB;
    return $scaleid
        && $DB->record_exists('zoomoodle', ['id' => $moduleinstanceid, 'grade' => -$scaleid]);
}

/**
 * Checks if scale is being used by any instance of mod_zoomoodle.
 *
 * @param int $scaleid ID of the scale.
 * @return bool
 */
function zoomoodle_scale_used_anywhere($scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('zoomoodle', ['grade' => -$scaleid]);
}

/**
 * Creates or updates grade item for the given mod_zoomoodle instance.
 *
 * @param stdClass $moduleinstance
 * @param mixed $grades
 * @return void
 */
function zoomoodle_grade_item_update($moduleinstance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname'  => clean_param($moduleinstance->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE
    ];

    if ($moduleinstance->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $moduleinstance->grade;
        $item['grademin']  = 0;
    } elseif ($moduleinstance->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$moduleinstance->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $grades = null;
        $item['reset'] = true;
    }

    grade_update('mod/zoomoodle', $moduleinstance->course, 'mod', 'zoomoodle',
        $moduleinstance->id, 0, $grades, $item);
}

/**
 * Delete grade item for given mod_zoomoodle instance.
 *
 * @param stdClass $moduleinstance
 * @return grade_item
 */
function zoomoodle_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/zoomoodle', $moduleinstance->course, 'mod', 'zoomoodle',
        $moduleinstance->id, 0, null, ['deleted' => 1]);
}

/**
 * Stub per il completamento (non usato).
 */
function zoomoodle_get_completion_state($course, $cm, $userid, $type) {
    return false;
}

/**
 * Update mod_zoomoodle grades in the gradebook (no-op).
 *
 * @param stdClass $moduleinstance
 * @param int $userid
 */
function zoomoodle_update_grades($moduleinstance, $userid = 0) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $grades = [];
    grade_update('mod/zoomoodle', $moduleinstance->course, 'mod', 'zoomoodle',
        $moduleinstance->id, 0, $grades);
}

/**
 * Returns the lists of all browsable file areas.
 */
function zoomoodle_get_file_areas($course, $cm, $context) {
    return [];
}

/**
 * File browsing support (none).
 */
function zoomoodle_get_file_info($browser, $areas, $course, $cm,
    $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the mod_zoomoodle file areas (none).
 */
function zoomoodle_pluginfile($course, $cm, $context,
    $filearea, $args, $forcedownload, $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }
    require_login($course, true, $cm);
    send_file_not_found();
}

/**
 * Navigation stubs: TUTTE le possibili hook di navigazione,
 * così non dà più undefined function.
 */
function zoomoodle_extend_navigation(navigation_node $navref,
    stdClass $course, stdClass $module, cm_info $cm) {
}

function zoomoodle_extend_settings_navigation(settings_navigation $settingsnav,
    navigation_node $zoomoodlenode = null) {
}

function zoomoodle_extend_navigation_course(navigation_node $parentnode,
    stdClass $course, context_course $context) {
}

function zoomoodle_extend_navigation_user_settings(navigation_node $parentnode,
    stdClass $user, context_user $context, stdClass $course, context_course $coursecontext) {
}

function zoomoodle_extend_navigation_category_settings(navigation_node $parentnode,
    context_coursecat $context) {
}

function zoomoodle_extend_navigation_frontpage(navigation_node $parentnode,
    stdClass $course, context_course $context) {
}

// Fine del file.
