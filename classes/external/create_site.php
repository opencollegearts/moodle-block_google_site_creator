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
 * External function: create a Google Site on behalf of a user.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_google_site_creator\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/../../lib.php');
require_once(__DIR__ . '/../../classes/drive_service.php');

/**
 * External function: create a Google Site on behalf of a user.
 *
 * @package   block_google_site_creator
 */
class create_site extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'userid' => new \external_value(PARAM_INT, 'User ID (owner of the site)'),
            'title' => new \external_value(PARAM_TEXT, 'Site title'),
            'templateid' => new \external_value(
                PARAM_ALPHANUMEXT,
                'Template Drive file ID from the admin-managed catalogue (optional; overrides course/site default)',
                VALUE_OPTIONAL,
                null
            ),
            'visibility' => new \external_value(
                PARAM_ALPHANUMEXT,
                'Visibility: course_teachers, course_participants, tutor_group, course_group, organisation_group (legacy: tutors, unit_group, all_oca)',
                VALUE_DEFAULT,
                BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS
            ),
        ]);
    }

    public static function execute(int $courseid, int $userid, string $title, ?string $templateid, string $visibility): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'title' => $title,
            'templateid' => $templateid,
            'visibility' => $visibility,
        ]);
        $courseid = $params['courseid'];
        $userid = $params['userid'];
        $title = $params['title'];
        $templateid = $params['templateid'];
        $visibility = block_google_site_creator_normalise_visibility($params['visibility'], $courseid);

        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('block/google_site_creator:create_for_others', \context_system::instance());

        $owner = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        require_capability('block/google_site_creator:create', $context, $owner);

        $templateid = !empty(trim($templateid ?? '')) ? trim($templateid) : block_google_site_creator_get_template_id($courseid);
        if (empty($templateid) || !block_google_site_creator_is_allowed_template_id($templateid)) {
            throw new \moodle_exception('invalidtemplate', 'block_google_site_creator');
        }

        // Visibility options are evaluated for the target owner, not the calling service user.
        $allowed = array_keys(block_google_site_creator_get_visibility_options($courseid, (int)$owner->id));
        if (!in_array($visibility, $allowed, true)) {
            throw new \invalid_parameter_exception('visibility must be one of: ' . implode(', ', $allowed));
        }

        $targets = block_google_site_creator_resolve_share_targets($visibility, $courseid, $userid);
        if (empty($targets)) {
            throw new \moodle_exception('novisibilitytargets', 'block_google_site_creator');
        }

        $credentials = block_google_site_creator_get_service_account_json();
        if (empty($credentials)) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }

        $drive = new \block_google_site_creator\drive_service(
            $credentials,
            block_google_site_creator_get_delegated_user()
        );
        $foldername = $course->shortname;
        $folderid = $drive->ensure_folder_by_name($foldername);
        $copy = $drive->copy_file($templateid, $title, $folderid);
        $newfileid = $copy['id'] ?? null;
        $url = $copy['webViewLink'] ?? $copy['webContentLink'] ?? 'https://drive.google.com/file/d/' . $newfileid . '/view';
        if (empty($newfileid)) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }

        $shareresult = block_google_site_creator_sync_visibility_permission(
            $drive,
            $newfileid,
            $courseid,
            $visibility,
            $userid
        );

        $record = (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'title' => $title,
            'drive_file_id' => $newfileid,
            'url' => $url,
            'visibility' => $visibility,
            'managed_permissions' => json_encode(array_values($shareresult['permissionids'] ?? [])),
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $id = $DB->insert_record('block_google_site_creator_sites', $record);

        $site = $DB->get_record('block_google_site_creator_sites', ['id' => $id], '*', MUST_EXIST);
        return [
            'id' => (int) $site->id,
            'userid' => (int) $site->userid,
            'courseid' => (int) $site->courseid,
            'title' => $site->title,
            'drive_file_id' => $site->drive_file_id,
            'url' => $site->url,
            'visibility' => $site->visibility,
            'timecreated' => (int) $site->timecreated,
            'timemodified' => (int) $site->timemodified,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'Record ID'),
            'userid' => new \external_value(PARAM_INT, 'User ID'),
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'title' => new \external_value(PARAM_TEXT, 'Site title'),
            'drive_file_id' => new \external_value(PARAM_TEXT, 'Google Drive file ID'),
            'url' => new \external_value(PARAM_URL, 'Site URL'),
            'visibility' => new \external_value(PARAM_ALPHANUMEXT, 'Visibility'),
            'timecreated' => new \external_value(PARAM_INT, 'Time created'),
            'timemodified' => new \external_value(PARAM_INT, 'Time modified'),
        ]);
    }
}
