<?php
/** ---------------------------------------------------------------------

   MyOOS [Dumper]
   http://www.oos-shop.de/

   Copyright (c) 2013 - 2023 by the MyOOS Development Team.
   ----------------------------------------------------------------------
   Based on:

   MySqlDumper
   http://www.mysqldumper.de

   Copyright (C)2004-2011 Daniel Schlichtholz (admin@mysqldumper.de)
   ----------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------- */

if (!defined('MOD_VERSION')) {
    exit('No direct access.');
}
require './inc/functions_imexport.php';
//Im-/Export
$import = (isset($_GET['import'])) ? 1 : 0;
if (1 == $import) {
    //IMPORT
    CheckcsvOptions();
    if (isset($_POST['f_import_csvtrenn'])) {
        $sql['import']['trenn'] = $_POST['f_import_csvtrenn'];
    }
    if (isset($_POST['f_import_csvenc'])) {
        $sql['import']['enc'] = $_POST['f_import_csvenc'];
    }
    if (isset($_POST['f_import_csvesc'])) {
        $sql['import']['esc'] = $_POST['f_import_csvesc'];
    }
    if (empty($sql['import']['endline'])) {
        $sql['import']['endline'] = $nl;
    } else {
        $sql['import']['endline'] = str_replace('\\r', "\015", (string) $sql['import']['endline']);
        $sql['import']['endline'] = str_replace('\\n', "\012", $sql['import']['endline']);
        $sql['import']['endline'] = str_replace('\\t', "\011", $sql['import']['endline']);
    }
    $sql['import']['endline'] = str_replace('\\t', "\011", (string) $sql['import']['endline']);
    if (isset($_POST['f_import_csvnull'])) {
        $sql['import']['null'] = $_POST['f_import_csvnull'];
    }
    $sql['import']['namefirstline'] = $_POST['f_import_namefirstline'] ?? 0;
    $sql['import']['emptydb'] = (isset($_POST['import_emptydb'])) ? 1 : 0;
    $sql['import']['createindex'] = (isset($_POST['import_createindex'])) ? 1 : 0;
    $sql['import']['table'] = $_POST['import_table'] ?? '';
    $sql['import']['import_source'] = $_POST['import_source'] ?? 0;
    $sql['import']['text'] = $_POST['import_text'] ?? '';
    $sql['import']['csv'] = '';

    if (isset($_POST['do_import'])) {
        $sql['import']['tablecreate'] = 0;
        if ('new' == $sql['import']['table']) {
            $sql['import']['table'] = 'import_';
            $sql['import']['tablecreate'] = 1;
        }
        if ('' == $sql['import']['table']) {
            $aus .= '<span class="error">'.$lang['L_IMPORT_NOTABLE'].'</span>';
        } else {
            if (0 == $_POST['import_source']) {
                //Import aus textbox
                $sql['import']['csv'] = explode($sql['import']['endline'], (string) $sql['import']['text']);
            } else {
                if (!isset($_FILES['upfile']['name']) || empty($_FILES['upfile']['name'])) {
                    $aus .= '<span class="error">'.$lang['L_FM_UPLOADFILEREQUEST'].'</span>';
                } else {
                    $fn = $_FILES['upfile']['tmp_name'];

                    $sql['import']['csv'] = (str_ends_with((string) $_FILES['upfile']['name'], '.gz')) ? gzfile($fn) : file($fn);
                    $sql['import']['text'] = implode('', $sql['import']['csv']);
                    $aus .= '<span>'.$lang['L_SQL_UPLOADEDFILE'].'<strong>'.$_FILES['upfile']['name'].'</strong>&nbsp;&nbsp;&nbsp;'.byte_output(filesize($_FILES['upfile']['tmp_name'])).'</span>';
                }
            }
            if (is_array($sql['import']['csv'])) {
                $aus .= DoImport();
            } else {
                $aus .= '<br><span class="error">'.$lang['L_CSV_NODATA'].'</span>';
            }
        }
    }
    $impaus = $aus;

    $impaus .= '<form action="sql.php?db='.$db.'&amp;dbid='.$dbid.'&amp;context=4&amp;import=1" method="post" enctype="multipart/form-data">'.$nl;
    $impaus .= '';
    $impaus .= '<a href="sql.php?db='.$db.'&amp;dbid='.$dbid.'&amp;context=4">'.$lang['L_EXPORT'].'</a>';
    $impaus .= '<h6>'.sprintf($lang['L_SQL_IMPORT'], $databases['Name'][$dbid]).'</h6>';
    $impaus .= '<table class="bordersmall"><tr class="thead"><th>'.$nl;
    $impaus .= $lang['L_IMPORTOPTIONS'].'</th><th>'.$lang['L_CSVOPTIONS'].'</th></tr>'.$nl;

    $impaus .= '<tr><td valign="top">'.$nl;
    $impaus .= '<table cellpadding="0" cellspacing="0">'.$nl;
    $impaus .= '<tr><td>'.$lang['L_IMPORTTABLE'].'</td><td><select name="import_table">'.TableComboBox($sql['import']['table']).'<option value="new" '.(('import_' == $sql['import']['table']) ? ' selected="selected"' : '').'>== '.$lang['L_NEWTABLE'].' ==</option></select></td></tr>'.$nl;
    $impaus .= '<tr><td>'.$lang['L_IMPORTSOURCE'].'</td>'.$nl;
    $impaus .= '<td><input type="radio" class="radio" name="import_source" value="0" '.((0 == $sql['import']['import_source']) ? 'checked' : '').' onclick="check_csvdivs(1); return true">'.$lang['L_FROMTEXTBOX'].'<br>'.$nl;
    $impaus .= '<input type="radio" class="radio" id="radio_csv0" name="import_source" value="1" '.((1 == $sql['import']['import_source']) ? 'checked' : '').' onclick="check_csvdivs(1); return true">'.$lang['L_FROMFILE'].'</td></tr>'.$nl;
    $impaus .= '<tr><td colspan="2"><input type="checkbox" class="checkbox" name="import_emptydb" value="1" '.((1 == $sql['import']['emptydb']) ? 'checked' : '').'>'.$lang['L_EMPTYTABLEBEFORE'].'</td></tr>'.$nl;
    $impaus .= '<tr><td colspan="2"><input type="checkbox" class="checkbox" name="import_createindex" value="1" '.((1 == $sql['import']['createindex']) ? 'checked' : '').'>'.$lang['L_CREATEAUTOINDEX'].'</td></tr>'.$nl;
    $impaus .= '</table>'.$nl;

    $impaus .= '</td><td valign="top">'.$nl;

    $impaus .= '<table cellpadding="0" cellspacing="0">'.$nl;
    $impaus .= '<tr><td colspan="2"><input type="checkbox" class="checkbox" name="f_import_namefirstline0" value="1" '.((1 == $sql['import']['namefirstline']) ? 'checked' : '').'>'.$lang['L_CSV_NAMEFIRSTLINE'].'</td></tr>'.$nl;
    $impaus .= '<tr><td>'.$lang['L_CSV_FIELDSEPERATE'].'</td><td><input type="text" class="text" name="f_import_csvtrenn" size="4" maxlength="12" value="'.$sql['import']['trenn'].'"></td></tr>'.$nl;
    $impaus .= '<tr><td>'.$lang['L_CSV_FIELDSENCLOSED'].'</td><td><input type="text" class="text" name="f_import_csvenc" size="4" maxlength="12" value="'.htmlspecialchars((string) $sql['import']['enc']).'"></td></tr>'.$nl;
    $impaus .= '<tr><td>'.$lang['L_CSV_FIELDSESCAPE'].'</td><td><input type="text" class="text" name="f_import_csvesc" size="4" maxlength="12" value="'.$sql['import']['esc'].'"></td></tr>'.$nl;
    $impaus .= '<tr><td>'.$lang['L_CSV_EOL'].'</td><td><input type="text" class="text" name="f_import_csvztrenn" size="4" maxlength="12" value="'.$sql['import']['ztrenn'].'"></td></tr>'.$nl;
    $impaus .= '<tr><td>'.$lang['L_CSV_NULL'].'</td><td><input type="text" class="text" name="f_import_csvnull" size="4" maxlength="12" value="'.$sql['import']['null'].'"></td></tr>'.$nl;
    $impaus .= '</table>'.$nl;

    $impaus .= '</td></tr>';

    $impaus .= '<tr><td colspan="2"><div id="csv0">'.$lang['L_CSV_FILEOPEN'].':&nbsp;&nbsp;
		<input type="file" name="upfile" accept="application/gzip">';
    $impaus .= '<input type="hidden" name="MAX_FILE_SIZE" VALUE="2500000"></div></td></tr>';

    $impaus .= '<tr><td colspan="2" align="right"><input class="Formbutton" type="submit" name="do_import" value=" '.$lang['L_IMPORTIEREN'].' "></td></tr>';

    $impaus .= '</table>'.$nl;

    $impaus .= '<p>&nbsp;</p>'.$lang['L_IMPORT'].':<br><textarea name="import_text" wrap="OFF" style="width:760px;height:400px;font-size=11px;">';
    //$impaus.= $sql['import']['text'];
    $impaus .= '</textarea></form>'.$nl;

    $impaus .= '<script>check_csvdivs(1);</script>'.$nl;

    echo $impaus.$nl;
} else {
    //EXPORT
    $tables = 0;
    $tblstr = '';
    $sql['export']['db'] = $db;

    if (isset($_POST['f_export_submit'])) {
        //echo '<pre>'.print_r($_POST,true).'</pre><hr>';
        $sql['export']['header_sent'] = '';
        $sql['export']['lines'] = 0;
        $sql['export']['format'] = $_POST['f_export_format'];
        $sql['export']['ztrenn'] = $_POST['f_export_csvztrenn'];
        $sql['endline']['ztrenn'] = $sql['export']['ztrenn'];
        if (0 == $sql['export']['format']) {
            //CSV
            $format = 0;
            $sql['export']['trenn'] = $_POST['f_export_csvtrenn'];
            $sql['export']['enc'] = $_POST['f_export_csvenc'];
            $sql['export']['esc'] = $_POST['f_export_csvesc'];
            if (empty($sql['export']['endline'])) {
                $sql['export']['endline'] = $nl;
            } else {
                $sql['export']['endline'] = str_replace('\\r', "\015", (string) $sql['export']['endline']);
                $sql['export']['endline'] = str_replace('\\n', "\012", $sql['export']['endline']);
                $sql['export']['endline'] = str_replace('\\t', "\011", $sql['export']['endline']);
            }
            $sql['export']['endline'] = str_replace('\\t', "\011", (string) $sql['export']['endline']);
        } elseif (1 == $sql['export']['format']) {
            //EXCEL
            $format = 1;
            $sql['export']['trenn'] = ',';
            $sql['export']['enc'] = '"';
            $sql['export']['esc'] = '"';
            $sql['export']['endline'] = "\015\012";
        } elseif (3 == $sql['export']['format']) {
            //EXCEL 2003
            $format = 1;
            $sql['export']['trenn'] = ';';
            $sql['export']['enc'] = '"';
            $sql['export']['esc'] = '"';
            $sql['export']['endline'] = "\015\012";
        } elseif (4 == $sql['export']['format']) {
            //XML
            $format = 4;
            CheckcsvOptions();
        } elseif (5 == $sql['export']['format']) {
            //HTML
            $format = 5;
            CheckcsvOptions();
        }
        if ($format < 3) {
            $sql['export']['null'] = $_POST['f_export_csvnull'.$format];
        }
        $sql['export']['namefirstline'] = $_POST['f_export_namefirstline'.$format] ?? 0;

        $sql['export']['sendfile'] = $_POST['f_export_sendresult'];
        $sql['export']['compressed'] = $_POST['f_export_compressed'] ?? 0;

        $sql['export']['exportfile'] = '';
        $sql['export']['xmlstructure'] = $_POST['f_export_xmlstructure'] ?? 0;
        $sql['export']['htmlstructure'] = $_POST['f_export_htmlstructure'] ?? 0;

        //ausgewählte Tabellen
        if (isset($_POST['f_export_tables'])) {
            $sql['export']['tables'] = $_POST['f_export_tables'];
        }
    } else {
        CheckcsvOptions();
    }

    //Tabellenliste
    $sqlt = "SHOW TABLE STATUS FROM `$db`";
    $res = mod_query($sqlt);
    if ($res) {
        $sql['export']['tablecount'] = mysqli_num_rows($res);
        $sql['export']['recordcount'] = 0;
        for ($i = 0; $i < $sql['export']['tablecount']; ++$i) {
            $row = mysqli_fetch_array($res);
            $tblstr .= '<option value="'.$row['Name'].'" '.((isset($sql['export']['tables']) && in_array($row['Name'], $sql['export']['tables'])) ? ' selected="selected"' : '').'>'.$row['Name'].' ('.$row['Rows'].')</option>'."\n";
            $sql['export']['recordcount'] += $row['Rows'];
        }
    }

    $exaus = $aus.'<h4>'.sprintf($lang['L_SQL_EXPORT'], $databases['Name'][$dbid]).'</h4>';

    $exaus .= '<form action="sql.php?db='.$db.'&amp;dbid='.$dbid.'&amp;context=4" method="post">'.$nl;
    $exaus .= '<a href="sql.php?db='.$db.'&amp;dbid='.$dbid.'&amp;context=4&amp;import=1">'.$lang['L_IMPORT'].'</a>';
    $exaus .= '<h6>'.sprintf($lang['L_SQL_EXPORT'], $databases['Name'][$dbid]).'</h6>';
    $exaus .= '<table class="bdr"><tr class="thead"><th>'.$lang['L_TABLES'].'</th>'.$nl;
    $exaus .= '<th>'.$lang['L_EXPORTOPTIONS'].'</th>';
    $exaus .= '<th>'.$lang['L_EXPORT'].'</th></tr><tr>';
    $exaus .= '';

    $exaus .= '<td><span class="ssmall"><strong>'.$sql['export']['tablecount'].'</strong> '.$lang['L_TABLES'].', <strong>'.$sql['export']['recordcount'].'</strong> '.$lang['L_RECORDS'].'</span>';
    $exaus .= '&nbsp;&nbsp;&nbsp;<a class="ssmall" href="#" onclick="SelectTableList(true);">'.$lang['L_ALL'].'</a>&nbsp;&nbsp;<a class="ssmall" href="#" onclick="SelectTableList(false);">'.$lang['L_NONE'].'</a>'.$nl;

    $exaus .= '<br><select name="f_export_tables[]" size="12" multiple>'.$tblstr.'</select><br>'.$nl;
    $exaus .= '</td><td>'.$nl;
    $exaus .= ''.$nl;
    $exaus .= '<input type="radio" class="radio" name="f_export_format" id="radio_csv0" value="0" '.((0 == $sql['export']['format']) ? 'checked' : '').' onclick="check_csvdivs(0); return true">'.'CSV'.'&nbsp;&nbsp;&nbsp;'.$nl;
    $exaus .= '<input type="radio" class="radio" name="f_export_format" id="radio_csv1" value="1" '.((1 == $sql['export']['format']) ? 'checked' : '').' onclick="check_csvdivs(0); return true">'.'Excel'.'&nbsp;&nbsp;&nbsp;'.$nl;
    $exaus .= '<input type="radio" class="radio" name="f_export_format" id="radio_csv2" value="3" '.((3 == $sql['export']['format']) ? 'checked' : '').' onclick="check_csvdivs(0); return true">'.$lang['L_EXCEL2003'].'<br>'.$nl;
    $exaus .= '<input type="radio" class="radio" name="f_export_format" id="radio_csv4" value="4" '.((4 == $sql['export']['format']) ? 'checked' : '').' onclick="check_csvdivs(0); return true">'.'XML'.'&nbsp;&nbsp;&nbsp;'.$nl;
    $exaus .= '<input type="radio" class="radio" name="f_export_format" id="radio_csv5" value="5" '.((5 == $sql['export']['format']) ? 'checked' : '').' onclick="check_csvdivs(0); return true">'.'HTML'.'<br><br>'.$nl;
    $exaus .= '<div id="csv0"><fieldset><legend>CSV-Optionen</legend><table cellpadding="0" cellspacing="0"><tr><td colspan="2">'.$nl;
    $exaus .= '<input type="checkbox" class="checkbox" name="f_export_namefirstline0" value="1" '.((1 == $sql['export']['namefirstline']) ? 'checked' : '').'>'.$lang['L_CSV_NAMEFIRSTLINE'].'</td></tr>'.$nl;
    $exaus .= '<tr><td>'.$lang['L_CSV_FIELDSEPERATE'].'</td><td><input type="text" class="text" name="f_export_csvtrenn" size="4" maxlength="12" value="'.$sql['export']['trenn'].'"></td></tr>'.$nl;
    $exaus .= '<tr><td>'.$lang['L_CSV_FIELDSENCLOSED'].'</td><td><input type="text" class="text" name="f_export_csvenc" size="4" maxlength="12" value="'.htmlspecialchars((string) $sql['export']['enc']).'"></td></tr>'.$nl;
    $exaus .= '<tr><td>'.$lang['L_CSV_FIELDSESCAPE'].'</td><td><input type="text" class="text" name="f_export_csvesc" size="4" maxlength="12" value="'.$sql['export']['esc'].'"></td></tr>'.$nl;
    $exaus .= '<tr><td>'.$lang['L_CSV_EOL'].'</td><td><input type="text" class="text" name="f_export_csvztrenn" size="4" maxlength="12" value="'.$sql['export']['ztrenn'].'"></td></tr>'.$nl;
    $exaus .= '<tr><td>'.$lang['L_CSV_NULL'].'</td><td><input type="text" class="text" name="f_export_csvnull0" size="4" maxlength="12" value="'.$sql['export']['null'].'"></td></tr>'.$nl;
    $exaus .= '</table></fieldset></div>'.$nl;

    $exaus .= '<div id="csv1"><fieldset><legend>Excel-Optionen</legend><table cellpadding="0" cellspacing="0"><tr><td colspan="2">';
    $exaus .= '<input type="checkbox" class="checkbox" name="f_export_namefirstline1" value="1"'.((1 == $sql['export']['namefirstline']) ? 'checked' : '').'>'.$lang['L_CSV_NAMEFIRSTLINE'].'</td></tr>'.$nl;
    $exaus .= '<tr><td>'.$lang['L_CSV_NULL'].'</td><td><input type="text" class="text" name="f_export_csvnull1" size="4" maxlength="12" value="'.$sql['export']['null'].'"></td></tr>'.$nl;
    $exaus .= '</table></fieldset></div>'.$nl;

    $exaus .= '<div id="csv4"><fieldset><legend>XML-Optionen</legend><table>';
    $exaus .= '<tr><td><input type="checkbox" name="f_export_xmlstructure" value="1" class="checkbox" '.((1 == $sql['export']['xmlstructure']) ? 'checked' : '').'> mit Struktur</td></tr>';
    $exaus .= '</table></fieldset></div>'.$nl;

    $exaus .= '<div id="csv5"><fieldset><legend>HTML-Optionen</legend><table>';
    $exaus .= '<tr><td><input type="checkbox" name="f_export_htmlstructure" value="1" class="checkbox" '.((1 == $sql['export']['htmlstructure']) ? 'checked' : '').'> mit Struktur</td></tr>';
    $exaus .= '</table></fieldset></div>'.$nl;

    $exaus .= '</td><td>'.$nl;
    $exaus .= '<input type="radio" class="radio" name="f_export_sendresult" value="0" '.((0 == $sql['export']['sendfile']) ? 'checked' : '').' onclick="check_csvdivs(0); return true">'.$lang['L_SHOWRESULT'].'<br>'.$nl;
    $exaus .= '<input type="radio" class="radio" name="f_export_sendresult" id="radio_csv3" value="1" '.((1 == $sql['export']['sendfile']) ? 'checked' : '').' onclick="check_csvdivs(0); return true">'.$lang['L_SENDRESULTASFILE'].'<br>'.$nl;
    $exaus .= '<div id="csv3"><input type="checkbox" class="checkbox" name="f_export_compressed" value="1" '.((1 == $sql['export']['compressed']) ? 'checked' : '').'>'.$lang['L_COMPRESSED'].'</div><br>'.$nl;

    $exaus .= '<img src="'.$icon['blank'].'" width="60" height="130" border="0"><br><input class="Formbutton" type="submit" name="f_export_submit" value="'.$lang['L_EXPORT'].'" onclick="if(SelectedTableCount()==0) {alert(msg1);return false;}">'.$nl;
    $exaus .= '</td></tr></table></form>'.$nl;

    $exaus .= '<script>check_csvdivs(0);</script>'.$nl;

    if (!$download) {
        echo $exaus.$nl;
    }
    if (isset($_POST['f_export_submit']) && isset($sql['export']['tables'])) {
        if (!$download) {
            echo '<br><br><table width="90%"><tr><td>'.$lang['L_EXPORT'].':</td><td align="right"><a href="javascript:BrowseInput(\'imexta\');">zeige in neuem Fenster</a></td></tr></table><textarea id="imexta" wrap="OFF" style="width:760px;height:400px;font-size=11px;">'.$nl;
        }
        if ($format < 3) {
            ExportCSV();
        } elseif (4 == $format) {
            ExportXML();
        } elseif (5 == $format) {
            ExportHTML();
        }
        if (!$download) {
            echo '</textarea><br>'.$nl;
            echo '<span style="color:blue;">'.$lang['L_EXPORTFINISHED'].'</span>&nbsp;&nbsp;'.sprintf($lang['L_EXPORTLINES'], $sql['export']['lines']).$nl;
        } else {
            exit();
        }
    }
}
