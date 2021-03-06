<?php

/**
 * Represents a null plugin
 *
 * @author gharlan
 * @package redaxo\core
 */
class rex_null_plugin extends rex_null_package implements rex_plugin_interface
{
    /**
     * {@inheritDoc}
     */
    public function getType()
    {
        return 'plugin';
    }
}
