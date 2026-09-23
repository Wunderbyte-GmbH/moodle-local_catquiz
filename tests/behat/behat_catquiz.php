<?php
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
 * Defines message providers (types of messages being sent)
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * To create catquiz specific behat scearios.
 * @package local_catquiz
 */
class behat_catquiz extends behat_base {
    /**
     * Opens the CAT attempt feedback review page for a user's most recent attempt.
     *
     * Issue #18: reviewing another user's attempt is the situation in which the
     * permission context matters. The attempt id is generated at runtime, so it
     * cannot be written into a feature file; this step looks it up by username.
     *
     * @Given /^I visit the CAT attempt feedback page for the last attempt of "([^"]*)"$/
     *
     * @param string $username
     * @return void
     */
    public function i_visit_the_cat_attempt_feedback_page_for_the_last_attempt_of(string $username): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempt = $DB->get_records(
            'local_catquiz_attempts',
            ['userid' => $userid],
            'id DESC',
            'attemptid, contextid',
            0,
            1
        );

        if (empty($attempt)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'No CAT attempt found for user ' . $username,
                $this->getSession()
            );
        }

        $attempt = reset($attempt);
        $url = new moodle_url('/local/catquiz/show_attemptfeedback.php', [
            'attemptid' => $attempt->attemptid,
            'contextid' => $attempt->contextid,
        ]);

        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Fill specified HTMLQuickForm element by its number under goven xpath with a value.
     * @When /^I fill in the "([^"]*)" element number "([^"]*)" with the dynamic identifier "([^"]*)" with "([^"]*)"$/
     *
     * @param string $fieldtype
     * @param string $numberofitem
     * @param string $dynamicidentifier
     * @param string $value
     *
     * @return void
     *
     */
    public function i_fill_in_the_element_with_dynamic_identifier($fieldtype, $numberofitem, $dynamicidentifier, $value) {
        // Use $dynamicIdentifier to locate and fill in the corresponding form field.
        // Use $value to set the desired value in the form field.

        // First we need to open all collapsibles.
        // We should probably have a single fuction for that.
        $xpathtarget = "//div[contains(@id, 'catquiz_feedback_collapse_')]";
        $fields = $this->getSession()->getPage()->findAll('xpath', $xpathtarget);

        foreach ($fields as $field) {
            $id = $field->getAttribute('id');
            // Use JavaScript to add the expected class to the element.
            $script = "document.getElementById('$id').classList.add('show');";
            $this->getSession()->executeScript($script);
            $this->getSession()->wait(500);
        }
        // Now we get the form element fields by the identifier and its number in DOM.
        switch ($fieldtype) {
            case 'editor':
                $xpathtarget = "(//div[contains(@id, '" . $dynamicidentifier . "')][@contenteditable='true'])
                    [" . $numberofitem . "]";
                break;
            case 'autocomplete':
                $xpathtarget = "(//div[contains(@id, '" . $dynamicidentifier . "')])
                    [" . $numberofitem . "]//input[contains(@id, 'form_autocomplete_input-')]";
                $xpathtarget1 = "(//div[contains(@id, '" . $dynamicidentifier . "')])
                    [" . $numberofitem . "]//li[contains(@id, 'form_autocomplete_suggestions-')]";
                break;
            case 'wb_colourpicker':
                $xpathtarget = "(//div[contains(@id, '" . $dynamicidentifier . "')])
                    [" . $numberofitem . "]//span[@data-colour=" . $value . "]";
                break;
            default:
                $xpathtarget = "(//" . $fieldtype . "[contains(@id, '" . $dynamicidentifier . "')])[" . $numberofitem . "]";
        }

        // Assuming you want to find an editor element related to the competency and fill it with the specified value.
        $field = $this->getSession()->getPage()->find('xpath', $xpathtarget);
        if ($field->isVisible()) {
            switch ($fieldtype) {
                case 'autocomplete':
                    $field->setValue($value);
                    // The suggestion list is populated asynchronously (debounced
                    // AJAX). Wait for the visible suggestion that actually
                    // contains the requested value instead of pressing Enter and
                    // clicking the first list item.
                    $escapedvalue = behat_context_helper::escape($value);
                    $suggestionxpath = $xpathtarget1
                        . "[@role='option']"
                        . "[not(@aria-hidden='true')]"
                        . "[contains(normalize-space(.), $escapedvalue)]";
                    $suggestion = null;
                    for ($attempt = 0; $attempt < 20; $attempt++) {
                        $candidate = $this->getSession()->getPage()->find('xpath', $suggestionxpath);
                        if ($candidate && $candidate->isVisible()) {
                            $suggestion = $candidate;
                            break;
                        }
                        $this->getSession()->wait(300);
                    }
                    if (!$suggestion) {
                        throw new \Behat\Mink\Exception\ExpectationException(
                            "Visible autocomplete suggestion containing '$value' did not appear",
                            $this->getSession()
                        );
                    }
                    $suggestion->click();
                    // Moodle's autocomplete transfers the selection to the hidden
                    // native <select> asynchronously (a promise chain that ends in
                    // option.selected = true plus a native change event). Wait for
                    // that to complete rather than forcing the native state
                    // ourselves: the test must verify Moodle's own behaviour, and
                    // the native <select> is the value actually submitted (Behat
                    // 001 / catquiz_courses).
                    $containerxpath = "(//div[contains(@id, '" . $dynamicidentifier . "')])[" . $numberofitem . "]";
                    $selected = false;
                    for ($attempt = 0; $attempt < 30; $attempt++) {
                        if ($this->native_autocomplete_has_selected_text($containerxpath, $value)) {
                            $selected = true;
                            break;
                        }
                        $this->getSession()->wait(200);
                    }
                    if (!$selected) {
                        throw new \Behat\Mink\Exception\ExpectationException(
                            "Autocomplete '$value' was clicked, but Moodle did not select the "
                                . "corresponding native option. Native options: "
                                . $this->get_native_autocomplete_debug($containerxpath),
                            $this->getSession()
                        );
                    }
                    // Close the suggestion list only after the selection completed,
                    // so it cannot overlap and swallow later clicks.
                    $field->keyPress(27);
                    break;
                case 'wb_colourpicker':
                    $field->click();
                    break;
                default:
                    // Fill in the form field with the specified value.
                    $field->setValue($value);
            }
        }
    }

    /**
     * Whether the hidden native <select> of a Moodle autocomplete has an option
     * with the given text selected.
     *
     * Uses evaluateScript so it reads the real DOM state: Moodle hides the
     * original <select> (display:none, aria-hidden), so Mink's getText() on its
     * options is unreliable, while option.selected and option.textContent are
     * accurate regardless of visibility.
     *
     * @param string $containerxpath Xpath of the form item wrapping the select.
     * @param string $value          The visible option text to look for.
     *
     * @return bool
     */
    protected function native_autocomplete_has_selected_text($containerxpath, $value) {
        $xpath = json_encode($containerxpath . '//select');
        $wanted = json_encode($value);
        $script = <<<JS
(function() {
    const select = document.evaluate(
        $xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
    ).singleNodeValue;
    if (!select) {
        return false;
    }
    const wanted = $wanted;
    return Array.from(select.options).some(function (option) {
        return option.selected && (option.textContent || '').trim().indexOf(wanted) !== -1;
    });
})()
JS;
        return (bool) $this->getSession()->evaluateScript($script);
    }

    /**
     * Returns a JSON dump of the native <select> options and their state.
     *
     * Included in failure messages so a red run shows the actual DOM state
     * (which option exists and whether it is selected) instead of only
     * "not selected".
     *
     * @param string $containerxpath Xpath of the form item wrapping the select.
     *
     * @return string
     */
    protected function get_native_autocomplete_debug($containerxpath) {
        $xpath = json_encode($containerxpath . '//select');
        $script = <<<JS
(function() {
    const select = document.evaluate(
        $xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
    ).singleNodeValue;
    if (!select) {
        return 'NO SELECT';
    }
    return JSON.stringify(Array.from(select.options).map(function (option) {
        return {
            value: option.value,
            text: (option.textContent || '').trim(),
            selected: option.selected,
            disabled: option.disabled
        };
    }));
})()
JS;
        return (string) $this->getSession()->evaluateScript($script);
    }

    /**
     * Asserts that the native <select> of an autocomplete has an option with the
     * given text selected.
     *
     * This checks the value that is actually submitted with the form, which is
     * independent of the visible autocomplete chips. Placing this assertion at
     * the hand-over points (right after selection, after a failed validation
     * submit, and after save + reload) localises exactly where a selection is
     * lost (Behat 001 / catquiz_courses).
     *
     * @Then /^the autocomplete number "([^"]*)" for "([^"]*)" has "([^"]*)" natively selected$/
     *
     * @param string $numberofitem
     * @param string $dynamicidentifier
     * @param string $value
     *
     * @return void
     */
    public function autocomplete_should_have_natively_selected($numberofitem, $dynamicidentifier, $value) {
        $containerxpath = "(//div[contains(@id, '" . $dynamicidentifier . "')])[" . $numberofitem . "]";
        if ($this->native_autocomplete_has_selected_text($containerxpath, $value)) {
            return;
        }
        throw new \Behat\Mink\Exception\ExpectationException(
            "Native autocomplete select under '$dynamicidentifier' number '$numberofitem' "
                . "does not have '$value' selected. Native options: "
                . $this->get_native_autocomplete_debug($containerxpath),
            $this->getSession()
        );
    }
}
