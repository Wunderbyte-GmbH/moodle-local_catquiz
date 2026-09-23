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

import CatquizDynamicForm from './catquiz_dynamic_form';
import {showNotification} from 'local_catquiz/notifications';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    FORMCONTAINER: '#lcq_csv_import_form',
    BUTTON: 'input[type="submit"]',
};

const FEEDBACK_CONTAINER_CLASS = 'lcq-import-feedback';

const FEEDBACK_STRING_KEYS = {
    warnings: 'csvimportwarnings',
    errors: 'csvimporterrors',
    generalerrors: 'csvimportgeneralerrors',
    missinglabels: 'csvimportmissingquestionlabels',
    missinglabeldetail: 'csvimportmissingquestionlabeldetail',
    removedolderversions: 'csvimportremovedolderquestionversions',
};

const stripHtml = (message) => {
    const temp = document.createElement('div');
    temp.innerHTML = message;
    return (temp.textContent || temp.innerText || '').trim();
};

const normalizeLabelNotFound = (message) => {
    const plainMessage = stripHtml(message);

    const patterns = [
        /^Question label ["“](.+?)["”] was not found in the question bank\.$/i,
        /^Fragen-Label ["„](.+?)["“] wurde in der Fragenbank nicht gefunden\.$/i,
        /^Wert von Label\s+(.+?)\s+nicht gefunden\.$/i,
        /^Label ["“](.+?)["”] not found\.$/i,
    ];

    for (const pattern of patterns) {
        const match = plainMessage.match(pattern);
        if (match) {
            return match[1].trim();
        }
    }

    return null;
};

const normalizeRemovedOlderVersions = (message) => {
    const plainMessage = stripHtml(message);
    const pattern = /^Removed older versions of question\s+"(.+?)"\s+from scale\s+"(.+?)"\.$/i;
    const match = plainMessage.match(pattern);

    if (match) {
        return {
            label: match[1].trim(),
            scale: match[2].trim(),
        };
    }

    return null;
};

const groupMessages = (messages, strings) => {
    const groups = new Map();

    messages.forEach((message) => {
        const label = normalizeLabelNotFound(message);
        const removedOlderVersions = normalizeRemovedOlderVersions(message);
        const plainMessage = stripHtml(message);
        let key = plainMessage;
        let summary = plainMessage;
        let detailPrefix = '';
        let isPatternGroup = false;

        if (label !== null) {
            key = 'label-not-found';
            summary = strings.missinglabels;
            detailPrefix = strings.missinglabeldetail;
            isPatternGroup = true;
        } else if (removedOlderVersions !== null) {
            key = 'removed-older-versions';
            summary = strings.removedolderversions;
        }

        if (!groups.has(key)) {
            groups.set(key, {
                summary,
                detailPrefix,
                count: 0,
                messages: [],
                isPatternGroup,
            });
        }

        const group = groups.get(key);
        group.count++;
        group.messages.push(plainMessage);
        if (label !== null) {
            group.messages[group.messages.length - 1] = label;
        } else if (removedOlderVersions !== null) {
            group.messages[group.messages.length - 1] = plainMessage;
        }
    });

    return Array.from(groups.values());
};

const removeExistingFeedback = (container) => {
    container.querySelectorAll(`.${FEEDBACK_CONTAINER_CLASS}`).forEach((element) => element.remove());
};

