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
 * Library functions for block_google_site_creator.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Visibility: share with course teachers via Moodle enrolment emails.
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS = 'course_teachers';

/**
 * Visibility: share with all course participants via Moodle enrolment emails.
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_PARTICIPANTS = 'course_participants';

/**
 * Visibility: share with configured tutor Google Group.
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP = 'tutor_group';

/**
 * Visibility: share with course-level Google Group.
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_GROUP = 'course_group';

/**
 * Visibility: share with organisation-wide Google Group.
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP = 'organisation_group';

/**
 * Legacy visibility values kept for migration/aliases.
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_LEGACY_TUTORS = 'tutors';
const BLOCK_GOOGLE_SITE_CREATOR_VIS_LEGACY_UNIT_GROUP = 'unit_group';
const BLOCK_GOOGLE_SITE_CREATOR_VIS_LEGACY_ALL_OCA = 'all_oca';

/**
 * Strict Google Drive file ID pattern (rejects path injection characters).
 */
const BLOCK_GOOGLE_SITE_CREATOR_DRIVE_ID_REGEX = '/^[A-Za-z0-9_-]{10,128}$/';

/**
 * Whether a string is a safe Google Drive file ID.
 *
 * @param string $fileid
 * @return bool
 */
function block_google_site_creator_is_valid_drive_file_id(string $fileid): bool {
    return (bool)preg_match(BLOCK_GOOGLE_SITE_CREATOR_DRIVE_ID_REGEX, trim($fileid));
}

/**
 * Parse the admin-managed template catalogue.
 *
 * Config format (one template per line): Title|DriveFileId
 *
 * @return array<string,string> fileid => title
 */
