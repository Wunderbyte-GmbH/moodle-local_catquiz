<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class contextcreator.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context;

use moodle_exception;

/**
 * Class contextcreator for test strategies.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contextcreator {

    /**
     * @var mixed loaders
     */
    protected $loaders;

    /**
     * @var int loaderindex
     */
    protected $loaderindex;

    /**
     * Instantiate parameters.
     *
     * @param array $loaders
     *
     */
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
     * @param  string[] $paramnames The Context items to load.
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

    /**
     * Load one.
     *
     * @param mixed $paramname
     * @param array $context
     *
     * @return mixed
     *
     */
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


    /**
     * Get loader.
     *
     * @param mixed $paramname
     *
     * @return mixed
     *
     */
    protected function getloader($paramname) {
        if (! isset($this->loaderindex[$paramname])) {
            throw new moodle_exception(sprintf('No Loader is available for "%s"', $paramname));
        }

        return $this->loaders[$this->loaderindex[$paramname]];
    }
}
