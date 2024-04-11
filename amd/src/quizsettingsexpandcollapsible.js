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
 * Function for mod_form to expand elements when they are not validated.
 *
 * @module     local_catquiz/quizsettingsexpandcollapsible
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


export const init = () => {

    let invalidElements = document.querySelectorAll(".is-invalid");
    invalidElements.forEach((element) => {
        let parent = element.closest('[data-name^="catquiz_feedback_header"]');
        while (parent) {
            parent.classList.add("show");
            parent = parent.parentNode.closest('[data-name^="catquiz_feedback_header"]');
        }
    });
};