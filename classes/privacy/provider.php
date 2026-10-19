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
 * Privacy provider for block_google_site_creator.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_google_site_creator\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_google_site_creator.
 *
 * @package   block_google_site_creator
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe stored and exported personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_google_site_creator_sites', [
            'userid' => 'privacy:metadata:block_google_site_creator_sites:userid',
            'courseid' => 'privacy:metadata:block_google_site_creator_sites:courseid',
            'title' => 'privacy:metadata:block_google_site_creator_sites:title',
            'description' => 'privacy:metadata:block_google_site_creator_sites:description',
            'drive_file_id' => 'privacy:metadata:block_google_site_creator_sites:drive_file_id',
            'url' => 'privacy:metadata:block_google_site_creator_sites:url',
            'visibility' => 'privacy:metadata:block_google_site_creator_sites:visibility',
            'managed_permissions' => 'privacy:metadata:block_google_site_creator_sites:managed_permissions',
            'timecreated' => 'privacy:metadata:block_google_site_creator_sites:timecreated',
            'timemodified' => 'privacy:metadata:block_google_site_creator_sites:timemodified',
        ], 'privacy:metadata:block_google_site_creator_sites');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:sitebanner');

        $collection->add_external_location_link('google', [
            'email' => 'privacy:metadata:external:google:email',
            'title' => 'privacy:metadata:external:google:title',
            'description' => 'privacy:metadata:external:google:description',
        ], 'privacy:metadata:external:google');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id FROM {context} c
                JOIN {block_google_site_creator_sites} s ON s.courseid = c.instanceid AND c.contextlevel = :courselevel
                WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $sql = "SELECT userid FROM {block_google_site_creator_sites} WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $sites = $DB->get_records('block_google_site_creator_sites', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
            if (empty($sites)) {
                continue;
            }
            $exportdata = [];
            foreach ($sites as $site) {
                $exportdata[] = (object)[
                    'title' => $site->title,
                    'description' => $site->description,
                    'url' => $site->url,
                    'drive_file_id' => $site->drive_file_id,
                    'visibility' => $site->visibility,
                    'managed_permissions' => $site->managed_permissions,
                    'timecreated' => \core_privacy\local\request\transform::datetime($site->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($site->timemodified),
                ];
                writer::with_context($context)->export_area_files(
                    [get_string('pluginname', 'block_google_site_creator'), $site->id],
                    'block_google_site_creator',
                    'sitebanner',
                    (int)$site->id
                );
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_google_site_creator')],
                (object)['sites' => $exportdata]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $sites = $DB->get_records('block_google_site_creator_sites', ['courseid' => $context->instanceid], '', 'id');
        $fs = get_file_storage();
        foreach ($sites as $site) {
            $fs->delete_area_files($context->id, 'block_google_site_creator', 'sitebanner', (int)$site->id);
        }
        $DB->delete_records('block_google_site_creator_sites', ['courseid' => $context->instanceid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $fs = get_file_storage();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $sites = $DB->get_records('block_google_site_creator_sites', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ], '', 'id');
            foreach ($sites as $site) {
                $fs->delete_area_files($context->id, 'block_google_site_creator', 'sitebanner', (int)$site->id);
            }
            $DB->delete_records('block_google_site_creator_sites', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $sites = $DB->get_records_select(
            'block_google_site_creator_sites',
            "courseid = :courseid AND userid $insql",
            $params,
            '',
            'id'
        );
        $fs = get_file_storage();
        foreach ($sites as $site) {
            $fs->delete_area_files($context->id, 'block_google_site_creator', 'sitebanner', (int)$site->id);
        }
        $DB->delete_records_select(
            'block_google_site_creator_sites',
            "courseid = :courseid AND userid $insql",
            $params
        );
    }
}
