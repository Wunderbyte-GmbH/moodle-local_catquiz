<?php
namespace local_catquiz; // Your module name.
use basic_testcase; // Moodle test class.
use local_catquiz\regex;

class regex_test extends basic_testcase {
    /**
     * @dataProvider regex_can_add_db_prefixes_provider
     */
    public function test_regex_can_add_db_prefixes(string $input, string $expected) {
        $regex = new regex();
        $this->assertEquals($expected, $regex->add_db_prefixes($input, 'm_'));
    }

    public static function regex_can_add_db_prefixes_provider() {
        return [
            'simple' => [
                'input' => '{local_catquiz}',
                'expected' => 'm_local_catquiz',
            ],
            'two occurences' => [
                'input' => "{local_catquiz} SOME SQL AND NOW ANOTHER {local_catquiz}",
                'expected' => "m_local_catquiz SOME SQL AND NOW ANOTHER m_local_catquiz",
            ],
        ];
    }
}
