<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_catquiz
 * @category    admin
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$componentname = 'local_catquiz';

// Default for users that have site config.
if ($hassiteconfig) {
    $settings = new admin_settingpage($componentname . '_settings', get_string('pluginname', 'local_catquiz'));
    $ADMIN->add('localplugins', $settings);

    foreach (core_plugin_manager::instance()->get_plugins_of_type('catmodel') as $plugin) {
            $plugin->load_settings($ADMIN, 'localplugins', $hassiteconfig);
    }

    foreach (core_plugin_manager::instance()->get_plugins_of_type('catquizcentralhub') as $plugin) {
        $plugin->load_settings($ADMIN, 'localplugins', $hassiteconfig);
    }

    $catscalelink = new moodle_url('/local/catquiz/manage_catscales.php');
    $actionlink = new action_link($catscalelink, get_string('catquizsettings', 'local_catquiz'));
    $settingslink = ['link' => $OUTPUT->render($actionlink)];
    $settings->add(
        new admin_setting_heading(
            'local_catquiz/catscales',
            get_string('catscales', 'local_catquiz'),
            get_string('catscales:information', 'local_catquiz', $settingslink),
        )
    );

    $calclink = new moodle_url('/local/catquiz/manage_calculation.php');
    $calcactionlink = new action_link($calclink, get_string('calculationmanagement', 'local_catquiz'));
    $settings->add(
        new admin_setting_heading(
            'local_catquiz/calculationmanagement',
            get_string('calculationmanagement', 'local_catquiz'),
            $OUTPUT->render($calcactionlink),
        )
    );

    $settings->add(
        new admin_setting_heading(
            'local_catquiz/cattags',
            get_string('cattags', 'local_catquiz'),
            get_string('cattags:information', 'local_catquiz'),
        )
    );

    $sql = "SELECT DISTINCT t.id, t.name
            FROM {tag} t
            LEFT JOIN {tag_instance} ti ON t.id=ti.tagid
            WHERE ti.component=:component AND ti.itemtype=:itemtype AND t.isstandard=1";

    $params = [
        'component' => 'core',
        'itemtype' => 'course',
    ];

    $records = $DB->get_records_sql($sql, $params);
    $options = [0 => 'notags'];
    foreach ($records as $record) {
        $options[$record->id] = $record->name;
    }

    $setting = new admin_setting_configmultiselect(
        'local_catquiz/cattags',
        get_string('choosetags', 'local_catquiz'),
        '',
        [],
        $options,
    );
    $settings->add(new admin_setting_description('cattagdisclaimer', '', get_string('choosetags:disclaimer', 'local_catquiz')));
    $settings->add($setting);
    $settings->add(new admin_setting_configtext(
        'local_catquiz/tr_sd_ratio',
        get_string('tr_sd_ratio_name', 'local_catquiz'),
        get_string('tr_sd_ratio_desc', 'local_catquiz'),
        3.0,
        PARAM_FLOAT
    ));
    $settings->add(new admin_setting_configtext(
        'local_catquiz/minquestions_default',
        get_string('minquestions_default_name', 'local_catquiz'),
        get_string('minquestions_default_desc', 'local_catquiz'),
        3,
        '/^\d+$/'
    ));

    $settings->add(
        new admin_setting_configcheckbox(
            'local_catquiz/automatic_reload_on_scale_selection',
            get_string('automatic_reload_on_scale_selection', 'local_catquiz'),
            get_string('automatic_reload_on_scale_selection_description', 'local_catquiz'),
            1
        )
    );

    $settings->add(new admin_setting_configtext(
        'local_catquiz/time_penalty_threshold',
        get_string('time_penalty_threshold_name', 'local_catquiz'),
        get_string('time_penalty_threshold_desc', 'local_catquiz'),
        10,
        '/^[1-9]\d*$/'
    ));

    // Issue #14: optional measurement-uncertainty gating for categorical feedback.
    // 0 disables it; a value like 1 requires the confidence interval
    // [ability - k*SE, ability + k*SE] to lie fully within a single range before
    // that range's feedback is shown.
    $settings->add(new admin_setting_configtext(
        'local_catquiz/feedback_uncertainty_factor',
        get_string('feedback_uncertainty_factor_name', 'local_catquiz'),
        get_string('feedback_uncertainty_factor_desc', 'local_catquiz'),
        0,
        '/^\d+(\.\d+)?$/'
    ));

    // Issue #15: minimum number of distinct peers required before the
    // peer comparison is shown (small groups are suppressed identically in
    // charts and exports).
    $settings->add(new admin_setting_configtext(
        'local_catquiz/minpeersforcomparison',
        get_string('minpeersforcomparison_name', 'local_catquiz'),
        get_string('minpeersforcomparison_desc', 'local_catquiz'),
        3,
        '/^[1-9]\d*$/'
    ));

    $settings->add(
        new admin_setting_configcheckbox(
            'local_catquiz/store_debug_info',
            get_string('store_debug_info_name', 'local_catquiz'),
            get_string('store_debug_info_desc', 'local_catquiz'),
            0
        )
    );

    // Issue #56: how long the progress of an attempt is kept. The default is the
    // data-sparing option on purpose - a plugin should not accumulate personal
    // answer data because nobody decided otherwise.
    $settings->add(new admin_setting_configselect(
        'local_catquiz/progressretention',
        get_string('progressretention', 'local_catquiz'),
        get_string('progressretention_desc', 'local_catquiz'),
        \local_catquiz\local\progress_retention::MINIMAL,
        [
            \local_catquiz\local\progress_retention::MINIMAL =>
                get_string('progressretention_minimal', 'local_catquiz'),
            \local_catquiz\local\progress_retention::KEEP =>
                get_string('progressretention_keep', 'local_catquiz'),
            \local_catquiz\local\progress_retention::TRACE =>
                get_string('progressretention_trace', 'local_catquiz'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_catquiz/progressretentiondays',
        get_string('progressretentiondays', 'local_catquiz'),
        get_string('progressretentiondays_desc', 'local_catquiz'),
        0,
        PARAM_INT
    ));

    // Add a setting for the default maximum attempt duration.
    $settings->add(new admin_setting_configtext(
        'local_catquiz/maximum_attempt_duration_hours',
        get_string('maxattemptduration', 'local_catquiz'),
        get_string('maxattemptduration_desc', 'local_catquiz'),
        24, // Default value.
        PARAM_INT // Expect integer type.
    ));
}
