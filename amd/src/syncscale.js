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

import {get_string as getString} from 'core/str';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';

const SELECTORS = {
    SYNCBUTTON: '#sync_button'
};

/**
 * Add event listener to buttons.
 * @param {Object} config Configuration object containing centralHost
 */
export const init = (config) => {
    const centralHost = config.centralHost;
    const syncButton = document.querySelector(SELECTORS.SYNCBUTTON);
    if (!syncButton) {
        return;
    }

    syncButton.addEventListener('click', () => {
        fetchParameters(centralHost);
    });
};

/**
 * Aggregate warnings by type and message.
 * @param {Array} warnings Array of warning objects
 * @return {Array} Aggregated warnings
 */
const aggregateWarnings = (warnings) => {
    const warningMap = new Map();
    let index = 0; // Add index counter.

    warnings.forEach(warning => {
        const key = warning.warning;
        if (warningMap.has(key)) {
            const existingWarning = warningMap.get(key);
            existingWarning.uniqueItems.add(warning.item);
            existingWarning.items = Array.from(existingWarning.uniqueItems);
            existingWarning.count++;
        } else {
            warningMap.set(key, {
                message: warning.warning,
                count: 1,
                uniqueItems: new Set([warning.item]),
                items: [warning.item],
                index: index++ // Add unique index for each warning.
            });
        }
    });

    return Array.from(warningMap.values()).map(({message, count, items, index}) => ({
        message,
        count,
        items,
        index,
        multipleItems: items.length > 1
    }));
};


/**
 * Fetch parameters from central instance.
 * @param {string} centralHost The host URL of the central instance
 */
const fetchParameters = async(centralHost) => {
    const urlParams = new URLSearchParams(window.location.search);
    const scaleid = urlParams.get('scaleid');
    let modal = null;
    const fetchMessage = await getString('fetchingparameters', 'local_catquiz');
    try {
        // Create and show loading modal.
        modal = await ModalFactory.create({
            title: await getString('fetchparamheading', 'local_catquiz', centralHost),
            body: `<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>${fetchMessage}</p></div>`,
            removeOnClose: true,
            large: true
        });
        modal.show();

        // Call webservice.
        const result = await Ajax.call([{
            methodname: 'local_catquiz_client_fetch_parameters',
            args: {
                scaleid: scaleid
            }
        }])[0];

        // Handle errors from the webservice.
        if (result.error) {
            throw new Error(result.error);
        }

        // Handle empty or invalid responses.
        if (!result || typeof result.status === 'undefined') {
            throw new Error(await getString('invalidresponse', 'local_catquiz'));
        }

        // Aggregate warnings before rendering.
        const aggregatedWarnings = aggregateWarnings(result.warnings);

        // Create result content.
        const content = await Templates.render('local_catquiz/fetch_parameters_result', {
            status: result.status,
            message: result.message,
            duration: result.duration,
            synced: result.synced,
            errors: result.errors,
            warnings: aggregatedWarnings,
            hasWarnings: aggregatedWarnings.length > 0
        });

        // Update modal.
        modal.setTitle(await getString('fetchparamheading', 'local_catquiz', centralHost));
        modal.setBody(content);

        // Add close button.
        const footer = modal.getFooter();
        footer.empty();
        const closeButton = '<button class="btn btn-primary" data-action="hide">' +
            await getString('close', 'core') + '</button>';
        footer.append(closeButton);

        // Disable sync button.
        document.querySelector(SELECTORS.SYNCBUTTON).setAttribute('disabled', true);

        // Register close handler.
        modal.getRoot().on(ModalEvents.hidden, () => {
            // Reload page if sync was successful.
            if (result.status) {
                window.location.reload();
            }
        });

    } catch (error) {
        // If we have a modal, show error there.
        if (modal) {
            const errorMessage = error.message || await getString('unknownerror', 'local_catquiz');
            try {
                const errorContent = await Templates.render('local_catquiz/fetch_parameters_result', {
                    status: false,
                    message: errorMessage,
                    duration: 0,
                    synced: 0,
                    errors: 1,
                    warnings: [],
                    hasWarnings: false
                });
                modal.setTitle(await getString('error', 'core'));
                modal.setBody(errorContent);

                // Add close button.
                const footer = modal.getFooter();
                footer.empty();
                const closeButton = '<button class="btn btn-primary" data-action="hide">' +
                    await getString('close', 'core') + '</button>';
                footer.append(closeButton);
            } catch (templateError) {
                // If template rendering fails, show basic error.
                modal.setBody('<div class="alert alert-danger">' + errorMessage + '</div>');
            }
        } else {
            // If we don't have a modal yet, use notification.
            Notification.exception(error);
        }
    }
};
