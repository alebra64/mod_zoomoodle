<?php
namespace mod_zoomoodle\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use mod_zoomoodle\sync_manager;

class webinar_graduation extends scheduled_task {
    /**
     * Nome mostrato in “Scheduled tasks”
     */
    public function get_name(): string {
        return get_string('webinar_graduation_task', 'mod_zoomoodle');
    }

    /**
     * Esegue la sincronizzazione del report Zoom e la marcatura della completion
     */
    public function execute() {
        sync_manager::run();
    }
}
