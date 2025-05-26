<?php
require(__DIR__ . '/../../config.php');
// global $DB;

// Check if user is logged in and is the admin!
// is_siteadmin() || die;
$context = context_system::instance();
$id = optional_param('id', 0, PARAM_INT); // course_module id
$cm = get_coursemodule_from_id('zoomoodle', $id, 0, false, MUST_EXIST);
//$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
//$zoomoodle = $DB->get_record('zoomoodle', array('id' => $cm->instance), '*', MUST_EXIST);


\mod_zoomoodle\event\webinar_graduated::create(array(
    'context' => $context,
    // 'objectid' => $id,
    'other' => array(
        'cmid' => $id,
        'courseid' => $cm->course,
        'zoomoodle' => $cm->instance
    )
))->trigger();

/*

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('zoomoodle', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);
$PAGE->set_url('/mod/zoomoodle/trigger.php', array('id' => $cm->id));
$PAGE->set_title('My modules page title');
$PAGE->set_heading('My modules page heading');

//$PAGE->set_context(context_system::instance());
//$PAGE->set_pagelayout('admin');
//$PAGE->set_title("Your title");
//$PAGE->set_heading("Blank page");
//$PAGE->set_url($CFG->wwwroot . '/blank_page.php');

echo $OUTPUT->header();
echo "Perform report recalculation request";
echo $OUTPUT->footer();

// Perform report recalculation request

*/