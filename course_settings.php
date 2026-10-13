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
 * Course settings page for Google Site Creator.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('block/google_site_creator:managecourse', $context);

$PAGE->set_url(new moodle_url('/blocks/google_site_creator/course_settings.php', ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('coursesettings', 'block_google_site_creator'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('coursesettings', 'block_google_site_creator'));

global $DB;

$existing = $DB->get_record('block_google_site_creator_course', ['courseid' => $course->id]);

$form = new \block_google_site_creator\form\course_settings_form(null, [
    'courseid' => $course->id,
    'existing' => $existing,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($data = $form->get_data()) {
    $templateid = trim((string)($data->template_id ?? ''));
    if ($templateid !== '' && !block_google_site_creator_is_allowed_template_id($templateid)) {
        throw new moodle_exception('invalidtemplate', 'block_google_site_creator');
    }
    $record = (object)[
        'courseid' => $course->id,
        'template_id' => $templateid,
        'unit_group_email' => trim($data->unit_group_email ?? ''),
        'timemodified' => time(),
    ];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('block_google_site_creator_course', $record);
    } else {
        $DB->insert_record('block_google_site_creator_course', $record);
    }
    \core\notification::success(get_string('changessaved'));
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursesettings', 'block_google_site_creator'));
$form->display();
echo $OUTPUT->footer();
