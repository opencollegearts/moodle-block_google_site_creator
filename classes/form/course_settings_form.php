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
 * Course-level settings: template selection and optional Course Google Group email.
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
 * Course-level settings form.
 *
 * @package   block_google_site_creator
 */
class course_settings_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];
        $existing = $custom['existing'] ?? null;

        $mform->addElement('hidden', 'courseid', $custom['courseid'] ?? 0);
        $mform->setType('courseid', PARAM_INT);

        $options = block_google_site_creator_get_template_options(true);
        $mform->addElement('select', 'template_id', get_string('coursetemplate', 'block_google_site_creator'), $options);
        $mform->setType('template_id', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('template_id', 'coursetemplate', 'block_google_site_creator');
        if ($existing && !empty($existing->template_id)
                && block_google_site_creator_is_allowed_template_id((string)$existing->template_id)) {
            $mform->setDefault('template_id', $existing->template_id);
        } else {
            $mform->setDefault('template_id', '');
        }

        $mform->addElement('text', 'unit_group_email', get_string('unitgroup', 'block_google_site_creator'), ['size' => 60]);
        $mform->setType('unit_group_email', PARAM_TEXT);
        $mform->addHelpButton('unit_group_email', 'unitgroup', 'block_google_site_creator');
        if ($existing && $existing->unit_group_email !== null) {
            $mform->setDefault('unit_group_email', $existing->unit_group_email);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Ensure the selected template is empty or in the admin allowlist.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $templateid = trim((string)($data['template_id'] ?? ''));
        if ($templateid !== '' && !block_google_site_creator_is_allowed_template_id($templateid)) {
            $errors['template_id'] = get_string('invalidtemplate', 'block_google_site_creator');
        }
        return $errors;
    }
}
