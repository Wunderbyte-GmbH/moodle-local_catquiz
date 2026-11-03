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
 * Jest unit tests for local_catquiz/chart: a chart that cannot be drawn says so (issue #150).
 *
 * The live error was "Invalid values received for series." from core/chart_series, thrown inside the require
 * callback of core/chart_builder: uncaught, the promise never settled, the page kept an empty chart area. The
 * module creates the chart itself and replaces a chart it cannot draw by a message.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init, problem} from '../../amd/src/chart';

const MESSAGE = 'This chart could not be drawn.';

/**
 * The markup of core/chart, with the data table.
 *
 * @param {string} uniqid
 */
const area = uniqid => {
    document.body.innerHTML = `
        <div class="chart-area" id="chart-area-${uniqid}">
            <div class="chart-image" role="presentation"></div>
            <div class="chart-table">
                <p class="chart-table-expand"><a href="#">Show chart data</a></p>
                <div class="chart-table-data" id="chart-table-data-${uniqid}"></div>
            </div>
        </div>`;
};

/**
 * Core's chart class as far as the module uses it: create() runs the check of core/chart_series.
 */
const createChart = jest.fn((Klass, data) => {
    data.series.forEach(series => {
        if (series.values.length < 1) {
            throw new Error('Invalid values received for series.');
        }
    });
    return {labels: data.labels, series: data.series};
});
const ChartBar = function() {
    return undefined;
};
ChartBar.prototype.create = createChart;
const Output = jest.fn();
const OutputTable = jest.fn();

/**
 * The bars of a histogram and, optionally, the test information line.
 *
 * @param {number} labels
 * @param {number[]} seriescounts
 * @return {Object}
 */
const chartdata = (labels, seriescounts) => ({
    type: 'bar',
    labels: Array.from({length: labels}, (v, i) => String(i)),
    series: seriescounts.map((n, i) => ({label: `Series ${i}`, type: i ? 'line' : null,
        values: Array.from({length: n}, () => 1)})),
    axes: {x: [], y: []},
});

beforeEach(() => {
    jest.clearAllMocks();
    jest.spyOn(window.console, 'error').mockImplementation(() => undefined);
    window.require = jest.fn((names, callback) => {
        callback(...names.map(name => ({
            'core/chart_bar': ChartBar,
            'core/chart_output_chartjs': Output,
            'core/chart_output_htmltable': OutputTable,
        })[name]));
    });
});

afterEach(() => {
    window.console.error.mockRestore();
});

describe('problem', () => {
    it('accepts series that fit the labels', () => {
        expect(problem(chartdata(20, [20, 20]))).toBeNull();
    });

    it('names the series without values - the live case', () => {
        expect(problem({...chartdata(0, [0, 20]), series: [
            {label: 'Test information', values: []}, {label: 'Score', values: Array(20).fill(1)},
        ]})).toBe('Series "Test information" has no values.');
    });

    it('refuses bars without labels: core draws them, and nothing is seen', () => {
        expect(problem(chartdata(0, [20]))).toBe('Series "Series 0" has 20 values for 0 labels.');
    });

    it('refuses series of different length and values that are no numbers', () => {
        expect(problem(chartdata(20, [20, 19]))).toBe('Series "Series 1" has 19 values for 20 labels.');
        const data = chartdata(2, [2]);
        data.series[0].values = [1, 'NaN'];
        expect(problem(data)).toBe('Series "Series 0" has a value that is not a number.');
    });
});

describe('init', () => {
    it('draws a chart that fits, with its table', async() => {
        area('ok');
        await expect(init('ok', chartdata(20, [20, 20]), MESSAGE)).resolves.toBe(true);
        expect(createChart).toHaveBeenCalledTimes(1);
        expect(Output).toHaveBeenCalledTimes(1);
        expect(OutputTable).toHaveBeenCalledTimes(1);
        expect(document.querySelector('[data-region="local_catquiz-chart-notice"]')).toBeNull();
    });

    it('shows the message for the live payload instead of an empty area', async() => {
        area('live');
        await expect(init('live', chartdata(0, [0, 20]), MESSAGE)).resolves.toBe(false);
        const notice = document.querySelector('#chart-area-live .chart-image [data-region="local_catquiz-chart-notice"]');
        expect(notice.textContent).toBe(MESSAGE);
        expect(document.querySelector('#chart-area-live .chart-table').hidden).toBe(true);
        expect(Output).not.toHaveBeenCalled();
        expect(window.console.error).toHaveBeenCalled();
    });

    it('shows the message for bars without labels, which core draws without an error and nothing is seen', async() => {
        area('nolabels');
        await expect(init('nolabels', chartdata(0, [20]), MESSAGE)).resolves.toBe(false);
        expect(document.querySelector('#chart-area-nolabels [data-region="local_catquiz-chart-notice"]').textContent)
            .toBe(MESSAGE);
        expect(createChart).not.toHaveBeenCalled();
    });

    it('shows the message when core refuses the chart - the error the builder left uncaught', async() => {
        area('core');
        const data = chartdata(1, [1]);
        // Passes the check here; core throws while creating it.
        createChart.mockImplementationOnce(() => {
            throw new Error('Invalid values received for series.');
        });
        await expect(init('core', data, MESSAGE)).resolves.toBe(false);
        expect(document.querySelector('#chart-area-core [data-region="local_catquiz-chart-notice"]').textContent)
            .toBe(MESSAGE);
        expect(window.console.error.mock.calls[0][0].message).toBe('Invalid values received for series.');
    });

    it('shows the message when the chart modules cannot be loaded', async() => {
        area('load');
        window.require = jest.fn((names, callback, errback) => errback(new Error('Script error')));
        await expect(init('load', chartdata(1, [1]), MESSAGE)).resolves.toBe(false);
        expect(document.querySelector('#chart-area-load [data-region="local_catquiz-chart-notice"]')).not.toBeNull();
    });

    it('does nothing without its area', async() => {
        document.body.innerHTML = '';
        await expect(init('none', chartdata(1, [1]), MESSAGE)).resolves.toBe(false);
        expect(window.require).not.toHaveBeenCalled();
    });
});
