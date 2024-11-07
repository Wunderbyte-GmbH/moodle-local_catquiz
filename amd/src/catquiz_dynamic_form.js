/*
 * @package    local_catquiz
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import DynamicForm from 'core_form/dynamicform';
import {showNotification} from 'local_catquiz/notifications';
import {get_string as getString} from 'core/str';

/**
 * Extended DynamicForm class with custom error handling for timeouts.
 */
export default class CatquizDynamicForm extends DynamicForm {
    /**
     * Override the onSubmitError method to handle timeout errors.
     *
     * @param {string|Object} exception The error message or object.
     * @protected
     */
    async onSubmitError(exception) {
        // Common timeout messages from different server setups.
        const timeoutMessages = [
            'Gateway Timeout',
            '504',
            'timeout',
            'request timed out',
            'gateway time-out'
        ];

        // Check if the error message includes any of our timeout indicators.
        const isTimeout = timeoutMessages.some(msg =>
            exception?.toString().toLowerCase().includes(msg.toLowerCase())
        );

        if (isTimeout) {
            // Prevent the default error handling.
            const event = this.trigger(this.events.ERROR, exception);
            if (event.defaultPrevented) {
                return;
            }

            try {
                const message = await getString('requesttimeout', 'local_catquiz');
                showNotification(message, 'danger', false);
            } catch (error) {
                // Fallback if string loading fails.
                showNotification('The request timed out. Please try again.', 'danger', false);
            }
            return;
        }

        // For all other errors, use the parent class handling.
        super.onSubmitError(exception);
    }
}
