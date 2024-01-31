<?php
namespace local_catquiz; // Your module name.
use basic_testcase; // Moodle test class.
use local_catquiz\regex;

class regex_test extends basic_testcase {

    private static $regex;

    public static function setUpBeforeClass(): void {
        echo "Setup before class" . PHP_EOL;
        self::$regex = new regex();
    }

    /**
     * @dataProvider regex_can_add_db_prefixes_provider
     */
    public function test_regex_can_add_db_prefixes(string $input, string $expected) {
        $this->assertEquals($expected, self::$regex->add_db_prefixes($input, 'm_'));
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