function block_google_site_creator_get_templates(): array {
    $raw = get_config('block_google_site_creator', 'templates');
    if ($raw === false || trim((string)$raw) === '') {
        return [];
    }
    $templates = [];
    foreach (preg_split("/\r\n|\n|\r/", (string)$raw) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('|', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $title = trim($parts[0]);
        $fileid = trim($parts[1]);
        if ($title === '' || !block_google_site_creator_is_valid_drive_file_id($fileid)) {
            continue;
        }
        $templates[$fileid] = $title;
    }
    return $templates;
}

/**
 * Whether a template ID is in the admin-managed allowlist.
 *
 * @param string $fileid
 * @return bool
 */
function block_google_site_creator_is_allowed_template_id(string $fileid): bool {
    $fileid = trim($fileid);
    if ($fileid === '' || !block_google_site_creator_is_valid_drive_file_id($fileid)) {
        return false;
    }
    return array_key_exists($fileid, block_google_site_creator_get_templates());
}

/**
 * Options for course template select menus.
 *
 * @param bool $includeempty Include "use site default" empty option
 * @return array<string,string> value => label
 */
function block_google_site_creator_get_template_options(bool $includeempty = true): array {
    $options = [];
    if ($includeempty) {
        $options[''] = get_string('coursetemplate_usedefault', 'block_google_site_creator');
    }
    foreach (block_google_site_creator_get_templates() as $fileid => $title) {
        $options[$fileid] = $title;
    }
    return $options;
}

/**
 * Get the Google Site template ID for a course (course-level or site default).
 *
 * Only admin-allowlisted template IDs are returned.
 *
 * @param int $courseid
 * @return string|null Template ID or null if not configured / not allowed
 */
function block_google_site_creator_get_template_id(int $courseid): ?string {
    global $DB;
    $course = $DB->get_record('block_google_site_creator_course', ['courseid' => $courseid], 'template_id');
    if ($course && !empty(trim($course->template_id ?? ''))) {
        $courseidvalue = trim($course->template_id);
        if (block_google_site_creator_is_allowed_template_id($courseidvalue)) {
            return $courseidvalue;
        }
    }
    $default = get_config('block_google_site_creator', 'defaulttemplate');
    if ($default !== false && trim((string)$default) !== '') {
        $default = trim((string)$default);
        if (block_google_site_creator_is_allowed_template_id($default)) {
            return $default;
        }
    }
    // Fall back to the first catalogue entry when no explicit default is set.
    $templates = block_google_site_creator_get_templates();
    if ($templates) {
        return (string)array_key_first($templates);
    }
    return null;
}

/**
 * Get the course Google Group email (optional).
 *
 * @param int $courseid
 * @return string|null
 */
function block_google_site_creator_get_unit_group(int $courseid): ?string {
    global $DB;
    $course = $DB->get_record('block_google_site_creator_course', ['courseid' => $courseid], 'unit_group_email');
    if ($course && !empty(trim($course->unit_group_email ?? ''))) {
        return trim($course->unit_group_email);
    }
    return null;
}

/**
 * Get the configured tutor Google Group email, or null if not set.
 *
 * @return string|null
 */
function block_google_site_creator_get_tutor_group_email(): ?string {
    $email = get_config('block_google_site_creator', 'tutorgroupemail');
    if ($email === false) {
        return null;
    }
    $email = trim((string)$email);
    return $email !== '' ? $email : null;
}

/**
 * Get the configured organisation-wide Google Group email, or null if not set.
 *
 * @return string|null
 */
function block_google_site_creator_get_organisation_group_email(): ?string {
    $email = get_config('block_google_site_creator', 'organisationgroupemail');
    if ($email === false) {
        return null;
    }
    $email = trim((string)$email);
    return $email !== '' ? $email : null;
}

/**
 * Normalise a legacy or current visibility value to a current constant.
 *
 * @param string $visibility
 * @param int $courseid Used when mapping legacy tutors with no tutor group configured
 * @return string
 */
function block_google_site_creator_normalise_visibility(string $visibility, int $courseid = 0): string {
    switch ($visibility) {
        case BLOCK_GOOGLE_SITE_CREATOR_VIS_LEGACY_TUTORS:
            return block_google_site_creator_get_tutor_group_email()
                ? BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP
                : BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS;
        case BLOCK_GOOGLE_SITE_CREATOR_VIS_LEGACY_UNIT_GROUP:
            return BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_GROUP;
        case BLOCK_GOOGLE_SITE_CREATOR_VIS_LEGACY_ALL_OCA:
            return BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP;
        default:
            return $visibility;
    }
}

/**
 * Whether a visibility option is currently available for a course and user.
 *
 * @param string $visibility
 * @param int $courseid
 * @param int|null $userid Defaults to the current user
 * @return bool
 */
function block_google_site_creator_is_visibility_available(
    string $visibility,
    int $courseid,
    ?int $userid = null
): bool {
    $visibility = block_google_site_creator_normalise_visibility($visibility, $courseid);
    $options = block_google_site_creator_get_visibility_options($courseid, $userid);
    return array_key_exists($visibility, $options);
}

/**
 * Build visibility select options for create/edit forms.
 *
 * Moodle enrolment-based teacher sharing is generally available. "Course participants"
 * is limited to users with managecourse (privacy). Google Group options appear only
 * when the corresponding group email is configured (preferred over individual emails).
 *
 * @param int $courseid
 * @param int|null $userid Defaults to the current user
 * @return array<string,string> value => label
 */
function block_google_site_creator_get_visibility_options(int $courseid, ?int $userid = null): array {
    global $USER;

    $userid = $userid ?? (int)$USER->id;
    $context = context_course::instance($courseid);

    $options = [
        BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS =>
            get_string('visibility_course_teachers', 'block_google_site_creator'),
    ];

    // Sharing with every participant exposes all course emails in Drive's sharing UI.
    if (has_capability('block/google_site_creator:managecourse', $context, $userid)) {
        $options[BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_PARTICIPANTS] =
            get_string('visibility_course_participants', 'block_google_site_creator');
    }

    if (block_google_site_creator_get_tutor_group_email()) {
        $options[BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP] =
            get_string('visibility_tutor_group', 'block_google_site_creator');
    }
    if (block_google_site_creator_get_unit_group($courseid)) {
        $options[BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_GROUP] =
            get_string('visibility_course_group', 'block_google_site_creator');
    }
    if (block_google_site_creator_get_organisation_group_email()) {
        $options[BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP] =
            get_string('visibility_organisation_group', 'block_google_site_creator');
    }

    return $options;
}

/**
 * Collect emails from enrolled users, optionally limited by capability.
 *
 * @param int $courseid
 * @param string $capability Empty string for all participants
 * @param int|null $excludeuserid Optional user id to skip (e.g. site creator)
 * @return string[] Lowercased unique emails
 */
function block_google_site_creator_get_enrolment_emails(
    int $courseid,
    string $capability = '',
    ?int $excludeuserid = null
): array {
    $context = context_course::instance($courseid);
    $users = get_enrolled_users($context, $capability, 0, 'u.id, u.email', null, 0, 0, true);
    $emails = [];
    foreach ($users as $user) {
        if ($excludeuserid !== null && (int)$user->id === $excludeuserid) {
            continue;
        }
        $email = trim((string)($user->email ?? ''));
        if ($email === '' || !validate_email($email)) {
            continue;
        }
        $emails[strtolower($email)] = $email;
    }
    return array_values($emails);
}

/**
 * Resolve Drive share targets for a visibility setting.
 *
 * @param string $visibility
 * @param int $courseid
 * @param int|null $excludeuserid Skip this user for Moodle-based shares
 * @return array<int,array{email:string,type:string,role:string}>
 */
function block_google_site_creator_resolve_share_targets(
    string $visibility,
    int $courseid,
    ?int $excludeuserid = null
): array {
    $visibility = block_google_site_creator_normalise_visibility($visibility, $courseid);
    $targets = [];

    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS) {
        // Prefer a Google Group when configured so individual teacher emails are not listed in Drive.
        $tutorgroup = block_google_site_creator_get_tutor_group_email();
        if ($tutorgroup) {
            return [['email' => $tutorgroup, 'type' => 'group', 'role' => 'writer']];
        }
        foreach (block_google_site_creator_get_enrolment_emails(
            $courseid,
            'moodle/course:manageactivities',
            $excludeuserid
        ) as $email) {
            $targets[] = ['email' => $email, 'type' => 'user', 'role' => 'writer'];
        }
        return $targets;
    }

    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_PARTICIPANTS) {
        // Prefer the course Google Group when configured (avoids listing every participant email).
        $coursegroup = block_google_site_creator_get_unit_group($courseid);
        if ($coursegroup) {
            return [['email' => $coursegroup, 'type' => 'group', 'role' => 'writer']];
        }
        foreach (block_google_site_creator_get_enrolment_emails($courseid, '', $excludeuserid) as $email) {
            $targets[] = ['email' => $email, 'type' => 'user', 'role' => 'writer'];
        }
        return $targets;
    }

    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP) {
        $email = block_google_site_creator_get_tutor_group_email();
        if ($email) {
            $targets[] = ['email' => $email, 'type' => 'group', 'role' => 'writer'];
        }
        return $targets;
    }

    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_GROUP) {
        $email = block_google_site_creator_get_unit_group($courseid);
        if ($email) {
            $targets[] = ['email' => $email, 'type' => 'group', 'role' => 'writer'];
        }
        return $targets;
    }

    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP) {
        $email = block_google_site_creator_get_organisation_group_email();
        if ($email) {
            $targets[] = ['email' => $email, 'type' => 'group', 'role' => 'reader'];
        }
        return $targets;
    }

    return $targets;
}

