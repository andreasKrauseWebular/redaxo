<?php

/**
 *
 * @package redaxo5
 */

$media_method = rex_request('media_method', 'string');
$media_name = rex_request('media_name', 'string');

// *************************************** CONFIG

$thumbs = true;
$media_manager = rex_addon::get('media_manager')->isAvailable();

// *************************************** KATEGORIEN CHECK UND AUSWAHL

// ***** kategorie auswahl
$db = rex_sql::factory();
$file_cat = $db->getArray('SELECT * FROM ' . rex::getTablePrefix() . 'media_category ORDER BY name ASC');

// ***** select bauen
$sel_media = new rex_media_category_select($check_perm = false);
$sel_media->setId('rex_file_category');
$sel_media->setName('rex_file_category');
$sel_media->setSize(1);
$sel_media->setStyle('class="rex-form-select"');
$sel_media->setSelected($rex_file_category);
$sel_media->setAttribute('onchange', 'this.form.submit();');
$sel_media->addOption(rex_i18n::msg('pool_kats_no'), '0');

// ----- EXTENSION POINT
echo rex_extension::registerPoint(new rex_extension_point('PAGE_MEDIAPOOL_HEADER', '', [
    'subpage' => $subpage,
    'category_id' => $rex_file_category
]));


// ***** formular
$cat_out = '<div class="rex-form" id="rex-form-mediapool-selectcategory">
                            <form action="' . rex_url::currentBackendPage() . '" method="post">
                                <fieldset class="rex-form-col-2">
                                    <legend>' . rex_i18n::msg('pool_select_cat') . '</legend>

                                    <div class="rex-form-wrapper">
                                        ' . $arg_fields . '

                                        <div class="rex-form-row">
                                            <p class="rex-form-col-a rex-form-text" id="be_search-media-search">
                                                <label for="be_search-media-name">' . rex_i18n::msg('be_search_mpool_media') . '</label>
                                                <input class="rex-form-text" type="text" name="media_name" id="be_search-media-name" value="' . $media_name . '" />
                                                <input class="rex-form-submit" type="submit" value="' . rex_i18n::msg('be_search_mpool_start') . '" />
                                            </p>

                                            <p class="rex-form-col-b rex-form-select">
                                                <label for="rex_file_category">' . rex_i18n::msg('pool_kats') . '</label>
                                                ' . $sel_media->get();

if ($file_id) {
    $cat_out .= '<input class="rex-form-submit" type="submit" value="' . rex_i18n::msg('show') . '" />';
}

$cat_out .= '
                                            </p>
                                        </div>';

if (!$file_id) {
    $cat_out .= '            <noscript>
                                            <div class="rex-form-row">
                                                <p class="rex-form-submit">
                                                    <input class="rex-form-submit" type="submit" value="' . rex_i18n::msg('pool_search') . '" />
                                                </p>
                                            </div>
                                        </noscript>';
}


$cat_out .= '     </div>
                                </fieldset>
                            </form>
                        </div>
';

// ----- EXTENSION POINT
$cat_out = rex_extension::registerPoint(new rex_extension_point('MEDIA_LIST_TOOLBAR', $cat_out, [
    'subpage' => $subpage,
    'category_id' => $rex_file_category
]));

// *************************************** Subpage: Media

if ($file_id && rex_post('btn_delete', 'string')) {
    $sql = rex_sql::factory()->setQuery('SELECT filename FROM ' . rex::getTable('media') . ' WHERE id = ?', [$file_id]);
    $media = null;
    if ($sql->getRows() == 1) {
        $media = rex_media::get($sql->getValue('filename'));
    }

    if ($media) {
        $filename = $media->getFileName();
        if ($PERMALL || rex::getUser()->getComplexPerm('media')->hasCategoryPerm($media->getCategoryId())) {
            $return = rex_mediapool_deleteMedia($filename);
            if ($return['ok']) {
                $info = $return['msg'];
            } else {
                $warning = $return['msg'];
            }
            $file_id = 0;
        } else {
            $warning = rex_i18n::msg('no_permission');
        }
    } else {
        $warning = rex_i18n::msg('pool_file_not_found');
        $file_id = 0;
    }
}

