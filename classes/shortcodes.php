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
 * Shortcodes for local_catquiz
 *
 * @package local_musi
 * @subpackage db
 * @since Moodle 3.11
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi;

use context_system;
use local_catquiz\table\quizattempts_table;
use mod_booking\output\page_allteachers;
use local_musi\output\userinformation;
use local_musi\table\musi_table;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use mod_booking\booking;
use mod_booking\singleton_service;
use moodle_url;

/**
 * Deals with local_shortcodes regarding catquiz.
 */
class shortcodes {

    /**
     * Prints out list of catquiz attempts.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return void
     */
    public static function allquizattempts($shortcode, $args, $content, $env, $next) {
        // TODO
    }

}
