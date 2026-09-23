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

namespace local_catquiz\local\calculation;

/**
 * Writes the aggregate item identifiability report (K5) into a calculation result.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait identifiability_aware {
    /**
     * Populate the response/person/item counts on the result.
     *
     * @param calculation_result $result
     * @param array|null $counts from catmodel_info::update_params
     * @return void
     */
    protected function apply_counts(calculation_result $result, ?array $counts): void {
        if (empty($counts)) {
            return;
        }
        $result->set('numresponses', (int) ($counts['numresponses'] ?? 0));
        $result->set('numpersons', (int) ($counts['numpersons'] ?? 0));
        $result->set('numitems', (int) ($counts['numitems'] ?? 0));
    }

    /**
     * Populate the AIC/BIC/CAIC before/after criteria and convergence metadata.
     *
     * The identifiability warnings (K5) are added to the result's warnings; the
     * numeric AIC/BIC/CAIC aggregates go into criteriabefore/criteriaafter.
     *
     * @param calculation_result $result
     * @param array $summary from catmodel_info::update_params
     * @return void
     */
    protected function apply_criteria(calculation_result $result, array $summary): void {
        if (isset($summary['criteriabefore'])) {
            $result->set('criteriabefore', $summary['criteriabefore']);
        }
        if (isset($summary['criteriaafter'])) {
            $result->set('criteriaafter', $summary['criteriaafter']);
        }
        if (isset($summary['iterations'])) {
            $result->set('iterations', (int) $summary['iterations']);
        }
        if (!empty($summary['convergencereason'])) {
            $result->set('convergencereason', $summary['convergencereason']);
        }
        // K5 identifiability warnings.
        $identifiability = $summary['identifiability'] ?? null;
        foreach (($identifiability['warnings'] ?? []) as $warning) {
            $result->add_warning($warning);
        }
    }
}
