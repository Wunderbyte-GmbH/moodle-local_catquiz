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

namespace local_catquiz\output\catscalemanager\testsandtemplates;

use html_writer;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\output\testenvironmentdashboard;
use local_catquiz\table\catscalequestions_table;
use moodle_url;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testsandtemplatesdisplay {


    /**
     * @var integer
     */
    private int $scaleid = 0; // The selected scale.


    /**
     * Constructor.
     *
     * @param int $catscaleid
     *
     */
    public function __construct(int $catscaleid = 0) {
        $this->scaleid = $catscaleid;
    }

    /**
     * Renders the scale selector.
     * @return string
     */
    private function render_scaleselectors() {
        $selectors = $this->render_selector($this->scaleid);
        $ancestorids = catscale::get_ancestors($this->scaleid);
        if (count($ancestorids) > 0) {
            foreach ($ancestorids as $ancestorid) {
                $selector = $this->render_selector($ancestorid);
                $selectors = "$selector <br> $selectors";
            }
        }
        $childids = catscale::get_subscale_ids($this->scaleid);
        if (count($childids) > 0) {
            // If the selected scale has subscales, we render a selector to choose them with no default selection.
            $subscaleselector = $this->render_selector($childids[0], true);
            $selectors .= "<br> $subscaleselector";
        }
        return $selectors;
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
    private function render_selector($scaleid, $noselection = false, $label = 'selectcatscale') {
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

    private function render_table() {

        $testenvironmentdashboard = new testenvironmentdashboard();
        return $testenvironmentdashboard->testenvironmenttable($this->scaleid);
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_data_array(): array {

        $data = [
            'scaleselectors' => empty($this->render_scaleselectors()) ? "" : $this->render_scaleselectors(),
            'table' => $this->render_table(),

        ];

        return $data;
    }
}