if ($file_id && rex_post('btn_update', 'string')) {

    $gf = rex_sql::factory();
    $gf->setQuery('select * from ' . rex::getTablePrefix() . "media where id='$file_id'");
    if ($gf->getRows() == 1) {
        if ($PERMALL || (rex::getUser()->getComplexPerm('media')->hasCategoryPerm($gf->getValue('category_id')) && rex::getUser()->getComplexPerm('media')->hasCategoryPerm($rex_file_category))) {

            $FILEINFOS = [];
            $FILEINFOS['rex_file_category'] = $rex_file_category;
            $FILEINFOS['file_id'] = $file_id;
            $FILEINFOS['title'] = rex_request('ftitle', 'string');
            $FILEINFOS['filetype'] = $gf->getValue('filetype');
            $FILEINFOS['filename'] = $gf->getValue('filename');

            $return = rex_mediapool_updateMedia($_FILES['file_new'], $FILEINFOS, rex::getUser()->getValue('login'));

            if ($return['ok'] == 1) {
                $info = $return['msg'];
                // ----- EXTENSION POINT
                 // rex_extension::registerPoint(new rex_extension_point('MEDIA_UPDATED','',array('id' => $file_id, 'type' => $FILEINFOS["filetype"], 'filename' => $FILEINFOS["filename"] )));
                 rex_extension::registerPoint(new rex_extension_point('MEDIA_UPDATED', '', $return));
            } else {
                $warning = $return['msg'];
            }
        } else {
            $warning = rex_i18n::msg('no_permission');
        }
    } else {
        $warning = rex_i18n::msg('pool_file_not_found');
        $file_id = 0;
    }
}

