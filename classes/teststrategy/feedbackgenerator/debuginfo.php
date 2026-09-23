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
 * Class debuginfo.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use context_system;
use local_catquiz\catquiz;
use local_catquiz\local\access\context_resolver;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\info;

/**
 * Returns debug info.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class debuginfo extends feedbackgenerator {
    /**
     * Default string for missing data
     *
     * @var string NA
     */
    const NA = 'NA';

    /**
     * Temporarily holds the data of the next row
     *
     * When full, will be added as a CSV styled row.
     *
     * @var array
     */
    private array $row = [];

    /**
     * Holds the column values
     *
     * @var array
     */
    private array $columns = [];

    /**
     * The data for the next row
     *
     * This contains the pool of data that can be used to build the next row.
     *
     * @var array
     */
    private array $rowdata = [];

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    public function get_studentfeedback(array $data): array {
        return [];
    }

    /**
     * Get teacher feedback.
     *
     * This renders a link with the href attribute set to data:text/csv and the
     * data contained within the link.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $data): array {
        global $OUTPUT, $DB, $CFG;

        // The export tab is always available to users with the feedback
        // permission. The raw debug exports below are added only when debug
        // storage is enabled for the plugin.
        $debugenabled = (bool) get_config('local_catquiz', 'store_debug_info');

        $csvstring = "";

        foreach (($data['debuginfo'] ?? []) as $row) {
            $newrow = $this->convert($row);
            $csvstring .= $this
                ->set_row_data($newrow)
                ->add_column_value('questionsattempted')
                ->add_column_value('timestamp')
                ->add_column_value('personabilities')
                ->add_column_value('activescales')
                ->add_column_value('lastmiddleware')
                ->add_column_value('lastresponse')
                ->add_column_value('state')
                ->add_column_value('rightanswer')
                ->add_column_value('responsesummary')
                ->add_column_value('originalfraction')
                ->add_column_value('fraction')
                ->add_column_value('questionattemptid')
                ->add_column_value('invaliditemparams')
                ->as_csv_string();
        }
        $heading = implode(';', $this->columns) . PHP_EOL;
        $csvstring = $heading . $csvstring;

        $attemptid = $data['attemptid'];

        // Resolve the context of this attempt through the central
        // resolver instead of querying the context table by hand with a hardcoded
        // context level, which silently produced no context when the attempt had
        // no course or the course context row was not found.
        $contextid = context_resolver::for_attempt($attemptid, $this->get_component())->id;

        $descriptionheading = get_string('debuginfo_desc_title', 'local_catquiz', $this->get_progress()->get_attemptid());
        $description = get_string('debuginfo_desc', 'local_catquiz');
        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/exportattempt',
            [
                'data' => rawurlencode($csvstring),
                'cid' => $contextid,
                'attemptid' => $attemptid,
                'description' => $description,
                'debuginfo_raw' => rawurlencode(nl2br(str_replace(" ", "&nbsp;", var_export($data, true)))),
                'cfg->root' => $CFG->wwwroot,
                'isteacher' => true,
                'debugenabled' => $debugenabled,
                'iscatmanager' => $debugenabled && has_capability(
                    'local/catquiz:canmanage',
                    context_system::instance()
                ),
            ]
        );

        if (empty($feedback)) {
            return [];
        } else {
            return [
                'heading' => $this->get_heading(),
                'content' => $feedback,
            ];
        }
    }

    /**
     * Converts from a possible old format to a new one.
     *
     * @param array $row The current row
     * @return array
     */
    private function convert(array $row): array {
        $updated = $row;
        if (is_array($row['lastresponse']) && array_key_exists('fraction', $row['lastresponse'])) {
            $updated['lastresponse'] = $row['lastresponse']['fraction'];
        }
        return $updated;
    }

    /**
     * Formats the collected "unusable item parameters" warnings for the debug output.
     *
     * pilotquestions_loader collects a warning whenever an item had to be demoted to
     * a pilot item because its stored parameters violate its model contract (for
     * example a 2PL item with discrimination 0, which is mathematically mute and
     * would freeze the ability estimate). Surfacing item id, model and the concrete
     * reason here makes such an item traceable from the attempt debug export/PDF.
     *
     * @param array $warnings
     *
     * @return string
     */
    private static function format_invalid_itemparams(array $warnings): string {
        if ($warnings === []) {
            return self::NA;
        }

        $lines = [];
        foreach ($warnings as $warning) {
            $lines[] = sprintf(
                '%s (%s / model "%s"): %s',
                (string) ($warning['itemid'] ?? '?'),
                (string) ($warning['label'] ?? ''),
                (string) ($warning['model'] ?? ''),
                (string) ($warning['reason'] ?? '')
            );
        }

        return '"' . implode('; ', $lines) . '"';
    }

    /**
     * Sets the data for the current row
     *
     * Used by add_column_value()
     *
     * @param array $row
     * @return self
     */
    private function set_row_data(array $row): self {
        $this->rowdata = $row;
        $this->row = [];
        return $this;
    }

    /**
     * Adds a value to the current row
     *
     * @param string $key
     * @return $this
     */
    private function add_column_value(string $key): self {
        if (!in_array($key, $this->columns)) {
            $this->columns[] = $key;
        }
        $this->row[] = $this->rowdata[$key] ?? self::NA;
        return $this;
    }

    /**
     * Implodes the current row by semicolon
     *
     * @return string
     */
    private function as_csv_string(): string {
        return implode(';', $this->row) . PHP_EOL;
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return 'Export';
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'debuginfo';
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return ['debuginfo'];
    }

    /**
     * Get question details.
     *
     * @param array $context
     *
     * @return mixed
     *
     */
    private function get_question_details(array $context) {
        return array_map(fn ($q) => (array) $q, $context['questions']);
    }

    /**
     * Load data.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {

        // Note: This has to be redone as well.
        if (!get_config('local_catquiz', 'store_debug_info')) {
            return null;
        }

        $teststrategy = $this->get_progress()->get_quiz_settings()->catquiz_selectteststrategy;

        $teststrategies = info::return_available_strategies();
        $teststrategy = array_filter(
            $teststrategies,
            fn ($t) => $t->id == $teststrategy
        );
        $reflect = new \ReflectionClass($teststrategy[array_key_first($teststrategy)]);

        $debuginfo = $existingdata['debuginfo'] ?? [];
        $catscales = catquiz::get_catscales(array_keys($newdata['person_ability']));
        $teststrategy = get_string($reflect->getShortName(), 'local_catquiz');

        $personabilities = [];
        foreach ($newdata['person_ability'] as $catscaleid => $pp) {
            if (empty($catscales[$catscaleid])) {
                continue;
            }
            $personabilities[] = $catscales[$catscaleid]->name . ": " . $pp;
        }

        $personabilities = '"' . implode(", ", $personabilities) . '"';

        $questions = [];
        $questionsperscale = [];

        $activescales = array_map(
            fn ($scaleid) => $catscales[$scaleid]->name,
            $this->get_progress()->get_active_scales()
        );
        $lastresponse = $this->get_progress()->get_last_response();
        $debuginfo[] = [
            'pluginversion' => get_config('local_catquiz')->version ?? self::NA,
            'attemptid' => $attemptid,
            'questionsattempted' => count($this->get_progress()->get_playedquestions()),
            'timestamp' => time(),
            'personabilities' => $personabilities,
            'questions' => $questions,
            'activescales' => '"' . implode(", ", $activescales) . '"',
            // On the first question of an attempt these keys do not exist,
            // and catquiz.php removes lastquestion from the attempt data outright. On
            // a normal instance (array) null is simply [], but with DEBUG_DEVELOPER
            // Moodle turns the notice into an exception. It is caught in
            // return_next_testitem() and reported as "couldn't define the first
            // question", pointing at the configuration instead of at this diagnostic
            // helper - so debug information was unobtainable exactly where it is
            // wanted. The neighbouring fields have always been guarded.
            'lastquestion' => (array) ($newdata['lastquestion'] ?? []),
            'lastmiddleware' => $newdata['lastmiddleware'] ?? self::NA,
            'lastresponse' => isset($lastresponse) ? $lastresponse['fraction'] : self::NA,
            'numquestionsperscale' => '"'
                . implode(", ", array_map(fn ($entry) => $entry['name'] . ": " . $entry['num'], $questionsperscale)) . '"',
            'state' => isset($lastresponse['state']) ? $lastresponse['state'] : self::NA,
            'rightanswer' => isset($lastresponse['rightanswer']) ? trim($lastresponse['rightanswer']) : self::NA,
            'responsesummary' => isset($lastresponse['responsesummary']) ? trim($lastresponse['responsesummary']) : self::NA,
            'originalfraction' => isset($lastresponse['originalfraction']) ? $lastresponse['originalfraction'] : self::NA,
            'fraction' => isset($lastresponse['fraction']) ? $lastresponse['fraction'] : self::NA,
            'questionattemptid' => isset($lastresponse['questionattemptid']) ? $lastresponse['questionattemptid'] : self::NA,
            'invaliditemparams' => self::format_invalid_itemparams($newdata['invaliditemparams'] ?? []),
        ];

        return ['debuginfo' => $debuginfo];
    }

    /**
     * Overwrite the inherited method to allow access only to CAT managers.
     *
     * @return bool
     */
    protected function has_teacherfeedbackpermission(): bool {
        // The debug output exposes internals of the estimation, so a CAT
        // manager always sees it. In addition a teacher of the course the attempt
        // belongs to may see it - checked in the attempt's own context, never in
        // the system context.
        //
        // This method previously contained the course based check behind an
        // unconditional return, so it was dead code that never ran (and would have
        // fatalled on the private $attemptid property if it had).
        if (has_capability('local/catquiz:canmanage', context_system::instance())) {
            return true;
        }

        return has_capability('local/catquiz:view_users_feedback', $this->get_attempt_context());
    }

    /**
     * Overwrite the inherited method to allow access only to CAT managers.
     *
     * @return bool
     */
    protected function has_catmanagerpermission(): bool {
        return has_capability(
            'local/catquiz:canmanage',
            context_system::instance()
        );
    }
}
