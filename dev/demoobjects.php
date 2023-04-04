<?php


//function get_demo_person_ability2(){
//    $demo_person_abilities = [];
//    for ($i=1;$i <=10000;$i++){
//        $abw = rand(-30,30)/100;
//        $demo_person_abilities[] = 0.5 + $abw;
//    }
//    return $demo_person_abilities;
//}
//
//// array with 10 random item difficulties
//function get_demo_item_difficulies(){
//    return [0.64, 0.95, 0.28, 0.88, 0.51, 0.61, 0.8, 0.29, 0.46, 0.41];
//}

//array with 10 random item discriminations


// ---------------- Advanced Structures -----------------




$demo_person_parameters = Array(
    'person_id' => 'person_ability'
);


$demo_item_parameter = Array(
    '1' => Array( // item id
        'difficulty' => 0.5,
        'discrimination' => 0.6,
        'param3' => 1.0
    )
);

$demo_person_response_const = Array( // response from a single person
    '1' => 1, // item_id
    '2' => 0,
    '3' => 1,
    '4' => 1,
    '5' => 1,
    '6' => 0,
    '7' => 1,
    '8' => 0,
    '9' => 1,
    '10' => 1
);


$demo_full_response = Array(
    '1' => Array( // item_id
        'person_abilities' => Array(0.5,0.3,0.2,0.8,0.8,0.4),
        'item_responses' => Array(1,0,1,1,0,1),
    ),
    '2' => Array( // item_id
        'person_abilities' => Array(0.5,0.3,0.2,0.8,0.8,0.4),
        'item_responses' => Array(0,1,0,1,0,1),
    )
);

$demo_item_response = Array(
    Array(
        'person_abilities' => Array(0.5,0.3,0.2,0.8,0.8,0.4),
        'item_responses' => Array(1,0,1,1,0,1),
    )
);

$demo_response = Array(
    "1" => Array( //userid
        "comp1" => Array( // component
            "1" => Array( //questionid
                "fraction" => 0,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955326
            ),
            "2" => Array(
                "fraction" => 0,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955332
            ),
            "3" => Array(
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955338
            )
        )
    ),
    "2" => Array(
        "comp2" => Array(
            "1" => Array(
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955326
            ),
            "2" => Array(
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955332
            ),
            "3" => Array(
                "fraction" => 1,
                "max_fraction" => 1,
                "min_fraction" => 0,
                "qtype" => "truefalse",
                "timestamp" => 1646955338
            )
        )
    )
);