if ($file_id) {
    $gf = rex_sql::factory();
    $gf->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'media WHERE id = "' . $file_id . '"');
    if ($gf->getRows() == 1) {
        $TPERM = false;
        if ($PERMALL || rex::getUser()->hasPerm('media[' . $gf->getValue('category_id') . ']')) {
            $TPERM = true;
        }

        echo $cat_out;

        $ftitle = $gf->getValue('title');
        $fname = $gf->getValue('filename');
        $ffiletype = $gf->getValue('filetype');
        $ffile_size = $gf->getValue('filesize');
        $ffile_size = rex_formatter::bytes($ffile_size);
        $rex_file_category = $gf->getValue('category_id');


        $isImage = rex_media::isImageType(rex_file::extension($fname));
        if ($isImage) {
            $fwidth = $gf->getValue('width');
            $fheight = $gf->getValue('height');
            if ($size = @getimagesize(rex_path::media($fname))) {
                $fwidth = $size[0];
                $fheight = $size[1];
            }

            if ($fwidth > 199) {
                $rfwidth = 200;
            } else {
                $rfwidth = $fwidth;
            }
        }

        $add_image = '';
        $add_ext_info = '';
        $style_width = '';
        $encoded_fname = urlencode($fname);
        if ($isImage) {
            $add_ext_info = '
            <div class="rex-form-row">
                <p class="rex-form-read">
                    <label for="fwidth">' . rex_i18n::msg('pool_img_width') . ' / ' . rex_i18n::msg('pool_img_height') . '</label>
                    <span class="rex-form-read" id="fwidth">' . $fwidth . ' px / ' . $fheight . ' px</span>
                </p>
            </div>';
            $imgn = rex_url::media($fname) . '" width="' . $rfwidth;
            $img_max = rex_url::media($fname);

            if ($thumbs) {
                if ($media_manager) {
                    $imgn = rex_url::frontendController(['rex_media_type' => 'rex_mediapool_detail', 'rex_media_file' => $encoded_fname]);
                    $img_max = rex_url::frontendController(['rex_media_type' => 'rex_mediapool_maximized', 'rex_media_file' => $encoded_fname]);
                }
            }

            if (!file_exists(rex_path::media($fname))) {
                $add_image = '
                <div class="rex-mediapool-detail-image">
                        <p class="rex-me1">
                            <span class="rex-mime rex-mime-error">' . $fname . '</span>
                        </p>
                </div>';
            } else {
                $add_image = '
                <div class="rex-mediapool-detail-image">
                        <p class="rex-me1">
                            <a href="' . $img_max . '">
                                <img src="' . $imgn . '" alt="' . htmlspecialchars($ftitle) . '" title="' . htmlspecialchars($ftitle) . '" />
                            </a>
                        </p>
                </div>';
            }

         $style_width = ' style="width:64.9%; border-right: 1px solid #FFF;"';
        }

        if ($warning != '') {
            echo rex_view::warning($warning);
            $warning = '';
        }
        if ($info != '') {
            echo rex_view::info($info);
            $info = '';
        }

        if ($opener_input_field == 'TINYIMG') {
            if ($isImage) {
                $opener_link .= '<a href="javascript:insertImage(\'' . $encoded_fname . '\',\'' . $gf->getValue('title') . '\');">' . rex_i18n::msg('pool_image_get') . '</a> | ';
            }
        } elseif ($opener_input_field == 'TINY') {
            $opener_link .= '<a href="javascript:insertLink(\'' . $encoded_fname . '\');">' . rex_i18n::msg('pool_link_get') . '</a>';
        } elseif ($opener_input_field != '') {
            $opener_link = '<a href="javascript:selectMedia(\'' . $encoded_fname . '\', \'' . addslashes(htmlspecialchars($gf->getValue('title'))) . '\');">' . rex_i18n::msg('pool_file_get') . '</a>';
            if (substr($opener_input_field, 0, 14) == 'REX_MEDIALIST_') {
                $opener_link = '<a href="javascript:selectMedialist(\'' . $encoded_fname . '\');">' . rex_i18n::msg('pool_file_get') . '</a>';
            }
        }

        if ($opener_link != '') {
            $opener_link = ' | ' . $opener_link;
        }

        if ($TPERM) {
            $cats_sel = new rex_media_category_select();
            $cats_sel->setStyle('class="rex-form-select"');
            $cats_sel->setSize(1);
            $cats_sel->setName('rex_file_category');
            $cats_sel->setId('rex_file_new_category');
            $cats_sel->addOption(rex_i18n::msg('pool_kats_no'), '0');
            $cats_sel->setSelected($rex_file_category);

            echo '
                <div id="rex-mediapool-detail-wrapper">
                <div class="rex-form" id="rex-form-mediapool-detail"' . $style_width . '>
                    <form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
                        <fieldset class="rex-form-col-1">
                            <legend>' . rex_i18n::msg('pool_file_edit') . $opener_link . '</legend>

                            <div class="rex-form-wrapper">
                                <input type="hidden" name="file_id" value="' . $file_id . '" />
                                ' . $arg_fields . '


                                    <div class="rex-form-row">
                                        <p class="rex-form-text">
                                            <label for="ftitle">Titel</label>
                                            <input class="rex-form-text" type="text" size="20" id="ftitle" name="ftitle" value="' . htmlspecialchars($ftitle) . '" />
                                        </p>
                                    </div>

                                    <div class="rex-form-row">
                                        <p class="rex-form-select">
                                            <label for="rex_file_new_category">' . rex_i18n::msg('pool_file_category') . '</label>
                                            ' . $cats_sel->get() . '
                                        </p>
                                    </div>

                                <div class="rex-clearer"></div>';

    // ----- EXTENSION POINT
    echo rex_extension::registerPoint(new rex_extension_point('MEDIA_FORM_EDIT', '', ['id' => $file_id, 'media' => $gf]));

    echo '
                                            ' . $add_ext_info . '
                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                            <label for="flink">' . rex_i18n::msg('pool_filename') . '</label>
                                            <span class="rex-form-read"><a href="' . rex_url::media($encoded_fname) . '" id="flink">' . htmlspecialchars($fname) . '</a> [' . $ffile_size . ']</span>
                                        </p>
                                    </div>

                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                            <label for="fupdate">' . rex_i18n::msg('pool_last_update') . '</label>
                                            <span class="rex-form-read" id="fupdate">' . strftime(rex_i18n::msg('datetimeformat'), strtotime($gf->getValue('updatedate'))) . ' [' . $gf->getValue('updateuser') . ']</span>
                                        </p>
                                    </div>

                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                            <label for="fcreate">' . rex_i18n::msg('pool_created') . '</label>
                                            <span class="rex-form-read" id="fcreate">' . strftime(rex_i18n::msg('datetimeformat'), strtotime($gf->getValue('createdate'))) . ' [' . $gf->getValue('createuser') . ']</span>
                                        </p>
                                    </div>

                                    <div class="rex-form-row">
                                        <p class="rex-form-file">
                                            <label for="file_new">' . rex_i18n::msg('pool_file_exchange') . '</label>
                                            <input class="rex-form-file" type="file" id="file_new" name="file_new" size="20" />
                                        </p>
                                    </div>

                                    <div class="rex-form-row">
                                        <p class="rex-form-submit">
                                            <input type="submit" class="rex-form-submit" value="' . rex_i18n::msg('pool_file_update') . '" name="btn_update"' . rex::getAccesskey(rex_i18n::msg('pool_file_update'), 'save') . ' />
                                            <input type="submit" class="rex-form-submit rex-form-submit-2" value="' . rex_i18n::msg('pool_file_delete') . '" name="btn_delete"' . rex::getAccesskey(rex_i18n::msg('pool_file_delete'), 'delete') . ' data-confirm="' . rex_i18n::msg('delete') . ' ?" />
                                        </p>
                                    </div>

                                <div class="rex-clearer"></div>
                            </div>
                        </fieldset>
                    </form>
                </div>
                ' . $add_image . '
                </div>';
        } else {
            $catname = rex_i18n::msg('pool_kats_no');
            $Cat = rex_media_category::get($rex_file_category);
            if ($Cat) {
                $catname = $Cat->getName();
            }

            $ftitle .= ' [' . $file_id . ']';
            $catname .= ' [' . $rex_file_category . ']';

            echo '<h2 class="rex-hl2">' . rex_i18n::msg('pool_file_details') . $opener_link . '</h2>
                        <div class="rex-form" id="rex-form-mediapool-detail">
                            <div class="rex-form-wrapper">
                                <div class="rex-mediapool-detail-data"' . $style_width . '>

                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                                <label for="ftitle">Titel</label>
                                                <span class="rex-form-read" id="ftitle">' . htmlspecialchars($ftitle) . '</span>
                                        </p>
                                    </div>
                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                                <label for="rex_file_new_category">' . rex_i18n::msg('pool_file_category') . '</label>
                                                <span class="rex-form-read" id="rex_file_new_category">' . htmlspecialchars($catname) . '</span>
                                        </p>
                                    </div>
                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                                <label for="flink">' . rex_i18n::msg('pool_filename') . '</label>
                                                <a class="rex-form-read" href="' . rex_url::media($encoded_fname) . '" id="flink">' . $fname . '</a> [' . $ffile_size . ']
                                        </p>
                                    </div>
                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                                <label for="fupdate">' . rex_i18n::msg('pool_last_update') . '</label>
                                                <span class="rex-form-read" id="fupdate">' . strftime(rex_i18n::msg('datetimeformat'), strtotime($gf->getValue('updatedate'))) . ' [' . $gf->getValue('updateuser') . ']</span>
                                        </p>
                                    </div>
                                    <div class="rex-form-row">
                                        <p class="rex-form-read">
                                                <label for="fcreate">' . rex_i18n::msg('pool_last_update') . '</label>
                                                <span class="rex-form-read" id="fcreate">' . strftime(rex_i18n::msg('datetimeformat'), strtotime($gf->getValue('createdate'))) . ' [' . $gf->getValue('createuser') . ']</span>
                                        </p>
                                    </div>

                                </div><!-- END rex-mediapool-detail-data //-->
                                ' . $add_image . '


                                <div class="rex-clearer"></div>
                            </div>
                        </div>';
        }
    } else {
        $warning = rex_i18n::msg('pool_file_not_found');
        $file_id = 0;
    }
}


