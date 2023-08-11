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
    public function load($paramnames, array $context) {
        $needtoload = array_values(array_unique(array_diff($paramnames, array_keys($context))));

        foreach ($needtoload as $paramname) {
            $context = $this->load_one($paramname, $context);
        }

        return $context;
    }

    protected function load_one($paramname, array $context) {
        $loader = $this->getLoader($paramname);

        foreach ($loader->requires() as $require) {
            if (! array_key_exists($require, $context)) {
                throw new moodle_exception(
                    sprintf(
                        'Loader for "%s" requires Context item "%s"', $paramname, $require
                    )
                );
            }
        }

        return $loader->load($context);
    }

    protected function getloader($paramname) {
        if (! isset($this->loaderindex[$paramname])) {
            throw new moodle_exception(sprintf('No Loader is available for "%s"', $paramname));
        }

        return $this->loaders[$this->loaderindex[$paramname]];
    }
}
