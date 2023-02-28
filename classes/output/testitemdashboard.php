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

namespace local_catquiz\output;

use context_system;
use html_writer;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use local_catquiz\table\testitems_table;
use moodle_url;
use templatable;
use renderable;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testitemdashboard implements renderable, templatable {

    /** @var integer of testitemid */
    public int $testitemid = 0;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @return array
     */
    public function __construct(int $testitemid) {

        $this->testitemid = $testitemid;
    }

    /**
     * Render the moodle charts.
     *
     * @return void
     */
    private function render_modelcards() {

        global $OUTPUT;

        $returnarray = [];

        $modelitemparams = catmodel_info::get_item_parameters(0, $this->testitemid);

        foreach ($modelitemparams as $item) {

            $chart = new \core\chart_line();
            $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

            // Create the graph for difficulty.
            $series1 = new \core\chart_series('Difficulty 1 (Line)', $item['itemparameters']['difficulty']);
            $series2 = new \core\chart_series('Discrimination 1 (Line)', $item['itemparameters']['discrimination']);

            $chart->add_series($series1);
            $chart->add_series($series2);

            $count = 1;
            $label = [];
            while ($count <= count($item['itemparameters']['difficulty'])) {
                $label[] = "$count";
                $count++;
            }
            $chart->set_labels($label);

            $body = html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);

            $returnarray[]= [
                'title' => $item['modelname'],
                'body' => $body,
            ];
        }

        return $returnarray;
    }

    /**
     * Render the moodle charts.
     *
     * @return void
     */
    private static function render_testitemstats() {
        return [
            [
                'title' => 'stat a',
                'body' => 'xyz',
            ],
            [
                'title' => 'stat b',
                'body' => 'xyz',
            ]
        ];
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $url = new moodle_url('/local/catquiz/manage_catscales.php');

        return [
            'returnurl' => $url->out(),
            'models' => $this->render_modelcards(),
            'testitemstats' => $this->render_testitemstats(),
        ];
    }
}