// *************************************** EXTRA FUNCTIONS

if ($PERMALL && $media_method == 'updatecat_selectedmedia') {
    $selectedmedia = rex_post('selectedmedia', 'array');
    if (isset($selectedmedia[0]) && $selectedmedia[0] != '') {

        foreach ($selectedmedia as $file_name) {

            $db = rex_sql::factory();
            // $db->setDebug();
            $db->setTable(rex::getTablePrefix() . 'media');
            $db->setWhere(['filename' => $file_name]);
            $db->setValue('category_id', $rex_file_category);
            $db->addGlobalUpdateFields();
            try {
                $db->update();
                $info = rex_i18n::msg('pool_selectedmedia_moved');
                rex_media_cache::delete($file_name);
            } catch (rex_sql_exception $e) {
                $warning = rex_i18n::msg('pool_selectedmedia_error');
            }
        }
    } else {
        $warning = rex_i18n::msg('pool_selectedmedia_error');
    }
}

if ($PERMALL && $media_method == 'delete_selectedmedia') {
    $selectedmedia = rex_post('selectedmedia', 'array');
    if (count($selectedmedia) != 0) {
        $warning = [];
        $info = [];

        $countDeleted = 0;
        foreach ($selectedmedia as $file_name) {
            $media = rex_media::get($file_name);
            if ($media) {
                if ($PERMALL || rex::getUser()->getComplexPerm('media')->hasCategoryPerm($media->getCategoryId())) {
                    $return = rex_mediapool_deleteMedia($file_name);
                    if ($return['ok']) {
                        $countDeleted++;
                    } else {
                        $warning[] = $return['msg'];
                    }
                } else {
                    $warning[] = rex_i18n::msg('no_permission');
                }
            } else {
                $warning[] = rex_i18n::msg('pool_file_not_found');
            }
        }
        if ($countDeleted) {
            $info[] = rex_i18n::msg('pool_files_deleted', $countDeleted);
        }
    } else {
        $warning = rex_i18n::msg('pool_selectedmedia_error');
    }
}


