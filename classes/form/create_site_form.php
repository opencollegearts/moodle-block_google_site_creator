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
 * Form for creating a Google Site (title + visibility).
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_google_site_creator\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/../../lib.php');

/**
 * Form for creating a Google Site (title + visibility).
 *
 * @package   block_google_site_creator
 */
class create_site_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];
        $courseid = (int)($custom['courseid'] ?? 0);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('sitetitle', 'block_google_site_creator'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('title', 'sitetitle', 'block_google_site_creator');

        $mform->addElement('textarea', 'description', get_string('sitedescription', 'block_google_site_creator'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'sitedescription', 'block_google_site_creator');

        $options = block_google_site_creator_get_visibility_options($courseid);
        $mform->addElement('select', 'visibility', get_string('visibility', 'block_google_site_creator'), $options);
        $mform->setType('visibility', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('visibility', 'visibility', 'block_google_site_creator');
        $mform->setDefault('visibility', BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS);

        $mform->addElement('filemanager', 'banner', get_string('sitebanner', 'block_google_site_creator'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ]);
        $mform->addHelpButton('banner', 'sitebanner', 'block_google_site_creator');
        if (isset($custom['bannerdraftitemid'])) {
            $mform->setDefault('banner', (int)$custom['bannerdraftitemid']);
        }

        $this->add_action_buttons(true, get_string('save', 'block_google_site_creator'));
    }

    /**
     * Validate visibility is still available.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $courseid = (int)($data['courseid'] ?? 0);
        $visibility = (string)($data['visibility'] ?? '');
        if ($visibility === '' || !block_google_site_creator_is_visibility_available($visibility, $courseid)) {
            $errors['visibility'] = get_string('invalidvisibility', 'block_google_site_creator');
        }
        return $errors;
    }
}
