<?php
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/*
* grade_item_update
* https://fad.fastvideoproduzioni.it
  /webservice/rest/server.php?moodlewsrestformat=json&wsfunction=zoomoodle_webinar_graduation&wstoken=d250f853897d1949cd90053e328185e4

    {
        "wid":"1234567890", // Webinar ID or UUID
        "users":[ // Attendees list: email and duration in seconds
            {"email":"server@fastvideoproduzioni.it","duration":"1800"},
            {"email":"studente1@fastvideoproduzioni.it","duration":"2736"}
        ]
    }
*/

class mod_zoomoodle_external extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function webinar_graduation_parameters() {
        // return new external_function_parameters(array(
        //     'wid' => new external_value(PARAM_INT, 'Zoom webinar id'),
        //     // 'users' => new external_multiple_structure(
        //     //     new external_single_structure(array(
        //     //         'email' => new external_value(PARAM_EMAIL, 'Attendee email'),
        //     //         'duration' => new external_value(PARAM_RAW, 'Attendee duration')
        //     //     ), 'Attendee record')
        //     // )
        //     'users' => new external_value(PARAM_RAW, 'Attendees JSON array')
        // ));

        return new external_function_parameters(array());
    }

    /**
     * Bypass the core validate_parameters method to allow pass JSON object
     * ********************************************************************
     * Validates submitted function parameters, if anything is incorrect
     * invalid_parameter_exception is thrown.
     * This is a simple recursive method which is intended to be called from
     * each implementation method of external API.
     *
     * @param external_description $description description of parameters
     * @param mixed $params the actual parameters
     * @return mixed params with added defaults for optional items, invalid_parameters_exception thrown if any problem found
     */
    public static function validate_parameters(external_description $description, $params) {
        // TODO: Validate params
        return json_decode(file_get_contents("php://input"), true);
    }

    /**
     * Creates or updates grade item for the given zoom instance and returns join url.
     * This function grabs most of the options to display for users in /mod/zoom/view.php
     *
     * @param int $zoomid the zoom course module id
     * @return array of warnings and status result
     * @throws moodle_exception
     */
    public static function webinar_graduation() {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . "/mod/zoomoodle/lib.php");
        require_once($CFG->libdir . '/gradelib.php');

        $params = self::validate_parameters(self::webinar_graduation_parameters(), array());
        // $params = json_decode(file_get_contents("php://input"), true);

        $warnings = array();

        $zoomoodle = $DB->get_record('zoomoodle', array('webinar_id' => $params['wid']), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $zoomoodle->course), '*', MUST_EXIST);
        $module = $DB->get_record('modules', array('name' => 'zoomoodle'), '*', MUST_EXIST);
        $cm = $DB->get_record('course_modules', array('course' => $zoomoodle->course, 'module' => $module->id, 'instance' => $zoomoodle->id, ), '*', MUST_EXIST);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/zoomoodle:view', $context);

        // TODO: Check if course is ended before to check graduation

        $enrolled = get_enrolled_users($context);
        // $gradelist = grade_get_grades($course->id, 'mod', 'zoomoodle', $cm->instance, array_keys($enrolled));
        $emails = array_map('strtolower', array_column($params['users'], 'email')); // Force lowercase for email addressess

        // $grade_coef = 100 / floatval($zoomoodle->duration);
        $grades = array();
        foreach($enrolled as $id => $user) {
            $key = array_search(strtolower($user->email), $emails);
            if($key !== false) {
                $grades[$id] = new stdClass();
                $grades[$id]->userid = $id;
                $grades[$id]->key = $key;
                $grades[$id]->usermodified = $id;
                $grades[$id]->dategraded = '';
                $grades[$id]->feedbackformat = '';
                $grades[$id]->feedback = '';
                $grades[$id]->rawgrade = self::webinar_graduation_calculate($zoomoodle, $params['users'][$key]); //  round(floatval($params['users'][$key]['duration']) * $grade_coef, 2);

                // TODO: Save wid, uuid, intervals and chunks in database
            }
        }

        $status = grade_update('mod/zoomoodle', $zoomoodle->course, 'mod', 'zoomoodle', $zoomoodle->id, 0, $grades);

    // ob_start();
    // var_dump(
    //     "==============================================", 
    //     $params, 
    //     $enrolled, 
    //     $zoomoodle, 
    //     $course, 
    //     $cm, 
    //     $grades, 
    //     $status,
    //     "=============================================="
    // );
    // file_put_contents(dirname(__FILE__) . '/log.txt', ob_get_clean(), FILE_APPEND);

    // zoomoodle_webinar_graduation($cm, $grades);


        // Assign full credits for user who has no grade yet, if this meeting is gradable
        // (i.e. the grade type is not "None").
        // if (!empty($gradelist->items) && empty($gradelist->items[0]->grades[$USER->id]->grade)) {
        //     $grademax = $gradelist->items[0]->grademax; // TODO: calculate right value
        //     $grades = array(
        //         'rawgrade' => $grademax,
        //         'userid' => $USER->id,
        //         'usermodified' => $USER->id,
        //         'dategraded' => '',
        //         'feedbackformat' => '',
        //         'feedback' => ''
        //     );
        //     // Call the zoom/lib API.
        //     zoomoodle_webinar_graduation($zoom, $grades);
        // }

        // Pass url to join zoom meeting in order to redirect user.
        // $joinurl = new moodle_url($zoom->join_url, array('uname' => fullname($USER)));

        // var_dump($warnings);
        // $warnings = array();


        $result = array();
        $result['status'] = true;
        // $result['joinurl'] = $joinurl->__toString();
        $result['warnings'] = $warnings;


        return $result;
    }

    /**
     * Returns the percentage of webinar partecipation for a single attendee
     *
     * @return float
     */
    public static function webinar_graduation_calculate($zoomoodle, $attendee) {
        $duration = (isset($zoomoodle->agenas_duration) && $zoomoodle->agenas_duration > 0) ? $zoomoodle->agenas_duration : $zoomoodle->duration;
        $grade_coef = 100 / floatval($duration);
        return round(floatval($attendee['duration']) * $grade_coef, 2);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function webinar_graduation_returns() {
        return new external_single_structure(array(
            'status' => new external_value(PARAM_BOOL, 'Status: true if success'),
            // 'joinurl' => new external_value(PARAM_RAW, 'Zoom meeting join url'),
            'warnings' => new external_warnings()
        ));
    }

    public static function webinar_graduation_test($data) {
        echo __METHOD__;
        var_dump($data);

        return true;
    }

}
