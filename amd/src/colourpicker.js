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
 * JavaScript for mod_form to reload when a CAT model has been chosen.
 *
 * @module     local_catquiz/colourpicker
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    SELECTCOLOURPICKER: 'select[name^="wb_colourpicker_"]',
    COLOURPICKER: 'span.wb_colourpick',
    COLOUR: 'span.colourpickercircle',
};

/**
 * Add event listener to form.
 */
export const init = () => {

    const selectcolors = document.querySelectorAll(SELECTORS.SELECTCOLOURPICKER);

    selectcolors.forEach(selectcolor => {
        if (selectcolor.dataset.initialized) {
            return;
        }
        selectcolor.dataset.initialized = 'true';

        addClickListener(selectcolor);
    });
};

/**
 * Add Click Listener to element.
 *
 * @param {mixed} selectcolor
 */
async function addClickListener(selectcolor) {
    const colours = await Promise.all([...selectcolor.querySelectorAll('option')].map(async el => {

        const colour = el.value;
        let colourname = el.textContent;
        try {
            colourname = await getString('color_' + el.value + '_name', 'local_catquiz');
        } catch (err) {
            // Nothing to do: we already have a default value.
        }

        return {
            colour,
            colourname,
            colourvalue: el.innerHTML,
            selected: selectcolor.value == el.value,
            id: selectcolor.name
        };
    }));

    const colourobject = colours.filter(e => e.selected).pop();

    const data = {
        colours,
        colour: colourobject.colour,
        id: selectcolor.name
    };

    Templates.renderForPromise('local_catquiz/colour_picker', data).then(({html}) => {

        selectcolor.classList.add('hidden');
        selectcolor.insertAdjacentHTML('afterend', html);
        const colourpicker = document.querySelector('span[data-id=wb_colourpick_id_' + selectcolor.name + ']');
        const colourselectnotify = document.querySelector('span[data-id=wb_colourselectnotify_id_' + selectcolor.name + ']');
        const colours = colourpicker.querySelectorAll(SELECTORS.COLOUR);

        colours.forEach(el => {
            el.addEventListener('click', e => {
                colourselectnotify.innerHTML = e.target.dataset.colourname;

                colours.forEach(el => el.classList.remove('selected'));
                e.target.classList.add('selected');
                selectcolor.value = e.target.dataset.colour;
            });
        });
        return true;
      }).catch((e) => {
          // eslint-disable-next-line no-console
          console.log(e);
      });
}