// *************************************** SUBPAGE: "" -> MEDIEN ANZEIGEN

if (!$file_id) {
    $cats_sel = new rex_media_category_select();
    $cats_sel->setSize(1);
    $cats_sel->setStyle('class="rex-form-select"');
    $cats_sel->setName('rex_file_category');
    $cats_sel->setId('rex_file_category');
    $cats_sel->addOption(rex_i18n::msg('pool_kats_no'), '0');
    $cats_sel->setSelected($rex_file_category);

    echo $cat_out;

    if (is_array($warning)) {
        if (count($warning) > 0) {
            echo rex_view::warning(implode('<br />', $warning));
        }
        $warning = '';
    } elseif ($warning != '') {
        echo rex_view::warning($warning);
        $warning = '';
    }

    if (is_array($info)) {
        if (count($info) > 0) {
            echo rex_view::info(implode('<br />', $info));
        }
        $info = '';
    } elseif ($info != '') {
        echo rex_view::info($info);
        $info = '';
    }

    if (!empty($args['types'])) {
        echo rex_view::info(rex_i18n::msg('pool_file_filter') . ' <code>' . $args['types'] . '</code>');
    }

    //deletefilelist und cat change
    echo '<div class="rex-form" id="rex-form-mediapool-media">
             <form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
                    <fieldset class="rex-form-col-1">
                        <legend class="rex-form-hidden-legend">' . rex_i18n::msg('pool_selectedmedia') . '</legend>

                        <div class="rex-form-wrapper">
                            <input type="hidden" id="media_method" name="media_method" value="" />
                            ' . $arg_fields . '

                            <table class="rex-table">
                                <caption>' . rex_i18n::msg('pool_file_caption', $rex_file_category_name) . '</caption>
                                <colgroup>
                                    <col width="40" />
                                    <col width="110" />
                                    <col width="*" />
                                    <col width="153" />
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="rex-icon">-</th>
                                        <th>' . rex_i18n::msg('pool_file_thumbnail') . '</th>
                                        <th>' . rex_i18n::msg('pool_file_info') . ' / ' . rex_i18n::msg('pool_file_description') . '</th>
                                        <th>' . rex_i18n::msg('pool_file_functions') . '</th>
                                    </tr>
                                </thead>';



    // ----- move, delete and get selected items
    if ($PERMALL) {
        $add_input = '';
        $filecat = rex_sql::factory();
        $filecat->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'media_category ORDER BY name ASC LIMIT 1');
        if ($filecat->getRows() > 0) {
            $cats_sel->setId('rex_move_file_dest_category');
            $add_input = '
                <label for="rex_move_file_dest_category">' . rex_i18n::msg('pool_selectedmedia') . '</label>
                ' . $cats_sel->get() . '
                <input class="rex-form-submit rex-form-submit-2" type="submit" value="' . rex_i18n::msg('pool_changecat_selectedmedia') . '" onclick="var needle=new getObj(\'media_method\');needle.obj.value=\'updatecat_selectedmedia\';" />';
        }
        $add_input .= '<input class="rex-form-submit rex-form-submit-2" type="submit" value="' . rex_i18n::msg('pool_delete_selectedmedia') . '"' . rex::getAccesskey(rex_i18n::msg('pool_delete_selectedmedia'), 'delete') . ' onclick="if(confirm(\'' . rex_i18n::msg('delete') . ' ?\')){var needle=new getObj(\'media_method\');needle.obj.value=\'delete_selectedmedia\';}else{return false;}" />';
        if (substr($opener_input_field, 0, 14) == 'REX_MEDIALIST_') {
            $add_input .= '<input class="rex-form-submit rex-form-submit-2" type="submit" value="' . rex_i18n::msg('pool_get_selectedmedia') . '" onclick="selectMediaListArray(\'selectedmedia[]\');return false;" />';
        }

        echo '
            <tfoot>
            <tr>
                <td class="rex-icon">
                    <label class="rex-form-hidden-label" for="checkie">' . rex_i18n::msg('pool_select_all') . '</label>
                    <input class="rex-form-checkbox" type="checkbox" name="checkie" id="checkie" value="0" onclick="setAllCheckBoxes(\'selectedmedia[]\',this)" />
                </td>
                <td colspan="3">
                    ' . $add_input . '
                </td>
            </tr>
            </tfoot>
        ';
    }



    $where = 'f.category_id=' . $rex_file_category;
    if (isset($args['types'])) {
        $types = [];
        foreach (explode(',', $args['types']) as $type) {
            $types[] = 'LOWER(RIGHT(f.filename, LOCATE(".", REVERSE(f.filename))-1))="' . strtolower(htmlspecialchars($type)) . '"';
        }
        $where .= ' AND (' . implode(' OR ', $types) . ')';
    }

    $addTable = '';
    if ($media_name != '') {
        $addTable = rex::getTablePrefix() . 'media_category c, ';
        $media_name = str_replace(['_', '%'], ['\_', '\%'], $media_name);
        $where .= " AND f.category_id = c.id AND (f.filename LIKE '%" . $media_name . "%' OR f.title LIKE '%" . $media_name . "%')";
        if (rex_addon::get('mediapool')->getConfig('searchmode', 'local') != 'global') {
            // Suche auf aktuellen Kontext eingrenzen
            if ($rex_file_category != 0) {
                $where .= " AND (c.path LIKE '%|" . $rex_file_category . "|%' OR c.id=" . $rex_file_category . ') ';
            } else {
                $where = str_replace('f.category_id=0', '1=1', $where);
            }
        }
    }
        $qry = 'SELECT * FROM ' . $addTable . rex::getTablePrefix() . 'media f WHERE ' . $where . ' ORDER BY f.updatedate desc, f.id desc';

    // ----- EXTENSION POINT
    $qry = rex_extension::registerPoint(new rex_extension_point('MEDIA_LIST_QUERY', $qry, [
        'category_id' => $rex_file_category
    ]));
    $files = rex_sql::factory();