const renderGroupedFeedback = (container, title, type, messages, strings) => {
    if (!messages || messages.length === 0) {
        return;
    }

    const groups = groupMessages(messages, strings);
    const section = document.createElement('div');
    section.className = `${FEEDBACK_CONTAINER_CLASS} alert alert-${type} mb-3`;

    const heading = document.createElement('div');
    const headingLabel = document.createElement('strong');
    headingLabel.textContent = `${title} (${messages.length})`;
    heading.appendChild(headingLabel);
    section.appendChild(heading);

    groups.forEach((group) => {
        const details = document.createElement('details');
        details.className = 'mt-2';

        const summary = document.createElement('summary');
        const summaryLabel = document.createElement('span');
        summaryLabel.textContent = group.count > 1 ? `${group.summary} (${group.count})` : group.summary;
        summary.appendChild(summaryLabel);
        details.appendChild(summary);

        const body = document.createElement('div');
        body.className = 'ps-3 mt-2';

        if (group.detailPrefix) {
            const prefix = document.createElement('div');
            prefix.textContent = group.detailPrefix;
            body.appendChild(prefix);
        }

        const list = document.createElement('ul');
        list.className = 'mb-0 mt-2';
        const uniqueMessages = [...new Set(group.messages)];
        uniqueMessages.forEach((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            list.appendChild(item);
        });
        body.appendChild(list);

        details.appendChild(body);
        section.appendChild(details);
    });

    removeExistingFeedback(container);
    container.parentElement.insertBefore(section, container);
};

/**
 * Add event listener to form.
 */
export const init = () => {

    const formContainer = document.querySelector(SELECTORS.FORMCONTAINER);

    // Issue #29: the CAT manager now renders only the active tab, so this form is
    // absent on every tab but the importer. Constructing the dynamic form with a null
    // container throws, and a thrown module leaves the page permanently "not ready" -
    // which is how seven Behat scenarios began failing at an unrelated step.
    if (!formContainer) {
        return;
    }

    // Initialize the form - pass the container element and the form class name.
    const dynamicForm = new CatquizDynamicForm(formContainer,
        'local_catquiz\\form\\csvimport'
    );

    // If a user imports an element, trigger treatment of input.
    dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, async(e) => {

        const response = e.detail;
        const errors = response.errors;

        dynamicForm.load({
            id: response.id,
            settingscallback: response.settingscallback,
        });

        // Display errors notifications if defined.
        if (errors != [] && errors !== undefined) {
            const [warningsTitle, errorsTitle, generalErrorsTitle, missingLabelsTitle, missingLabelsDetail,
                removedOlderVersionsTitle] = await Promise.all([
                getString(FEEDBACK_STRING_KEYS.warnings, 'local_catquiz'),
                getString(FEEDBACK_STRING_KEYS.errors, 'local_catquiz'),
                getString(FEEDBACK_STRING_KEYS.generalerrors, 'local_catquiz'),
                getString(FEEDBACK_STRING_KEYS.missinglabels, 'local_catquiz'),
                getString(FEEDBACK_STRING_KEYS.missinglabeldetail, 'local_catquiz'),
                getString(FEEDBACK_STRING_KEYS.removedolderversions, 'local_catquiz'),
            ]);

            const feedbackStrings = {
                missinglabels: missingLabelsTitle,
                missinglabeldetail: missingLabelsDetail,
                removedolderversions: removedOlderVersionsTitle,
            };

            renderGroupedFeedback(formContainer, warningsTitle, 'warning', errors.warnings ?? [], feedbackStrings);
            renderGroupedFeedback(formContainer, errorsTitle, 'danger', errors.lineerrors ?? [], feedbackStrings);
            renderGroupedFeedback(formContainer, generalErrorsTitle, 'danger', errors.generalerrors ?? [], feedbackStrings);
        }

        // Display general success status.
        if (response.success == 1) {

            getString('importsuccess', 'local_catquiz', response.numberofsuccessfullyupdatedrecords).then(message => {
                showNotification(message, 'success', false);
                return;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.error(e);
            });
            if (response.callbackresponse !== null && response.callbackresponse.message !== null) {
                showNotification(response.callbackresponse.message, 'success', false);
            }
        } else {
            getString('importfailed', 'local_catquiz').then(message => {
                showNotification(message, 'danger', false);
                return;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.error(e);
            });
        }

    });

    // Cancel button triggers reload of empty form.
    dynamicForm.addEventListener(dynamicForm.events.FORM_CANCELLED, (e) => {
        e.preventDefault();
        dynamicForm.load({});
    });

};
