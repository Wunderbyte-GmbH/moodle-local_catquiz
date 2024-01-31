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

    /**
     * @dataProvider regex_can_remove_db_prefixes_provider
     */
    public function test_regex_can_remove_db_prefixes(string $input, string $expected) {
        $this->assertEquals($expected, self::$regex->remove_db_prefixes($input));
    }

    public static function regex_can_remove_db_prefixes_provider() {
        return [
            'simple' => [
                'input' => 'm_local_catquiz',
                'expected' => '{local_catquiz}',
            ],
            'two occurences' => [
                'input' => 'mdl_local_catquiz_bla',
                'expected' => "{local_catquiz_bla}",
            ],
            'longer example' => [
                'input' => 'SELECT * FROM m_local_catquiz_attempts a JOIN m_local_catquiz_bla b ON a.userid = b.userid',
                'expected' => 'SELECT * FROM {local_catquiz_attempts} a JOIN {local_catquiz_bla} b ON a.userid = b.userid',
            ]
        ];
    }
}
