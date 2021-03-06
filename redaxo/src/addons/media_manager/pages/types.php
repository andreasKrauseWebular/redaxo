<?php

$content = '';

$Basedir = __DIR__;

$type_id = rex_request('type_id', 'int');
$func = rex_request('func', 'string');

if (rex_request('effects', 'boolean')) {
    include __DIR__ . '/effects.php';
    return;
}

$success = '';
$error = '';

//-------------- delete cache on type_name change or type deletion
if ((rex_post('func') == 'edit' || $func == 'delete') && $type_id > 0) {
    $counter = rex_media_manager::deleteCacheByType($type_id);
    //  $info = rex_i18n::msg('media_manager_cache_files_removed', $counter);
}

//-------------- delete type
if ($func == 'delete' && $type_id > 0) {
    $sql = rex_sql::factory();
    //  $sql->setDebug();
    $sql->setTable(rex::getTablePrefix() . 'media_manager_type');
    $sql->setWhere(['id' => $type_id]);

    try {
        $sql->delete();
        $success = rex_i18n::msg('media_manager_type_deleted') ;
    } catch (rex_sql_exception $e) {
        $error = $sql->getError();
    }
    $func = '';
}

//-------------- delete cache by type-id
if ($func == 'delete_cache' && $type_id > 0) {
    $counter = rex_media_manager::deleteCacheByType($type_id);
    $success = rex_i18n::msg('media_manager_cache_files_removed', $counter);
    $func = '';
}

//-------------- output messages
if ($success != '') {
    echo rex_view::success($success);
}

if ($error != '') {
    echo rex_view::error($error);
}

if ($func == '') {
    // Nach Status sortieren, damit Systemtypen immer zuletzt stehen
    // (werden am seltesten bearbeitet)
    $query = 'SELECT * FROM ' . rex::getTablePrefix() . 'media_manager_type ORDER BY status, name';

    $list = rex_list::factory($query);
    $list->setNoRowsMessage(rex_i18n::msg('media_manager_type_no_types'));
    $list->setCaption(rex_i18n::msg('media_manager_type_caption'));
    $list->addTableColumnGroup([40, 300, '*']);

    $list->removeColumn('id');
    $list->removeColumn('status');
    $list->removeColumn('description');

    $list->setColumnLabel('name', rex_i18n::msg('media_manager_type_name'));
    $list->setColumnFormat('name', 'custom', function ($params) {
            $list = $params['list'];
            $name = '<b>' . $list->getValue('name') . '</b>';
            $name .= ($list->getValue('description') != '') ? '<br /><span class="rex-note">' . $list->getValue('description') . '</span>' : '';
        return $name;
    });


    // icon column
    $thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '" title="' . rex_i18n::msg('media_manager_type_create') . '"><span class="rex-icon rex-icon-add-mediatype"></span></a>';
    $tdIcon = '<span class="rex-icon rex-icon-mediatype"</span>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-slim">###VALUE###</th>', '<td class="rex-slim">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'type_id' => '###id###']);

    // functions column spans 2 data-columns
    $funcs = rex_i18n::msg('media_manager_type_functions');

    $list->addColumn($funcs, rex_i18n::msg('media_manager_type_edit'), -1, ['<th colspan="4">###VALUE###</th>', '<td>###VALUE###</td>']);
    $list->setColumnParams($funcs, ['func' => 'edit', 'type_id' => '###id###']);

    $list->addColumn('editEffects', rex_i18n::msg('media_manager_type_effekts_edit'), -1, ['', '<td>###VALUE###</td>']);
    $list->setColumnParams('editEffects', ['type_id' => '###id###', 'effects' => 1]);


    $list->addColumn('deleteCache', rex_i18n::msg('media_manager_type_cache_delete'), -1, ['', '<td>###VALUE###</td>']);
    $list->setColumnParams('deleteCache', ['type_id' => '###id###', 'func' => 'delete_cache']);
    $list->addLinkAttribute('deleteCache', 'data-confirm', rex_i18n::msg('media_manager_type_cache_delete') . ' ?');

    // remove delete link on internal types (status == 1)
    $list->addColumn('deleteType', '', -1, ['', '<td>###VALUE###</td>']);
    $list->setColumnParams('deleteType', ['type_id' => '###id###', 'func' => 'delete']);
    $list->addLinkAttribute('deleteType', 'data-confirm', rex_i18n::msg('delete') . ' ?');
    $list->setColumnFormat('deleteType', 'custom', function ($params) {
        $list = $params['list'];
        if ($list->getValue('status') == 1) {
            return rex_i18n::msg('media_manager_type_system');
        }
        return $list->getColumnLink('deleteType', rex_i18n::msg('media_manager_type_delete'));
    });

    $content .= $list->get();

} elseif ($func == 'add' || $func == 'edit' && $type_id > 0) {
    if ($func == 'edit') {
        $formLabel = rex_i18n::msg('media_manager_type_edit');
    } elseif ($func == 'add') {
        $formLabel = rex_i18n::msg('media_manager_type_create');
    }

    rex_extension::register('REX_FORM_CONTROL_FIELDS', function (rex_extension_point $ep) {
        $controlFields = $ep->getSubject();
        $form = $ep->getParam('form');
        $sql  = $form->getSql();

        // remove delete button on internal types (status == 1)
        if ($sql->getRows() > 0 && $sql->hasValue('status') && $sql->getValue('status') == 1) {
            $controlFields['delete'] = '';
        }
        return $controlFields;
    });

    $form = rex_form::factory(rex::getTablePrefix() . 'media_manager_type', $formLabel, 'id=' . $type_id);

    $form->addErrorMessage(REX_FORM_ERROR_VIOLATE_UNIQUE_KEY, rex_i18n::msg('media_manager_error_type_name_not_unique'));

    $field = $form->addTextField('name');
    $field->setLabel(rex_i18n::msg('media_manager_type_name'));

    $field = $form->addTextareaField('description');
    $field->setLabel(rex_i18n::msg('media_manager_type_description'));

    if ($func == 'edit') {
        $form->addParam('type_id', $type_id);
    }

    $content .= $form->get();
}


echo rex_view::content('block', $content, '', $params = ['flush' => true]);
