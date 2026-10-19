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
 * External function: get Google Site details.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_google_site_creator\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External function: get Google Site details by record id.
 *
 * @package   block_google_site_creator
 */
class get_site_details extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'siteid' => new \external_value(PARAM_INT, 'Moodle record ID of the Google Site'),
        ]);
    }

    public static function execute(int $siteid): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['siteid' => $siteid]);
        $siteid = $params['siteid'];

        $site = $DB->get_record('block_google_site_creator_sites', ['id' => $siteid], '*', MUST_EXIST);
        $context = \context_course::instance($site->courseid);
        self::validate_context($context);

        $isowner = ((int)$site->userid === (int)$USER->id);
        $canmanagecourse = has_capability('block/google_site_creator:managecourse', $context);
        $cancreateforothers = has_capability(
            'block/google_site_creator:create_for_others',
            \context_system::instance()
        );
        if (!$isowner && !$canmanagecourse && !$cancreateforothers) {
            throw new \moodle_exception('nopermissions', 'error');
        }

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
