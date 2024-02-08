# ALiSe CAT Quiz #

## Short description ##
The plugin local_catquiz implements Computer Adaptive Testing (CAT) in Moodle. Using static Item Response Theory (IRT) models, test takers are presented only with questions that fall within their identified ability range, based on the answers they give.
The plugin is a central part of the CATQuiz plugin family.

## Detailed description ##

The plugin local_catquiz implements Computer Adaptive Testing (CAT) in Moodle. Using static Item Response Theory (IRT) models, test takers are presented only with questions that fall within their identified ability range, based on the answers they give. This shortens the required test length and increases test accuracy.
The plugin can be used with all question types in Moodle that allow automatic scoring (e.g. multiple choice or cloze text). Depending on the question format, the plugin provides different IRT models:
* Rasch-Model (1 parametric logistic model)
* 2PL-Rasch-Birnbaum
* 3PL-Mixed-Rasch-Birnbaum
* Graded Response Model
* Generalized Partial Credit Model

In addition, the plug-in enables the creation, administration and modification of different scales on which measurements are taken. For this purpose, the plug-in creates a new role "Test Administrator", which is authorized to perform these administrative tasks for defined course areas.

The plugin is a central part of the CATQuiz plugin family. It is recommended to install the following plugins as well:
* mod_adaptive - the activity from which a CAT test can be started
* mod_catquizfeedbackgrouping - a text block that gives detailed feedback on a CAT test and optionally assigns users to groups according to this feedback
* task_catquizparamest - a background task for calculating item parameters of deployed CAT test questions

## Contexts ##

* On import a new context is created and all testitems of concerned parentscale are duplicated. Only those present in the import are updated.

## Attempts ##
* Attempts are calculated by the time of the context. Switching contexts only affects the number of attempts when the time includes or excludes responses.

## Shortcodes ##

Shortcodes can be added to a course in a Text and Media area.

1. To display feedbacks of the past quiz attempts use [catquizfeedback].

The following parameters can be defined:
* numberofattempts=3 // Defined the number of feedbacks displayed in collapsables. Starting with the newest.
* primaryscale=parent // Each strategy defines a scale that is be primary for the feedback. It is used as comparison to averagevalues and values  from other scales. If you display a feedback via shortcode, you can change this primaryscale. You can either choose a specific scale via ID or name, or choose "parent", "lowest", "strongest", "highest" (the second two pointing to the same scale).
* show=questionssummary // Parts of the feedback that are hidden by default can be displayed with the show attribute.
* hide=comparetotestaverage,chart,pilotquestions // Parts of the feedbacks can be hidden. Either the enitre feedback of a section or keys for certain parts. Generators and subkeys are:

'personabilities' => [
    'feedback_personabilities',
    'personabilitychart',
    'progressindividual',
    'progresscomparison',
    'abilityprofile',
];
'comparetotestaverage' => [
    'comparisontext', // You performed better than ...
    'colorbar',
    'colorbarlegend', // Legend of the colorbar.
];
'customscalefeedback' => [];
'debuginfo' => [];
'graphicalsummary' => [
    'teststrategyname',
    'testprogresschart',
    'testresultstable',
];
'pilotquestions' => [];
'questionssummary' => []; // This part is hidden by default.

2. To display an overview table of all scales use [catscalesoverview].


## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/catquiz

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2022 Wunderbyte GmbH <info@wunderbyte.at>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
