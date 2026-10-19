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
 * Restore steps for the google_site_creator block.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one google_site_creator block.
 *
 * Course-scoped plugin tables are restored idempotently so multiple block
 * instances in the same course do not duplicate rows.
 *
 * @package   block_google_site_creator
 */
class restore_google_site_creator_block_structure_step extends restore_structure_step {

    /** @var int|null Old course context id from the backup (for banner files). */
    protected $oldcoursecontextid = null;

    /**
     * Define the structure to be processed by this restore step.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element(
            'google_site_creator',
            '/block/google_site_creator'
        );
        $paths[] = new restore_path_element(
            'google_site_creator_course_settings',
            '/block/google_site_creator/course_settings'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'google_site_creator_site',
                '/block/google_site_creator/sites/site'
            );
        }

        return $paths;
    }

    /**
     * Process the root element (captures old course context for files).
     *
     * @param array $data
     */
    protected function process_google_site_creator($data) {
        $data = (object)$data;
        if (!empty($data->coursecontextid)) {
            $this->oldcoursecontextid = (int)$data->coursecontextid;
        }
    }

    /**
     * Restore course-level template / Google Group settings.
     *
     * @param array $data
     */
    protected function process_google_site_creator_course_settings($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $courseid = $this->get_courseid();

        // Already restored by another block instance in this course.
        if ($DB->record_exists('block_google_site_creator_course', ['courseid' => $courseid])) {
            $existing = $DB->get_field(
                'block_google_site_creator_course',
                'id',
                ['courseid' => $courseid],
                MUST_EXIST
            );
            $this->set_mapping('google_site_creator_course_settings', $oldid, $existing);
            return;
        }

        $record = (object)[
            'courseid' => $courseid,
            'template_id' => $data->template_id ?? null,
            'unit_group_email' => $data->unit_group_email ?? null,
            'timemodified' => $data->timemodified ?? time(),
        ];
        $newitemid = $DB->insert_record('block_google_site_creator_course', $record);
        $this->set_mapping('google_site_creator_course_settings', $oldid, $newitemid);
    }

    /**
     * Restore a created Google Site listing record.
     *
     * @param array $data
     */
    protected function process_google_site_creator_site($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $courseid = $this->get_courseid();

        $userid = $this->get_mappingid('user', $data->userid, 0);
        if (!$userid) {
            // User was not included / could not be mapped.
            return;
        }

        // Avoid duplicates when the same course data is restored from multiple block instances.
        $existing = $DB->get_record('block_google_site_creator_sites', [
            'courseid' => $courseid,
            'userid' => $userid,
            'drive_file_id' => $data->drive_file_id,
        ]);
        if ($existing) {
            $this->set_mapping(
                'google_site_creator_site',
                $oldid,
                $existing->id,
                true,
                $this->oldcoursecontextid
            );
            return;
        }

        $record = (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'title' => $data->title,
            'description' => $data->description ?? null,
            'drive_file_id' => $data->drive_file_id,
            'url' => $data->url,
            'visibility' => $data->visibility,
            'managed_permissions' => $data->managed_permissions ?? null,
            'timecreated' => $data->timecreated ?? time(),
            'timemodified' => $data->timemodified ?? time(),
        ];
        $newitemid = $DB->insert_record('block_google_site_creator_sites', $record);

        // restorefiles=true; filesctxid = old course context (banners are not in block context).
        $this->set_mapping(
            'google_site_creator_site',
            $oldid,
            $newitemid,
            true,
            $this->oldcoursecontextid
        );
    }

    /**
     * Restore related banner files after all site records exist.
     */
    protected function after_execute() {
        if (empty($this->oldcoursecontextid)) {
            return;
        }

        $this->add_related_files(
            'block_google_site_creator',
            'sitebanner',
            'google_site_creator_site',
            $this->oldcoursecontextid
        );
    }
}
