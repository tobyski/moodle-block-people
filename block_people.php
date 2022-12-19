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
 * Block "people"
 *
 * @package    block_people
 * @copyright  2013 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class block_people
 *
 * @package    block_people
 * @copyright  2013 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_people extends block_base {
    /**
     * init function
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_people');
    }

    /**
     * applicable_formats function
     * @return array
     */
    public function applicable_formats() {
        return array('course-view' => true, 'site' => true);
    }

    /**
     * has_config function
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * instance_allow_multiple function
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * instance_can_be_hidden function
     * @return bool
     */
    public function instance_can_be_hidden() {
        // By default, instances can be hidden by the user.
        $hideblock = true;
        // If config 'hideblock' is disabled.
        if ((get_config('block_people', 'hideblock')) == '0') {
            // Set value to false, so instance cannot be hidden.
            $hideblock = false;
        }
        return $hideblock;
    }

    /**
     * get_content function
     * @return string
     */
    public function get_content() {
        global $COURSE, $CFG, $OUTPUT, $USER, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        // Prepare output.
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get context.
        $currentcontext = $this->page->context;

        // get the icons wew ant to show
        $iconFields = get_config('block_people', 'iconfields');
        if($iconFields) {
            $iconFields = explode(',', $iconFields);
        }

        // get the user fields we want to show
        $userFields = get_config('block_people', 'userfields');
        if($userFields) {
            $userFields = explode(',', $userFields);
        }

        // get any user profile fields we would like to show, make sure they
        // are stored in the correct order
        $categorisedProfileFields = [];
        $categories = $DB->get_records('user_info_category', null, 'sortorder ASC');
        foreach ($categories as $category) {
            $profileFields = get_config('block_people', 'profilefields_cat_' . $category->id);
            if($profileFields) {
                $profileFields = explode(',', $profileFields);
                // we also need to know the current sort order of the profile fields
                // in this category so we can show them in the correct order without
                // having to reorder everything in settings
                $sortedProfileFields = $DB->get_records('user_info_field', array('categoryid' => $category->id), 'sortorder ASC');
                $categorisedProfileFields[] = [
                    'category' => $category,
                    'fields' => $profileFields,
                    'currentFieldSort' => $sortedProfileFields
                ];
            }
        }

        // Get teachers separated by roles.
        $roles = get_config('block_people', 'roles');
        if (!empty($roles)) {
            $teacherroles = explode(',', $roles);
            $teachers = get_role_users(
                    $teacherroles,
                    $currentcontext,
                    true,
                    'ra.id AS raid, r.id AS roleid, r.sortorder, u.*',
                    'r.sortorder ASC, u.lastname ASC, u.firstname ASC'
            );
        } else {
            $teachers = array();
        }

        // Get role names / aliases in course context.
        $rolenames = role_get_names($currentcontext, ROLENAME_ALIAS, true);

        // Get multiple roles config.
        $multipleroles = get_config('block_people', 'multipleroles');

        // Start teachers list.
        $this->content->text .= html_writer::start_tag('div', array('class' => 'teachers'));

        // Initialize running variables.
        $teacherrole = null;
        $displayedteachers = array();

        // Check every teacher.
        foreach ($teachers as $teacher) {
            // If the user is suspended, skip him.
            if ($teacher->suspended == true) {
                continue;
            }

            // If users should only be listed once.
            if (!$multipleroles) {
                // Continue if we have already shown this user.
                if (isset($displayedteachers[$teacher->id])) {
                    continue;
                }
                // Remember that we have shown this user.
                $displayedteachers[$teacher->id] = 1;

                // Otherwise.
            } else {
                // Continue if we have already shown this user.
                if (isset($displayedteachers[$teacher->id][$teacher->roleid])) {
                    continue;
                }
                // Remember that we have shown this user and his role.
                $displayedteachers[$teacher->id][$teacher->roleid] = 1;
            }

            // If we have to process a new role.
            if ($teacherrole != $teacher->roleid) {
                // End previous role list if necessary.
                if ($teacherrole != null) {
                    $this->content->text .= html_writer::end_tag('ul');
                }

                // Write heading and open new role list.
                $teacherrole = $teacher->roleid;
                $this->content->text .= html_writer::tag('h3', $rolenames[$teacherrole]);
                $this->content->text .= html_writer::start_tag('ul');
            }

            // Start output teacher.
            $this->content->text .= html_writer::start_tag('li');

            // Create user object for picture output.
            $user = new stdClass();
            $user->id = $teacher->id;
            $user->lastname = $teacher->lastname;
            $user->firstname = $teacher->firstname;
            $user->lastnamephonetic = $teacher->lastnamephonetic;
            $user->firstnamephonetic = $teacher->firstnamephonetic;
            $user->middlename = $teacher->middlename;
            $user->alternatename = $teacher->alternatename;
            $user->picture = $teacher->picture;
            $user->imagealt = $teacher->imagealt;
            $user->email = $teacher->email;

            // picture is now optional and must be enabled
            if(in_array('picture', $userFields)) {
                $this->content->text .= html_writer::start_tag('div', array('class' => 'image'));
                if (has_capability('moodle/user:viewdetails', $currentcontext)) {
                    $this->content->text .= $OUTPUT->user_picture($user,
                            array('size' => 30, 'link' => true, 'courseid' => $COURSE->id, 'includefullname' => false));
                } else {
                    $this->content->text .= $OUTPUT->user_picture($user,
                            array('size' => 30, 'link' => false, 'courseid' => $COURSE->id, 'includefullname' => false));
                }
                $this->content->text .= html_writer::end_tag('div');
            }

            $this->content->text .= html_writer::start_tag('div', array('class' => 'details'));

            // display userFields
            foreach($userFields as $userField) {

                // ignore these as they are special cases which are handled elsewhere
                if($userField == 'picture') {
                    continue;
                }

                // special case for fullname virtual field
                if($userField == 'fullname') {
                    $this->content->text .= html_writer::start_tag('div', array('class' => 'name'));

                    if (has_capability('moodle/user:viewdetails', $currentcontext)) {
                        $courseid = $COURSE->id;
                        if ($courseid == SITEID) {
                            $url = new moodle_url('/user/profile.php', array('id' => $user->id));
                        } else {
                            $url = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $courseid));
                        }
                        $attributes = array('href' => $url, 'class' => 'd-inline-block aabtn');                
                        $this->content->text .= html_writer::tag('a', fullname($teacher), $attributes);
                    }
                    else {
                        $this->content->text .= fullname($teacher);
                    }

                    $this->content->text .= html_writer::end_tag('div');
                }
                // email get's a special mailto: link- @TODO this may need to leverage the logic around hiddenfields
                else if($userField == 'email') {
                    $this->content->text .= html_writer::start_tag('div', array('class' => 'email'));
                    $this->content->text .= html_writer::start_tag('a', array('href'  => new moodle_url('mailto:' . $teacher->email), 'title' => get_string('sendmessageto', 'core_message', fullname($teacher))));
                    $this->content->text .= $teacher->email;
                    $this->content->text .= html_writer::end_tag('a');
                    $this->content->text .= html_writer::end_tag('div');
                }
                // all other cases
                else {
                    $this->content->text .= html_writer::start_tag('div', array('class' => $userField));
                    $this->content->text .= $teacher->$userField;
                    $this->content->text .= html_writer::end_tag('div');
                }
            }

            // display profileFields - index all the data first for easy lookup
            $indexedTeacherProfileFields = [];
            $teacherProfileFields = profile_get_user_fields_with_data($teacher->id);
            foreach($teacherProfileFields as $teacherProfileField) {
                $indexedTeacherProfileFields["{$teacherProfileField->field->categoryid}-{$teacherProfileField->fieldid}"] = $teacherProfileField->data;
            }
            foreach($categorisedProfileFields as $categoryMeta) {
                $displayFields = $categoryMeta['fields'];
                // loop over all the possible fields in this category in correct order
                foreach($categoryMeta['currentFieldSort'] as $categoryField) {
                    // lookup to determine if this should be displayed
                    if(in_array($categoryField->shortname, $displayFields)) {
                        $key = "{$categoryMeta['category']->id}-{$categoryField->id}";
                        if(isset($indexedTeacherProfileFields[$key]) && strlen($indexedTeacherProfileFields[$key]) > 0) {
                            $this->content->text .= html_writer::start_tag('div', array('class' => $categoryField->shortname));
                            $this->content->text .= $indexedTeacherProfileFields[$key];
                            $this->content->text .= html_writer::end_tag('div');
                        }
                    }
                }
            }

            // display icons
            $this->content->text .= html_writer::start_tag('div', array('class' => 'icons'));

            foreach($iconFields as $iconField) {
                if($iconField == 'chat') {
                    if ($CFG->messaging && has_capability('moodle/site:sendmessage', $currentcontext) && $teacher->id != $USER->id && \core_message\api::can_send_message($teacher->id, $USER->id)) {
                        $this->content->text .= html_writer::start_tag('a', array('href'  => new moodle_url('/message/index.php', array('id' => $teacher->id)), 'title' => get_string('sendmessageto', 'core_message', fullname($teacher))));
                        $this->content->text .= $OUTPUT->pix_icon('i/rss', get_string('sendmessageto', 'core_message', fullname($teacher)), 'moodle');
                        $this->content->text .= html_writer::end_tag('a');
                    }
                }
                else if($iconField == 'email') {
                    if ($CFG->messaging && has_capability('moodle/site:sendmessage', $currentcontext) && $teacher->id != $USER->id && \core_message\api::can_send_message($teacher->id, $USER->id)) {
                        $this->content->text .= html_writer::start_tag('a', array('href'  => new moodle_url('mailto:' . $teacher->email), 'title' => get_string('sendmessageto', 'core_message', fullname($teacher))));
                        $this->content->text .= $OUTPUT->pix_icon('t/email', get_string('sendemailto', 'block_people', fullname($teacher)), 'moodle');
                        $this->content->text .= html_writer::end_tag('a');
                    }
                }
            }

            // end icons div
            $this->content->text .= html_writer::end_tag('div');

            // end details div
            $this->content->text .= html_writer::end_tag('div');

            // End output teacher.
            $this->content->text .= html_writer::end_tag('li');

        }

        // End role list if necessary.
        if ($teacherrole != null) {
            $this->content->text .= html_writer::end_tag('ul');
        }

        // End teachers list.
        $this->content->text .= html_writer::end_tag('div');

        // Output participants list if the setting linkparticipantspage is enabled.
        if ((get_config('block_people', 'linkparticipantspage')) != 0) {
            $this->content->text .= html_writer::start_tag('div', array('class' => 'participants'));
            $this->content->text .= html_writer::tag('h3', get_string('participants'));

            // Only if user is allow to see participants list.
            if (course_can_view_participants($currentcontext)) {
                $this->content->text .= html_writer::start_tag('a',
                    array('href'  => new moodle_url('/user/index.php', array('contextid' => $currentcontext->id)),
                          'title' => get_string('participants')));
                $this->content->text .= $OUTPUT->pix_icon('i/users',
                        get_string('participants', 'core'), 'moodle');
                $this->content->text .= get_string('participantslist', 'block_people');
                $this->content->text .= html_writer::end_tag('a');
            } else {
                $this->content->text .= html_writer::start_tag('span', array('class' => 'hint'));
                $this->content->text .= get_string('noparticipantslist', 'block_people');
                $this->content->text .= html_writer::end_tag('span');
            }

            $this->content->text .= html_writer::end_tag('div');
        }

        return $this->content;
    }

    /**
     * Return the plugin config settings for external functions.
     *
     * @return stdClass the configs for both the block instance and plugin
     * @since Moodle 3.8
     */
    public function get_config_for_external() {

        // Return all settings for all users since it is safe (no private keys, etc..).
        $instanceconfigs = !empty($this->config) ? $this->config : new stdClass();
        $pluginconfigs = get_config('block_people');

        return (object) [
                'instance' => $instanceconfigs,
                'plugin' => $pluginconfigs,
        ];
    }
}
