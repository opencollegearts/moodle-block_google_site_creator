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
 * Language strings for block_google_site_creator.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Google Site Creator';
$string['google_site_creator:addinstance'] = 'Add a Google Site Creator block';
$string['google_site_creator:myaddinstance'] = 'Add a Google Site Creator block to Dashboard';
$string['google_site_creator:create'] = 'Create a Google Site';
$string['google_site_creator:managecourse'] = 'Manage course Google Site settings';
$string['google_site_creator:create_for_others'] = 'Create Google Site on behalf of another user (web service)';

$string['creategooglesite'] = 'Create a Google Site';
$string['mygooglesite'] = 'My Google Site';
$string['createsite'] = 'Create Google Site';
$string['sitetitle'] = 'Site title';
$string['sitetitle_help'] = 'The title for your new Google Site.';
$string['sitedescription'] = 'Site description';
$string['sitedescription_help'] = 'Optional description for this site listing and the Google Site file metadata.';
$string['sitebanner'] = 'Banner image';
$string['sitebanner_help'] = 'Optional banner image used in listing or discovery views.';
$string['visibility'] = 'Visibility';
$string['visibility_help'] = 'Who can access the Google Site. Prefer Google Group options when available: they share with one group address instead of listing individual Moodle emails in Google Drive. Moodle-based options fall back to individual user emails only when no matching Google Group is configured. "Course participants (Moodle)" is limited to users who can manage course Google Site settings, because Drive writers and owners can see recipient email addresses.';
$string['visibility_course_teachers'] = 'Course teachers (Moodle)';
$string['visibility_course_participants'] = 'Course participants (Moodle)';
$string['visibility_tutor_group'] = 'Tutor Google Group';
$string['visibility_course_group'] = 'Course Google Group';
$string['visibility_organisation_group'] = 'Organisation Google Group';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['coursesettings'] = 'Course settings';
$string['coursesettings_help'] = 'Choose a course template from the admin-managed catalogue and optionally set a Course Google Group email.';
$string['pluginsettings'] = 'Plugin settings';
$string['instancebuttonlabel'] = 'Create button label';
$string['instancebuttonlabel_help'] = 'Optional label for the "Create a Google Site" button in this block instance.';
$string['templatesheading'] = 'Google Site templates';
$string['templatesheading_help'] = 'Only templates listed here can be used by courses. Course managers select from this catalogue; free-text Drive IDs are not accepted.';
$string['templates'] = 'Template catalogue';
$string['templates_help'] = 'One template per line as Title|DriveFileId (for example "Portfolio template|1a2B3cD4eF5gH6"). Drive file IDs must match [A-Za-z0-9_-] and be 10–128 characters. Lines starting with # are ignored.';
$string['defaulttemplate'] = 'Default template';
$string['defaulttemplate_help'] = 'Site-level default template from the catalogue. Used when a course has not selected a specific template. Save the catalogue first if the list looks empty.';
$string['coursetemplate'] = 'Course template';
$string['coursetemplate_help'] = 'Optional. Choose a template from the site catalogue for this course. If set to use the site default, the admin default template is used.';
$string['coursetemplate_usedefault'] = 'Use site default template';
$string['unitgroup'] = 'Course Google Group';
$string['unitgroup_help'] = 'Optional. Google Group email address for this course (for example course-team@example.edu). Used only when visibility is set to "Course Google Group". Leave empty if your institution shares only via Moodle enrolments.';
$string['credentialsheading'] = 'Google service account credentials';
$string['credentialsheading_help'] = 'Prefer a JSON key file stored under Moodle dataroot (outside the web root). Optional inline JSON is encrypted at rest and is never shown again in clear text on this page.';
$string['serviceaccountpath'] = 'Service account JSON file path';
$string['serviceaccountpath_help'] = 'Preferred. Path to the service account JSON key file, relative to $CFG->dataroot (for example google/service-account.json). Absolute paths are allowed only when they resolve inside dataroot. When set, this is used instead of the encrypted JSON field.';
$string['serviceaccountjson'] = 'Service account JSON (encrypted)';
$string['serviceaccountjson_help'] = 'Optional alternative to a file path. Paste the full service account JSON once; Moodle stores it encrypted and does not redisplay the secret. Leave unchanged if already set. Clear the value to remove it. A configured file path takes precedence over this field.';
$string['delegateduser'] = 'Delegated Workspace user';
$string['delegateduser_help'] = 'Optional. User email to impersonate via Google Workspace domain-wide delegation. Leave empty to act as the service account directly.';
$string['allowedemaildomains'] = 'Allowed email domains for ownership';
$string['allowedemaildomains_help'] = 'Optional comma-separated list of email domains (for example example.edu) eligible for automatic Google Site ownership transfer. Leave empty to allow any valid user email.';
$string['sharegroups'] = 'Optional Google Group sharing';
$string['sharegroups_help'] = 'These settings are optional. They enable Google Groups-based visibility options in addition to Moodle enrolment-based sharing. Institutions that do not use Google Groups can leave them empty.';
$string['tutorgroupemail'] = 'Tutor Google Group email';
$string['tutorgroupemail_help'] = 'Optional Google Group shared when visibility is "Tutor Google Group". Leave empty to hide that visibility option.';
$string['organisationgroupemail'] = 'Organisation Google Group email';
$string['organisationgroupemail_help'] = 'Optional Google Group shared when visibility is "Organisation Google Group". Leave empty to hide that visibility option.';
$string['sitescreated'] = 'Google Sites created';
$string['viewsite'] = 'View site';
$string['createdon'] = 'Created on';
$string['nocourseconfig'] = 'The selected Google Group visibility option is not available. Configure the Course Google Group in course settings, or choose a Moodle-based visibility option.';
$string['novisibilitytargets'] = 'No share recipients were found for the selected visibility. Check enrolments or Google Group settings.';
$string['notemplate'] = 'No Google Site template is configured. Ask an administrator to add templates in the Google Site Creator plugin settings.';
$string['invalidtemplate'] = 'The selected template is not in the admin-managed catalogue or has an invalid Drive file ID.';
$string['invaliddriveid'] = 'Invalid Google Drive file ID.';
$string['createfailed'] = 'Failed to create Google Site. Please try again or contact support.';
$string['error'] = 'An error occurred in Google Site Creator.';
$string['templatenotaccessible'] = 'The Google Site template could not be found or is not shared with the service account / delegated Workspace user. Ask an administrator to share the template Drive file with that account (viewer is enough) and check the catalogue Drive file ID.';
$string['createsuccess'] = 'Google Site created successfully.';
$string['createsuccessownershipwarning'] = 'Google Site created successfully, but ownership could not be transferred to you automatically. You should still have editor access.';
$string['createsuccesspartialshare'] = 'Google Site created, but some Drive sharing permissions could not be applied.';
$string['editsitelisting'] = 'Edit site listing';
$string['editsitepartialsave'] = 'Saved in Moodle, but one or more Google Site updates could not be applied automatically.';
$string['invalidvisibility'] = 'The selected visibility option is not available.';

