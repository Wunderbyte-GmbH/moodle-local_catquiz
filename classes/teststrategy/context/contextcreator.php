<?php

namespace local_catquiz\teststrategy\context;
use moodle_exception;

class contextcreator {

    protected $loaders;
    protected $loaderindex;

    public function __construct(array $loaders) {
        $this->loaders = $loaders;

        foreach ($loaders as $index => $loader) {
            if (! $loader instanceof contextloaderinterface) {
                throw new moodle_exception(
                    "contextloader was passed a class that does not implement the contextloader interface"
                );
            }
            
            foreach ($loader->provides() as $param) {
                $this->loaderindex[$param] = $index;
            }
        }
    }

    /**
     * Loads context items specified by itemNames into the given Context.
     * 
     * @param  string[] $param_names The Context items to load.
     * @param  array  $context   The initial context to load into.
     * @return array
     */
    public function load($param_names, array $context)
    {
        $need_to_load = array_values(array_unique(array_diff($param_names, array_keys($context))));
        
        foreach ($need_to_load as $param_name) {
            $context = $this->load_one($param_name, $context);
        }
        
        return $context;
    }
    
    protected function load_one($param_name, array $context)
    {
        $loader = $this->getLoader($param_name);
        
        foreach ($loader->requires() as $require) {
            if (! array_key_exists($require, $context)) {
                throw new moodle_exception(
                    sprintf(
                        'Loader for "%s" requires Context item "%s"', $param_name, $require
                    )
                );
            }
        }
        
        return $loader->load($context);
    }
    
    protected function getLoader($param_name)
    {
        if (! isset($this->loaderindex[$param_name])) {
            throw new moodle_exception(sprintf('No Loader is available for "%s"', $param_name));
        }
        
        return $this->loaders[$this->loaderindex[$param_name]];
    }
}