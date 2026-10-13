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
 * Unit tests for visibility helpers.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_google_site_creator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/google_site_creator/lib.php');

/**
 * Tests for visibility normalisation and option building.
 *
 * @covers ::block_google_site_creator_normalise_visibility
 * @covers ::block_google_site_creator_get_visibility_options
 * @covers ::block_google_site_creator_can_transfer_to_user_email
 */
final class visibility_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_normalise_legacy_visibility_without_tutor_group(): void {
        set_config('tutorgroupemail', '', 'block_google_site_creator');
        $this->assertSame(
            BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS,
            block_google_site_creator_normalise_visibility('tutors')
        );
        $this->assertSame(
            BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_GROUP,
            block_google_site_creator_normalise_visibility('unit_group')
        );
        $this->assertSame(
            BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP,
            block_google_site_creator_normalise_visibility('all_oca')
        );
    }

    public function test_normalise_legacy_tutors_with_tutor_group(): void {
        set_config('tutorgroupemail', 'tutors@example.edu', 'block_google_site_creator');
        $this->assertSame(
            BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP,
            block_google_site_creator_normalise_visibility('tutors')
        );
    }

    public function test_visibility_options_teachers_without_managecourse(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        set_config('tutorgroupemail', '', 'block_google_site_creator');
        set_config('organisationgroupemail', '', 'block_google_site_creator');

        $options = block_google_site_creator_get_visibility_options((int)$course->id, (int)$teacher->id);
        $this->assertArrayHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS, $options);
        $this->assertArrayNotHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_PARTICIPANTS, $options);
        $this->assertArrayNotHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP, $options);
    }

    public function test_visibility_options_participants_requires_managecourse(): void {
        $course = $this->getDataGenerator()->create_course();
        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        set_config('tutorgroupemail', '', 'block_google_site_creator');
        set_config('organisationgroupemail', '', 'block_google_site_creator');

        $options = block_google_site_creator_get_visibility_options((int)$course->id, (int)$editingteacher->id);
        $this->assertArrayHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS, $options);
        $this->assertArrayHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_PARTICIPANTS, $options);
    }

    public function test_visibility_options_include_configured_groups(): void {
        $course = $this->getDataGenerator()->create_course();
        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        set_config('tutorgroupemail', 'tutors@example.edu', 'block_google_site_creator');
        set_config('organisationgroupemail', 'everyone@example.edu', 'block_google_site_creator');

        global $DB;
        $DB->insert_record('block_google_site_creator_course', (object)[
            'courseid' => $course->id,
            'template_id' => '',
            'unit_group_email' => 'course@example.edu',
            'timemodified' => time(),
        ]);

        $options = block_google_site_creator_get_visibility_options((int)$course->id, (int)$editingteacher->id);
        $this->assertArrayHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTOR_GROUP, $options);
        $this->assertArrayHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_GROUP, $options);
        $this->assertArrayHasKey(BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP, $options);
    }

    public function test_resolve_organisation_group_target(): void {
        $course = $this->getDataGenerator()->create_course();
        set_config('organisationgroupemail', 'everyone@example.edu', 'block_google_site_creator');
        $targets = block_google_site_creator_resolve_share_targets(
            BLOCK_GOOGLE_SITE_CREATOR_VIS_ORGANISATION_GROUP,
            (int)$course->id
        );
        $this->assertCount(1, $targets);
        $this->assertSame('everyone@example.edu', $targets[0]['email']);
        $this->assertSame('group', $targets[0]['type']);
        $this->assertSame('reader', $targets[0]['role']);
    }

    public function test_moodle_teachers_prefers_tutor_google_group(): void {
        $course = $this->getDataGenerator()->create_course();
        set_config('tutorgroupemail', 'tutors@example.edu', 'block_google_site_creator');
        $targets = block_google_site_creator_resolve_share_targets(
            BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_TEACHERS,
            (int)$course->id
        );
        $this->assertCount(1, $targets);
        $this->assertSame('tutors@example.edu', $targets[0]['email']);
        $this->assertSame('group', $targets[0]['type']);
    }

    public function test_moodle_participants_prefers_course_google_group(): void {
        $course = $this->getDataGenerator()->create_course();
        global $DB;
        $DB->insert_record('block_google_site_creator_course', (object)[
            'courseid' => $course->id,
            'template_id' => '',
            'unit_group_email' => 'course@example.edu',
            'timemodified' => time(),
        ]);
        $targets = block_google_site_creator_resolve_share_targets(
            BLOCK_GOOGLE_SITE_CREATOR_VIS_COURSE_PARTICIPANTS,
            (int)$course->id
        );
        $this->assertCount(1, $targets);
        $this->assertSame('course@example.edu', $targets[0]['email']);
        $this->assertSame('group', $targets[0]['type']);
    }

    public function test_allowed_email_domains(): void {
        set_config('allowedemaildomains', '', 'block_google_site_creator');
        $this->assertTrue(block_google_site_creator_can_transfer_to_user_email('anyone@elsewhere.com'));

        set_config('allowedemaildomains', 'example.edu, school.org', 'block_google_site_creator');
        $this->assertTrue(block_google_site_creator_can_transfer_to_user_email('user@example.edu'));
        $this->assertTrue(block_google_site_creator_can_transfer_to_user_email('user@school.org'));
        $this->assertFalse(block_google_site_creator_can_transfer_to_user_email('user@elsewhere.com'));
        $this->assertFalse(block_google_site_creator_can_transfer_to_user_email(''));
    }

    public function test_template_catalogue_parsing_and_allowlist(): void {
        set_config(
            'templates',
            "Portfolio|1Pz0Vezda67P17oryladHG7NSTBCBNxfW\n# comment\nBad|/etc/passwd\nUnit|abcDEF1234_-xyz",
            'block_google_site_creator'
        );
        set_config('defaulttemplate', '1Pz0Vezda67P17oryladHG7NSTBCBNxfW', 'block_google_site_creator');

        $templates = block_google_site_creator_get_templates();
        $this->assertArrayHasKey('1Pz0Vezda67P17oryladHG7NSTBCBNxfW', $templates);
        $this->assertArrayHasKey('abcDEF1234_-xyz', $templates);
        $this->assertArrayNotHasKey('/etc/passwd', $templates);
        $this->assertTrue(block_google_site_creator_is_allowed_template_id('abcDEF1234_-xyz'));
        $this->assertFalse(block_google_site_creator_is_allowed_template_id('not-in-list-xxxxxxxxxx'));
        $this->assertFalse(block_google_site_creator_is_valid_drive_file_id('id/with/slash'));
        $this->assertFalse(block_google_site_creator_is_valid_drive_file_id('short'));
    }
}
