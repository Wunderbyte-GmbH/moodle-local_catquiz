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
 */
class behat_catquiz extends behat_base {
    /**
     * @When /^I fill in the "([^"]*)" element number "([^"]*)" with the dynamic identifier "([^"]*)" with "([^"]*)"$/
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
        // Now we get all the editor fields by the identifier.
        switch ($fieldtype) {
            case 'editor':
                $xpathtarget = "//div[contains(@id, '" . $dynamicidentifier . "')][@contenteditable='true']";
                break;
            case 'multiselect':
                $xpathtarget = "//div[contains(@id, '" . $dynamicidentifier . "')]";
                break;
            default:
                $xpathtarget = "//" . $fieldtype . "[contains(@id, '" . $dynamicidentifier . "')]";
        }
        //if ($fieldtype == 'div') {
        //    $xpathtarget = "//" . $fieldtype . "[contains(@id, '" . $dynamicidentifier . "')][@contenteditable='true']";
        //} else {
        //    $xpathtarget = "//" . $fieldtype . "[contains(@id, '" . $dynamicidentifier . "')]";
        //}
        // Assuming you want to find an editor element related to the competency and fill it with the specified value.
        $fields = $this->getSession()->getPage()->findAll('xpath', $xpathtarget);

        $counter = 1;
        foreach ($fields as $field) {
            if ($field->isVisible()) {
                if ($counter == (int) $numberofitem) {
                    switch ($fieldtype) {
                        case 'multiselect':
                            // Search target for multiselect inside parent.
                            //And I click on "NextMay (nextmay)" "text" in the "//div[contains(@id, 'id_datesheader_')]//ul[contains(@class, 'form-autocomplete-suggestions')]" "xpath_element"
                            //$field->find('xpath', "//span[contains(@id, 'form_autocomplete_downarrow-')]");
                            //$field->click();

                            $field->find('xpath', "//input[contains(@id, 'form_autocomplete_input-')]");
                            //var_dump($field->getHtml());
                            $field->setValue($value);
                            $field->keyPress(13); // Enter.
                            break;
                        default:
                            // Fill in the form field with the specified value.
                            $field->setValue($value);
                    }
                }
                $counter++;
            }
        }
    }

}
