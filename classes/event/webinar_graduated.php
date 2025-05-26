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
 * Plugin event classes are defined here.
 *
 * @package     mod_zoomoodle
 * @copyright   2020 (c) Fast Video Produzioni <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomoodle\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The webinar_graduated event class.
 *
 * @package    mod_zoomoodle
 * @copyright  2020 (c) Fast Video Produzioni <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webinar_graduated extends \core\event\base {
    protected function init() {
        //$this->data['objecttable'] = 'zoomoodle';
        //$this->data['objectid'] = $_GET['id'];
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        //$this->data['other']['courseid'] = intval($cm->course);  
    }
}
