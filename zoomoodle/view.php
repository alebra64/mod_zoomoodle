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
 * Pagina principale del modulo Zoomoodle
 * 
 * Questa pagina gestisce la visualizzazione dei dettagli del meeting Zoom
 * e fornisce l'interfaccia per partecipare al meeting
 * 
 * @package   mod_zoomoodle
 * @copyright 2023 Your Name <your@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

// Recupera i parametri dalla richiesta
$id = required_param('id', PARAM_INT); // Course Module ID

// Recupera il course module
$cm = get_coursemodule_from_id('zoomoodle', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$moduleinstance = $DB->get_record('zoomoodle', array('id' => $cm->instance), '*', MUST_EXIST);

// Richiede il login e verifica l'accesso al corso
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);
require_capability('mod/zoomoodle:view', $modulecontext);

// ————————————————————————————————————————————————
// 1) Registrazione automatica degli utenti Zoom → Moodle
// ————————————————————————————————————————————————
if (!empty($moduleinstance->enablesync)) {
    $context = context_module::instance($cm->id);
    $users   = get_enrolled_users($context);
    $token   = \mod_zoomoodle\api::get_token();
    if (!empty($token)) {
        foreach ($users as $u) {
            list($zoomuserid, $joinurl) = \mod_zoomoodle\api::register_user_to_webinar(
                $token,
                $moduleinstance->webinar_id,
                $u->id
            );
            if (!empty($zoomuserid) && !empty($joinurl)) {
                $record = (object)[
                    'zoomoodleid' => $moduleinstance->id,
                    'module'      => $cm->id,
                    'userid'      => $u->id,
                    'user'        => $u->id,
                    'course'      => $course->id,
                    'zoom_user'   => $zoomuserid,
                    'zoom_url'    => $joinurl
                ];
                $existing = $DB->get_record('zoomoodle_urls', [
                    'zoomoodleid' => $moduleinstance->id,
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

// Imposta le variabili della pagina
$PAGE->set_url('/mod/zoomoodle/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Marca il modulo come visualizzato per il completamento
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Inizializza l'output
echo $OUTPUT->header();

// Mostra il titolo del meeting
echo $OUTPUT->heading(format_string($moduleinstance->name));

// Mostra la descrizione se presente
if (!empty($moduleinstance->intro)) {
    echo $OUTPUT->box(format_module_intro('zoomoodle', $moduleinstance, $cm->id), 'generalbox mod_introbox', 'zoommodleintro');
}

// Verifica URL personalizzato già salvato
$zoomurl = $DB->get_record('zoomoodle_urls', [
    'zoomoodleid' => $moduleinstance->id,
    'userid'      => $USER->id
], '*', IGNORE_MISSING);

if ($zoomurl && !empty($zoomurl->zoom_url)) {
    // Mostra "Partecipa al webinar"
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

// Pulsanti "Aggiungi ai calendari"
echo $OUTPUT->heading(get_string('add_to_calendars', 'mod_zoomoodle'), 5);
echo '<p>'
   . zoomoodle_view_google_calendar_button($course, $moduleinstance)
   . '&nbsp;'
   . zoomoodle_view_yahoo_calendar_button($course, $moduleinstance)
   . '&nbsp;'
   . zoomoodle_view_outlook_calendar_button($course, $moduleinstance)
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
                  . '/' . date('Ymd\\THi00\\Z', $zoomoodle->start_time + $zoomoodle->duration * 60),
    ]);
    $icon = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoomoodle'));
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
        'et'    => date('Ymd\\THi00', $zoomoodle->start_time + $zoomoodle->duration * 60),
    ]);
    $icon = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoomoodle'));
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
        'dtend'    => date('Ymd\\THi00\\Z', $zoomoodle->start_time + $zoomoodle->duration * 60),
        'summary'  => format_string($course->shortname) . ' - ' . format_string($zoomoodle->name),
    ]);
    $icon = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoomoodle'));
    $button = html_writer::span($icon . ' ' . get_string('calendaraddtooutlook', 'mod_zoomoodle'), 'btn btn-secondary');
    return html_writer::link($ocallink->out(false), $button, ['target' => '_blank']);
}
