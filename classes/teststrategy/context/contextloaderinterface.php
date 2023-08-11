<?php

namespace local_catquiz\teststrategy\context;

interface contextloaderinterface {
    /**
     * The given data will be loaded into the context once the `load()` method is called
     * @return array
     */
    public function provides(): array;

    /**
     * Requires the given data
     * @return array
     */
    public function requires(): array;

    /**
     * Loads the data it provides into the given context
     * @param array $context
     * @return array
     */
    public function load(array $context): array;
}
