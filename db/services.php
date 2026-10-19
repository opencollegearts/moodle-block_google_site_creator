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
 * External service function definitions.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_google_site_creator_get_site_details' => [
        'classname' => 'block_google_site_creator\external\get_site_details',
        'methodname' => 'execute',
        'description' => 'Get Google Site details by record ID (owner, course managers, or create_for_others)',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/google_site_creator:create,block/google_site_creator:managecourse,block/google_site_creator:create_for_others',
    ],
    'block_google_site_creator_create_site' => [
        'classname' => 'block_google_site_creator\external\create_site',
        'methodname' => 'execute',
        'description' => 'Create a Google Site on behalf of a user (courseid, userid, title, optional templateid, visibility)',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/google_site_creator:create_for_others',
    ],
];
