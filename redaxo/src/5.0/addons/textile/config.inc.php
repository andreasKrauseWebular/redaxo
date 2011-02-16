<?php

/**
 * Textile Addon
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 *
 * @package redaxo4
 * @version svn:$Id$
 */

$REX['PERM'][] = 'textile[]';
$REX['EXTPERM'][] = 'textile[help]';

require_once rex_path::addon('textile', 'functions/function_textile.inc.php');

if ($REX['REDAXO'])
{
  require_once rex_path::addon('textile', 'extensions/function_extensions.inc.php');
  require_once rex_path::addon('textile', 'functions/function_help.inc.php');

  rex_register_extension('PAGE_HEADER', 'rex_a79_css_add');
}