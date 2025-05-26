<?php
// File: mod/zoomoodle/mod_form.php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

class mod_zoomoodle_mod_form extends moodleform_mod {
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // --- General settings ---
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('topic', 'mod_zoomoodle'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        // --- Zoomoodle webinar settings ---
        $mform->addElement('header', 'zoomoodlewebinar', get_string('webinar', 'mod_zoomoodle'));
        $mform->addElement('text', 'webinar_id', get_string('webinar_id', 'mod_zoomoodle'), ['size' => '20']);
        $mform->setType('webinar_id', PARAM_INT);
        $mform->addRule('webinar_id', null, 'required', null, 'client');
        $mform->addHelpButton('webinar_id', 'webinar_id', 'mod_zoomoodle');

        // Sync enrolments block
        $mform->addElement('advcheckbox', 'enablesync', get_string('syncenrolments', 'mod_zoomoodle'));
        $mform->setType('enablesync', PARAM_INT);
        $mform->addHelpButton('enablesync', 'syncenrolments', 'mod_zoomoodle');

        // --- Attendance threshold ---
        $mform->addElement('text', 'attendance_threshold', get_string('attendancethreshold', 'mod_zoomoodle'), ['size' => '4']);
        $mform->setType('attendance_threshold', PARAM_INT);
        $mform->addRule('attendance_threshold', null, 'required', null, 'client');
        $mform->addHelpButton('attendance_threshold', 'attendancethreshold', 'mod_zoomoodle');

        // --- Quiz to release ---
        $quizoptions = [];
        if (!empty($this->current->course)) {
            $quizzes = get_all_instances_in_course('quiz', get_course($this->current->course));
            foreach ($quizzes as $quiz) {
                $quizoptions[$quiz->id] = format_string($quiz->name);
            }
        }
        $mform->addElement('select', 'quiztoleitura', get_string('quiztorelease', 'mod_zoomoodle'), $quizoptions);
        $mform->setType('quiztoleitura', PARAM_INT);
        $mform->addHelpButton('quiztoleitura', 'quiztorelease', 'mod_zoomoodle');

        // --- Date/time and duration ---
        $mform->addElement('date_time_selector', 'start_time', get_string('start_time', 'mod_zoomoodle'));
        $mform->addHelpButton('start_time', 'start_time', 'mod_zoomoodle');
        $mform->addElement('date_time_selector', 'end_time', get_string('end_time', 'mod_zoomoodle'), ['optional' => true]);
        $mform->addHelpButton('end_time', 'end_time', 'mod_zoomoodle');
        $mform->addElement('duration', 'duration', get_string('duration', 'mod_zoomoodle'));
        $mform->setDefault('duration', ['number' => 1, 'timeunit' => 3600]);
        $mform->addHelpButton('duration', 'duration', 'mod_zoomoodle');

        // --- Grading and completion ---
        // 1) grading settings
        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);
        $mform->setDefault('gradepass', 80);

        // 2) standard module settings (includes availability, groups,
        //    completion tracking and rules)
        $this->standard_coursemodule_elements();

        // --- Action buttons ---
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['duration'] <= 0) {
            $errors['duration'] = get_string('err_duration_nonpositive', 'mod_zoomoodle');
        }
        if (!empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
            $errors['end_time'] = get_string('err_end_time_past', 'mod_zoomoodle');
        }
        if ($data['attendance_threshold'] < 0 || $data['attendance_threshold'] > 100) {
            $errors['attendance_threshold'] = get_string('errthresholdrange', 'mod_zoomoodle');
        }

        return $errors;
    }
}