/**
 * Emails that must never be revoked by visibility sync (creator / delegated admin).
 *
 * @param int|null $preserveuserid Site creator / owner user id
 * @return array<string,true> Lowercased email => true
 */
function block_google_site_creator_get_preserved_share_emails(?int $preserveuserid = null): array {
    $preserve = [];
    if ($preserveuserid) {
        $user = \core_user::get_user($preserveuserid, 'id, email', IGNORE_MISSING);
        $email = strtolower(trim((string)($user->email ?? '')));
        if ($email !== '') {
            $preserve[$email] = true;
        }
    }
    $delegated = block_google_site_creator_get_delegated_user();
    if ($delegated) {
        $preserve[strtolower(trim($delegated))] = true;
    }
    return $preserve;
}

/**
 * Emails this plugin considers "managed" when syncing visibility (safe to remove).
 *
 * @param int $courseid
 * @param array<string,true> $preserveemails Lowercased emails that must not be revoked
 * @return array<string,true> Lowercased email => true
 */
function block_google_site_creator_get_managed_share_emails(int $courseid, array $preserveemails = []): array {
    $known = [];
    foreach ([
        block_google_site_creator_get_tutor_group_email(),
        block_google_site_creator_get_organisation_group_email(),
        block_google_site_creator_get_unit_group($courseid),
    ] as $email) {
        if ($email) {
            $known[strtolower($email)] = true;
        }
    }
    // Include all course participant emails so Moodle-based shares can be revoked on change.
    foreach (block_google_site_creator_get_enrolment_emails($courseid) as $email) {
        $known[strtolower($email)] = true;
    }
    foreach ($preserveemails as $email => $unused) {
        unset($known[strtolower((string)$email)]);
    }
    return $known;
}

