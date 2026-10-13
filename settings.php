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
 * Admin settings for block_google_site_creator.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/google_site_creator/lib.php');

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'block_google_site_creator/templatesheading',
        get_string('templatesheading', 'block_google_site_creator'),
        get_string('templatesheading_help', 'block_google_site_creator')
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_google_site_creator/templates',
        get_string('templates', 'block_google_site_creator'),
        get_string('templates_help', 'block_google_site_creator'),
        '',
        PARAM_RAW,
        8,
        60
    ));

    $templateoptions = ['' => get_string('choosedots')] + block_google_site_creator_get_templates();
    $settings->add(new admin_setting_configselect(
        'block_google_site_creator/defaulttemplate',
        get_string('defaulttemplate', 'block_google_site_creator'),
        get_string('defaulttemplate_help', 'block_google_site_creator'),
        '',
        $templateoptions
    ));

    $settings->add(new admin_setting_heading(
        'block_google_site_creator/credentialsheading',
        get_string('credentialsheading', 'block_google_site_creator'),
        get_string('credentialsheading_help', 'block_google_site_creator')
    ));

    $settings->add(new admin_setting_configtext(
        'block_google_site_creator/serviceaccountpath',
        get_string('serviceaccountpath', 'block_google_site_creator'),
        get_string('serviceaccountpath_help', 'block_google_site_creator'),
        '',
        PARAM_PATH
    ));

    // Encrypted at rest; value is never re-displayed in clear text on the settings page.
    $settings->add(new admin_setting_encryptedpassword(
        'block_google_site_creator/serviceaccountjson',
        get_string('serviceaccountjson', 'block_google_site_creator'),
        get_string('serviceaccountjson_help', 'block_google_site_creator')
    ));

    $settings->add(new admin_setting_configtext(
        'block_google_site_creator/delegateduser',
        get_string('delegateduser', 'block_google_site_creator'),
        get_string('delegateduser_help', 'block_google_site_creator'),
        '',
        PARAM_EMAIL
    ));

    $settings->add(new admin_setting_configtext(
        'block_google_site_creator/allowedemaildomains',
        get_string('allowedemaildomains', 'block_google_site_creator'),
        get_string('allowedemaildomains_help', 'block_google_site_creator'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'block_google_site_creator/sharegroups',
        get_string('sharegroups', 'block_google_site_creator'),
        get_string('sharegroups_help', 'block_google_site_creator')
    ));

    $settings->add(new admin_setting_configtext(
        'block_google_site_creator/tutorgroupemail',
        get_string('tutorgroupemail', 'block_google_site_creator'),
        get_string('tutorgroupemail_help', 'block_google_site_creator'),
        '',
        PARAM_EMAIL
    ));

    $settings->add(new admin_setting_configtext(
        'block_google_site_creator/organisationgroupemail',
        get_string('organisationgroupemail', 'block_google_site_creator'),
        get_string('organisationgroupemail_help', 'block_google_site_creator'),
        '',
        PARAM_EMAIL
    ));
}
