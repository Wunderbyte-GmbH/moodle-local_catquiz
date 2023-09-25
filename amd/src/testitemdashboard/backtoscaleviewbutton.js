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
 * @module     local_catquiz/backbutton
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


var SELECTORS = {
    BACKBUTTON: "[data-id='backtoscaleview']",
    TABCONTAINER: '#lcq_catscales',
};

export const init = () => {

    const container = document.querySelector(SELECTORS.TABCONTAINER);
    const button = container.querySelector(SELECTORS.BACKBUTTON);

    if (!button) {
        return;
    }
    if (!button.dataset.initialized) {
        button.dataset.initialized = 'true';

        button.addEventListener('click', e => {
            e.stopPropagation();
            goBackToTable();
        });
    }
};

/**
 * Delete param from URL to go to overview table
 */
function goBackToTable() {
    // To get back to the table, we simply have to remove the sdv param from the URL.
    let searchParams = new URLSearchParams(window.location.search);
    searchParams.delete('sdv');
    window.location.search = searchParams.toString();
}