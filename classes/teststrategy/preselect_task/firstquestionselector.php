<?php

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

final class firstquestionselector extends preselect_task implements wb_middleware
{
const STARTWITHEASIESTQUESTION = 'startwitheasiestquestion';
const  STARTWITHFIRSTOFSECONDQUINTIL = 'startwithfirstofsecondquintil';
const  STARTWITHFIRSTOFSECONDQUARTIL = 'startwithfirstofsecondquartil';
const  STARTWITHMOSTDIFFICULTSECONDQUARTIL = 'startwithmostdifficultsecondquartil';
const  STARTWITHAVERAGEABILITYOFTEST = 'startwithaverageabilityoftest';
const  STARTWITHCURRENTABILITY = 'startwithcurrentability';

    public function run(array $context, callable $next): result {

        switch ($context['selectfirstquestion']) {
            case self::STARTWITHEASIESTQUESTION:
               throw new \Exception("TODO implement");
            case self::STARTWITHFIRSTOFSECONDQUINTIL:
               throw new \Exception("TODO implement");
            case self::STARTWITHFIRSTOFSECONDQUARTIL:
               throw new \Exception("TODO implement");
            case self::STARTWITHMOSTDIFFICULTSECONDQUARTIL:
               throw new \Exception("TODO implement");
            case self::STARTWITHAVERAGEABILITYOFTEST:
               throw new \Exception("TODO implement");
            case self::STARTWITHCURRENTABILITY:
                return $next($context);
            
            default:
                throw new \Exception(sprintf("Unknown option to select first question: %s"), $context['selectfirstquestion']);
        }
    }

    public function get_required_context_keys(): array {
        return [
            'selectfirstquestion',
            'lastquestion'
        ];
    }
}