//   $files->setDebug();
    $files->setQuery($qry);


    print '<tbody>';
    for ($i = 0; $i < $files->getRows(); $i++) {
        $file_id =   $files->getValue('id');
        $file_name = $files->getValue('filename');
        $file_oname = $files->getValue('originalname');
        $file_title = $files->getValue('title');
        $file_type = $files->getValue('filetype');
        $file_size = $files->getValue('filesize');
        $file_stamp = rex_formatter::strftime($files->getDateTimeValue('updatedate'), 'datetime');
        $file_updateuser = $files->getValue('updateuser');

        $encoded_file_name = urlencode($file_name);

        // Eine titel Spalte schätzen
        $alt = '';
        foreach (['title'] as $col) {
            if ($files->hasValue($col) && $files->getValue($col) != '') {
                $alt = htmlspecialchars($files->getValue($col));
                break;
            }
        }

        // Eine beschreibende Spalte schätzen
        $desc = '';
        foreach (['med_description'] as $col) {
            if ($files->hasValue($col) && $files->getValue($col) != '') {
                $desc = htmlspecialchars($files->getValue($col));
                break;
            }
        }
        if ($desc != '') {
            $desc .= '<br />';
        }

        // wenn datei fehlt
        if (!file_exists(rex_path::media($file_name))) {
            $thumbnail = '<span class="rex-mime rex-mime-error" title="file does not exist">' . $file_name . '</span>';
        } else {
            $file_ext = substr(strrchr($file_name, '.'), 1);
            $icon_class = ' rex-mime-default';
            if (rex_media::isDocType($file_ext)) {
                $icon_class = ' rex-mime-' . $file_ext;
            }
            $thumbnail = '<span class="rex-mime' . $icon_class . '" title="' . $alt . '">' . $file_name . '</span>';

            if (rex_media::isImageType(rex_file::extension($file_name)) && $thumbs) {
                $thumbnail = '<img src="' . rex_url::media($file_name) . '" width="80" alt="' . $alt . '" title="' . $alt . '" />';
                if ($media_manager) {
                    $thumbnail = '<img src="' . rex_url::frontendController(['rex_media_type' => 'rex_mediapool_preview', 'rex_media_file' => $encoded_file_name]) . '" alt="' . $alt . '" title="' . $alt . '" />';
                }
            }
        }

        // ----- get file size
        $size = $file_size;
        $file_size = rex_formatter::bytes($size);

        if ($file_title == '') {
            $file_title = '[' . rex_i18n::msg('pool_file_notitle') . ']';
        }
        $file_title .= ' [' . $file_id . ']';

        // ----- opener
        $opener_link = '';
        if ($opener_input_field == 'TINYIMG') {
            if (rex_media::isImageType(rex_file::extension($file_name))) {
                $opener_link .= "<a href=\"javascript:insertImage('$file_name','" . $files->getValue('title') . "')\">" . rex_i18n::msg('pool_image_get') . '</a><br>';
            }

        } elseif ($opener_input_field == 'TINY') {
                $opener_link .= "<a href=\"javascript:insertLink('" . $file_name . "');\">" . rex_i18n::msg('pool_link_get') . '</a>';
        } elseif ($opener_input_field != '') {
            $opener_link = "<a href=\"javascript:selectMedia('" . $file_name . "', '" . addslashes(htmlspecialchars($files->getValue('title'))) . "');\">" . rex_i18n::msg('pool_file_get') . '</a>';
            if (substr($opener_input_field, 0, 14) == 'REX_MEDIALIST_') {
                $opener_link = "<a href=\"javascript:selectMedialist('" . $file_name . "');\">" . rex_i18n::msg('pool_file_get') . '</a>';
            }
        }

        $ilink = rex_url::currentBackendPage(array_merge(['file_id' => $file_id, 'rex_file_category' => $rex_file_category], $arg_url));

        $add_td = '<td></td>';
        if ($PERMALL) {
            $add_td = '<td class="rex-icon"><input class="rex-form-checkbox" type="checkbox" name="selectedmedia[]" value="' . $file_name . '" /></td>';
        }

        echo '<tr>
                        ' . $add_td . '
                        <td class="rex-thumbnail"><a href="' . $ilink . '">' . $thumbnail . '</a></td>
                        <td>
                                <p class="rex-tx4">
                                    <a href="' . $ilink . '">' . htmlspecialchars($file_title) . '</a>
                                </p>
                                <p class="rex-tx4">
                                    ' . $desc . '
                                    <span class="rex-suffix">' . htmlspecialchars($file_name) . ' [' . $file_size . ']</span>
                                </p>
                                <p class="rex-tx1">
                                    ' . $file_stamp . ' | ' . htmlspecialchars($file_updateuser) . '
                                </p>
                        </td>
                        <td>';

        echo rex_extension::registerPoint(new rex_extension_point('MEDIA_LIST_FUNCTIONS', $opener_link, [
            'file_id' => $files->getValue('id'),
            'file_name' => $files->getValue('filename'),
            'file_oname' => $files->getValue('originalname'),
            'file_title' => $files->getValue('title'),
            'file_type' => $files->getValue('filetype'),
            'file_size' => $files->getValue('filesize'),
            'file_stamp' => $files->getDateTimeValue('updatedate'),
            'file_updateuser' => $files->getValue('updateuser')
        ]));

        echo '</td>
                 </tr>';

        $files->next();
    } // endforeach

    // ----- no items found
    if ($files->getRows() == 0) {
        echo '
            <tr>
                <td></td>
                <td colspan="3">' . rex_i18n::msg('pool_nomediafound') . '</td>
            </tr>';
    }

    print '
            </tbody>
            </table>
            </div>
        </fieldset>
    </form>
    </div>';
}
