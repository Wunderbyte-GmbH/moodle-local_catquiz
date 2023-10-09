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
 *  Demo object.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable
// function get_demo_person_ability2(){
// $demo_person_abilities = [];
// for ($i=1;$i <=10000;$i++){
// $abw = rand(-30,30)/100;
// $demo_person_abilities[] = 0.5 + $abw;
// }
// return $demo_person_abilities;
// }
//
// array with 10 random item difficulties
// function get_demo_item_difficulies(){
// return [0.64, 0.95, 0.28, 0.88, 0.51, 0.61, 0.8, 0.29, 0.46, 0.41];
// }
// phpcs:enable

// Array with 10 random item discriminations.
// Advanced Structures.

$demopersonparameters = [
    'person_id' => 'person_ability',
];


$demoitemparameter = [
    '1' => [ // Item id.
        'difficulty' => 0.5,
        'discrimination' => 0.6,
        'param3' => 1.0,
    ],
];

$demopersonresponseconst = [ // Response from a single person.
    '1' => 1, // Item id.
    '2' => 0,
    '3' => 1,
    '4' => 1,
    '5' => 1,
    '6' => 0,
    '7' => 1,
    '8' => 0,
    '9' => 1,
    '10' => 1,
];

$demofullresponse = [
    '1' => [ // Item_id.
        'person_abilities' => [0.5, 0.3, 0.2, 0.8, 0.8, 0.4],
        'item_responses' => [1, 0, 1, 1, 0, 1],
    ],
    '2' => [ // Item_id.
        'person_abilities' => [0.5, 0.3, 0.2, 0.8, 0.8, 0.4],
        'item_responses' => [0, 1, 0, 1, 0, 1],
    ],
];

$demoitemresponse = [
    [
        'person_abilities' => [0.5, 0.3, 0.2, 0.8, 0.8, 0.4],
        'item_responses' => [1, 0, 1, 1, 0, 1],
    ],
];

$demoresponse = [
    "1" => [ // Userid.
        "comp1" => [ // Component.
            "1" => [ // Questionid.
                "fraction" => 0,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955326,
            ],
            "2" => [
                "fraction" => 0,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955332,
            ],
            "3" => [
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955338,
            ],
        ],
    ],
    "2" => [
        "comp2" => [
            "1" => [
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955326,
            ],
            "2" => [
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955332,
            ],
            "3" => [
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955338,
            ],
        ],
    ],
];
