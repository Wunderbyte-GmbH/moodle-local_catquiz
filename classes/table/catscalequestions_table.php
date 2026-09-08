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
 * Class catscalequestions_table.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

use cache_helper;
use coding_exception;
use html_writer;
use local_catquiz\catscale;
use local_wunderbyte_table\wunderbyte_table;
use context_system;
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\event\catscale_updated;
use local_catquiz\local\itemparam_validity;
use local_catquiz\local\model\model_item_param;
use local_wunderbyte_table\output\table;
use moodle_url;

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscalequestions_table extends wunderbyte_table {
    /**
     * Whether the question text restriction has already been applied.
     * @var bool
     */
    private bool $questiontextsearchapplied = false;
    /** @var int $catscaleid */
    private $catscaleid = 0;

    /** @var int */
    private $contextid = 0;

    /**
     * As we don't allow the constructor anymore in wb table, we must set the values like this.
     * But to avoid caching wrong values, they both must appear in the idstring.
     * @param int $catscaleid
     * @param int $contextid
     * @return void
     * @throws dml_exception
     */
    public function set_catscaleid_and_contextid(int $catscaleid = 0, int $contextid = 0) {
        $this->catscaleid = $catscaleid;
        $this->contextid = $contextid;
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_userid($values) {
        return $values->id;
    }

    /**
     * Overrides the output for action column.
     *
     * @param mixed $values
     *
     * @return string
     *
     */
    public function col_action($values) {
        global $OUTPUT;

        $url = new moodle_url('manage_catscales.php', [
            'id' => $values->id,
            'contextid' => $this->contextid,
            'scaleid' => $values->catscaleid ?? 0,
            'component' => $values->component ?? "",
            // The tab is a request parameter now; the fragment it used to
            // use points at a pane that is no longer rendered unless it is active.
            'tab' => 'questions',
        ]);

        $data['showactionbuttons'][] = [
            'class' => 'btn btn-plain btn-smaller',
            'iclass' => empty($values->testitemstatus) ? 'fa fa-eye' : 'fa fa-eye-slash',
            'arialabel' => empty($values->testitemstatus) ? 'eye icon' : 'eye icon slashed',
            'title' => get_string('eyeicontitle', 'local_catquiz'),
            'href' => '#',
            'id' => $values->id,
            'methodname' => 'togglestatus', // The method needs to be added to your child of wunderbyte_table class.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'testitemstatus' => !empty($values->testitemstatus) ? $values->testitemstatus : "",
                'catscaleid' => $values->catscaleid ?? $this->catscaleid,
                'titlestring' => 'toggleactivity', // Will be shown in modal title.
                'bodystring' => 'confirmactivitychange', // Will be shown in modal body.
                'component' => 'local_catquiz',
                'labelcolumn' => 'name',
            ],
        ];

        $data['showactionbuttons'][] = [
                'class' => 'btn btn-plain btn-smaller',
                'iclass' => 'fa fa-cog',
                'arialabel' => 'cogwheel',
                'title' => get_string('cogwheeltitle', 'local_catquiz'),
                'href' => $url->out(false),
                'methodname' => 'managedetails',
                'nomodal' => true,
                'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                    'id' => 'id',
                ],
            ];
        $data['showactionbuttons'][] = [
            'class' => 'btn btn-plain btn-smaller',
            'iclass' => 'fa fa-trash',
            'arialabel' => 'trash bin',
            'title' => get_string('trashbintitle', 'local_catquiz'),
            'id' => $values->id,
            'href' => '#',
            'methodname' => 'removetestitem',
            'nomodal' => false,
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'questionid' => $values->id,
                'id' => $values->id,
                'catscaleid' => $values->catscaleid ?? $this->catscaleid,
                'titlestring' => 'deletedatatitle', // Will be shown in modal title.
                'bodystring' => 'confirmdeletion', // Will be shown in modal body in case elements are selected.
                'component' => 'local_catquiz',
                'labelcolumn' => 'name', // Verify value of record that will be deleted.
            ],
        ];
        table::transform_actionbuttons_array($data['showactionbuttons']);
        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);
    }


    /**
     * Overrides the output for action column.
     *
     * @param mixed $values
     *
     * @return string
     *
     */
    public function col_view($values) {

        global $OUTPUT;

        $url = new moodle_url('manage_catscales.php', [
            'id' => $values->id,
            'contextid' => $this->contextid,
            'scaleid' => $values->catscaleid ?? $this->catscaleid,
            'component' => $values->component ?? "",
        ], 'questions');

        $data['showactionbuttons'][] = [
            'label' => get_string('view', 'core'), // Name of your action button.
            'class' => 'btn btn-plain btn-smaller',
            'iclass' => 'fa fa-edit',
            'href' => $url->out(false),
            'id' => $values->id,
            'methodname' => '', // The method needs to be added to your child of wunderbyte_table class.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => $values->id,
            ],
        ];

        // This transforms the array to make it easier to use in mustache template.
        table::transform_actionbuttons_array($data['showactionbuttons']);

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);
    }

    /**
     * Return value for lastattempttime column.
     *
     * @param \stdClass $values
     * @return string
     */
    public function col_astatlastattempttime($values) {

        if (intval($values->astatlastattempttime) === 0) {
            return get_string('notyetcalculated', 'local_catquiz');
        }
        return userdate($values->astatlastattempttime);
    }

    /**
     * Return symbols for status column.
     *
     * @param \stdClass $values
     * @return string
     */
    public function col_status($values) {

        if ($this->is_downloading()) {
            return !empty($values->status) ? $values->status : LOCAL_CATQUIZ_STATUS_NOT_CALCULATED;
        }
        $bootstrapclass = "";
        $status = $values->status ?? LOCAL_CATQUIZ_STATUS_NOT_CALCULATED;

        switch ($status) {
            case LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY:
                $bootstrapclass = LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY_COLOR_CLASS;
                break;
            case LOCAL_CATQUIZ_STATUS_CALCULATED:
                $bootstrapclass = LOCAL_CATQUIZ_STATUS_CALCULATED_COLOR_CLASS;
                break;
            case LOCAL_CATQUIZ_STATUS_NOT_CALCULATED:
                $bootstrapclass = LOCAL_CATQUIZ_STATUS_NOT_CALCULATED_COLOR_CLASS;
                break;
            case LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY:
                $bootstrapclass = LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY_COLOR_CLASS;
                break;
            case LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY:
                $bootstrapclass = LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY_COLOR_CLASS;
                break;
        }

        $labelstring = "itemstatus_" . $status;

        return html_writer::tag('i', "", [
            "class" => "fa fa-circle $bootstrapclass",
            "aria-label" => get_string($labelstring, 'local_catquiz'),
            "title" => get_string($labelstring, 'local_catquiz'),
        ]);
    }

    /**
     * Return strings for column type.
     *
     * @param \stdClass $values
     * @return string
     */
    public function col_qtype($values) {

        if (!empty($values->qtype)) {
            return get_string('pluginname', 'qtype_' . $values->qtype);
        }

        return "problem with $values->id, no qtype";
    }


    /**
     * Upper bound for the id list produced by the question text search.
     *
     * A two letter search term can match most of the question bank, and an IN()
     * clause with tens of thousands of ids is a performance problem of its own.
     * @var int
     */
    const QUESTIONTEXT_SEARCH_LIMIT = 2000;

    /**
     * Scale ids and context this table was built for, for the light count query.
     * @var array|null
     */
    private ?array $countcontext = null;

    /**
     * Context whose attempt counts are shown, or null when the column is not used.
     * @var int|null
     */
    private ?int $contextattemptscontext = null;

    /**
     * Remembers what the light count query needs to know.
     *
     * @param array $catscaleids
     * @param int $contextid
     * @return void
     */
    public function set_count_context(array $catscaleids, int $contextid): void {
        $this->countcontext = ['catscaleids' => $catscaleids, 'contextid' => $contextid];
    }

    /**
     * Counts the list without computing the attempt statistics.
     *
     * Counting the list meant counting the rows of the full query - the
     * one carrying the per question and per user aggregates. Those are computed only
     * to be discarded by COUNT(). The row set is defined by the joins up to the
     * question bank; the statistics are LEFT JOINs and can neither add nor remove a
     * row, so a light query returns the same number for a fraction of the work.
     *
     * Set here rather than when the table is built, for two reasons:
     *
     *   * The table library appends its own filter and search to $this->sql->where
     *     AFTER the table is set up. A count fixed earlier would ignore them and
     *     report a total that does not match the list - pagination would then offer
     *     pages that are empty.
     *   * The free text search may match on columns that only exist in the
     *     aggregates (the last attempt time among them). With such a condition the
     *     light query cannot answer the question at all.
     *
     * So the light count is used only while neither applies; otherwise the library
     * falls back to its own behaviour, which stays correct.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        if ($this->countsql === null && $this->can_use_light_count()) {
            [$from, $where, $params] = catquiz::return_sql_for_catscalequestions_count(
                $this->countcontext['catscaleids'],
                $this->countcontext['contextid']
            );
            $this->countsql = "SELECT COUNT(*) FROM $from WHERE $where";
            $this->countparams = $params;
        }

        $restore = $this->push_limit_into_subquery($pagesize);

        parent::query_db($pagesize, $useinitialsbar);

        if ($restore !== null) {
            $this->sql->from = $restore;
        }

        $this->attach_contextattempts();
    }

    /**
     * Moves the page limit into the derived table when that is provably equivalent.
     *
     * The statement reads FROM ( SELECT ... ) as s1, and Moodle applies the limit to
     * the outer query. MariaDB then materialises the whole derived table before
     * taking ten rows - measured with ANALYZE: 20.010 loops with four index lookups
     * each, for a page of ten. PostgreSQL stops early and is unaffected.
     *
     * Pushing the limit inside asks the same question only while the outer query
     * neither sorts nor filters. Otherwise the inner limit would take an arbitrary
     * ten rows and the page would look correct while showing the wrong ones. Both are
     * checked, and the rewrite is skipped when either is present - which still leaves
     * the common case covered: the dialog as it opens.
     *
     * @param int $pagesize
     * @return string|null The original FROM to restore afterwards, or null.
     */
    private function push_limit_into_subquery(int $pagesize): ?string {
        if ($pagesize <= 0 || $this->is_downloading()) {
            return null;
        }

        // A filter stays outside, and a page that has one keeps the old path.
        //
        // Moving it inside looked possible - the columns it names are selected there
        // too - but they are *aliases*: the inner query has `qc.name as categoryname`,
        // and SQL does not allow an alias in the WHERE of the same level. A filter on
        // categoryname fails inside while working outside, and the failure is a
        // database error on a page that used to work.
        //
        // ORDER BY is different, which is why sorting could move: aliases are allowed
        // there. The two clauses look interchangeable and are not.
        if (trim((string) ($this->sql->filter ?? '')) !== '') {
            return null;
        }

        // Sorting can move inside, but only if every column it names exists there.
        // The derived table selects id, idnumber, name, qtype and categoryname; a
        // sort on anything else - an outer alias such as questioncontextattempts, a
        // constant - would be an unknown column and break the query.
        $sort = trim((string) $this->get_sql_sort());
        if ($sort !== '' && !$this->sort_is_available_in_subquery($sort)) {
            return null;
        }

        $where = trim((string) $this->sql->where);
        if ($where !== '1=1' && $where !== '1 = 1') {
            return null;
        }

        // Only the shape this was measured against.
        if (!preg_match('/^\\s*\\(\\s*SELECT\\b(.*)\\)\\s*as\\s+(\\w+)\\s*$/is', (string) $this->sql->from, $m)) {
            return null;
        }

        $original = (string) $this->sql->from;

        // LIMIT ... OFFSET is understood by both engines this plugin supports; the
        // DML layer offers no portable helper for a limit inside a subquery.
        $this->sql->from = sprintf(
            '( SELECT %s %s LIMIT %d OFFSET %d ) as %s',
            $m[1],
            $sort === '' ? '' : 'ORDER BY ' . $sort,
            $pagesize,
            (int) $this->currpage * $pagesize,
            $m[2]
        );

        return $original;
    }


    /**
     * Enables loading the attempt count for the visible page.
     *
     * @param int $contextid
     * @return void
     */
    public function set_contextattempts_context(int $contextid): void {
        $this->contextattemptscontext = $contextid;
    }

    /**
     * Fills in the attempt count for the rows on this page.
     *
     * The count used to come from the main query, which produced it by
     * aggregating every question attempt of the context and joining the result onto
     * all candidates. That aggregate is driven by the number of attempt steps rather
     * than by the page size, so it cost the same whether ten rows were shown or none
     * - about eight seconds of a twelve second query in the measured instance.
     *
     * One additional query for the visible ids replaces it. Not one per row: that
     * would trade a slow page for a slower one.
     *
     * @return void
     */
    private function attach_contextattempts(): void {
        if ($this->contextattemptscontext === null || empty($this->rawdata)) {
            return;
        }

        $questionids = [];
        foreach ($this->rawdata as $row) {
            if (isset($row->id)) {
                $questionids[] = (int) $row->id;
            }
        }

        if (empty($questionids)) {
            return;
        }

        $counts = catquiz::get_contextattempts_for_questions(
            $questionids,
            $this->contextattemptscontext
        );

        foreach ($this->rawdata as $row) {
            // Absent means the question has no attempts in this context, which is
            // zero - not "unknown". The previous LEFT JOIN said the same thing.
            $row->questioncontextattempts = $counts[(int) $row->id] ?? 0;
        }
    }

    /**
     * Whether the light count can answer the current query.
     *
     * @return bool
     */
    private function can_use_light_count(): bool {
        if ($this->countcontext === null) {
            return false;
        }

        // Any filter or search the library added narrows the list in a way the light
        // query does not know about.
        if (!empty($this->sql->filter) || !empty($this->searchtext)) {
            return false;
        }

        // The question text search restricts by id and is not part of the light
        // query either.
        if ($this->questiontextsearchapplied) {
            return false;
        }

        return true;
    }

    /**
     * Narrows the list to questions whose text matches, as the SQL is defined.
     *
     * The list queries do not select questiontext, which also removed the
     * ability to search inside question texts. Carrying the text in every row just
     * so that it can be searched is precisely what made the lists slow, so the text
     * is consulted only when somebody actually searches: a small dedicated query
     * resolves the matching question ids and the list is narrowed to them.
     *
     * This is a separate restriction with AND semantics, not part of the table's own
     * free text box: that box searches a concatenated column which would have to
     * contain the question text again, which is the thing we removed.
     *
     * The restriction has to be applied here rather than while rendering. The table
     * serialises its own $sql into an encoded, cached instance that later AJAX
     * reloads are built from; a restriction added at render time is not part of that
     * snapshot, so the first page looked filtered and every reload silently showed
     * the unfiltered list again.
     *
     * @param string $fields
     * @param string $from
     * @param string $where
     * @param string $filter
     * @param array $params
     * @return void
     */
    public function set_filter_sql(string $fields, string $from, string $where, string $filter, array $params = []) {
        parent::set_filter_sql($fields, $from, $where, $filter, $params);

        $this->apply_questiontext_search();
    }

    /**
     * Appends the id restriction for the current question text search term.
     *
     * @return void
     */
    public function apply_questiontext_search(): void {
        global $DB;

        if ($this->questiontextsearchapplied) {
            return;
        }

        $searchtext = trim(optional_param('qtsearch', '', PARAM_TEXT));
        if ($searchtext === '') {
            return;
        }
        $this->questiontextsearchapplied = true;

        $ids = self::resolve_questiontext_matches($searchtext);

        if ($ids === null) {
            // Too many matches to restrict by id; leave the list untouched rather
            // than building a huge IN() clause.
            return;
        }

        if (empty($ids)) {
            // Nothing matched. The correct answer is an empty list, not the
            // unfiltered one.
            $this->sql->where .= ' AND 1=0 ';
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'catquizqtid');
        $this->sql->where .= " AND id $insql ";
        $this->sql->params = array_merge($this->sql->params, $inparams);
    }

    /**
     * Returns the ids of questions whose text matches, or null if there are too many.
     *
     * @param string $searchtext
     * @return int[]|null
     */
    public static function resolve_questiontext_matches(string $searchtext): ?array {
        global $DB;

        $like = $DB->sql_like('questiontext', ':catquizqtsearch', false, false);
        $ids = $DB->get_fieldset_sql(
            "SELECT id FROM {question} WHERE $like",
            ['catquizqtsearch' => '%' . $DB->sql_like_escape($searchtext) . '%']
        );

        if (count($ids) > self::QUESTIONTEXT_SEARCH_LIMIT) {
            return null;
        }

        return array_map('intval', $ids);
    }

    /**
     * Shows whether the stored item parameters are usable for the item's model.
     *
     * An item whose parameters violate the model contract is silently
     * treated as a pilot item at runtime. That was visible only in the import
     * feedback and the attempt debug output, never where the pool is maintained.
     *
     * The classification is computed from the row the list already selected - model,
     * difficulty, discrimination, guessing and json are part of the query - so this
     * column costs no additional query per item.
     *
     * The state is conveyed by text and an icon, not by colour alone.
     *
     * @param object $values
     * @return string
     */
    public function col_itemparamvalidity($values) {
        $classification = itemparam_validity::classify((object) (array) $values);
        $label = itemparam_validity::get_state_label($classification['state']);

        if ($classification['state'] !== itemparam_validity::STATE_UNUSABLE) {
            return html_writer::span(s($label), 'catquiz-itemparams-' . $classification['state']);
        }

        $reason = itemparam_validity::get_reason_text($classification);

        return html_writer::span(
            html_writer::tag('i', '', [
                'class' => 'fa fa-exclamation-triangle mr-1',
                'aria-hidden' => 'true',
            ]) . s($label),
            'catquiz-itemparams-unusable text-danger',
            ['title' => $reason]
        ) . html_writer::span(s($reason), 'sr-only');
    }

    /**
     * Returns the short label shown in the list for a question.
     *
     * Falls back through the fields the different list queries provide, so both
     * the scale question list and the "add items" list get a sensible label
     * without either of them selecting the question text.
     *
     * @param object $values
     * @return string
     */
    private function get_question_label($values): string {
        foreach (['questionname', 'name', 'label', 'idnumber'] as $field) {
            if (!empty($values->{$field})) {
                return format_string($values->{$field});
            }
        }

        return get_string('question', 'core') . ' ' . (int) $values->id;
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_name($values) {
        global $OUTPUT;

        // The list no longer selects or renders the question text. The
        // row shows the question name and opens the preview on demand through
        // local_catquiz_get_question_preview, so a page of rows no longer carries
        // the formatted text - and any embedded images - of every question.
        $data = [
            'label' => $this->get_question_label($values),
            'id' => $values->id,
        ];

        return $OUTPUT->render_from_template('local_catquiz/modals/modal_questionpreview', $data);
    }

    /**
     * Function to handle the action buttons.
     * @param int $testitemid
     * @param string $data
     * @return array
     */
    public function action_removetestitem(int $testitemid, string $data) {
        global $USER;

        $jsonobject = json_decode($data);

        $catscaleid = $jsonobject->catscaleid;

        if ($testitemid == -1) {
            if (gettype($jsonobject->checkedids) == 'string') {
                $idarray = explode(',', $jsonobject->checkedids);
            } else if (gettype($jsonobject->checkedids) == 'array') {
                $idarray = $jsonobject->checkedids;
            } else {
                $idarray = [$jsonobject->checkedids[0]];
            }
        } else if ($testitemid > 0) {
            $idarray = [$testitemid];
        }

        foreach ($idarray as $id) {
            catscale::remove_testitem_from_scale($catscaleid, $id);
        }

        $event = catscale_updated::create([
            'objectid' => $catscaleid,
            'context' => context_system::instance(),
            'userid' => $USER->id,
            'other' => [
                'catscaleid' => $catscaleid,
            ],
        ]);
        $event->trigger();

        return [
            'success' => 1,
            'message' => get_string('success'),
        ];
    }

    /**
     * Toggle status to set item active / inactive.
     * @param int $id
     * @param string $data
     * @return array
     */
    public function action_togglestatus(int $id, string $data) {

        $jsonobject = json_decode($data);

        $catscaleid = $jsonobject->catscaleid;
        $status = empty($jsonobject->testitemstatus) ?
        LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE : LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE;

        catscale::add_or_update_testitem_to_scale((int)$catscaleid, $id, $status);

        return [
            'success' => 1,
            'message' => get_string('success'),
        ];
    }
    /**
     * Return string of parentscale like "childscale|parentscale|grandparentscale".
     *
     * @param \stdClass $values
     * @return string
     */
    public function col_parentscalenames($values) {

        $ancestors = catscale::get_ancestors($values->catscaleid, 2);
        return implode('|', $ancestors);
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     *
     * @return string
     */
    public function col_idnumber($values) {
        return html_writer::tag('span', $values->idnumber, ['class' => 'badge badge-primary']);
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_questiontext($values) {
        global $OUTPUT;

        // The list no longer selects or renders the question text. The
        // row shows the question name and opens the preview on demand through
        // local_catquiz_get_question_preview, so a page of rows no longer carries
        // the formatted text - and any embedded images - of every question.
        $data = [
            'label' => $this->get_question_label($values),
            'id' => $values->id,
        ];

        return $OUTPUT->render_from_template('local_catquiz/modals/modal_questionpreview', $data);
    }


    /**
     * Overrides the output for questioncontextattempts column.
     *
     * @param mixed $values
     *
     * @return string
     *
     */
    public function col_questioncontextattempts($values) {
        return $values->questioncontextattempts;
    }

    /**
     * Override the model value to set a string for missing values.
     *
     * @param mixed $values
     * @return string
     * @throws coding_exception
     */
    public function col_model($values): string {
        if (!$values->model) {
            return get_string('notavailable', 'core');
        }
        return $values->model;
    }

    /**
     * Function to handle the action buttons.
     * @param int $testitemid
     * @param string $data
     * @param bool $overridecatscale // When true, an item already assigned to a catscale of the same tree will be updated.
     * @return array
     */
    public static function action_addtestitem(int $testitemid, string $data, bool $overridecatscale = false) {

        $jsonobject = json_decode($data);

        $catscaleid = $jsonobject->catscaleid;

        if ($catscaleid == -1) {
            return [
                'success' => 0,
                'message' => get_string('noscaleselected', 'local_catquiz'),
            ];
        }

        if ($testitemid == -1) {
            $idarray = $jsonobject->checkedids;
            if (gettype($idarray) === "string") {
                $idarray = explode(",", $idarray);
            }
        } else if ($testitemid > 0) {
            $idarray = [$testitemid];
        }

        foreach ($idarray as $id) {
            $result[] = catscale::add_or_update_testitem_to_scale(
                $catscaleid,
                $id,
                LOCAL_CATQUIZ_TESTITEM_STATUS_UNDEFINED,
                'question',
                $overridecatscale
            );
        }
        $failed = array_filter($result, fn($r) => $r->isErr());

        // All items were added successfully.
        if (empty($failed)) {
            return [
                'success' => 1,
                'message' => get_string('success'),
            ];
        }

        // If a single item could not be added, show a specific error message.
        if (count($idarray) === 1 && count($failed) === 1) {
            return [
                'success' => 0,
                'message' => $failed[0]->getErrorMessage(),
            ];
        }

        // Multiple items could not be added.
        $numadded = count($result) - count($failed);
        $failedids = array_map(fn($f) => $f->unwrap(), $failed);
        return [
            'success' => 0,
            'message' => get_string(
                'failedtoaddmultipleitems',
                'local_catquiz',
                [
                    'numadded' => $numadded,
                    'numfailed' => count($failed),
                    'failedids' => implode(',', $failedids),
                ]
            ),
        ];
    }
    /**
     * Whether every column of a sort clause exists inside the derived table.
     *
     * The outer select adds columns the inner one does not have - a literal
     * 'question' as component, 0 as questioncontextattempts. Sorting by those inside
     * would fail with "unknown column", so the rewrite has to recognise them and step
     * aside rather than produce a broken statement.
     *
     * @param string $sort The clause as get_sql_sort() returns it.
     * @return bool
     */
    private function sort_is_available_in_subquery(string $sort): bool {
        // What return_sql_for_addcatscalequestions() selects in its inner query.
        $available = ['id', 'idnumber', 'name', 'qtype', 'categoryname'];

        foreach (explode(',', $sort) as $part) {
            $column = strtolower(trim(preg_replace('/\\s+(asc|desc)$/i', '', trim($part))));

            // A qualified name would refer to the outer alias, which does not exist
            // inside either.
            if ($column === '' || str_contains($column, '.')) {
                return false;
            }

            if (!in_array($column, $available, true)) {
                return false;
            }
        }

        return true;
    }
}
