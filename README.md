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
