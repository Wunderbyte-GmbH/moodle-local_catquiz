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

/*
 * @package    local_catquiz
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


import {listenToSelect} from "./manage_catscale_questions";

/**
 * Initialize the form with event listener that update url params.
 */
export const init = () => {

    const selectcontainer = document.querySelector("[id='catmanagertestandtemplates scaleselectors']");
    const selects = selectcontainer.querySelectorAll('[id*="select_scale_form_scaleid"]');
    selects.forEach(select => {
        listenToSelect(select, 'local_catquiz\\form\\scaleselector', "scaleid");
    });
};
