<?php
namespace local_catquiz\local;

use local_catquiz\local\status;

class result
{
    protected $status;
    protected $value;
    
    public static function err($status = status::ERROR_GENERAL, $value = null)
    {
        return new result($value, $status);
    }
    
    public static function ok($value = null)
    {
        return new result($value, status::OK);
    }
    
    public function __construct($value = null, $status = status::OK)
    {
        $this->value  = $value;
        $this->status = $status;
    }
    
    public function get_status()
    {
        return $this->status;
    }
    
    public function unwrap()
    {
        return $this->value;
    }
    
    public function isOk()
    {
        return $this->status === Status::OK;
    }
    
    public function isErr()
    {
        return $this->status !== Status::OK;
    }

    public function getErrorMessage()
    {
        if ($this->isOk()) {
            throw new \moodle_exception("Trying to get the error message for a result that has no error.");
        }

        return get_string($this->get_status(), 'local_catquiz');
    }
}