/**
 * Decode stored Drive permission ids created for a site's visibility shares.
 *
 * @param stdClass|null $site
 * @return string[]
 */
function block_google_site_creator_get_stored_managed_permission_ids(?stdClass $site): array {
    if (empty($site) || empty($site->managed_permissions)) {
        return [];
    }
    $decoded = json_decode((string)$site->managed_permissions, true);
    if (!is_array($decoded)) {
        return [];
    }
    $ids = [];
    foreach ($decoded as $id) {
        $id = trim((string)$id);
        if ($id !== '') {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

/**
 * Ensure a user still has writer access without treating that grant as a visibility target.
 *
 * @param \block_google_site_creator\drive_service $drive
 * @param string $fileid
 * @param string $email
 * @return void
 */
function block_google_site_creator_ensure_writer_permission(
    \block_google_site_creator\drive_service $drive,
    string $fileid,
    string $email
): void {
    $email = trim($email);
    if ($email === '' || !validate_email($email)) {
        return;
    }
    if ($drive->find_permission_id_for_email($fileid, $email) !== null) {
        return;
    }
    try {
        $drive->add_permission($fileid, $email, 'writer', 'user');
    } catch (Throwable $e) {
        block_google_site_creator_debug_log(
            'Failed to ensure writer permission for ' . $email . ': ' . $e->getMessage()
        );
    }
}

/**
 * Get all Google Sites with organisation-group visibility (optional discovery hook).
 *
 * @param int $limit
 * @return stdClass[]
 */
function block_google_site_creator_get_sites_for_index(int $limit = 100): array {
    global $DB;
    return $DB->get_records(
        'block_google_site_creator_sites',
        ['visibility' => BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP],
        'timecreated DESC',
        '*',
        0,
        $limit
    );
}

/**
 * Build card objects for Google Sites for use with discovery/listing templates.
 *
 * @param stdClass[] $sites
 * @return stdClass[]
 */
function block_google_site_creator_build_cards_for_grid(array $sites): array {
    global $OUTPUT;
    $cards = [];
    foreach ($sites as $site) {
        $user = \core_user::get_user($site->userid, '*', MUST_EXIST);
        $card = new stdClass();
        $card->id = $site->id;
        $card->title = format_string($site->title);
        $card->summary = !empty($site->description)
            ? shorten_text(strip_tags((string)$site->description), 200)
            : get_string('pluginname', 'block_google_site_creator');
        $card->date = userdate($site->timecreated);
        $card->url = $site->url;
        $card->userpicture = $OUTPUT->user_picture($user, ['size' => 48, 'link' => false]);
        $card->fullname = fullname($user);
        $card->status = get_string('pluginname', 'block_google_site_creator');
        $card->bannerurl = block_google_site_creator_get_site_banner_url($site);
        $card->sorttime = $site->timecreated;
        $cards[] = $card;
    }
    return $cards;
}

/**
 * Log a developer message without calling debugging().
 *
 * Whoops (debugdisplay) turns debugging() notices into exceptions, which aborts
 * catch-and-continue flows such as create-as-user → delegated fallback.
 *
 * @param string $message
 */
function block_google_site_creator_debug_log(string $message): void {
    error_log('block_google_site_creator: ' . $message);
}

/**
 * Resolve a configured service-account key file path under dataroot.
 *
 * @param string $configured Path relative to dataroot, or absolute under dataroot
 * @return string|null Absolute readable path, or null if invalid / outside dataroot
 */
function block_google_site_creator_resolve_service_account_path(string $configured): ?string {
    global $CFG;

    $configured = trim($configured);
    if ($configured === '') {
        return null;
    }

    $dataroot = realpath($CFG->dataroot);
    if ($dataroot === false) {
        return null;
    }

    if (str_starts_with($configured, '/')) {
        $candidate = $configured;
    } else {
        $candidate = $CFG->dataroot . '/' . ltrim($configured, '/');
    }

    $resolved = realpath($candidate);
    if ($resolved === false || !is_readable($resolved) || !is_file($resolved)) {
        return null;
    }

    // Only allow files inside Moodle dataroot (typically outside the web root).
    $datarootprefix = rtrim($dataroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($resolved . DIRECTORY_SEPARATOR, $datarootprefix)
            && $resolved !== $dataroot) {
        return null;
    }

    return $resolved;
}

/**
 * Decode service-account JSON from path (preferred) or encrypted admin setting.
 *
 * @return array|null
 */
function block_google_site_creator_get_service_account_json(): ?array {
    $pathconfig = get_config('block_google_site_creator', 'serviceaccountpath');
    if ($pathconfig !== false && trim((string)$pathconfig) !== '') {
        $path = block_google_site_creator_resolve_service_account_path((string)$pathconfig);
        if ($path === null) {
            return null;
        }
        $content = file_get_contents($path);
        $decoded = json_decode((string)$content, true);
        return is_array($decoded) ? $decoded : null;
    }

    $stored = get_config('block_google_site_creator', 'serviceaccountjson');
    if ($stored === false || trim((string)$stored) === '') {
        return null;
    }
    $stored = trim((string)$stored);

    // Encrypted values use Moodle's encryption envelope (e.g. sodium:...).
    if (preg_match('/^(sodium|openssl-aes-256-ctr):/', $stored)) {
        try {
            $stored = \core\encryption::decrypt($stored);
        } catch (Throwable $e) {
            block_google_site_creator_debug_log('Failed to decrypt service account JSON: ' . $e->getMessage());
            return null;
        }
    }

    $stored = trim($stored);
    if ($stored === '' || !str_starts_with($stored, '{')) {
        return null;
    }
    $decoded = json_decode($stored, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Get delegated Workspace user email (domain-wide delegation subject), if configured.
 *
 * @return string|null
 */
function block_google_site_creator_get_delegated_user(): ?string {
    $email = get_config('block_google_site_creator', 'delegateduser');
    if ($email === false) {
        return null;
    }
    $email = trim((string)$email);
    return $email !== '' ? $email : null;
}

/**
 * Check whether a Moodle user email is eligible for ownership transfer.
 *
 * Empty allowedemaildomains means any non-empty valid email is eligible.
 *
 * @param string|null $email
 * @return bool
 */
function block_google_site_creator_can_transfer_to_user_email(?string $email): bool {
    $email = trim((string)$email);
    if ($email === '' || !validate_email($email)) {
        return false;
    }
    $domains = get_config('block_google_site_creator', 'allowedemaildomains');
    if ($domains === false || trim((string)$domains) === '') {
        return true;
    }
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return false;
    }
    $userdomain = strtolower($parts[1]);
    foreach (preg_split('/\s*,\s*/', (string)$domains) as $domain) {
        $domain = strtolower(trim($domain));
        if ($domain !== '' && $domain === $userdomain) {
            return true;
        }
    }
    return false;
}

/**
 * Get a user's created Google Site for a course.
 *
 * @param int $userid
 * @param int $courseid
 * @return stdClass|null
 */
function block_google_site_creator_get_user_site_for_course(int $userid, int $courseid): ?stdClass {
    global $DB;
    $record = $DB->get_record(
        'block_google_site_creator_sites',
        ['userid' => $userid, 'courseid' => $courseid],
        '*',
        IGNORE_MULTIPLE
    );
    return $record ?: null;
}

/**
 * Apply visibility share targets, removing previously managed permissions first.
 *
 * Creator and delegated-user emails are never revoked. When a site record is
 * provided, only previously stored plugin-created permission ids are deleted;
 * otherwise a legacy email-based managed set is used (minus preserved emails).
 *
 * @param \block_google_site_creator\drive_service $drive
 * @param string $fileid
 * @param int $courseid
 * @param string $visibility
 * @param int|null $excludeuserid Site creator user id (also preserved from revoke)
 * @param stdClass|null $site Existing site record (for stored permission ids)
 * @return array{ok:bool,permissionids:string[]}
 */
function block_google_site_creator_sync_visibility_permission(
    \block_google_site_creator\drive_service $drive,
    string $fileid,
    int $courseid,
    string $visibility,
    ?int $excludeuserid = null,
    ?stdClass $site = null
): array {
    $preserveemails = block_google_site_creator_get_preserved_share_emails($excludeuserid);
    $storedids = block_google_site_creator_get_stored_managed_permission_ids($site);
    $storedidmap = array_fill_keys($storedids, true);
    $knownemails = block_google_site_creator_get_managed_share_emails($courseid, $preserveemails);

    $permissions = $drive->list_permissions($fileid);
    foreach ($permissions as $permission) {
        $email = strtolower(trim((string)($permission['emailAddress'] ?? '')));
        $permissionid = (string)($permission['id'] ?? '');
        if ($permissionid === '') {
            continue;
        }
        if ($email !== '' && isset($preserveemails[$email])) {
            continue;
        }
        $shoulddelete = false;
        if ($storedidmap) {
            $shoulddelete = isset($storedidmap[$permissionid]);
        } else if ($email !== '' && isset($knownemails[$email])) {
            $shoulddelete = true;
        }
        if (!$shoulddelete) {
            continue;
        }
        try {
            $drive->delete_permission($fileid, $permissionid);
        } catch (Throwable $e) {
            block_google_site_creator_debug_log('Failed to delete Drive permission: ' . $e->getMessage());
        }
    }

    $targets = block_google_site_creator_resolve_share_targets($visibility, $courseid, $excludeuserid);
    $allok = true;
    $newpermissionids = [];
    foreach ($targets as $target) {
        $targetemail = strtolower(trim($target['email']));
        if ($targetemail !== '' && isset($preserveemails[$targetemail])) {
            // Creator/delegated access is handled separately; do not track as visibility ACL.
            continue;
        }
        try {
            $permissionid = $drive->add_permission(
                $fileid,
                $target['email'],
                $target['role'],
                $target['type']
            );
            if (!empty($permissionid)) {
                $newpermissionids[] = $permissionid;
            }
        } catch (Throwable $e) {
            $allok = false;
            block_google_site_creator_debug_log(
                'Failed to add Drive permission for ' . $target['email'] . ': ' . $e->getMessage()
            );
        }
    }

    // Keep the site creator able to open/edit even when ownership transfer failed
    // and they were excluded from visibility targets.
    foreach (array_keys($preserveemails) as $preserveemail) {
        block_google_site_creator_ensure_writer_permission($drive, $fileid, $preserveemail);
    }

    return [
        'ok' => $allok,
        'permissionids' => $newpermissionids,
    ];
}

/**
 * Save site banner image from draft area.
 *
 * @param context_course $context
 * @param int $siteid
 * @param int $draftitemid
 * @return void
 */
function block_google_site_creator_save_site_banner(context_course $context, int $siteid, int $draftitemid): void {
    file_save_draft_area_files(
        $draftitemid,
        $context->id,
        'block_google_site_creator',
        'sitebanner',
        $siteid,
        [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ]
    );
}

/**
 * Get banner image URL for a created Google Site record.
 *
 * @param stdClass $site
 * @return string|null
 */
function block_google_site_creator_get_site_banner_url(stdClass $site): ?string {
    $context = context_course::instance((int)$site->courseid);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'block_google_site_creator', 'sitebanner', (int)$site->id, 'filename', false);
    if (!$files) {
        return null;
    }
    $file = reset($files);
    return moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    )->out(false);
}

/**
 * File serving for Google Site Creator banner images.
 */
function block_google_site_creator_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_COURSE) {
        return false;
    }
    if ($filearea !== 'sitebanner') {
        return false;
    }
    require_login($course, false);
    $canaccess = is_enrolled($context, null, '', true)
        || has_capability('block/google_site_creator:create', $context)
        || has_capability('block/google_site_creator:managecourse', $context)
        || has_capability('moodle/course:view', $context);
    if (!$canaccess) {
        return false;
    }
    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'block_google_site_creator', 'sitebanner', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
