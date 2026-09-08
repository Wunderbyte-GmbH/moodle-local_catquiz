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
 * Builds a synthetic body of data in the shape of a production instance.
 *
 * Only used by explain_hotqueries.php on an otherwise empty installation. The
 * proportions matter more than the absolute numbers: a large question bank of which
 * only a small fraction are CAT items is what makes the "add question" query
 * interesting, because that one walks the whole bank.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seeds questions, CAT items, item parameters and attempts.
 *
 * @param int $questions
 * @param int $items
 * @param int $itemparams
 * @param int $attempts
 * @return void
 */
function catquiz_explain_seed(int $questions, int $items, int $itemparams, int $attempts): void {
    global $DB;

    $now = time();
    $batchsize = 2000;

    $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
        'name' => 'Explain context',
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'starttimestamp' => $now - 86400,
        'endtimestamp' => $now + 86400,
        'timecreated' => $now,
        'timemodified' => $now,
        'usermodified' => 0,
    ]);
    $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
        'parentid' => 0,
        'name' => 'Explain scale',
        'contextid' => $contextid,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
    $categoryid = (int) $DB->insert_record('question_categories', (object) [
        'name' => 'Explain category',
        'contextid' => context_system::instance()->id,
        'info' => '',
        'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(),
        'parent' => 0,
        'sortorder' => 0,
    ]);

    mtrace("Seeding $questions questions ...");
    $questionids = [];
    for ($offset = 0; $offset < $questions; $offset += $batchsize) {
        $batch = [];
        $upto = min($batchsize, $questions - $offset);
        for ($i = 0; $i < $upto; $i++) {
            $n = $offset + $i;
            $batch[] = (object) [
                'name' => "Explain question $n",
                'questiontext' => "<p>Body of question $n with some filler text.</p>",
                'questiontextformat' => FORMAT_HTML,
                'qtype' => 'truefalse',
                'generalfeedback' => '',
                'generalfeedbackformat' => FORMAT_HTML,
                'timecreated' => $now,
                'timemodified' => $now,
                'createdby' => 2,
                'modifiedby' => 2,
            ];
        }
        $DB->insert_records('question', $batch);
        mtrace('  ' . ($offset + $upto) . ' / ' . $questions);
    }

    // Bank entries and versions are what the "add question" query joins through, so
    // they have to exist for every question - otherwise the plan would be measured
    // against a join that finds nothing.
    mtrace('Seeding bank entries and versions ...');
    $questionids = $DB->get_fieldset_sql('SELECT id FROM {question} ORDER BY id');
    foreach (array_chunk($questionids, $batchsize) as $chunk) {
        $entries = [];
        foreach ($chunk as $qid) {
            $entries[] = (object) [
                'questioncategoryid' => $categoryid,
                'idnumber' => null,
                'ownerid' => 2,
            ];
        }
        $DB->insert_records('question_bank_entries', $entries);
    }
    $entryids = $DB->get_fieldset_sql('SELECT id FROM {question_bank_entries} ORDER BY id');
    $versions = [];
    foreach ($questionids as $index => $qid) {
        if (!isset($entryids[$index])) {
            break;
        }
        $versions[] = (object) [
            'questionbankentryid' => $entryids[$index],
            'version' => 1,
            'questionid' => $qid,
            'status' => 'ready',
        ];
        if (count($versions) >= $batchsize) {
            $DB->insert_records('question_versions', $versions);
            $versions = [];
        }
    }
    if ($versions) {
        $DB->insert_records('question_versions', $versions);
    }

    mtrace("Seeding $items CAT items ...");
    $itemrows = [];
    for ($i = 0; $i < $items && isset($questionids[$i]); $i++) {
        $itemrows[] = (object) [
            'componentid' => $questionids[$i],
            'componentname' => 'question',
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'activeparamid' => 0,
            'status' => 0,
        ];
        if (count($itemrows) >= $batchsize) {
            $DB->insert_records('local_catquiz_items', $itemrows);
            $itemrows = [];
        }
    }
    if ($itemrows) {
        $DB->insert_records('local_catquiz_items', $itemrows);
    }

    mtrace("Seeding $itemparams item parameters ...");
    $itemids = $DB->get_fieldset_sql('SELECT id FROM {local_catquiz_items} ORDER BY id');
    $paramrows = [];
    for ($i = 0; $i < $itemparams && isset($itemids[$i]); $i++) {
        // Every twentieth parameter is unusable, so the filter has something to find.
        $unusable = ($i % 20) === 0;
        $paramrows[] = (object) [
            'itemid' => $itemids[$i],
            'componentname' => 'question',
            'contextid' => $contextid,
            'model' => $unusable ? 'raschbirnbaum' : 'rasch',
            'difficulty' => 0.5,
            'discrimination' => $unusable ? 0.0 : 1.0,
            'usable' => $unusable ? 0 : 1,
            'status' => 4,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        if (count($paramrows) >= $batchsize) {
            $DB->insert_records('local_catquiz_itemparams', $paramrows);
            $paramrows = [];
        }
    }
    if ($paramrows) {
        $DB->insert_records('local_catquiz_itemparams', $paramrows);
    }

    // Link the active parameter, otherwise every item would look like a pilot item
    // and the statistics joins would never match.
    $DB->execute('UPDATE {local_catquiz_items} lci
                     SET activeparamid = (SELECT MIN(p.id) FROM {local_catquiz_itemparams} p
                                           WHERE p.itemid = lci.id)
                   WHERE EXISTS (SELECT 1 FROM {local_catquiz_itemparams} p2
                                  WHERE p2.itemid = lci.id)');

    mtrace("Seeding $attempts attempts ...");
    $attemptrows = [];
    for ($i = 0; $i < $attempts; $i++) {
        $attemptrows[] = (object) [
            'userid' => 2 + ($i % 500),
            'scaleid' => $scaleid,
            'contextid' => $contextid,
            'courseid' => 1,
            'attemptid' => 100000 + $i,
            'component' => 'mod_adaptivequiz',
            'instanceid' => 1,
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        if (count($attemptrows) >= $batchsize) {
            $DB->insert_records('local_catquiz_attempts', $attemptrows);
            $attemptrows = [];
        }
    }
    if ($attemptrows) {
        $DB->insert_records('local_catquiz_attempts', $attemptrows);
    }

    mtrace('Seeding done.');
}
