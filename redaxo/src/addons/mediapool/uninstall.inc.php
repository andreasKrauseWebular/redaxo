<?php

/**
 * Mediapool Addon
 *
 * @author redaxo
 *
 * @package redaxo5
 * @version svn:$Id$
 */

$error = '';

if ($error == '')
{
  $this->setProperty('install', false);
  rex_generateAll();
}