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
    $settings = new admin_settingpage($componentname . '_settings',  get_string('pluginname', 'local_catquiz'));
    $ADMIN->add('localplugins', $settings);

    foreach (core_plugin_manager::instance()->get_plugins_of_type('catmodel') as $plugin) {
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
        PARAM_FLOAT)
    );
    $settings->add(new admin_setting_configtext(
        'local_catquiz/minquestions_default',
        get_string('minquestions_default_name', 'local_catquiz'),
        get_string('minquestions_default_desc', 'local_catquiz'),
        3,
        '/^\d+$/'
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_catquiz/automatic_reload_on_scale_selection',
            get_string('automatic_reload_on_scale_selection', 'local_catquiz'),
            get_string('automatic_reload_on_scale_selection_description', 'local_catquiz'),
            1));

    $settings->add(new admin_setting_configtext(
        'local_catquiz/time_penalty_threshold',
        get_string('time_penalty_threshold_name', 'local_catquiz'),
        get_string('time_penalty_threshold_desc', 'local_catquiz'),
        10,
        '/^[1-9]\d*$/'
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_catquiz/store_debug_info',
            get_string('store_debug_info_name', 'local_catquiz'),
            get_string('store_debug_info_desc', 'local_catquiz'),
            0));

    // Add a setting for the default maximum attempt duration.
    $settings->add(new admin_setting_configtext(
        'local_catquiz/maximum_attempt_duration_hours',
        get_string('maxattemptduration', 'local_catquiz'),
        get_string('maxattemptduration_desc', 'local_catquiz'),
        24, // Default value.
        PARAM_INT // Expect integer type.
    ));
    $settings->add(new admin_setting_configtext(
        'local_catquiz/central_host',
        get_string('central_host', 'local_catquiz'),
        get_string('central_host_desc', 'local_catquiz'),
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_catquiz/central_token',
        get_string('central_token', 'local_catquiz'),
        get_string('central_token_desc', 'local_catquiz'),
        PARAM_ALPHANUM
    ));
    $settings->add(new admin_setting_configtext(
        'local_catquiz/sync_scale',
        get_string('sync_scale', 'local_catquiz'),
        get_string('sync_scale_desc', 'local_catquiz'),
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_catquiz/central_scale_labels',
        get_string('central_scale_labels', 'local_catquiz'),
        get_string('central_scale_labels_desc', 'local_catquiz'),
        '', // Default value.
        PARAM_TEXT
    ));
}
