<?php
namespace local_catquiz\local;

/**
 * Contains status codes, that can be part of a local_catquiz\local\result.
 * 
 * The value of each error constant should have a translation string entry so
 * that it can be automatically translated by the result class.
 */
class status
{
    const OK = 'ok';
    const ERROR_GENERAL = 'error';
    const ERROR_NO_REMAINING_QUESTIONS = 'noremainingquestions';
    const ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE = 'testiteminrelatedscale';
    const ERROR_MISSING_CONTEXT_KEY = 'missingcontextkey';
}