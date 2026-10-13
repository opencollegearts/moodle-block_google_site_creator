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
 * Backup steps for the google_site_creator block.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete google_site_creator structure for backup.
 *
 * @package   block_google_site_creator
 */
class backup_google_site_creator_block_structure_step extends backup_block_structure_step {

    /**
     * Define the structure to be processed by this backup step.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $courseid = $this->get_courseid();
        $coursecontext = context_course::instance($courseid);

        $googlesitecreator = new backup_nested_element('google_site_creator', ['id'], ['coursecontextid']);

        $coursesettings = new backup_nested_element('course_settings', ['id'], [
            'template_id',
            'unit_group_email',
            'timemodified',
        ]);

        $sites = new backup_nested_element('sites');
        $site = new backup_nested_element('site', ['id'], [
            'userid',
            'title',
            'description',
            'drive_file_id',
            'url',
            'visibility',
            'timecreated',
            'timemodified',
        ]);

        $googlesitecreator->add_child($coursesettings);
        $googlesitecreator->add_child($sites);
        $sites->add_child($site);

        $googlesitecreator->set_source_array([(object)[
            'id' => $this->task->get_blockid(),
            'coursecontextid' => $coursecontext->id,
        ]]);

        $coursesettings->set_source_table('block_google_site_creator_course', [
            'courseid' => backup::VAR_COURSEID,
        ]);

        if ($userinfo) {
            $site->set_source_table('block_google_site_creator_sites', [
                'courseid' => backup::VAR_COURSEID,
            ]);
        }

        $site->annotate_ids('user', 'userid');

        // Banner images are stored in the course context, keyed by site id.
        $site->annotate_files(
            'block_google_site_creator',
            'sitebanner',
            'id',
            $coursecontext->id
        );

        return $this->prepare_block_structure($googlesitecreator);
    }
}
