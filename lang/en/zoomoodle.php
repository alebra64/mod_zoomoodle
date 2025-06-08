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
 * Plugin strings are defined here.
 *
 * @package     mod_zoomoodle
 * @category    string
 * @copyright   2020
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Module name and help.
$string['modulename']               = 'ZooMoodle';
$string['modulenameplural']         = 'ZoomMoodles';
$string['modulename_help']          = "The ZooMoodle activity module allows you to integrate a Zoom webinar into your course.

When a student is enrolled in a course, they are automatically registered to the Zoom webinar and receive an email with connection instructions.
The student can save the date and time to their calendar and also see their personal join URL in the webinar information.

Once the webinar is finished, participation percentage is calculated automatically and completion of the activity is set according to the configured thresholds.

The instructor can review the official Zoom report and manually adjust results to correct any anomalies.";
$string['pluginname']               = 'ZooMoodle';
$string['pluginadministration']     = 'Manage ZooMoodle';

// Webinar settings.
$string['webinar']                  = 'Webinar';
$string['topic']                    = 'Topic';
$string['webinar_id']               = 'Webinar ID';
$string['webinar_id_help']          = 'Enter the Zoom webinar ID.';
$string['start_time']               = 'Start time';
$string['start_time_help']          = 'Enter the webinar start date and time.';
$string['duration']                 = 'Duration (minutes)';
$string['duration_help']            = 'Enter the total duration of all sessions in minutes.';
$string['recurring']                = 'Recurring';
$string['end_time']                 = 'End time of last session';
$string['end_time_help']            = 'If the webinar recurs, enable and enter the end date/time of the last session.';
$string['calendariconalt']          = 'Calendar icon';
$string['add_to_calendars']         = 'Add to calendars';
$string['calendaraddtogoogle']      = 'Google';
$string['calendaraddtoyahoo']       = 'Yahoo';
$string['calendaraddtooutlook']     = 'Outlook';
$string['webinarjoin']              = 'Join webinar';

// Synchronisation settings.
$string['syncsettings']             = 'Enrolment synchronisation';
$string['syncenrolments']           = 'Synchronise Zoom enrolments';
$string['syncenrolments_help']      = 'If enabled, each course enrolment will be automatically sent to the configured Zoom webinar.';

// OAuth credentials.
$string['clientid']                 = 'Client ID';
$string['clientid_desc']            = 'Server-to-Server OAuth Client ID for Zoom integration.';
$string['clientsecret']             = 'Client Secret';
$string['clientsecret_desc']        = 'Server-to-Server OAuth Client Secret for Zoom integration.';
$string['accountid']                = 'Account ID';
$string['accountid_desc']           = 'Zoom Account ID for Server-to-Server OAuth integration.';

// Legacy API token (static).
$string['apitoken']                 = 'API token (legacy)';
$string['apitoken_desc']            = 'A static API token for legacy Zoom integration (ignored if using OAuth).';

// Admin settings page.
$string['modsettingzoomoodle']      = 'Zoomoodle settings';
$string['apiurl']                   = 'API URL';
$string['apiurl_desc']              = 'Base URL for the Zoom API, e.g. https://api.zoom.us/v2';

// Grading.
$string['grade']                    = 'Maximum grade';
$string['grade_help']               = 'Maximum possible points for this activity.';
$string['gradepass']                = 'Passing grade';
$string['gradepass_help']           = 'Minimum points required to pass the activity.';

// Errors.
$string['err_duration_nonpositive'] = 'Duration must be a positive number.';
$string['err_duration_too_long']    = 'Duration cannot exceed 150 hours.';
$string['err_end_time_past']        = 'End time must be after start time.';

// Capabilities.
$string['zoomoodle:addinstance']    = 'Add a ZooMoodle activity';
$string['zoomoodle:view']           = 'View ZooMoodle activity';

// Scheduled tasks.
$string['webinar_graduation_task']  = 'Webinar graduation task';
$string['tasksyncenrolments']       = 'Synchronise Zoom enrolments'; // <-- qui

// Attendance-based quiz release.
$string['attendancethreshold']      = 'Attendance threshold (%)';
$string['attendancethreshold_help'] = 'Minimum percentage of webinar duration required to be eligible for the quiz.';
$string['quiztorelease']            = 'Quiz to release';
$string['quiztorelease_help']       = 'Select the quiz that will be made visible when the attendance threshold is met.';
$string['errthresholdrange']        = 'Please enter a value between 0 and 100 for the attendance threshold.';

// Privacy API.
$string['privacy:metadata:zoomoodle']                       = 'Stores Zoom integration settings and enrolments.';
$string['privacy:metadata:zoomoodle:attendance_threshold'] = 'Minimum attendance percentage.';
$string['privacy:metadata:zoomoodle:quiztorelease']        = 'ID of the quiz to release.';

// New strings
$string['meeting_error'] = 'Error retrieving meeting details. Please try again later or contact support if the problem persists.';
$string['meeting_time'] = 'Meeting time';
$string['minutes'] = 'minutes';
$string['join_meeting'] = 'Join meeting';
$string['attendance_stats'] = 'Attendance statistics';
$string['participant'] = 'Participant';
$string['status'] = 'Status';
$string['present'] = 'Present';
$string['absent'] = 'Absent';
$string['meeting_not_found'] = 'Meeting not found or no longer available';
$string['meeting_access_error'] = 'Error accessing the meeting';
$string['no_join_url'] = 'Join URL not available';
$string['meeting_expired'] = 'This meeting has expired';
$string['not_registered'] = 'You are not registered for this meeting';
$string['registration_pending'] = 'Your registration is pending approval';
$string['meeting_full'] = 'This meeting is full';
$string['join_before_start'] = 'This meeting has not started yet';
$string['join_after_end'] = 'This meeting has already ended';
