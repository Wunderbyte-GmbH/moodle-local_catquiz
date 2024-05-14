# Adaptive Quiz - Advanced CAT Module #

## Short description ##
The plugin local_catquiz implements full Computer Adaptive Testing (CAT) capabilities in Moodle. Using common one-dimensional Item Response Theory (IRT) models, test takers are presented only with questions that fall within their identified ability range, based on the answers they give.

## Detailed description ##
The plugin can be used with all types of questions in Moodle that allow for automatic scoring (e.g. multiple choice or cloze text). The plugin provides different IRT models:
* dichotomous questions (only account for right or wrong answers)
    * Rasch-Model (1 parametric logistic model)
    * 2PL-Rasch-Birnbaum
    * 3PL-Mixed-Rasch-Birnbaum
* polytomous questions (account for right, wrong and partially correct answers)
    * Graded Response Model and Generalized Graded Response Model
    * further models will be realized in upcoming versions

The plugin enables the creation, administration and modification of different scales on which measurements are taken. For this purpose, the plug-in creates a new role "CAT Manager", which is authorized to perform these administrative tasks.

Furthermore, the plugin allows to pursuit different test strategies:
* running a normal CAT
* adaptive diagnose for weakest/strongest ability in selected (sub-)scales
* adaptive diagnose for all given (sub-)scales
* run a classical test (ask all questions), but evaluate by using IRT

### Dependencies

For using this plugin, you are required to install the following plugins:
* mod_adaptivequiz - the activity from which a CAT test can be started (vers 3.1 onwards). Please use the [wb-0.9.0-rc2 release in the wunderbyte fork](https://github.com/Wunderbyte-GmbH/moodle-mod_adaptivequiz/releases/tag/wb-0.9.0-rc2) until our changes are integrated into the [upstream plugin](https://github.com/vtos/moodle-mod_adaptivequiz) (in progress).
* [adaptivequizcatmodel_catquiz](https://github.com/Wunderbyte-GmbH/moodle-adaptivequizcatmodel_catquiz/releases/tag/1.0.0) - which serves as a bridge between mod_adaptivequiz and local_adaptivequiz
* [local_wunderbyte_table](https://github.com/Wunderbyte-GmbH/moodle-local_wunderbyte_table) - database and tables handling

It is recommended to install the following plugins as well:
* local_shortcodes - helps to render results at any point in your courses
* local_adaptivelearningpaths - adds the possibility to define learning paths based on quiz and adaptive quiz results (to be released soon)

## Core concepts & Terminology ##
The plugin tries to present questions matching the ability of the student. To assign parameters like difficulty to each question, questions and params are grouped as items.

* Items: question with parameters
* Parameters: characteristics of a question (e.g. difficulty, discrimination, guessing probability).
* Models: model the charcteristics of an item by a specified set of parameters.
* Scales: items are grouped in scales (and subscales) according to the construct they are measuring. For example you may have a parentscale "mathematics" that measures a general construct "mathematical abilities" with subscales "algebra" and "geometry" that contain items of the field of algebra or geometry each.
* Contexts: allow you to reuse the same question within different time periods or usage frames without loosing or confusing its parameters and attempt data. This could be useful if e.g. the question is part of different quizzes or for managing data of different years.
* Importer: imports items, params and scales from a csv file.

## CSV Importer ##
The csv importer accepts different formats of separators and encodings. Some columns are mandatory whereas others are optional. Find detailed descriptions of all columns on the same page, also the demo csv file can be found in: local/catquiz/classes/importer/demo.csv

## Contexts ##
* When importing with the csv importer, a new context is created automatically. With respect to the new context, new items from the import csv file are added to the corresponding scales whereas existing items are updated with data from the import file.

## Shortcodes ##
To use the shortcode functionality, use plugin filter_shortcodes: https://moodle.org/plugins/filter_shortcodes

Shortcodes can be added in any text area and label via editor, e.g. in the course.

1. To display feedbacks of the past quiz attempts use [catquizfeedback].

The following parameters can be defined:
* numberofattempts=3 // Defined the number of feedbacks displayed in collapsables. Starting with the newest.

2. To display an overview table of all scales use [catscalesoverview].


## Installing via uploaded ZIP file ##

1. Make sure to install the dependencies as described in [dependencies](#dependencies)
1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

Make sure to install the dependencies as described in the [dependencies](#dependencies).

The plugin can then be installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/catquiz

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Setup with sample data

### Import sample data

You can follow these steps to setup a quiz with sample data. This assumes that you've already installed the required plugins and created a course.

1. Import questions to the course. You can use the [simulation.xml](https://github.com/Wunderbyte-GmbH/moodle-local_catquiz/blob/main/tests/fixtures/simulation.xml) file from the `tests/fixtures` directory.
2. Import item parameters and create CAT scales: Click on the "Catquiz" link in the main menu and select the "Import" tab. You can use the [simulation.csv](https://github.com/Wunderbyte-GmbH/moodle-local_catquiz/blob/main/tests/fixtures/simulation.csv) file from the `tests/fixtures` directory. Select `;` as CSV separator. When you press import, this will automatically create new CAT scales. There will be warnings about missing labels for pilot questions, but you can ignore these.
3. If you navigate to the CAT manager via the "Catquiz" link in the main menu, you can check the different tabs and see that items were imported.

### Setup a quiz

1. Navigate to your course, activate the edit mode, and add a new activity "Adaptive Quiz".
2. After entering a name, in the "CAT model" section select Catquiz CAT model.
3. Under "Purpose of test" you can select a teststrategy. Here we will use "CAT".
4. You can check "Active pilot mode" to include questions without item parameters in the quiz. When value of 25 is used, on average 25% of the displayed questions will be pilot questions.
5.  Additionally, you can choose what question the attempt should be started with, set a minimum and maximum number of questions per attempt (maybe for testing set the maximum to a low value like e.g. 8) and choose at which standard error the test should abort.

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

