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
 * class feedback.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\feedback;

use local_catquiz\catscale;
use local_catquiz\data\dataapi;
use local_catquiz\testenvironment;
use MoodleQuickForm;
use stdClass;

/**
 * Base class for test strategies.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedbackclass {
    /** @var array Fields of a range, without scale and range, and what they hold (issue #51). */
    public const RANGE_FIELDS = [
        'feedback_scaleid_limit_lower_' => 'float',
        'feedback_scaleid_limit_upper_' => 'float',
        'feedbackeditor_scaleid_' => 'editor',
        'feedbacklegend_scaleid_' => 'text',
        'wb_colourpicker_' => 'alnum',
        'catquiz_courses_' => 'ints',
        'catquiz_group_' => 'text',
        'enrolment_message_checkbox_' => 'int',
    ];

    /** @var array Fields of a scale, without the scale. */
    public const SCALE_FIELDS = ['catquiz_scalereportcheckbox_' => 'int'];

    /** @var string Hidden field with the values of a scale not being edited, followed by the scale id. */
    public const CARRIER = 'catquiz_feedbackcarrier_';

    /** @var string Hidden field with the scale being edited. */
    public const EDITSCALE = 'catquiz_feedbackeditscale';

    /** @var string Button opening a scale without JavaScript, followed by the scale id. */
    public const EDITBUTTON = 'catquiz_feedbackedit_';

    /** @var string Summary of a scale not being edited, followed by the scale id. */
    public const SUMMARY = 'catquiz_feedbacksummary_';

    /**
     * Add Form elements to form.
     * @param MoodleQuickForm $mform
     * @param array $elements
     * @param ?testenvironment $template
     *
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm &$mform, array &$elements, ?testenvironment $template) {

        global $CFG, $PAGE, $OUTPUT;

        require_once($CFG->libdir . '/datalib.php');

        // If the user just selected a template, get the values from there.
        if ($template) {
            $data = (array) $template->return_settings();
        } else {
            // Otherwise: Get all values from the existing form.
            $data = $mform->getSubmitValues();
        }
        $defaultvalues = $mform->_defaultValues;

        // phpcs:ignore
        // TODO: Display Name of Teststrategy. $teststrategyid = intval($data['catquiz_selectteststrategy']);
        $elements[] = $mform->addElement(
            'header',
            'catquiz_feedback',
            get_string('catquiz_feedbackheader', 'local_catquiz')
        );
        $mform->setExpanded('catquiz_feedback');

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('noselection', 'local_catquiz'),
        ];

        $selectedparentscale = optional_param('catquiz_catscales', 0, PARAM_INT);
        if ($template) {
            $selectedparentscale = $template->return_as_array()['catscaleid'];
        }

        if (!empty($selectedparentscale)) {
            $scales = dataapi::get_catscale_and_children($selectedparentscale, true);
        } else {
            // Right now, we just get all subscales.
            $scales = dataapi::get_all_catscales();
        }

        // Select to set number of feedback options per subscale.
        $options = [];
        for ($i = 1; $i <= LOCAL_CATQUIZ_MAX_SCALERANGE; $i++) {
            $options[$i] = $i;
        }

        $nfeedbpersubscale = $data['numberoffeedbackoptionsselect']
            ?? optional_param('numberoffeedbackoptionsselect', 0, PARAM_INT);
        $nfeedbpersubscale = empty($nfeedbpersubscale)
            ? LOCAL_CATQUIZ_DEFAULT_NUMBER_OF_FEEDBACKS_PER_SCALE : intval($nfeedbpersubscale);

        /* The number of feedback options has just been changed (its reload button). The limits that
           arrive with that request belong to the old number of ranges: kept, they leave the new last
           range overlapping the old ones, and the form can no longer be saved ("No gap in the
           feedbackrange allowed"). Every range is laid out anew, evenly over the scale. */
        $rangesrelaidout = optional_param('submitnumberoffeedbackoptions', '', PARAM_ALPHANUMEXT)
            === 'numberoffeedbackoptionssubmit';

        $element = $mform->addElement(
            'static',
            'disclaimer:numberoffeedbackchange',
            "",
            get_string('disclaimer:numberoffeedbackchange', 'local_catquiz'),
        );
        $elements[] = $element;

        $element = $mform->addElement(
            'select',
            'numberoffeedbackoptionsselect',
            get_string('numberoffeedbackoptionpersubscale', 'local_catquiz'),
            $options,
            ['data-on-change-action' => 'numberOfFeedbacksSubmit'],
        );
        $element->setValue($nfeedbpersubscale);
        $mform->addHelpButton('numberoffeedbackoptionsselect', 'numberoffeedbackoptionpersubscale', 'local_catquiz');
        $elements[] = $element;

        $instance = $data['instance'] ?? $defaultvalues['instance'] ?? "";
        if (!$instance) {
            $picturewarning = get_string('picturesavewarning', 'local_catquiz');
            $element = $mform->createElement(
                'html',
                '<div class="alert alert-warning" role="alert">' . $picturewarning . '</div>'
            );
            $element->setName('picturesavewarning');
            $mform->addElement($element);
            $elements[] = $element;
        }

        // Button to attach JavaScript to reload the form.
        $mform->registerNoSubmitButton('submitnumberoffeedbackoptions');
        $elements[] = $mform->addElement(
            'submit',
            'submitnumberoffeedbackoptions',
            'numberoffeedbackoptionssubmit',
            [
            'class' => 'd-none',
            'data-action' => 'submitNumberOfFeedbackOptions',
            ]
        );

        // Generate the options for the colors. Same values are applied to all subscales and subfeedbacks.
        $coloroptions = self::get_array_of_colors($nfeedbpersubscale);
        // We need this to close the collapsable header elements. Issue #32: the closing part of the one
        // collapsible template - opening and closing are no longer two fragments the template lint cannot check.
        $html2 = $OUTPUT->render_from_template('local_catquiz/feedback/feedbackform_collapsible', ['close' => true]);
        $courses = self::course_choices();
        $parentscale = reset($scales);
        $lowestability = $highestability = 0.0;
        if ($parentscale) {
            $lowestability = (float) $parentscale->minscalevalue;
            $highestability = (float) $parentscale->maxscalevalue;
        }

        // Issue #51: the scales shown in the form - the parent scale and the selected subscales.
        $visible = [];
        foreach ($scales as $scale) {
            $checkboxchecked = optional_param('catquiz_subscalecheckbox_' . $scale->id, 0, PARAM_INT);
            if ($scale->depth !== 0 && $checkboxchecked !== 1) {
                continue;
            }
            $visible[(int) $scale->id] = $scale;
        }

        /* Issue #51: the fields of a scale - three editors, limits, courses and colours per range - are
           built for one scale at a time, the one being edited. Every other scale shown carries its values
           in one hidden field and gets its fields when it is opened: in place, loaded as a fragment, or -
           without JavaScript - by its button, reloading the form. Built for every scale, the form had
           several hundred editors with a large scale tree and was no longer delivered completely. */
        $posted = $mform->getSubmitValues();
        $editscaleid = self::edited_scale_id(array_keys($visible), $posted);
        $postededitscaleid = (int) ($posted[self::EDITSCALE] ?? 0);
        $filecontext = self::file_context($defaultvalues, $posted);
        $element = $mform->addElement('hidden', self::EDITSCALE, $editscaleid);
        $mform->setType(self::EDITSCALE, PARAM_INT);
        self::force_value($mform, self::EDITSCALE, $editscaleid);
        $elements[] = $element;

        $rootattributes = [
            'data-region' => 'catquiz-feedback',
            'data-rootscaleid' => $parentscale ? (int) $parentscale->id : 0,
            'data-nranges' => $nfeedbpersubscale,
            'data-cmid' => $filecontext ? $filecontext['cmid'] : 0,
            'data-instanceid' => $filecontext ? $filecontext['instanceid'] : 0,
            'data-contextid' => $PAGE->context->id,
        ];
        $element = $mform->createElement('html', \html_writer::start_div('catquiz-feedback-scales', $rootattributes));
        $element->setName('catquiz_feedback_scales_start');
        $mform->addElement($element);
        $elements[] = $element;

        $opendepths = [];
        foreach ($visible as $scale) {
            $subelements = [];
            $isedited = (int) $scale->id === $editscaleid;
            $carriername = self::CARRIER . $scale->id;

            // The values of the scale: from its carrier, or as the form has them.
            // A template just chosen brings its own values; the carriers of the old settings are left aside.
            $carrierposted = !$template && isset($posted[$carriername]) && (int) $scale->id !== $postededitscaleid;
            $values = self::scale_values(
                $scale,
                $nfeedbpersubscale,
                $carrierposted ? self::decode_carrier($posted[$carriername], (int) $scale->id) : [],
                $data,
                $defaultvalues,
                $rangesrelaidout,
                $lowestability,
                $highestability
            );

            $element = $mform->createElement('html', \html_writer::start_div('catquiz-feedback-panel', [
                'data-region' => 'catquiz-feedback-panel',
                'data-scaleid' => $scale->id,
                'data-edited' => $isedited ? 1 : 0,
            ]));
            $element->setName('catquiz_feedback_panel_start_' . $scale->id);
            $subelements[] = $mform->addElement($element);

            // The carrier with the summary and the button to open the scale.
            $element = $mform->createElement('html', \html_writer::start_div($isedited ? 'd-none' : '', [
                'data-region' => 'catquiz-feedback-carrier',
            ]));
            $element->setName('catquiz_feedback_carrier_start_' . $scale->id);
            $subelements[] = $mform->addElement($element);
            $nfeedbacksgiven = self::count_texts($values, (int) $scale->id, $nfeedbpersubscale);
            $subelements[] = $mform->addElement(
                'static',
                self::SUMMARY . $scale->id,
                '',
                get_string('feedbackpanelsummary', 'local_catquiz', (object) [
                    'given' => $nfeedbacksgiven, 'ranges' => $nfeedbpersubscale,
                ])
            );
            $mform->registerNoSubmitButton(self::EDITBUTTON . $scale->id);
            $subelements[] = $mform->addElement(
                'submit',
                self::EDITBUTTON . $scale->id,
                get_string('feedbackpaneledit', 'local_catquiz', $scale->name),
                ['data-action' => 'catquizEditFeedback', 'data-scaleid' => $scale->id]
            );
            $json = json_encode($values);
            $attributes = $isedited ? ['disabled' => 'disabled'] : [];
            $subelements[] = $mform->addElement('hidden', $carriername, $json, $attributes);
            $mform->setType($carriername, PARAM_RAW);
            self::force_value($mform, $carriername, $json);
            $element = $mform->createElement('html', '</div>');
            $element->setName('catquiz_feedback_carrier_end_' . $scale->id);
            $subelements[] = $mform->addElement($element);

            // The fields - for the scale being edited only.
            $element = $mform->createElement('html', \html_writer::start_div('', ['data-region' => 'catquiz-feedback-fields']));
            $element->setName('catquiz_feedback_fields_start_' . $scale->id);
            $subelements[] = $mform->addElement($element);
            if ($isedited) {
                $scaleposted = $posted;
                $scaledata = $data;
                $scaledefaults = $defaultvalues;
                if ($carrierposted) {
                    // Opened by its button: the values come from its carrier, and are what the form is sent with.
                    foreach ($values as $name => $value) {
                        $scaleposted[$name] = $scaledata[$name] = $mform->_submitValues[$name] = $value;
                    }
                    $scaledefaults = [];
                }
                [$fields, $nfeedbacksgiven] = self::add_scale_elements(
                    $mform,
                    $scale,
                    $nfeedbpersubscale,
                    $scaledata,
                    $scaleposted,
                    $scaledefaults,
                    $rangesrelaidout,
                    $lowestability,
                    $highestability,
                    $coloroptions,
                    $courses,
                    $filecontext
                );
                array_push($subelements, ...$fields);
            }
            $element = $mform->createElement('html', '</div></div>');
            $element->setName('catquiz_feedback_panel_end_' . $scale->id);
            $subelements[] = $mform->addElement($element);

            // Add a header for each scale.
            // We check if feedbacks completed partially, entirely or not at all.
            if ($nfeedbacksgiven == 0) {
                // No feedback entries saved in editor.
                $headersuffix = "";
            } else if ($nfeedbacksgiven == $nfeedbpersubscale) {
                // All feedback entries saved in editor.
                $headersuffix = ' : ' . get_string('feedbackcompletedentirely', 'local_catquiz');
            } else {
                // Partially submitted feedback.
                $statusofcompletion = strval($nfeedbacksgiven) . "/" . strval($nfeedbpersubscale);
                $headersuffix = ' : ' . get_string('feedbackcompletedpartially', 'local_catquiz', $statusofcompletion);
            }
            $subscaleids = catscale::get_subscale_ids($scale->id);
            if (!empty($subscaleids)) {
                $mform->registerNoSubmitButton('copysettingsforallsubscales_' . $scale->id);
                $subelements[] = $mform->addElement(
                    'submit',
                    'copysettingsforallsubscales_' . $scale->id,
                    get_string('copysettingsforallsubscales', 'local_catquiz'),
                    [
                        'data-action' => 'submitFeedbackValues',
                        'data-source-scale-id' => $scale->id,
                        'data-subscale-ids' => implode(',', $subscaleids),
                        'data-copying-text' => get_string('copyingsubscalesettings', 'local_catquiz'),
                        'data-copied-text' => get_string('copiedsubscalesettings', 'local_catquiz'),
                        'data-nothing-copied-text' => get_string('nosubscalechosenforcopy', 'local_catquiz'),
                    ]
                );
            }

            /* Make the different feedback options nested. Issue #32: the open collapsibles are a stack of
               depths - before a scale, every open one at its depth or deeper is closed. Counting depth
               differences closed too many or too few as soon as an unselected scale was left out between
               two levels, and the end closed one where a level was still open below the first. */
            $numberofclosinghtmls = 0;
            while ($opendepths && end($opendepths) >= (int) $scale->depth) {
                array_pop($opendepths);
                $numberofclosinghtmls++;
            }
            $opendepths[] = (int) $scale->depth;
            $previousdepth = $scale->depth;
            $headername = get_string('catquizfeedbackheader', 'local_catquiz', $scale->name) . $headersuffix;
            $headerid = 'catquiz_feedback_header_' . $scale->id;
            $collapseid = 'catquiz_feedback_collapse_' . $scale->id;
            $accordionid = 'accordion_header_scaleid_' . $scale->id;
            $dataname = 'catquiz_feedback_header_' . $scale->name;
            $headerdata = [
                'headername' => $headername,
                'headerid' => $headerid,
                'collapseid' => $collapseid,
                'accordionid' => $accordionid,
                'datadepth' => $previousdepth,
                'dataname' => $dataname,
            ];

            // Closing the elements.
            self::add_closing_html($numberofclosinghtmls, $scale->id, $mform, $elements, $html2);

            $html1 = $OUTPUT->render_from_template(
                'local_catquiz/feedback/feedbackform_collapsible',
                ['open' => true] + $headerdata
            );

            $element = $mform->createElement('html', $html1);
            $element->setName('header_accordion_start_scale_' . $scale->id);
            $mform->addElement($element);
            $elements[] = $element;

            // Now append elements from loop.
            foreach ($subelements as $element) {
                $elements[] = $element;
            }
        }
        if (!empty($scale) && $opendepths) {
            // Issue #32: everything still open - closing just one left a div open per level below the first.
            self::add_closing_html(count($opendepths), 'last', $mform, $elements, $html2);
        }
        $element = $mform->createElement('html', '</div>');
        $element->setName('catquiz_feedback_scales_end');
        $mform->addElement($element);
        $elements[] = $element;
        $PAGE->requires->js_call_amd('local_catquiz/colourpicker', 'init');
        $PAGE->requires->js_call_amd('local_catquiz/feedbackpanel', 'init');
        $PAGE->requires->js_call_amd('local_catquiz/quizsettingsexpandcollapsible', 'init');
    }

    /**
     * Adds the fields of one scale: its report checkbox and, per range, limits, editor, legend, colour,
     * course and group enrolment (issue #51: for the scale being edited only).
     *
     * @param MoodleQuickForm $mform
     * @param object $scale A scale record or catscale_structure.
     * @param int $nfeedbpersubscale Number of ranges.
     * @param array $data Values the form has: submitted, or of a template just chosen.
     * @param array $posted Values of the request.
     * @param array $defaultvalues Values the form was set up with.
     * @param bool $rangesrelaidout The number of ranges has just been changed.
     * @param float $lowestability
     * @param float $highestability
     * @param array $coloroptions
     * @param array $courses Courses to choose from.
     * @param array|null $filecontext Where the files of the editors are kept, {@see self::file_context()}.
     * @return array [the elements added, the number of ranges with a text]
     */
    public static function add_scale_elements(
        MoodleQuickForm $mform,
        object $scale,
        int $nfeedbpersubscale,
        array $data,
        array $posted,
        array $defaultvalues,
        bool $rangesrelaidout,
        float $lowestability,
        float $highestability,
        array $coloroptions,
        array $courses,
        ?array $filecontext
    ): array {
        global $DB;

        $subelements = [];
        $nfeedbacksgiven = 0;
        $subelements[] = $mform->addElement(
            'advcheckbox',
            'catquiz_scalereportcheckbox_' . $scale->id,
            get_string('reportscale', 'local_catquiz'),
        );

        $scalereportcheckbox = isset($defaultvalues['catquiz_scalereportcheckbox_' . $scale->id])
            ? $defaultvalues['catquiz_scalereportcheckbox_' . $scale->id]
            : self::posted_int($posted, 'catquiz_scalereportcheckbox_' . $scale->id, LOCAL_CATQUIZ_RANDOM_DEFAULT);
        if ($scalereportcheckbox == LOCAL_CATQUIZ_RANDOM_DEFAULT) {
            $mform->setDefault('catquiz_scalereportcheckbox_' . $scale->id, 1);
        }

        for ($j = 1; $j <= $nfeedbpersubscale; $j++) {
            // We need to create a div tag to "wrap" feedback range.
            // Element is only used for testing purpose and can therefore contain scale name.
            $element = $mform->createElement(
                'html',
                '<div data-name="feedback_scale_' . s($scale->name) . '_range_' . $j . '" data-depth="' . $scale->depth . '" >'
            );
            $element->setName('feedback_scale_' . $scale->id . '_rangestart_' . $j);
            $subelements[] = $mform->addElement($element);

            // Check for each feedback editor field, if there is content.
            // This is the preparation for the header element (to be appended in the end) where we apply the distinction.

            // If reload was triggered (ie via nosubmitbutton), data is set in submitvalues.
            $editorfieldname = sprintf('feedbackeditor_scaleid_%s_%s', $scale->id, $j);
            // Per range: the text of the range before must not count for this one.
            $feedback = null;
            $feedbacktext = null;
            if (isset($data[$editorfieldname])) {
                $feedback = $data[$editorfieldname];
            } else if (isset($defaultvalues[$editorfieldname])) {
                // If values of form where saved before, and form is loaded, data is in defaultvalues.
                $feedback = $defaultvalues[$editorfieldname];
            }
            // Check type and value.
            if (!empty($feedback)) {
                $feedbacktext = $feedback;
                if (is_array($feedback)) {
                    $feedbacktext = $feedback['text'];
                }
                if (is_object($feedback)) {
                    $feedbacktext = $feedback->text;
                }
                $feedbacktext = strip_tags($feedbacktext ?? '');
            }

            if (isset($feedbacktext) && strlen($feedbacktext) > 0) {
                $nfeedbacksgiven++;
            }

            // Header for Subfeedback.
            $subelements[] = $mform->addElement(
                'static',
                'headingforfeedback' . $scale->id . '_' . $j,
                get_string('feedbacknumber', 'local_catquiz', $j)
            );

            // Define range.
            // For the lowest range (first range, min) value should be set statically to min of scale ability.
            // Max (last range, max) is set to max of scale.
            if ($j === 1) {
                $static = $mform->addElement(
                    'static',
                    'lowest_limit',
                    get_string('lowerlimit', 'local_catquiz'),
                    $lowestability
                );
                $subelements[] = $static;
                $element = $mform->addElement(
                    'hidden',
                    'feedback_scaleid_limit_lower_' . $scale->id . '_' . $j,
                    $lowestability
                );
                $element->setValue($lowestability);
            } else {
                $element = $mform->addElement(
                    'float',
                    'feedback_scaleid_limit_lower_' . $scale->id . '_' . $j,
                    get_string('lowerlimit', 'local_catquiz')
                );
                $lowerlimit = $rangesrelaidout ? LOCAL_CATQUIZ_RANDOM_DEFAULT
                    : ($defaultvalues['feedback_scaleid_limit_lower_' . $scale->id . '_' . $j]
                    ?? self::limit_value(
                        $posted['feedback_scaleid_limit_lower_' . $scale->id . '_' . $j] ?? null,
                        LOCAL_CATQUIZ_RANDOM_DEFAULT
                    ));
                if ($lowerlimit === LOCAL_CATQUIZ_RANDOM_DEFAULT) {
                    $lowerlimit = self::return_limits_for_scale(
                        $nfeedbpersubscale,
                        $j,
                        true,
                        $lowestability,
                        $highestability
                    );
                }
                $element->setValue($lowerlimit);
                if ($rangesrelaidout) {
                    self::replace_submitted_value($mform, $element->getName(), $lowerlimit);
                }
            }

            // If the Element is new, we set the default.
            // If we get a value here, the overriding value is set in the set_data_after_definition function.
            $subelements[] = $element;
            if ($j === $nfeedbpersubscale) {
                $static = $mform->addElement(
                    'static',
                    'highestvalue',
                    get_string('upperlimit', 'local_catquiz'),
                    $highestability
                );
                $subelements[] = $static;
                $element = $mform->addElement(
                    'hidden',
                    'feedback_scaleid_limit_upper_' . $scale->id . '_' . $j,
                    $highestability
                );
                $element->setValue($highestability);
            } else {
                $element = $mform->addElement(
                    'float',
                    'feedback_scaleid_limit_upper_' . $scale->id . '_' . $j,
                    get_string('upperlimit', 'local_catquiz')
                );
                $upperlimit = $rangesrelaidout ? LOCAL_CATQUIZ_RANDOM_DEFAULT
                    : ($defaultvalues['feedback_scaleid_limit_upper_' . $scale->id . '_' . $j]
                    ?? self::limit_value(
                        $posted['feedback_scaleid_limit_upper_' . $scale->id . '_' . $j] ?? null,
                        LOCAL_CATQUIZ_RANDOM_DEFAULT
                    ));
                if ($upperlimit === LOCAL_CATQUIZ_RANDOM_DEFAULT) {
                    $upperlimit = self::return_limits_for_scale(
                        $nfeedbpersubscale,
                        $j,
                        false,
                        $lowestability,
                        $highestability
                    );
                }
                $element->setValue($upperlimit);
                if ($rangesrelaidout) {
                    self::replace_submitted_value($mform, $element->getName(), $upperlimit);
                }
            }

            // If the Element is new, we set the default.
            // If we get a value here, the overriding value is set in the set_data_after_definition function.

            $subelements[] = $element;

            // Rich text field for subfeedback.
            $element = $mform->addElement(
                'editor',
                'feedbackeditor_scaleid_' . $scale->id . '_' . $j,
                get_string('feedback', 'core'),
                ['rows' => 10],
                [
                    'maxfiles' => EDITOR_UNLIMITED_FILES,
                    'noclean' => true, CONTEXT_SYSTEM,
                    'subdirs' => true,
                ]
            );
            $mform->setType('feedbackeditor_scaleid_' . $scale->id . '_' . $j, PARAM_RAW);
            $editorcontentfieldname = $editorfieldname . '_editor';
            // Issue #51: the files of an editor are copied into a draft area only when it is shown - the
            // value of a scale not edited before carries no draft area yet (item id 0).
            $element->setValue(self::editor_with_draft(
                self::editor_value($data[$editorfieldname]
                    ?? $data[$editorcontentfieldname]
                    ?? $defaultvalues[$editorcontentfieldname]
                    ?? $defaultvalues[$editorfieldname]
                    ?? null),
                $filecontext,
                (int) $scale->id,
                $j
            ));
            $subelements[] = $element;

            // Text field for feedback legend. Displayed only for parentscale.
            $subelements[] = $mform->addElement(
                'text',
                'feedbacklegend_scaleid_' . $scale->id . '_' . $j,
                get_string('feedbacklegend', 'local_catquiz'),
                'size="80"',
            );

            $subelements[] = $mform->addElement(
                'select',
                'wb_colourpicker_' . $scale->id . '_' . $j,
                get_string('feedback_colorrange', 'local_catquiz'),
                $coloroptions,
            );
            // Preset selected color regarding order of feedbacks.
            $sequencecolors = array_keys($coloroptions);

            $savedcolorvalue = $defaultvalues['wb_colourpicker_' . $scale->id . '_' . $j] ??
                $data['wb_colourpicker_' . $scale->id . '_' . $j] ?? -1;

            if ($savedcolorvalue === -1) {
                $mform->setDefault('wb_colourpicker_' . $scale->id . '_' . $j, $sequencecolors[$j - 1]);
            }

            $subelements[] = $mform->addElement('hidden', 'selectedcolour', '', PARAM_TEXT);

            // Enrol to a group.
            // Limit Courses - See GH-183.
            $options = [
                'multiple' => true,
                'noselectionstring' => get_string('courseselection', 'local_catquiz'),
            ];
            $select = [
                0 => get_string('courseselection', 'local_catquiz'),
            ];
            // Check if courses were saved before (ie from other teacher, directly in db) and in this case allow them.
            $preselectcourseids = $defaultvalues['catquiz_courses_' . $scale->id . '_' . $j] ?? [];
            $presavedcourseids = $data['catquiz_courses_' . $scale->id . '_' . $j] ?? [];
            $precourses = empty($preselectcourseids) ? $presavedcourseids : $preselectcourseids;

            if (!empty($precourses) && is_array($precourses)) {
                $newcourses = [];
                foreach ($precourses as $preselectcourseid) {
                    $foundcourse = false;
                    foreach ($courses as $course) {
                        if ($preselectcourseid == $course->id) {
                            $foundcourse = true;
                        }
                    }
                    if (!$foundcourse && !empty($preselectcourseid)) {
                        // A course deleted since is left out instead of stopping the form.
                        $course = $DB->get_record('course', ['id' => (int) $preselectcourseid], 'id, fullname');
                        if ($course) {
                            $newcourses[] = $course;
                        }
                    }
                }
                $courses = array_merge($newcourses, $courses);
            }

            foreach ($courses as $course) {
                $select[$course->id] = $course->fullname;
            }
            $subelements[] = $mform->addElement(
                'autocomplete',
                'catquiz_courses_' . $scale->id . '_' . $j,
                get_string('setcourseenrolmentforscale', 'local_catquiz'),
                $select,
                $options
            );
            $mform->addHelpButton('catquiz_courses_' . $scale->id . '_' . $j, 'setcourseenrolmentforscale', 'local_catquiz');

            // Enrol to a group.
            $element = $mform->addElement(
                'text',
                'catquiz_group_' . $scale->id . '_' . $j,
                get_string('setgrouprenrolmentforscale', 'local_catquiz')
            );
            $mform->addHelpButton('catquiz_group_' . $scale->id . '_' . $j, 'groupenrolmenthelptext', 'local_catquiz');
            $subelements[] = $element;

            // Checkbox messaging of groupselect and courseselect.
            $subelements[] = $mform->addElement(
                'advcheckbox',
                'enrolment_message_checkbox_' . $scale->id . '_' . $j,
                get_string('setautonitificationonenrolmentforscale', 'local_catquiz'),
                null,
                null,
                [0, 1]
            );

            $enrolmentcheckbox = isset($defaultvalues['enrolment_message_checkbox_' . $scale->id . '_' . $j])
                ? $defaultvalues['enrolment_message_checkbox_' . $scale->id . '_' . $j]
                : self::posted_int($posted, 'catquiz_scalereportcheckbox_' . $scale->id, LOCAL_CATQUIZ_RANDOM_DEFAULT);
            if ($enrolmentcheckbox == LOCAL_CATQUIZ_RANDOM_DEFAULT) {
                $mform->setDefault('enrolment_message_checkbox_' . $scale->id . '_' . $j, 1);
            }

            // Close of feedback range HTML tag element.
            $element = $mform->createElement('html', '</div>');
            $element->setName('feedback_scale_' . $scale->id . '_rangeend_' . $j);
            $subelements[] = $mform->addElement($element);
        }

        return [$subelements, $nfeedbacksgiven];
    }

    /**
     * Add HTML Elements to close collapsable accordion.
     *
     * @param int $counter
     * @param int $scaleid
     * @param mixed $mform
     * @param array $elements
     * @param string $html2
     *
     */
    private static function add_closing_html($counter, $scaleid, &$mform, &$elements, $html2) {
        for ($i = 1; $i <= $counter; $i++) {
            $element = $mform->createElement('html', $html2);
            $element->setName('header_accordion_end_scale_' . $scaleid . '_' . $i);
            $mform->addElement($element);
            $elements[] = $element;
        }
    }

    /**
     * Fill coloroptions array with strings.
     *
     * @param string $color
     * @param array $coloroptions
     *
     */
    public static function add_coloroption(string $color, array &$coloroptions) {
        $coloroptions[$color] = get_string(
            'color_' . $color . '_code',
            'local_catquiz'
        );
    }

    /**
     * Get right number and type of colors.
     *
     * @param int $nfeedbpersubscale
     * @return array $coloroptions
     *
     */
    public static function get_array_of_colors($nfeedbpersubscale) {

        $coloroptions = [];
        // Depending of the number of options, different colors will be chosen.
        // See strings for colornames.
        switch ($nfeedbpersubscale) {
            case 1:
                self::add_coloroption("3", $coloroptions);
                break;
            case 2:
                self::add_coloroption("3", $coloroptions);
                self::add_coloroption("6", $coloroptions);
                break;
            case 3:
                self::add_coloroption("3", $coloroptions);
                self::add_coloroption("5", $coloroptions);
                self::add_coloroption("6", $coloroptions);
                break;
            case 4:
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                break;
            case 5:
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                self::add_coloroption('7', $coloroptions);
                break;
            case 6:
                self::add_coloroption('2', $coloroptions);
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                self::add_coloroption('7', $coloroptions);
                break;
            case 7:
                self::add_coloroption('1', $coloroptions);
                self::add_coloroption('2', $coloroptions);
                self::add_coloroption('3', $coloroptions);
                self::add_coloroption('4', $coloroptions);
                self::add_coloroption('5', $coloroptions);
                self::add_coloroption('6', $coloroptions);
                self::add_coloroption('7', $coloroptions);
                break;
            case 8:
                self::add_coloroption('1', $coloroptions); // 1
                self::add_coloroption('2', $coloroptions); // 2
                self::add_coloroption('3', $coloroptions); // 3
                self::add_coloroption('4', $coloroptions); // 4
                self::add_coloroption('5', $coloroptions); // 5
                self::add_coloroption('6', $coloroptions); // 6
                self::add_coloroption('7', $coloroptions); // 7
                self::add_coloroption('8', $coloroptions); // 8
                break;
        }

        return $coloroptions;
    }

    /**
     * Replaces the submitted value of a form element.
     *
     * QuickForm takes the value of a submitted form from the submission whenever it refreshes an
     * element (setDefaults() and friends refresh all of them); a value only set on the element
     * would be replaced by the old one again before the form is shown.
     *
     * @param MoodleQuickForm $mform
     * @param string $name Element name.
     * @param mixed $value The value to show.
     */
    private static function replace_submitted_value(MoodleQuickForm $mform, string $name, $value): void {
        $mform->_submitValues[$name] = $value;
        $_POST[$name] = $value;
    }

    /**
     * Returns lower or upper value for scales, depending on bool.
     *
     * @param mixed $nroptions
     * @param mixed $optioncounter
     * @param bool $lower
     * @param float $lowestlimit
     * @param float $highestlimit
     *
     * @return float
     *
     */
    public static function return_limits_for_scale(
        $nroptions,
        $optioncounter,
        bool $lower,
        float $lowestlimit,
        float $highestlimit
    ) {

        // Calculate equal default values for limits in scales.
        $sizeofrange = abs($lowestlimit - $highestlimit);
        $increment = round($sizeofrange / $nroptions, 2);

        if ($lower) {
            return round($lowestlimit + ($optioncounter - 1) * $increment, 2);
        } else {
            return round($lowestlimit + $optioncounter * $increment, 2);
        }
    }

    /**
     * Check if no gaps in limits of the feedback ranges.
     *
     * @param array $errors
     * @param array $data
     *
     */
    public static function validation_range_limits_nogaps(array &$errors, array $data) {
        if (
            isset($data["catquiz_catscales"]) &&
            !empty((int) $data["catquiz_catscales"])
        ) {
            $scales = dataapi::get_catscale_and_children((int) $data["catquiz_catscales"], true);
            $nfeedbpersubscale = ((int) $data['numberoffeedbackoptionsselect']) ?? 1;
            foreach ($scales as $scale) {
                if (
                    ((int) $data["catquiz_catscales"]) === $scale->id ||
                    !empty($data["catquiz_subscalecheckbox_" . $scale->id])
                ) {
                    // The first range must be ascending too
                    // (upper_1 > lower_1); the loop below only covers j >= 2.
                    if (
                        isset($data['feedback_scaleid_limit_upper_' . $scale->id . '_1'])
                        && isset($data['feedback_scaleid_limit_lower_' . $scale->id . '_1'])
                        && (float) $data['feedback_scaleid_limit_upper_' . $scale->id . '_1'] <=
                            (float) $data['feedback_scaleid_limit_lower_' . $scale->id . '_1']
                    ) {
                        $errors['feedback_scaleid_limit_upper_' . $scale->id . '_1'] =
                            get_string('errorupperlimitvalue', 'local_catquiz');
                    }
                    for ($j = 2; $j <= $nfeedbpersubscale; $j++) {
                        // Upper limit of previous range must be equal of lowest limit for current range.
                        if (
                            (float) $data['feedback_scaleid_limit_upper_' . $scale->id . '_' . ($j - 1)] !==
                            (float) $data['feedback_scaleid_limit_lower_' . $scale->id . '_' . $j]
                        ) {
                            $errors['feedback_scaleid_limit_lower_' . $scale->id . '_' . $j] =
                                get_string('nogapallowed', 'local_catquiz');
                        }
                        // Upper limit must be always greater than lowest limit.
                        if (
                            (float) $data['feedback_scaleid_limit_upper_' . $scale->id . '_' . $j] <=
                            (float) $data['feedback_scaleid_limit_lower_' . $scale->id . '_' . $j]
                        ) {
                            $errors['feedback_scaleid_limit_upper_' . $scale->id . '_' . $j] =
                                get_string('errorupperlimitvalue', 'local_catquiz');
                        }
                    }
                }
            }
        }
    }

    /**
     * Ids of the scales a field belongs to, by its name: [scale id, range or 0], or null for other fields.
     *
     * @param string $name
     * @return array|null
     */
    public static function field_scale(string $name): ?array {
        if (preg_match('/^(' . implode('|', array_keys(self::RANGE_FIELDS)) . ')(\d+)_(\d+)$/', $name, $m)) {
            return [(int) $m[2], (int) $m[3], $m[1]];
        }
        if (preg_match('/^(' . implode('|', array_keys(self::SCALE_FIELDS)) . ')(\d+)$/', $name, $m)) {
            return [(int) $m[2], 0, $m[1]];
        }
        return null;
    }

    /**
     * The scale being edited: the one whose button was pressed, the one edited before, or the first one shown.
     *
     * @param int[] $visibleids Scales shown, the parent scale first.
     * @param array $posted Values of the request.
     * @return int 0 when no scale is shown.
     */
    public static function edited_scale_id(array $visibleids, array $posted): int {
        foreach ($visibleids as $scaleid) {
            if (!empty($posted[self::EDITBUTTON . $scaleid])) {
                return (int) $scaleid;
            }
        }
        $previous = (int) ($posted[self::EDITSCALE] ?? 0);
        if (in_array($previous, array_map('intval', $visibleids), true)) {
            return $previous;
        }
        return (int) (reset($visibleids) ?: 0);
    }

    /**
     * Where the files of the feedback editors of an activity are kept.
     *
     * @param array $defaultvalues
     * @param array $posted
     * @return array|null [cmid, instanceid, contextid], null for an activity not created yet.
     */
    public static function file_context(array $defaultvalues, array $posted): ?array {
        $cmid = (int) ($defaultvalues['coursemodule'] ?? $posted['coursemodule'] ?? 0);
        $instanceid = (int) ($defaultvalues['instance'] ?? $posted['instance'] ?? 0);
        if (!$cmid || !$instanceid) {
            return null;
        }
        $cm = get_coursemodule_from_id('adaptivequiz', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || (int) $cm->instance !== $instanceid) {
            return null;
        }
        return [
            'cmid' => $cmid,
            'instanceid' => $instanceid,
            'contextid' => \context_module::instance($cmid)->id,
        ];
    }

    /**
     * Sets the value of an element so that it stays - also when QuickForm refreshes it from the request.
     *
     * @param MoodleQuickForm $mform
     * @param string $name
     * @param mixed $value
     */
    private static function force_value(MoodleQuickForm $mform, string $name, $value): void {
        $mform->_submitValues[$name] = $value;
        if ($mform->elementExists($name)) {
            $mform->getElement($name)->setValue($value);
        }
    }

    /**
     * The courses a scale can enrol into: the user's own and the ones of the settings tags.
     *
     * @return array
     */
    private static function course_choices(): array {
        $enrolledcourses = enrol_get_my_courses();
        $coursesfromtags = dataapi::get_courses_from_settings_tags() ?? [];
        return array_merge($enrolledcourses, $coursesfromtags);
    }

    /**
     * An integer of the request.
     *
     * @param array $posted
     * @param string $name
     * @param int $default
     * @return int
     */
    private static function posted_int(array $posted, string $name, int $default): int {
        if (!isset($posted[$name]) || is_array($posted[$name]) || trim((string) $posted[$name]) === '') {
            return $default;
        }
        return (int) $posted[$name];
    }

    /**
     * A range limit as typed - in the decimal format of the language, or with a dot.
     *
     * Moodle's PARAM_FLOAT is a plain cast, so clean_param('1,5', PARAM_FLOAT) yields 1.0 - a German
     * decimal comma was silently truncated, and the next lower limit, still 1,5, no longer matched.
     *
     * @param mixed $raw
     * @param mixed $default Returned when the value is absent or not a number.
     * @return mixed
     */
    public static function limit_value($raw, $default) {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        if ($raw === null || is_array($raw) || trim((string) $raw) === '') {
            return $default;
        }
        $raw = trim((string) $raw);
        // Unformat_float() understands the decimal separator of the active language.
        $parsed = unformat_float($raw, true);
        if ($parsed !== false && $parsed !== null) {
            return (float) $parsed;
        }
        // The value did not match the current locale - accept a plain dot decimal as well, and treat a
        // comma as decimal separator when there is no dot.
        if (strpos($raw, ',') !== false && strpos($raw, '.') === false) {
            $raw = str_replace(',', '.', $raw);
        }
        return is_numeric($raw) ? (float) $raw : $default;
    }

    /**
     * The value of a feedback editor in one shape: text, format, draft item id.
     *
     * @param mixed $value Array or object as the editor has it, the text alone as saved, or nothing.
     * @return array
     */
    public static function editor_value($value): array {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            return [
                'text' => (string) ($value['text'] ?? ''),
                'format' => (int) ($value['format'] ?? FORMAT_HTML),
                'itemid' => (int) ($value['itemid'] ?? 0),
            ];
        }
        return ['text' => (string) ($value ?? ''), 'format' => FORMAT_HTML, 'itemid' => 0];
    }

    /**
     * An editor value with a draft area: the one it has, or one with the saved files of its range.
     *
     * @param array $value {@see self::editor_value()}
     * @param array|null $filecontext {@see self::file_context()}
     * @param int $scaleid
     * @param int $range
     * @return array
     */
    public static function editor_with_draft(array $value, ?array $filecontext, int $scaleid, int $range): array {
        if (!empty($value['itemid'])) {
            return $value;
        }
        $draftitemid = 0;
        $value['text'] = file_prepare_draft_area(
            $draftitemid,
            $filecontext['contextid'] ?? null,
            'local_catquiz',
            sprintf('feedback_files_%d_%d', $scaleid, $range),
            $filecontext['instanceid'] ?? null,
            ['subdirs' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES],
            $value['text']
        );
        $value['itemid'] = (int) $draftitemid;
        return $value;
    }

    /**
     * A value of a feedback field, cleaned as its field cleans it.
     *
     * @param string $prefix The field without scale and range, see self::RANGE_FIELDS and self::SCALE_FIELDS.
     * @param mixed $value
     * @return mixed
     */
    private static function clean_field_value(string $prefix, $value) {
        switch (self::RANGE_FIELDS[$prefix] ?? self::SCALE_FIELDS[$prefix] ?? '') {
            case 'editor':
                return self::editor_value($value);
            case 'float':
                return self::limit_value($value, null);
            case 'ints':
                $value = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
                return array_values(array_filter(array_map('intval', $value)));
            case 'int':
                return is_array($value) ? 0 : (int) $value;
            case 'alnum':
                return is_array($value) ? '' : clean_param((string) $value, PARAM_ALPHANUMEXT);
            default:
                return is_array($value) ? '' : clean_param((string) $value, PARAM_TEXT);
        }
    }

    /**
     * The values of a carrier: the fields of its scale, cleaned; anything else in it is left out.
     *
     * @param mixed $json
     * @param int $scaleid
     * @return array
     */
    public static function decode_carrier($json, int $scaleid): array {
        $values = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($values)) {
            return [];
        }
        $clean = [];
        foreach ($values as $name => $value) {
            $field = self::field_scale((string) $name);
            if ($field === null || $field[0] !== $scaleid) {
                continue;
            }
            $clean[$name] = self::clean_field_value($field[2], $value);
        }
        return $clean;
    }

    /**
     * Every value of the feedback fields of a scale.
     *
     * In this order: what its carrier brought, what the form has (submitted or of a template), what it
     * was set up with, and the default.
     *
     * @param object $scale A scale record or catscale_structure.
     * @param int $nranges
     * @param array $carrier Values of the carrier, {@see self::decode_carrier()}.
     * @param array $data
     * @param array $defaultvalues
     * @param bool $rangesrelaidout The number of ranges has just been changed: the limits are laid out anew.
     * @param float $lowestability
     * @param float $highestability
     * @return array Field name => value.
     */
    public static function scale_values(
        object $scale,
        int $nranges,
        array $carrier,
        array $data,
        array $defaultvalues,
        bool $rangesrelaidout,
        float $lowestability,
        float $highestability
    ): array {
        $pick = function (string $name, ?string $alternative = null) use ($carrier, $data, $defaultvalues) {
            foreach ([$carrier, $data, $defaultvalues] as $source) {
                foreach (array_filter([$name, $alternative]) as $key) {
                    if (array_key_exists($key, $source) && $source[$key] !== null) {
                        return $source[$key];
                    }
                }
            }
            return null;
        };
        $colours = array_keys(self::get_array_of_colors($nranges));
        $values = [];
        $name = 'catquiz_scalereportcheckbox_' . $scale->id;
        $values[$name] = (int) ($pick($name) ?? 1);
        if ($values[$name] === LOCAL_CATQUIZ_RANDOM_DEFAULT) {
            $values[$name] = 1;
        }
        for ($j = 1; $j <= $nranges; $j++) {
            $suffix = $scale->id . '_' . $j;
            foreach (['lower', 'upper'] as $bound) {
                $name = 'feedback_scaleid_limit_' . $bound . '_' . $suffix;
                $computed = self::return_limits_for_scale($nranges, $j, $bound === 'lower', $lowestability, $highestability);
                if ($bound === 'lower' && $j === 1) {
                    $values[$name] = $lowestability;
                } else if ($bound === 'upper' && $j === $nranges) {
                    $values[$name] = $highestability;
                } else if ($rangesrelaidout) {
                    $values[$name] = $computed;
                } else {
                    $limit = self::limit_value($pick($name), null);
                    $values[$name] = ($limit === null || $limit === (float) LOCAL_CATQUIZ_RANDOM_DEFAULT) ? $computed : $limit;
                }
            }
            $name = 'feedbackeditor_scaleid_' . $suffix;
            $values[$name] = self::editor_value($pick($name, $name . '_editor'));
            $values['feedbacklegend_scaleid_' . $suffix] = self::clean_field_value(
                'feedbacklegend_scaleid_',
                $pick('feedbacklegend_scaleid_' . $suffix) ?? ''
            );
            $colour = $pick('wb_colourpicker_' . $suffix);
            $values['wb_colourpicker_' . $suffix] = ($colour === null || $colour === '' || (int) $colour === -1)
                ? (string) ($colours[$j - 1] ?? '') : self::clean_field_value('wb_colourpicker_', $colour);
            $values['catquiz_courses_' . $suffix] = self::clean_field_value(
                'catquiz_courses_',
                $pick('catquiz_courses_' . $suffix)
            );
            $values['catquiz_group_' . $suffix] = self::clean_field_value(
                'catquiz_group_',
                $pick('catquiz_group_' . $suffix) ?? ''
            );
            $enrolment = $pick('enrolment_message_checkbox_' . $suffix);
            $values['enrolment_message_checkbox_' . $suffix] =
                ($enrolment === null || (int) $enrolment === LOCAL_CATQUIZ_RANDOM_DEFAULT) ? 1 : (int) $enrolment;
        }
        return $values;
    }

    /**
     * The number of ranges of a scale with a feedback text.
     *
     * @param array $values {@see self::scale_values()}
     * @param int $scaleid
     * @param int $nranges
     * @return int
     */
    private static function count_texts(array $values, int $scaleid, int $nranges): int {
        $given = 0;
        for ($j = 1; $j <= $nranges; $j++) {
            $text = $values['feedbackeditor_scaleid_' . $scaleid . '_' . $j]['text'] ?? '';
            if (strlen(trim(strip_tags($text))) > 0) {
                $given++;
            }
        }
        return $given;
    }

    /**
     * Puts the values of the carriers into the data, as if their fields had been in the form (issue #51).
     *
     * The carrier of the scale being edited is left out - its fields were in the form. Values the data
     * already has are kept.
     *
     * @param array|stdClass $data
     * @return array|stdClass Of the type given.
     */
    public static function expand_carriers($data) {
        $isobject = is_object($data);
        $values = (array) $data;
        $edited = (int) ($values[self::EDITSCALE] ?? 0);
        foreach ($values as $key => $json) {
            if (!preg_match('/^' . self::CARRIER . '(\d+)$/', (string) $key, $m)) {
                continue;
            }
            unset($values[$key]);
            if ((int) $m[1] === $edited) {
                continue;
            }
            foreach (self::decode_carrier($json, (int) $m[1]) as $name => $value) {
                if (!array_key_exists($name, $values)) {
                    $values[$name] = $value;
                }
            }
        }
        unset($values[self::EDITSCALE]);
        return $isobject ? (object) $values : $values;
    }

    /**
     * Errors of fields not in the form - of a scale not being edited - are shown at the scale's summary.
     *
     * @param array $errors
     * @param int $editedscaleid
     */
    public static function move_errors_to_summaries(array &$errors, int $editedscaleid): void {
        foreach ($errors as $name => $message) {
            $field = self::field_scale((string) $name);
            if ($field === null || $field[0] === $editedscaleid) {
                continue;
            }
            unset($errors[$name]);
            $summary = self::SUMMARY . $field[0];
            $errors[$summary] = isset($errors[$summary]) ? $errors[$summary] . ' ' . $message : $message;
        }
    }

    /**
     * Sets a value of a scale not being edited, in its carrier.
     *
     * @param MoodleQuickForm $mform
     * @param string $name Name of the field.
     * @param mixed $value
     * @return bool Whether the field belongs to a carrier of the form.
     */
    public static function set_carrier_value(MoodleQuickForm $mform, string $name, $value): bool {
        $field = self::field_scale($name);
        if ($field === null || !$mform->elementExists(self::CARRIER . $field[0])) {
            return false;
        }
        $carrier = $mform->getElement(self::CARRIER . $field[0]);
        if ((int) $field[0] === (int) $mform->getElementValue(self::EDITSCALE)) {
            return false;
        }
        $values = self::decode_carrier($carrier->getValue(), $field[0]);
        if (is_string($value) && $field[2] === 'feedbackeditor_scaleid_') {
            // The text of a template: its files are not taken along.
            $value = ['text' => $value, 'format' => FORMAT_HTML, 'itemid' => 0];
        }
        $values[$name] = self::clean_field_value($field[2], $value);
        self::force_value($mform, self::CARRIER . $field[0], json_encode($values));
        return true;
    }

    /**
     * The fields of one scale, for the form they are put into (issue #51): fragment "feedbackpanel".
     *
     * @param array $args context, scaleid, rootscaleid, nranges, cmid, instanceid, values (the carrier)
     * @return string
     */
    public static function panel_fragment(array $args): string {
        global $OUTPUT, $PAGE;

        $context = $args['context'];
        require_capability('moodle/course:manageactivities', $context);
        $scaleid = (int) ($args['scaleid'] ?? 0);
        $rootscaleid = (int) ($args['rootscaleid'] ?? 0);
        $nranges = min(max((int) ($args['nranges'] ?? 1), 1), LOCAL_CATQUIZ_MAX_SCALERANGE);

        $filecontext = null;
        $cmid = (int) ($args['cmid'] ?? 0);
        if ($cmid) {
            $cm = get_coursemodule_from_id('adaptivequiz', $cmid, 0, false, MUST_EXIST);
            $cmcontext = \context_module::instance($cm->id);
            require_capability('moodle/course:manageactivities', $cmcontext);
            if ((int) $cm->instance !== (int) ($args['instanceid'] ?? 0)) {
                throw new \moodle_exception('invalidcoursemodule');
            }
            $filecontext = ['cmid' => $cmid, 'instanceid' => (int) $cm->instance, 'contextid' => $cmcontext->id];
        }

        // Adapter issue #2: the tree of a root scale only - the root scales the activity form offers to choose.
        global $DB;
        $isroot = $rootscaleid && $DB->record_exists('local_catquiz_catscales', ['id' => $rootscaleid, 'parentid' => 0]);
        $scales = $isroot ? dataapi::get_catscale_and_children($rootscaleid, true) : [];
        if (!$isroot || !isset($scales[$scaleid])) {
            return $OUTPUT->notification(get_string('feedbackpanelscalegone', 'local_catquiz'), 'warning', false);
        }
        $scale = $scales[$scaleid];
        $root = $scales[$rootscaleid] ?? reset($scales);
        $lowestability = (float) $root->minscalevalue;
        $highestability = (float) $root->maxscalevalue;
        $values = self::scale_values(
            $scale,
            $nranges,
            self::decode_carrier($args['values'] ?? '', $scaleid),
            [],
            [],
            false,
            $lowestability,
            $highestability
        );

        $mform = new MoodleQuickForm('catquizfeedbackpanel' . $scaleid, 'post', '#');
        // As sent: every field takes its value from the carrier.
        $mform->updateSubmission($values, []);
        // The fields go into the activity form, which watches its changes itself; a watcher for this form,
        // which has no element on the page, would fail and keep the page from being ready.
        $mform->disable_form_change_checker();
        self::add_scale_elements(
            $mform,
            $scale,
            $nranges,
            $values,
            $values,
            [],
            false,
            $lowestability,
            $highestability,
            self::get_array_of_colors($nranges),
            self::course_choices(),
            $filecontext
        );
        $renderer = new \MoodleQuickForm_Renderer();
        $mform->accept($renderer);
        // The fields go into the activity form: without a form of their own.
        $html = preg_replace(
            ['~^\s*<form[^>]*>~', '~</form>\s*$~', '~<input[^>]*name="_qf__[^"]*"[^>]*>~'],
            '',
            $renderer->toHtml()
        );
        $PAGE->requires->js_call_amd('local_catquiz/colourpicker', 'init');
        return $html;
    }
}
