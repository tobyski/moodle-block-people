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
 * Block "people" - Custom admin setting for user profile fields
 *
 * @package    block_people
 * @copyright  2022 Toby Skinner, Global Optima <toby@globaloptima.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_people\setting;

require_once($CFG->dirroot.'/lib/adminlib.php');

/**
 * Admin setting that allows a user to pick appropriate user profile fields for something.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_pickprofilefields extends \admin_setting_configmulticheckbox {
    private $category;

    /**
     * @param string $name Name of config variable
     * @param string $visiblename Display name
     * @param string $description Description
     * @param array $types Array of archetypes which identify
     *              roles that will be enabled by default.
     */
    public function __construct($name, $visiblename, $description, $category) {
        parent::__construct($name, $visiblename, $description, NULL, NULL);
        $this->category = $category;
    }

    /**
     * Load profile fields as choices
     *
     * @return bool true=>success, false=>error
     */
    public function load_choices() {
        global $CFG, $DB;
        if (during_initial_install()) {
            return false;
        }
        if (is_array($this->choices)) {
            return true;
        }

        $this->choices = [];
    
        // custom profile fields
        $fields = $DB->get_records('user_info_field', array('categoryid' => $this->category->id), 'sortorder ASC');
        if($fields) {
            foreach($fields as $field) {
                $this->choices[$field->shortname] = $field->name;
            }
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Return the default setting for this control
     *
     * @return array Array of default settings
     */
    public function get_defaultsetting() {
        global $CFG;

        if (during_initial_install()) {
            return null;
        }

        return null;
    }
}