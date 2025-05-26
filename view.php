<?php
// This file is part of Moodle - http://moodle.org/
// [...]
/**
 * Prints an instance of mod_zoomoodle.
 *
 * @package     mod_zoomoodle
 * @copyright   2020 (c)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

global $DB, $OUTPUT, $PAGE, $USER;

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n  = optional_param('n', 0, PARAM_INT);  // Zoomoodle instance id.

if ($id) {
    $cm = get_coursemodule_from_id('zoomoodle', $id, 0, false, MUST_EXIST);
    $course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $zoomoodle = $DB->get_record('zoomoodle', ['id' => $cm->instance], '*', MUST_EXIST);
} elseif ($n) {
    $zoomoodle = $DB->get_record('zoomoodle', ['id' => $n], '*', MUST_EXIST);
    $course   = $DB->get_record('course', ['id' => $zoomoodle->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('zoomoodle', $zoomoodle->id, $course->id, false, MUST_EXIST);
} else {
    print_error(get_string('error_missing_id', 'mod_zoomoodle'));
}

require_login($course, true, $cm);

// ————————————————————————————————————————————————
// 1) Registrazione automatica degli utenti Zoom → Moodle
// ————————————————————————————————————————————————
if (!empty($zoomoodle->enablesync)) {
    $context = context_module::instance($cm->id);
    $users   = get_enrolled_users($context);
    $token   = \mod_zoomoodle\api::get_token();
    if (!empty($token)) {
        foreach ($users as $u) {
            list($zoomuserid, $joinurl) = \mod_zoomoodle\api::register_user_to_webinar(
                $token,
                $zoomoodle->webinar_id,
                $u->id
            );
            if (!empty($zoomuserid) && !empty($joinurl)) {
                $record = (object)[
                    'zoomoodleid' => $zoomoodle->id,
                    'module'      => $cm->id,
                    'userid'      => $u->id,
                    'user'        => $u->id,
                    'course'      => $course->id,
                    'zoom_user'   => $zoomuserid,
                    'zoom_url'    => $joinurl
                ];
                $existing = $DB->get_record('zoomoodle_urls', [
                    'zoomoodleid' => $zoomoodle->id,
                    'userid'      => $u->id
                ], '*', IGNORE_MISSING);
                if ($existing) {
                    $existing->zoom_user = $zoomuserid;
                    $existing->zoom_url  = $joinurl;
                    $DB->update_record('zoomoodle_urls', $existing);
                } else {
                    $DB->insert_record('zoomoodle_urls', $record);
                }
            }
        }
    }
}

// ————————————————————————————————————————————————
// 2) Trigger “viewed” event & preparazione pagina
// ————————————————————————————————————————————————
$modulecontext = context_module::instance($cm->id);
require_capability('mod/zoomoodle:view', $modulecontext);

// Trigger course_module_viewed event.
$event = \mod_zoomoodle\event\course_module_viewed::create([
    'objectid' => $zoomoodle->id,
    'context'  => $modulecontext
]);
$event->trigger();

// Page setup.
$PAGE->set_url('/mod/zoomoodle/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($zoomoodle->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// ————————————————————————————————————————————————
// 3) Output HTML: header, intro e “Partecipa”
// ————————————————————————————————————————————————
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($zoomoodle->name), 2);

if (!empty($zoomoodle->intro)) {
    echo $OUTPUT->box(
        format_module_intro('zoomoodle', $zoomoodle, $cm->id),
        'generalbox mod_introbox',
        'intro'
    );
}

// Verifica URL personalizzato già salvato
$zoomurl = $DB->get_record('zoomoodle_urls', [
    'zoomoodleid' => $zoomoodle->id,
    'userid'      => $USER->id
], '*', IGNORE_MISSING);

if ($zoomurl && !empty($zoomurl->zoom_url)) {
    // Mostra “Partecipa al webinar”
    $icon   = $OUTPUT->pix_icon('t/right', get_string('webinarjoin', 'mod_zoomoodle'));
    $button = html_writer::span(
        $icon . ' ' . get_string('webinarjoin', 'mod_zoomoodle'),
        'btn btn-primary'
    );
    echo html_writer::link($zoomurl->zoom_url, $button, ['target' => '_blank']);
} else {
    // Qui non serve nulla: il pulsante verrà creato dal sync in apertura
    echo html_writer::tag('p', get_string('notregistered', 'mod_zoomoodle'), ['class' => 'text-warning']);
}

// Pulsanti “Aggiungi ai calendari”.
echo $OUTPUT->heading(get_string('add_to_calendars', 'mod_zoomoodle'), 5);
echo '<p>'
   . zoomoodle_view_google_calendar_button($course, $zoomoodle)
   . '&nbsp;'
   . zoomoodle_view_yahoo_calendar_button($course, $zoomoodle)
   . '&nbsp;'
   . zoomoodle_view_outlook_calendar_button($course, $zoomoodle)
   . '</p>';

echo $OUTPUT->footer();

/**
 * Google Calendar button.
 */
function zoomoodle_view_google_calendar_button($course, $zoomoodle) {
    global $OUTPUT;
    $gcallink = new moodle_url('https://www.google.com/calendar/render', [
        'action' => 'TEMPLATE',
        'text'   => format_string($course->shortname) . ' - ' . format_string($zoomoodle->name),
        'dates'  => date('Ymd\\THi00\\Z', $zoomoodle->start_time)
                  . '/' . date('Ymd\\THi00\\Z', $zoomoodle->start_time + $zoomoodle->duration),
    ]);
    $icon   = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoomoodle'));
    $button = html_writer::span($icon . ' ' . get_string('calendaraddtogoogle', 'mod_zoomoodle'), 'btn btn-secondary');
    return html_writer::link($gcallink->out(false), $button, ['target' => '_blank']);
}

/**
 * Yahoo Calendar button.
 */
function zoomoodle_view_yahoo_calendar_button($course, $zoomoodle) {
    global $OUTPUT;
    $ycallink = new moodle_url('https://calendar.yahoo.com/', [
        'v'     => '60',
        'view'  => 'd',
        'type'  => '20',
        'title' => format_string($course->shortname) . ' - ' . format_string($zoomoodle->name),
        'st'    => date('Ymd\\THi00', $zoomoodle->start_time),
        'et'    => date('Ymd\\THi00', $zoomoodle->start_time + $zoomoodle->duration),
    ]);
    $icon   = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoomoodle'));
    $button = html_writer::span($icon . ' ' . get_string('calendaraddtoyahoo', 'mod_zoomoodle'), 'btn btn-secondary');
    return html_writer::link($ycallink->out(false), $button, ['target' => '_blank']);
}

/**
 * Outlook Calendar button.
 */
function zoomoodle_view_outlook_calendar_button($course, $zoomoodle) {
    global $OUTPUT;
    $ocallink = new moodle_url('https://outlook.live.com/owa/', [
        'path'     => '/calendar/view/Month',
        'rru'      => 'addevent',
        'dtstart'  => date('Ymd\\THi00\\Z', $zoomoodle->start_time),
        'dtend'    => date('Ymd\\THi00\\Z', $zoomoodle->start_time + $zoomoodle->duration),
        'summary'  => format_string($course->shortname) . ' - ' . format_string($zoomoodle->name),
    ]);
    $icon   = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoomoodle'));
    $button = html_writer::span($icon . ' ' . get_string('calendaraddtooutlook', 'mod_zoomoodle'), 'btn btn-secondary');
    return html_writer::link($ocallink->out(false), $button, ['target' => '_blank']);
}
