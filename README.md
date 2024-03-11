# ALiSe CAT Quiz #

## Short description ##
The plugin local_catquiz implements Computer Adaptive Testing (CAT) in Moodle. Using static Item Response Theory (IRT) models, test takers are presented only with questions that fall within their identified ability range, based on the answers they give. This shortens the required test length and increases test accuracy.
The plugin is a central part of the CATQuiz plugin family.

## Detailed description ##
The plugin can be used with dichotomous question types in Moodle that allow automatic scoring (e.g. multiple choice or cloze text). Depending on the question format, the plugin provides different IRT models:
* Rasch-Model (1 parametric logistic model)
* 2PL-Rasch-Birnbaum
* 3PL-Mixed-Rasch-Birnbaum

In addition, the plug-in enables the creation, administration and modification of different scales on which measurements are taken. For this purpose, the plug-in creates a new role "catquiz Manager", who is authorized to perform these administrative tasks for defined course areas.

The plugin makes use of the mod_adaptive plugin. It is therefore required to install that as well.

## Core concepts & Terminology ##
The plugin tries to present questions matching the ability of the student. To assign parameters like difficulty to each question, questions and params are grouped as items.

* Items: Question with parameters
* Parameters: characteristics of a question (e.g. difficulty, discrimination, guessing probability).
* Models: model the charcteristics of an item by a specified set of parameters.
* Scales: Items are grouped in scales (and subscales) according to the construct they are measuring. For example you may have a parentscale "mathematics" that measures a general construct "mathematical abilities" with subscales "algebra" and "geometry" that contain items of the field of algebra or geometry each.
* Contexts: allow you to reuse the same question within different time periods or usage frames without loosing or confusing its paramtesters and attempt data each. This could be useful if e.g. the question is part of different quizzes or for managing data of different years.
* Importer: imports items, params and scales from a csv file.

## CSV Importer ##
The csv importer accepts different formats of separators and encodings. Some columns are mandatory whereas others are optional. Find detailed descriptions of all columns are explained on the same page, also the demo csv file can be found in: local/catquiz/classes/importer/demo.csv

## Contexts ##
* When importing with the csv importer, a new context is created automatically. With respect to the new context, new items from the import csv file are added to the corresponding scales whereas existing items are updated with idata from the import file.

## Shortcodes ##
To use the shortcode functionality, use plugin filter_shortcodes: https://moodle.org/plugins/filter_shortcodes

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

2024 Wunderbyte GmbH <info@wunderbyte.at>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
