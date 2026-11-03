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
 * The feedback of the CAT settings, one scale at a time (issue #51).
 *
 * Only the scale being edited has its fields in the form. Every other scale keeps its values in a hidden
 * carrier. Opening a scale - its header or its button - loads its fields as a fragment, with the values
 * of its carrier; the scale edited until then gets a carrier with what its fields hold, unsaved changes
 * included. The form is sent with the fields of one scale and the carriers of all others.
 *
 * @module     local_catquiz/feedbackpanel
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from 'core/fragment';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Pending from 'core/pending';

const SELECTORS = {
    ROOT: '[data-region="catquiz-feedback"]',
    PANEL: '[data-region="catquiz-feedback-panel"]',
    CARRIERBLOCK: '[data-region="catquiz-feedback-carrier"]',
    FIELDS: '[data-region="catquiz-feedback-fields"]',
    EDITBUTTON: '[data-action="catquizEditFeedback"]',
    EDITSCALE: 'input[name="catquiz_feedbackeditscale"]',
};

/** @var {string} Event on the fields of a scale when they have been loaded. */
export const PANELLOADED = 'local_catquiz/feedbackpanel:loaded';

/** @var {string[]} The fields of a scale, without scale and range. */
const FIELDPREFIXES = [
    'feedback_scaleid_limit_lower_',
    'feedback_scaleid_limit_upper_',
    'feedbackeditor_scaleid_',
    'feedbacklegend_scaleid_',
    'wb_colourpicker_',
    'catquiz_courses_',
    'catquiz_group_',
    'enrolment_message_checkbox_',
    'catquiz_scalereportcheckbox_',
];

/** @var {Promise} The switch under way: a second one waits for it. */
let running = Promise.resolve();

/**
 * Sets up the switching.
 */
export const init = () => {
    const root = document.querySelector(SELECTORS.ROOT);
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';

    root.addEventListener('click', e => {
        const button = e.target.closest(SELECTORS.EDITBUTTON);
        if (button) {
            // Without JavaScript the button reloads the form with the scale opened; here it opens in place.
            e.preventDefault();
            switchTo(root, button.dataset.scaleid);
            return;
        }
        const toggle = e.target.closest('[data-bs-toggle="collapse"], [data-toggle="collapse"]');
        if (!toggle) {
            return;
        }
        const target = toggle.dataset.bsTarget || toggle.dataset.target || '';
        const match = target.match(/^#catquiz_feedback_collapse_(\d+)$/);
        const collapse = match ? document.getElementById(target.substring(1)) : null;
        if (collapse && !collapse.classList.contains('show')) {
            switchTo(root, match[1]);
        }
    });
};

/**
 * The panel of a scale.
 *
 * @param {HTMLElement} root
 * @param {string|number} scaleId
 * @returns {HTMLElement|null}
 */
const panelOf = (root, scaleId) => root.querySelector(SELECTORS.PANEL + '[data-scaleid="' + scaleId + '"]');

/**
 * The carrier input of a scale. A hidden field: the form puts it at its start, not into the panel.
 *
 * @param {HTMLElement} panel
 * @returns {HTMLInputElement|null}
 */
const carrierOf = panel => (panel.closest('form') || document)
    .querySelector('input[name="catquiz_feedbackcarrier_' + panel.dataset.scaleid + '"]');

/**
 * The text of an editor, also while TinyMCE has not written it back to its textarea yet.
 *
 * @param {HTMLTextAreaElement} textarea
 * @returns {string}
 */
const editorText = textarea => {
    if (typeof window.tinymce !== 'undefined' && textarea.id) {
        const editor = window.tinymce.get(textarea.id);
        if (editor) {
            return editor.getContent();
        }
    }
    return textarea.value;
};

/**
 * Whether a name is one of the feedback fields.
 *
 * @param {string} name
 * @returns {boolean}
 */
const isFeedbackField = name => FIELDPREFIXES.some(prefix => name.startsWith(prefix));

/**
 * What the fields of a panel hold, as its carrier keeps it.
 *
 * @param {HTMLElement} container
 * @returns {Object} Field name => value; an editor as {text, format, itemid}, courses as a list of ids.
 */
export const serialisePanel = container => {
    const values = {};
    container.querySelectorAll('input[name], select[name], textarea[name]').forEach(element => {
        let name = element.name;
        const editor = name.match(/^(.+)\[(text|format|itemid)\]$/);
        if (editor) {
            if (!isFeedbackField(editor[1])) {
                return;
            }
            values[editor[1]] = values[editor[1]] || {text: '', format: 1, itemid: 0};
            const value = editor[2] === 'text' ? editorText(element) : element.value;
            values[editor[1]][editor[2]] = editor[2] === 'text' ? value : parseInt(value, 10) || 0;
            return;
        }
        if (element.multiple || name.endsWith('[]')) {
            name = name.replace(/\[\]$/, '');
            if (isFeedbackField(name)) {
                values[name] = Array.from(element.options || [])
                    .filter(option => option.selected && option.value !== '' && option.value !== '0')
                    .map(option => option.value);
            }
            return;
        }
        if (!isFeedbackField(name)) {
            return;
        }
        if (element.type === 'checkbox') {
            values[name] = element.checked ? 1 : 0;
        } else if (element.type === 'hidden') {
            // Also the hidden 0 of an advanced checkbox: the checkbox itself decides.
            if (!(name in values)) {
                values[name] = element.value;
            }
        } else {
            values[name] = element.value;
        }
    });
    return values;
};

/**
 * Writes the values of a scale into its carrier and shows it in place of the fields.
 *
 * @param {HTMLElement} panel
 * @param {Object} values
 */
const parkPanel = (panel, values) => {
    const fields = panel.querySelector(SELECTORS.FIELDS);
    if (typeof window.tinymce !== 'undefined') {
        fields.querySelectorAll('textarea[id]').forEach(textarea => {
            const editor = window.tinymce.get(textarea.id);
            if (editor) {
                editor.remove();
            }
        });
    }
    fields.innerHTML = '';
    const carrier = carrierOf(panel);
    carrier.value = JSON.stringify(values);
    carrier.disabled = false;
    panel.querySelector(SELECTORS.CARRIERBLOCK).classList.remove('d-none');
    panel.dataset.edited = '0';
};

/**
 * Loads the fields of a scale.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} panel
 * @returns {Promise<Array>} [html, js]
 */
const loadFields = (root, panel) => new Promise((resolve, reject) => {
    Fragment.loadFragment('local_catquiz', 'feedbackpanel', parseInt(root.dataset.contextid, 10), {
        scaleid: panel.dataset.scaleid,
        rootscaleid: root.dataset.rootscaleid,
        nranges: root.dataset.nranges,
        cmid: root.dataset.cmid,
        instanceid: root.dataset.instanceid,
        values: carrierOf(panel).value,
    }).then((html, js) => resolve([html, js])).catch(reject);
});

/**
 * Opens a scale: its fields come in, the scale edited so far keeps its values in its carrier.
 *
 * @param {HTMLElement} root
 * @param {string|number} scaleId
 * @returns {Promise<boolean>} Whether the scale is open now.
 */
export const switchTo = (root, scaleId) => {
    running = running.then(() => doSwitch(root, String(scaleId))).catch(error => {
        Notification.exception(error);
        return false;
    });
    return running;
};

/**
 * The switch itself, one at a time.
 *
 * @param {HTMLElement} root
 * @param {string} scaleId
 * @returns {Promise<boolean>}
 */
const doSwitch = async(root, scaleId) => {
    const target = panelOf(root, scaleId);
    if (!target) {
        return false;
    }
    if (target.dataset.edited === '1') {
        return true;
    }
    const pending = new Pending('local_catquiz/feedbackpanel:switch');
    try {
        const [html, js] = await loadFields(root, target);
        const current = root.querySelector(SELECTORS.PANEL + '[data-edited="1"]');
        if (current) {
            parkPanel(current, serialisePanel(current.querySelector(SELECTORS.FIELDS)));
        }
        carrierOf(target).disabled = true;
        target.querySelector(SELECTORS.CARRIERBLOCK).classList.add('d-none');
        target.dataset.edited = '1';
        const editscale = root.closest('form').querySelector(SELECTORS.EDITSCALE);
        if (editscale) {
            editscale.value = scaleId;
        }
        const fields = target.querySelector(SELECTORS.FIELDS);
        await Templates.replaceNodeContents(fields, '<div>' + html + '</div>', js);
        fields.dispatchEvent(new CustomEvent(PANELLOADED, {bubbles: true}));
        return true;
    } finally {
        pending.resolve();
    }
};

/**
 * The values of a scale for another one: the scale ids in the names exchanged.
 *
 * @param {Object} values
 * @param {string} sourceId
 * @param {string} targetId
 * @returns {Object}
 */
export const valuesFor = (values, sourceId, targetId) => {
    const result = {};
    Object.keys(values).forEach(name => {
        const renamed = name
            .replace(new RegExp('^(.+_)' + sourceId + '(_\\d+)$'), '$1' + targetId + '$2')
            .replace(new RegExp('^(catquiz_scalereportcheckbox_)' + sourceId + '$'), '$1' + targetId);
        if (renamed !== name) {
            result[renamed] = values[name];
        }
    });
    return result;
};

/**
 * Copies the settings of a scale to the subscales shown in the form.
 *
 * The scale is opened first: its files are then in a draft area, and go along to every subscale.
 *
 * @param {HTMLFormElement} form
 * @param {string} sourceId
 * @param {string[]} subscaleIds
 * @returns {Promise<number>} The number of subscales copied to.
 */
export const copyScaleSettings = async(form, sourceId, subscaleIds) => {
    const root = form.querySelector(SELECTORS.ROOT);
    if (!root || !(await switchTo(root, sourceId))) {
        return 0;
    }
    const values = serialisePanel(panelOf(root, sourceId).querySelector(SELECTORS.FIELDS));
    let copied = 0;
    subscaleIds.forEach(subscaleId => {
        const panel = panelOf(root, subscaleId);
        if (!panel || subscaleId === String(sourceId)) {
            return;
        }
        const carrier = carrierOf(panel);
        const current = JSON.parse(carrier.value || '{}');
        carrier.value = JSON.stringify(Object.assign(current, valuesFor(values, String(sourceId), subscaleId)));
        const checkbox = form.querySelector('input[type="checkbox"][name="catquiz_subscalecheckbox_' + subscaleId + '"]');
        if (checkbox) {
            checkbox.checked = true;
        }
        copied++;
    });
    return copied;
};
