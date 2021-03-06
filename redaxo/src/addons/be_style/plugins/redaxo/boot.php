<?php

/**
 * REDAXO Default-Theme
 *
 * @author Design
 * @author ralph.zumkeller[at]yakamara[dot]de Ralph Zumkeller
 * @author <a href="http://www.yakamara.de">www.yakamara.de</a>
 *
 * @author Umsetzung
 * @author thomas[dot]blum[at]redaxo[dot]de Thomas Blum
 * @author <a href="http://www.blumbeet.com">www.blumbeet.com</a>
 *
 * @package redaxo5
 *
 * @var rex_plugin $this
 */

$mypage = 'redaxo';

if (rex::isBackend()) {

    require __DIR__ . '/pages/font.php';

    rex_view::addCssFile(rex_url::backendController(['be_style_' . $mypage . '_font' => 'entypo']));
    rex_view::addCssFile($this->getAssetsUrl('import.css'));
    rex_view::addJsFile($this->getAssetsUrl('js.js'));
    rex_view::setFavicon($this->getAssetsUrl('favicon.ico'));

    rex_extension::register('PAGE_BODY_ATTR', function (rex_extension_point $ep) {
        $subject = $ep->getSubject();
        $subject['class'][] = 'redaxo';
        return $subject;
    });
}
