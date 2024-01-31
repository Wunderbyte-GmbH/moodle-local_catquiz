<?php
namespace local_catquiz; // Your module name.
use basic_testcase; // Moodle test class.
use local_catquiz\regex;

class regex_test extends basic_testcase {
    public function test_regex_can_add_db_prefixes() {
        $regex = new regex();
        $input = "{local_catquiz}";
        $expected = "m_local_catquiz";
        $this->assertEquals($expected, $regex->add_db_prefixes($input, 'm_'));
    }
}
