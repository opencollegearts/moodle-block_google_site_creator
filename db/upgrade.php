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
 * Upgrade script for block_google_site_creator.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for block_google_site_creator.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_google_site_creator_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026031901) {
        $table = new xmldb_table('block_google_site_creator_sites');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'title');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026031901, 'google_site_creator');
    }

    if ($oldversion < 2026031904) {
        // Historical step: previously seeded institution-specific group defaults.
        // Kept for upgrade path continuity; values may be cleared in a later step.
        if (get_config('block_google_site_creator', 'tutorgroupemail') === false) {
            set_config('tutorgroupemail', '', 'block_google_site_creator');
        }
        if (get_config('block_google_site_creator', 'organisationgroupemail') === false) {
            set_config('organisationgroupemail', '', 'block_google_site_creator');
        }
        upgrade_block_savepoint(true, 2026031904, 'google_site_creator');
    }

    if ($oldversion < 2026032900) {
        // Migrate legacy visibility values to portable names.
        $tutorgroup = trim((string)(get_config('block_google_site_creator', 'tutorgroupemail') ?: ''));
        $tutorreplacement = $tutorgroup !== '' ? 'tutor_group' : 'course_teachers';

        $DB->set_field('block_google_site_creator_sites', 'visibility', $tutorreplacement, ['visibility' => 'tutors']);
        $DB->set_field('block_google_site_creator_sites', 'visibility', 'course_group', ['visibility' => 'unit_group']);
        $DB->set_field('block_google_site_creator_sites', 'visibility', 'organisation_group', ['visibility' => 'all_oca']);

        // Drop unused staff group setting if present.
        unset_config('staffgroupemail', 'block_google_site_creator');

        upgrade_block_savepoint(true, 2026032900, 'google_site_creator');
    }

    if ($oldversion < 2026032902) {
        // Refresh capability definitions (RISK_PERSONAL, archetypes without student).
        update_capabilities('block_google_site_creator');

        // Revoke legacy default grants of :create on the student role.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if ($studentroleid) {
            $DB->delete_records('role_capabilities', [
                'roleid' => $studentroleid,
                'capability' => 'block/google_site_creator:create',
            ]);
        }
        accesslib_clear_all_caches(true);

        upgrade_block_savepoint(true, 2026032902, 'google_site_creator');
    }

    if ($oldversion < 2026032904) {
        $table = new xmldb_table('block_google_site_creator_sites');
        $field = new xmldb_field('managed_permissions', XMLDB_TYPE_TEXT, null, null, null, null, null, 'visibility');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026032904, 'google_site_creator');
    }

    if ($oldversion < 2026032905) {
        // Seed the admin template catalogue from a legacy free-text defaulttemplate value.
        $templates = trim((string)(get_config('block_google_site_creator', 'templates') ?: ''));
        $default = trim((string)(get_config('block_google_site_creator', 'defaulttemplate') ?: ''));
        if ($templates === '' && $default !== '' && preg_match('/^[A-Za-z0-9_-]{10,128}$/', $default)) {
            set_config('templates', 'Default template|' . $default, 'block_google_site_creator');
        }
        upgrade_block_savepoint(true, 2026032905, 'google_site_creator');
    }

    if ($oldversion < 2026032906) {
        // Migrate legacy clear-text serviceaccountjson to path or encrypted storage.
        $legacy = get_config('block_google_site_creator', 'serviceaccountjson');
        if ($legacy !== false && trim((string)$legacy) !== '') {
            $legacy = trim((string)$legacy);
            if (str_starts_with($legacy, '{')) {
                if (!preg_match('/^(sodium|openssl-aes-256-ctr):/', $legacy)) {
                    try {
                        set_config(
                            'serviceaccountjson',
                            \core\encryption::encrypt($legacy),
                            'block_google_site_creator'
                        );
                    } catch (Throwable $e) {
                        // Leave value in place if encryption key is unavailable; admin can re-enter.
                        debugging('Could not encrypt legacy serviceaccountjson: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
            } else {
                // Legacy path stored in the JSON setting — move to dedicated path setting.
                if (get_config('block_google_site_creator', 'serviceaccountpath') === false
                        || trim((string)get_config('block_google_site_creator', 'serviceaccountpath')) === '') {
                    set_config('serviceaccountpath', $legacy, 'block_google_site_creator');
                }
                set_config('serviceaccountjson', '', 'block_google_site_creator');
            }
        }
        upgrade_block_savepoint(true, 2026032906, 'google_site_creator');
    }

    if ($oldversion < 2026032907) {
        // Restore student archetype for :create (block is empty/hidden without it).
        // Privacy mitigations remain: RISK_PERSONAL, course_participants for :managecourse only.
        update_capabilities('block_google_site_creator');
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if ($studentroleid) {
            assign_capability(
                'block/google_site_creator:create',
                CAP_ALLOW,
                $studentroleid,
                context_system::instance()->id,
                true
            );
        }
        accesslib_clear_all_caches(true);
        upgrade_block_savepoint(true, 2026032907, 'google_site_creator');
    }

    return true;
}
