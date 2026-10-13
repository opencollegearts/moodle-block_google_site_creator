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
 * Create a Google Site from a Drive template.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);

global $DB, $USER;

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('block/google_site_creator:create', $context);

$PAGE->set_url(new moodle_url('/blocks/google_site_creator/create.php', ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('creategooglesite', 'block_google_site_creator'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('creategooglesite', 'block_google_site_creator'));

$templateid = block_google_site_creator_get_template_id($course->id);
if (empty($templateid) || !block_google_site_creator_is_allowed_template_id($templateid)) {
    throw new moodle_exception('notemplate', 'block_google_site_creator');
}

$bannerdraftitemid = file_get_submitted_draft_itemid('banner');
file_prepare_draft_area(
    $bannerdraftitemid,
    $context->id,
    'block_google_site_creator',
    'sitebanner',
    0,
    ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
);

$form = new \block_google_site_creator\form\create_site_form(
    new moodle_url('/blocks/google_site_creator/create.php', ['courseid' => $course->id]),
    [
        'courseid' => $course->id,
        'bannerdraftitemid' => $bannerdraftitemid,
    ]
);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($data = $form->get_data()) {
    $visibility = block_google_site_creator_normalise_visibility((string)$data->visibility, $course->id);
    if (!block_google_site_creator_is_visibility_available($visibility, $course->id)) {
        \core\notification::error(get_string('invalidvisibility', 'block_google_site_creator'));
        redirect($PAGE->url);
    }

    $targets = block_google_site_creator_resolve_share_targets($visibility, $course->id, (int)$USER->id);
    if (empty($targets)) {
        if (in_array($visibility, [
            BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP,
            BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_GROUP,
            BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP,
        ], true)) {
            \core\notification::error(get_string('nocourseconfig', 'block_google_site_creator'));
        } else {
            \core\notification::error(get_string('novisibilitytargets', 'block_google_site_creator'));
        }
        redirect($PAGE->url);
    }

    $credentials = block_google_site_creator_get_service_account_json();
    if (empty($credentials)) {
        \core\notification::error(get_string('createfailed', 'block_google_site_creator'));
        redirect($PAGE->url);
    }

    try {
        $delegateduser = block_google_site_creator_get_delegated_user();
        $owneremail = trim((string)($USER->email ?? ''));
        $cancreateasuser = block_google_site_creator_can_transfer_to_user_email($owneremail);
        $foldername = $course->shortname;
        $ownershipfailed = false;
        $createdasuser = false;
        $newfileid = null;
        $url = null;
        $folderid = null;
        $drive = null;

        if ($cancreateasuser) {
            try {
                $drive = new \block_google_site_creator\drive_service($credentials, $owneremail);
                $folderid = $drive->ensure_folder_by_name($foldername);
                $copy = $drive->copy_file($templateid, $data->title, $folderid);
                $newfileid = $copy['id'] ?? null;
                $url = $copy['webViewLink'] ?? $copy['webContentLink'] ?? null;
                $createdasuser = !empty($newfileid);
            } catch (Throwable $usercopyerror) {
                // Must not call debugging() here: Whoops turns it into an exception and skips fallback.
                block_google_site_creator_debug_log(
                    'Creating site as end user failed, falling back to delegated user: '
                    . $usercopyerror->getMessage()
                );
            }
        }

        if (empty($newfileid)) {
            $drive = new \block_google_site_creator\drive_service($credentials, $delegateduser);
            $folderid = $drive->ensure_folder_by_name($foldername);
            $copy = $drive->copy_file($templateid, $data->title, $folderid);
            $newfileid = $copy['id'] ?? null;
            $url = $copy['webViewLink'] ?? $copy['webContentLink'] ?? null;

            if ($cancreateasuser && !empty($newfileid)) {
                try {
                    $drive->transfer_ownership($newfileid, $owneremail);
                } catch (Throwable $siteownershipe) {
                    $ownershipfailed = true;
                    block_google_site_creator_debug_log(
                        'Site ownership transfer failed: ' . $siteownershipe->getMessage()
                    );
                    try {
                        $drive->add_permission($newfileid, $owneremail, 'writer');
                    } catch (Throwable $addowneraseditore) {
                        block_google_site_creator_debug_log(
                            'Adding creator as editor failed: ' . $addowneraseditore->getMessage()
                        );
                    }
                }
            }
        }

        if (empty($newfileid) || empty($drive)) {
            throw new moodle_exception('createfailed', 'block_google_site_creator');
        }
        if (empty($url)) {
            $url = 'https://drive.google.com/file/d/' . $newfileid . '/view';
        }

        $shareresult = block_google_site_creator_sync_visibility_permission(
            $drive,
            $newfileid,
            (int)$course->id,
            $visibility,
            (int)$USER->id
        );
        $shareok = !empty($shareresult['ok']);

        if (!empty(trim((string)$data->description))) {
            $drive->update_file_metadata($newfileid, ['description' => trim((string)$data->description)]);
        }

        if (!empty($delegateduser) && strcasecmp($delegateduser, $owneremail) !== 0) {
            try {
                $drive->add_permission($newfileid, $delegateduser, 'writer');
                if (!$createdasuser && !empty($folderid)) {
                    $drive->add_permission($folderid, $delegateduser, 'writer');
                }
            } catch (Throwable $keepdelegatededitore) {
                block_google_site_creator_debug_log(
                    'Keeping delegated user as editor failed: ' . $keepdelegatededitore->getMessage()
                );
            }
        }

        $record = (object)[
            'userid' => $USER->id,
            'courseid' => $course->id,
            'title' => $data->title,
            'description' => trim((string)($data->description ?? '')),
            'drive_file_id' => $newfileid,
            'url' => $url,
            'visibility' => $visibility,
            'managed_permissions' => json_encode(array_values($shareresult['permissionids'] ?? [])),
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('block_google_site_creator_sites', $record);
        if (isset($data->banner)) {
            block_google_site_creator_save_site_banner($context, (int)$record->id, (int)$data->banner);
        }
        if ($ownershipfailed) {
            \core\notification::warning(get_string('createsuccessownershipwarning', 'block_google_site_creator'));
        } else if (!$shareok) {
            \core\notification::warning(get_string('createsuccesspartialshare', 'block_google_site_creator'));
        } else {
            \core\notification::success(get_string('createsuccess', 'block_google_site_creator'));
        }
    } catch (Throwable $e) {
        $debuginfo = ($e instanceof moodle_exception) ? (string)$e->debuginfo : '';
        block_google_site_creator_debug_log(
            'Google Site create failed: ' . $e->getMessage()
            . ($debuginfo !== '' ? ' | ' . $debuginfo : '')
        );
        $detail = $e->getMessage() . ' ' . $debuginfo;
        if (stripos($detail, 'notFound') !== false || stripos($detail, 'File not found') !== false) {
            \core\notification::error(get_string('templatenotaccessible', 'block_google_site_creator'));
        } else {
            \core\notification::error(get_string('createfailed', 'block_google_site_creator'));
        }
    }
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('creategooglesite', 'block_google_site_creator'));
$form->display();
echo $OUTPUT->footer();
