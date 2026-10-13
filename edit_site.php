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
 * Edit Google Site listing page.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$siteid = optional_param('siteid', 0, PARAM_INT);

global $DB, $USER;

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);

if ($siteid > 0) {
    $site = $DB->get_record('block_google_site_creator_sites', ['id' => $siteid, 'courseid' => $courseid], '*', MUST_EXIST);
} else {
    $site = $DB->get_record('block_google_site_creator_sites', ['userid' => $USER->id, 'courseid' => $courseid], '*', MUST_EXIST);
}

$canmanagecourse = has_capability('block/google_site_creator:managecourse', $context);
if ((int)$site->userid !== (int)$USER->id && !$canmanagecourse) {
    throw new required_capability_exception($context, 'block/google_site_creator:managecourse', 'nopermissions', '');
}

$PAGE->set_url(new moodle_url('/blocks/google_site_creator/edit_site.php', ['courseid' => $course->id, 'siteid' => $site->id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('editsitelisting', 'block_google_site_creator'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editsitelisting', 'block_google_site_creator'));

$bannerdraftitemid = file_get_submitted_draft_itemid('banner');
file_prepare_draft_area(
    $bannerdraftitemid,
    $context->id,
    'block_google_site_creator',
    'sitebanner',
    (int)$site->id,
    ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
);

$currentvisibility = block_google_site_creator_normalise_visibility((string)$site->visibility, (int)$course->id);

$form = new \block_google_site_creator\form\edit_site_form(
    new moodle_url('/blocks/google_site_creator/edit_site.php', ['courseid' => $course->id, 'siteid' => $site->id]),
    [
        'courseid' => $course->id,
        'siteid' => $site->id,
        'bannerdraftitemid' => $bannerdraftitemid,
        'currentvisibility' => $currentvisibility,
    ]
);
$form->set_data([
    'courseid' => $course->id,
    'siteid' => $site->id,
    'title' => $site->title,
    'description' => $site->description ?? '',
    'visibility' => $currentvisibility,
    'banner' => $bannerdraftitemid,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($data = $form->get_data()) {
    $visibility = block_google_site_creator_normalise_visibility((string)$data->visibility, (int)$course->id);
    if (!block_google_site_creator_is_visibility_available($visibility, (int)$course->id)) {
        \core\notification::error(get_string('invalidvisibility', 'block_google_site_creator'));
        redirect($PAGE->url);
    }

    $updated = (object)[
        'id' => $site->id,
        'title' => trim((string)$data->title),
        'description' => trim((string)($data->description ?? '')),
        'visibility' => $visibility,
        'timemodified' => time(),
    ];
    $DB->update_record('block_google_site_creator_sites', $updated);
    block_google_site_creator_save_site_banner($context, (int)$site->id, (int)$data->banner);

    $credentials = block_google_site_creator_get_service_account_json();
    if (!empty($credentials)) {
        try {
            $drive = new \block_google_site_creator\drive_service($credentials, block_google_site_creator_get_delegated_user());
            $drive->update_file_metadata(
                (string)$site->drive_file_id,
                [
                    'name' => $updated->title,
                    'description' => $updated->description,
                ]
            );
            $shareresult = block_google_site_creator_sync_visibility_permission(
                $drive,
                (string)$site->drive_file_id,
                (int)$course->id,
                (string)$updated->visibility,
                (int)$site->userid,
                $site
            );
            $DB->set_field(
                'block_google_site_creator_sites',
                'managed_permissions',
                json_encode(array_values($shareresult['permissionids'] ?? [])),
                ['id' => $site->id]
            );
            if (empty($shareresult['ok'])) {
                \core\notification::warning(get_string('createsuccesspartialshare', 'block_google_site_creator'));
            }
        } catch (Throwable $e) {
            block_google_site_creator_debug_log('Google Site update failed: ' . $e->getMessage());
            \core\notification::warning(get_string('editsitepartialsave', 'block_google_site_creator'));
        }
    }

    \core\notification::success(get_string('changessaved'));
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editsitelisting', 'block_google_site_creator'));
$form->display();
echo $OUTPUT->footer();