$string['privacy:metadata:block_google_site_creator_sites'] = 'Stores created Google Site records linked to Moodle users and courses.';
$string['privacy:metadata:block_google_site_creator_sites:userid'] = 'The user who created the site.';
$string['privacy:metadata:block_google_site_creator_sites:courseid'] = 'The course the site belongs to.';
$string['privacy:metadata:block_google_site_creator_sites:title'] = 'The site title.';
$string['privacy:metadata:block_google_site_creator_sites:description'] = 'The optional site description.';
$string['privacy:metadata:block_google_site_creator_sites:drive_file_id'] = 'The Google Drive file ID of the created site.';
$string['privacy:metadata:block_google_site_creator_sites:url'] = 'The Google Site URL.';
$string['privacy:metadata:block_google_site_creator_sites:visibility'] = 'The chosen sharing/visibility mode.';
$string['privacy:metadata:block_google_site_creator_sites:managed_permissions'] = 'JSON list of Google Drive permission IDs created by this plugin for visibility sharing.';
$string['privacy:metadata:block_google_site_creator_sites:timecreated'] = 'When the site record was created.';
$string['privacy:metadata:block_google_site_creator_sites:timemodified'] = 'When the site record was last modified.';
$string['privacy:metadata:sitebanner'] = 'Optional banner images uploaded for site listings.';
$string['privacy:metadata:external:google'] = 'In order to create and share Google Sites, this plugin sends site titles, descriptions, and recipient email addresses to Google Drive / Google Sites APIs.';
$string['privacy:metadata:external:google:email'] = 'User or Google Group email addresses used when granting Drive permissions.';
$string['privacy:metadata:external:google:title'] = 'The site title sent to Google Drive.';
$string['privacy:metadata:external:google:description'] = 'The optional site description sent to Google Drive.';
