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

namespace local_catquiz\output\catscalemanager;

use html_writer;
use local_catquiz\catscale;

/**
 * Class to render scale and context selector.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scaleandcontexselector {

    /**
     * Renders the context selector.
     *
     * @param int $catcontextid
     *
     * @return mixed
     *
     */
    public static function render_contextselector(int $catcontextid) {
        $ajaxformdata = empty($catcontextid) ? [] : ['contextid' => $catcontextid];

        $customdata = [
            "hideheader" => true,
            "hidelabel" => false,
            "labeltext" => get_string('versionchosen', 'local_catquiz'),
        ];

        $form = new \local_catquiz\form\contextselector(null, $customdata, 'post', '', [], true, $ajaxformdata);
        // Set the form data with the same method that is called when loaded from JS.
        // It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'lcq_select_context_form']);
    }

    /**
     * Renders the scale selector.
     *
     * @param int $scale
     *
     * @return mixed
     *
     */
    public static function render_scaleselectors(int $scale) {
        $selectors = self::render_selector($scale);
        $ancestorids = catscale::get_ancestors($scale);
        if (count($ancestorids) > 0) {
            foreach ($ancestorids as $ancestorid) {
                $selector = self::render_selector($ancestorid);
                $selectors = "$selector <br> $selectors";
            }
        }
        $childids = catscale::get_subscale_ids($scale);
        if (count($childids) > 0) {
            // If the selected scale has subscales, we render a selector to choose them with no default selection.
            $subscaleselector = self::render_selector($childids[0], true);
            $selectors .= "<br> $subscaleselector";
        }
        return $selectors;
    }

    /**
     * Renders the scale selector.
     *
     * @param int $scale
     *
     * @return string
     *
     */
    public static function render_rootscaleselector(int $scale) {
        $ancestorids = catscale::get_ancestors($scale);
        $catscaleid = end($ancestorids) ?: $scale;
        $selector = self::render_selector($catscaleid);
        return $selector;
    }

    /**
     * Renders the scale selector.
     *
     * @param mixed $scaleid
     * @param bool $noselection
     * @param string $label
     *
     * @return string
     *
     */
    public static function render_selector($scaleid, $noselection = false, $label = 'selectcatscale') {
        $selected = $noselection ? 0 : $scaleid;
        $ajaxformdata = [
                        'scaleid' => $scaleid,
                        'selected' => $selected,
                        ];
        $customdata = [
            'type' => 'scale',
            'label' => $label, // String localized in 'local_catquiz'.
        ];

        $form = new \local_catquiz\form\scaleselector(null, $customdata, 'post', '', [], true, $ajaxformdata);
        // Set the form data with the same method that is called when loaded from JS.
        // It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_scale_form_scaleid_' . $scaleid]);
    }

    /**
     * If checked subscales are integrated in the table query.
     * @param int $usesubs
     * @return array
     */
    public static function render_subscale_checkbox(int $usesubs) {
        $checked = "checked";
        if ($usesubs < 1) {
            $checked = "";
        }
        $checkboxarray = [
            'label' => get_string('integratequestions', 'local_catquiz'),
            'checked' => $checked,
        ];

        return $checkboxarray;
    }
}
