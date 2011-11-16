<?php

// ----------------------------------------- Alles generieren

/**
 * Löscht den vollständigen Artikel-Cache und generiert den clang-cache
 */
function rex_generateAll()
{
  // ----------------------------------------------------------- generated löschen
  rex_deleteAll();

  // ----------------------------------------------------------- message
  $MSG = rex_i18n::msg('delete_cache_message');

  // ----- EXTENSION POINT
  $MSG = rex_extension::registerPoint('ALL_GENERATED', $MSG);

  return $MSG;
}

/**
 * Löscht den vollständigen Artikel-Cache.
 */
function rex_deleteAll()
{
  // unregister logger, so the logfile can also be deleted
  rex_logger::unregister();

  rex_dir::deleteIterator(rex_dir::recursiveIterator(rex_path::cache())->excludeFiles(array('.htaccess', '_readme.txt'), false));

  rex_logger::register();

  rex_clang::reset();
}