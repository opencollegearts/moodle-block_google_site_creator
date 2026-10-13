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
 * Restore task for the google_site_creator block.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/google_site_creator/backup/moodle2/restore_google_site_creator_stepslib.php');

/**
 * Specialised restore task for the google_site_creator block.
 *
 * @package   block_google_site_creator
 */
class restore_google_site_creator_block_task extends restore_block_task {

    /**
     * Define particular settings for this block.
     */
    protected function define_my_settings() {
    }

    /**
     * Define particular steps for this block.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_google_site_creator_block_structure_step(
            'google_site_creator_structure',
            'google_site_creator.xml'
        ));
    }

    /**
     * File areas associated with the block instance context.
     *
     * @return array
     */
    public function get_fileareas() {
        return [];
    }

    /**
     * Configdata attributes that need decoding.
     *
     * @return array
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * Define the contents in the block that must be processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Define the decoding rules for links belonging to the block.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [];
    }
}
