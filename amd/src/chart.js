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
 * Draws a chart of CATquiz, and says so when it cannot (issue #150).
 *
 * core/chart hands its data to core/chart_builder, which creates the chart inside a require callback: an error
 * there - "Invalid values received for series." for a series without values - is uncaught, the promise of the
 * builder never settles, and the page keeps an empty chart area with a link to a table that never comes. Here
 * the chart is created where its error can be caught, and a chart that cannot be drawn is replaced by a message.
 *
 * @module     local_catquiz/chart
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';

/**
 * Loads AMD modules whose names are known at run time only (the type of the chart).
 *
 * @param {string[]} names
 * @return {Promise<Array>}
 */
const loadModules = names => new Promise((resolve, reject) => {
    window.require(names, (...modules) => resolve(modules), reject);
});

/**
 * Why the data cannot be drawn, or null: the checks of core/chart_series and core/chart_base, and labels for the
 * values - a series without labels is drawn by core, but shows nothing.
 *
 * @param {Object} data The chart as core\chart_base serialises it.
 * @return {string|null}
 */
export const problem = data => {
    if (!data || !Array.isArray(data.labels) || !Array.isArray(data.series) || !data.series.length) {
        return 'The chart has no labels or no series.';
    }
    for (const series of data.series) {
        const values = series.values;
        if (!Array.isArray(values) || !values.length) {
            return `Series "${series.label}" has no values.`;
        }
        if (values.length !== data.labels.length) {
            return `Series "${series.label}" has ${values.length} values for ${data.labels.length} labels.`;
        }
        if (values.some(value => value !== null && !Number.isFinite(Number(value)))) {
            return `Series "${series.label}" has a value that is not a number.`;
        }
    }
    return null;
};

/**
 * Draws the chart into its area, or puts the message there.
 *
 * @param {string} uniqid The id of the chart area: chart-area-{uniqid}.
 * @param {Object} data The chart as core\chart_base serialises it.
 * @param {string} message The text in place of a chart that cannot be drawn.
 * @return {Promise<boolean>} Whether the chart was drawn.
 */
export const init = async(uniqid, data, message) => {
    const area = document.getElementById('chart-area-' + uniqid);
    if (!area) {
        return false;
    }
    const image = area.querySelector('.chart-image');
    const table = area.querySelector('.chart-table-data');
    const refuse = error => {
        const link = area.querySelector('.chart-table');
        if (link) {
            link.hidden = true;
        }
        const notice = document.createElement('div');
        notice.className = 'alert alert-warning';
        notice.dataset.region = 'local_catquiz-chart-notice';
        notice.textContent = message;
        image.replaceChildren(notice);
        window.console.error(error);
        return false;
    };
    const pending = new Pending('local_catquiz/chart:' + uniqid);
    try {
        const reason = problem(data);
        if (reason) {
            return refuse(new Error(reason));
        }
        const [Klass, Output, OutputTable] = await loadModules([
            'core/chart_' + data.type,
            'core/chart_output_chartjs',
            'core/chart_output_htmltable',
        ]);
        const chart = Klass.prototype.create(Klass, data);
        new Output(image, chart);
        if (table) {
            new OutputTable(table, chart);
        }
        return true;
    } catch (error) {
        return refuse(error);
    } finally {
        pending.resolve();
    }
};
