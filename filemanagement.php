<?php
/** ---------------------------------------------------------------------

   MyOOS [Dumper]
   http://www.oos-shop.de/

   Copyright (c) 2003 - 2023 by the MyOOS Development Team.
   ----------------------------------------------------------------------
   Based on:

   MySqlDumper
   http://www.mysqldumper.de

   Copyright (C)2004-2011 Daniel Schlichtholz (admin@mysqldumper.de)
   ----------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------- */

define('OOS_VALID_MOD', true);

if (isset($_GET['action']) && 'dl' == $_GET['action']) {
    $download = true;
}
require './inc/header.php';
require_once './language/'.$config['language'].'/lang.php';
require_once './language/'.$config['language'].'/lang_dump.php';
require_once './language/'.$config['language'].'/lang_filemanagement.php';
require_once './language/'.$config['language'].'/lang_config_overview.php';
require_once './language/'.$config['language'].'/lang_main.php';
require_once './inc/functions_files.php';
require_once './inc/functions_sql.php';

$msg = '';
$dump = [];
if (isset($config['auto_delete']) && (1 == $config['auto_delete'])) {
    $msg = AutoDelete();
}
get_sql_encodings(); // get possible sql charsets and also get default charset
//0=Datenbank  1=Struktur
$action = filter_string_polyfill(filter_input(INPUT_GET, 'action')) ?: 'files';
$kind = filter_input(INPUT_GET, 'kind', FILTER_VALIDATE_INT) ?: 0; 
$expand = filter_input(INPUT_GET, 'expand', FILTER_VALIDATE_INT) ?: -1;

$selectfile = filter_string_polyfill(filter_input(INPUT_POST, 'selectfile'));
$destfile = filter_string_polyfill(filter_input(INPUT_POST, 'destfile'));
$compressed = filter_string_polyfill(filter_input(INPUT_POST, 'compressed'));
$dk = filter_string_polyfill(filter_input(INPUT_POST, 'dk'));


if ($dk === null) {
    $dk = '';
}
$dk = str_replace(':', '|', (string) $dk); // remove : because of statusline
$dump['sel_dump_encoding'] = $_POST['sel_dump_encoding'] ?? get_index($config['mysql_possible_character_sets'], $config['mysql_standard_character_set']);
$dump['dump_encoding'] = $config['mysql_possible_character_sets'][$dump['sel_dump_encoding']] ?? 0;

if ('dl' == $action) {
    // Download of a backup file wanted
    $file = './'.$config['paths']['backup'].urldecode((string) $_GET['f']);
    if (is_readable($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: '.(string) filesize($file));
        flush();
        $file = fopen($file, 'rb');
        while (!feof($file)) {
            echo fread($file, round(100 * 1024));
            flush();
        }
        fclose($file);
    }

    //readfile($file);
    exit();
}

if (!@ob_start('ob_gzhandler')) {
    @ob_start();
}
echo MODHeader();

$toolboxstring = '';
$fpath = $config['paths']['backup'];
$dbactiv = filter_string_polyfill(filter_input(INPUT_GET, 'dbactiv')) ?: $databases['db_actual']; 

$databases['multi'] = [];
if ('' == $databases['multisetting']) {
    $databases['multi'][0] = $databases['db_actual'];
} else {
    $databases['multi'] = explode(';', (string) $databases['multisetting']);
    $multi_praefixe = [];
    $multi_praefixe = explode(';', (string) $databases['multisetting_praefix']);
    $toolboxstring = '<br>';
    if (is_array($databases['multi'])) {
        for ($i = 0; $i < sizeof($databases['multi']); ++$i) {
            if ($i > 0) {
                $toolboxstring .= ', ';
            }
            $toolboxstring .= $databases['multi'][$i];
            if ($multi_praefixe[$i] > '') {
                $toolboxstring .= ' (<i>\''.$multi_praefixe[$i].'\'</i>)';
            }
        }
    }
}

//*** Abfrage ob Dump nach Tabellenaufruf ***
if (isset($_POST['dump_tbl'])) {
    $check_dirs = TestWorkDir();
    if (true === !$check_dirs) {
        exit($check_dirs);
    }
    $databases['db_actual_tableselected'] = substr((string) $_POST['tbl_array'], 0, strlen($_POST['tbl_array'] ?? '') - 1);
    WriteParams();
    $dump['fileoperations'] = 0;
    echo '<script>parent.MyOOS_Dumper_content.location.href="dump.php?comment='.urlencode($dk).'&sel_dump_encoding='.$dump['sel_dump_encoding'].'&config='.urlencode((string) $config['config_file']).'";</script></body></html>';
    exit();
}

//*** Abfrage ob Dump ***
if (isset($_POST['dump'])) {
    if (isset($_POST['tblfrage']) && 1 == $_POST['tblfrage']) {
        //Tabellenabfrage
        $tblfrage_refer = 'dump';
        include 'inc/table_query.php';
        exit();
    } else {
        @$check_dir = TestWorkDir();
        if (true === !$check_dir) {
            exit($check_dir);
        }
        $databases['db_actual_tableselected'] = '';
        WriteParams();
        $dump['fileoperations'] = 0;

        $sUrl = 'dump.php?comment='.urlencode($dk).'&sel_dump_encoding='.$dump['sel_dump_encoding'].'&config='.urlencode((string) $config['config_file']);
        if ((isset($config['optimize_tables_beforedump']) && (1 == $config['optimize_tables_beforedump']))) {
            echo '<div id="pagetitle">'.$lang['L_DUMP_HEADLINE'].'</div><div id="content"><p>';
            echo '<br><br><p>'.sprintf($lang['L_DUMP_INFO'], $sUrl).'</p></div>';
        }
        echo '<script>parent.MyOOS_Dumper_content.location.href="dump.php?comment='.urlencode($dk).'&sel_dump_encoding='.$dump['sel_dump_encoding'].'&config='.urlencode((string) $config['config_file']).'";</script></body></html>';
        exit();
    }
}

//*** Abfrage ob Restore nach Tabellenaufruf ***
if (isset($_POST['restore_tbl'])) {
    $databases['db_actual_tableselected'] = substr((string) $_POST['tbl_array'], 0, strlen($_POST['tbl_array'] ?? '') - 1);
    WriteParams();
    echo '<script>parent.MyOOS_Dumper_content.location.href="restore.php?filename='.urlencode((string) $_POST['filename']).'";</script></body></html>';

    exit();
}

//*** Abfrage ob Restore ***
if (isset($_POST['restore'])) {
    if (isset($_POST['file'])) {
        if (isset($_POST['tblfrage']) && 1 == $_POST['tblfrage']) {
            //Tabellenabfrage
            $tblfrage_refer = 'restore';
            $filename = urldecode((string) $_POST['file'][0]);
            include 'inc/table_query.php';
            exit();
        } else {
            $file = $_POST['file'][0];
            $statusline = read_statusline_from_file($file);
            if (isset($_POST['sel_dump_encoding_restore'])) {
                $encodingstring = $config['mysql_possible_character_sets'][$_POST['sel_dump_encoding_restore']];
                $encoding = explode(' ', (string) $encodingstring);
                $dump_encoding = $encoding[0];
            } else {
                if (!isset($statusline['charset']) || '?' == trim((string) $statusline['charset'])) {
                    echo headline($lang['L_FM_RESTORE'].': '.$file);

                    // if we can't detect encoding ask user
                    echo '<br>'.$lang['L_CHOOSE_CHARSET'].'<br><br>';
                    echo '<form action="filemanagement.php?action=restore&amp;kind=0" method="POST">';
                    echo '<table><tr><td>'.$lang['L_FM_CHOOSE_ENCODING'].':</td><td>';
                    echo '<select name="sel_dump_encoding_restore">';
                    echo make_options($config['mysql_possible_character_sets'], $dump['sel_dump_encoding']);
                    echo '</select></td></tr><tr><td>';
                    echo $lang['L_MYSQL_CONNECTION_ENCODING'].':</td><td><strong>'.$config['mysql_standard_character_set'].'</strong></td></tr>';

                    echo '<tr><td colspan="2"><br><input type="submit" name="restore" class="Formbutton" value="'.$lang['L_FM_RESTORE'].'">';
                    echo '<input type="hidden" name="file[0]" value="'.$file.'">';
                    echo '</td></tr></table></form></body></html>';
                    exit();
                } else {
                    $dump_encoding = $statusline['charset'];
                }
            }

            $databases['db_actual_tableselected'] = '';
            WriteParams();
            echo '<script>parent.MyOOS_Dumper_content.location.href="restore.php?filename='.$file.'&dump_encoding='.$dump_encoding.'&kind='.$kind.'";</script></body></html>';
            exit();
        }
    } else {
        $msg .= '<p class="error">'.$lang['L_FM_NOFILE'].'</p>';
    }
}

//*** Abfrage ob Delete ***
$del = [];
if (isset($_POST['delete'])) {
    if (isset($_POST['file'])) {
        $delfiles = [];
        for ($i = 0; $i < (is_countable($_POST['file']) ? count($_POST['file']) : 0); ++$i) {
            if (false === !strpos((string) $_POST['file'][$i], '_part_')) {
                $delfiles[] = substr((string) $_POST['file'][$i], 0, strpos((string) $_POST['file'][$i], '_part_') + 6).'*';
            } else {
                $delfiles[] = $_POST['file'][$i];
            }
        }
        for ($i = 0; $i < count($delfiles); ++$i) {
            $del = array_merge($del, DeleteFilesM($fpath, $delfiles[$i]));
        }
    } else {
        $msg .= '<p class="error">'.$lang['L_FM_NOFILE'].'</p>';
    }
}
if (isset($_POST['deleteauto'])) {
    $delete_result = AutoDelete();
    if ($delete_result > '') {
        $msg .= '<p class="small">'.$delete_result.'</p>';
    }
}

if (isset($_POST['deleteall']) || isset($_POST['deleteallfilter'])) {
    if (isset($_POST['deleteall'])) {
        $del = DeleteFilesM($fpath, '*.sql');
        $del = array_merge($del, DeleteFilesM($fpath, '*.gz'));
    } else {
        $del = DeleteFilesM($fpath, $databases['db_actual'].'*');
    }
}

// print file-delete-messages
if (is_array($del) && sizeof($del) > 0) {
    foreach ($del as $filename => $success) {
        if ($success) {
            $msg .= '<span class="small">';
            $msg .= $lang['L_FM_DELETE1'].' \''.$filename.'\' '.$lang['L_FM_DELETE2'];
            WriteLog("deleted '$filename'.");
            $msg .= '</span><br>';
        } else {
            $msg .= '<span class="small error">';
            $msg .= $lang['L_FM_DELETE1'].' \''.$filename.'\' '.$lang['L_FM_DELETE3'];
            WriteLog("deleted '$filename'.");
            $msg .= '</span><br>';
        }
    }
}

// Upload
if (isset($_POST['upload'])) {
    $error = false;
    if (!isset($_FILES['upfile']['name'])) {
        echo '<span class="error">'.$lang['L_FM_UPLOADFILEREQUEST'].'</span><br><br>';
    } else {
        if (!file_exists($fpath.$_FILES['upfile']['name'])) {
            // Extension ermitteln -strrpos f&auml;ngt hinten an und ermittelt somit den letzten Punkt
            $endung = strrchr((string) $_FILES['upfile']['name'], '.');
            $erlaubt = [
            '.gz', '.sql', ];
            if (!in_array($endung, $erlaubt)) {
                $msg .= '<font color="red">'.$lang['L_FM_UPLOADNOTALLOWED1'].'<br>';
                $msg .= $lang['L_FM_UPLOADNOTALLOWED2'].'</font>';
            } else {
                if (!$error) {
                    if (move_uploaded_file($_FILES['upfile']['tmp_name'], $fpath.$_FILES['upfile']['name'])) {
                        @chmod($fpath.$upfile_name, 0777);
                    } else {
                        $error .= '<font color="red">'.$lang['L_FM_UPLOADMOVEERROR'].'<br>';
                    }
                }
                if ($error) {
                    $msg .= $error.'<font color="red">'.$lang['L_FM_UPLOADFAILED'].'</font><br>';
                }
            }
        } else {
            $msg .= '<font color="red">'.$lang['L_FM_UPLOADFILEEXISTS'].'</font><br>';
        }
    }
}

//Seitenteile vordefinieren
$href = 'filemanagement.php?action='.$action.'&amp;kind='.$kind;
$tbl_abfrage = '';
if (isset($config['multi_dump']) && (0 == $config['multi_dump'])) {
    $tbl_abfrage = '<tr><td>'.$lang['L_FM_SELECTTABLES'].'</td><td><input type="checkbox" class="checkbox" name="tblfrage" value="1"></td></tr>';
}
$dk = (isset($_POST['dumpKommentar'])) ? htmlentities((string) $_POST['dumpKommentar']) : '';
$tbl_abfrage .= '<tr><td>'.$lang['L_FM_COMMENT'].':</td><td><input type="text" class="text" style="width:260px;" name="dumpKommentar" value="'.$dk.'"></td></tr>';
$autodel = '<p class="autodel">'.$lang['L_AUTODELETE'].': ';
$autodel .= (isset($config['auto_delete']) && (0 == $config['auto_delete'])) ? $lang['L_NOT_ACTIVATED'] : $lang['L_ACTIVATED'];
if (isset($config['max_backup_files'])) {
    $autodel .= '  ('.$config['max_backup_files'].' '.$lang['L_MAX_BACKUP_FILES_EACH2'].')';
}

$autodel .= '</p>';

//Fallunterscheidung
switch ($action) {
case 'dump':
    $dbName = $databases['Name'][$databases['db_selected_index']];
    if ((isset($config['multi_dump']) && (0 == $config['multi_dump'])) && in_array($dbName, $dontBackupDatabases)) {
        echo headline($lang['L_FM_DUMP_HEADER'].' <span class="small">("'.$lang['L_CONFIG_HEADLINE'].': '.$config['config_file'].'")</span>');
        echo '<span class="error">'.sprintf($lang['L_BACKUP_NOT_POSSIBLE'], $dbName).'</span>';
        break;
    }
    if (isset($config['multi_dump']) && (0 == $config['multi_dump'])) {
        DBDetailInfo($databases['db_selected_index']);
    }
    $cext = (isset($config['cron_extender']) && (0 == $config['cron_extender'])) ? 'pl' : 'cgi';
        
    $document_root = (isset($_SERVER['DOCUMENT_ROOT'])) ? filter_string_polyfill($_SERVER['DOCUMENT_ROOT']) : '';
    $script_name = (isset($_SERVER['SCRIPT_NAME'])) ? filter_string_polyfill($_SERVER['SCRIPT_NAME']) : '';
    $server_name = (isset($_SERVER['SERVER_NAME'])) ? filter_string_polyfill($_SERVER['SERVER_NAME']) : '';
        
    $actualUrl = substr((string) $script_name, 0, strrpos((string) $script_name, '/') + 1);
    if (!str_ends_with($actualUrl, '/')) {
        $actualUrl .= '/';
    }
    if (!str_starts_with($actualUrl, '/')) {
        $actualUrl = "/$actualUrl";
    }
    $refdir = (str_starts_with((string) $config['cron_execution_path'], '/')) ? '' : $actualUrl;
    $scriptdir = $config['cron_execution_path'].'crondump.'.$cext;
    $sfile = $config['cron_execution_path']."perltest.$cext";
    $simplefile = $config['cron_execution_path']."simpletest.$cext";
    $scriptentry = Realpfad('./').$config['paths']['config'];
    $cronabsolute = (str_starts_with((string) $config['cron_execution_path'], '/')) ? $document_root.$scriptdir : Realpfad('./').$scriptdir;
    $confabsolute = $config['config_file'];
        

    $scriptref = getServerProtocol().$server_name.$refdir.$config['cron_execution_path'].'crondump.'.$cext.'?config='.$confabsolute;
    $cronref = 'perl '.$cronabsolute.' -config='.$confabsolute.' -html_output=0';

    //Ausgabe
    echo headline($lang['L_FM_DUMP_HEADER'].' <span class="small">("'.$lang['L_CONFIG_HEADLINE'].': '.$config['config_file'].'")</span>');
    if (!is_writable($config['paths']['backup'])) {
        exit('<span class="error">'.sprintf($lang['L_WRONG_RIGHTS'], 'work/backup', '777').'</span>');
    }
    echo ($msg > '') ? $msg.'<br>' : '';
    echo $autodel;

    //Auswahl
    echo '<div>
		<input type="button" value=" '.$lang['L_DUMP'].' PHP " class="Formbutton" onclick="document.getElementById(\'buperl\').style.display=\'none\';document.getElementById(\'buphp\').style.display=\'block\';">
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="button" value=" '.$lang['L_DUMP'].' PERL " class="Formbutton" onclick="document.getElementById(\'buphp\').style.display=\'none\';document.getElementById(\'buperl\').style.display=\'block\';">
		</div>';
    echo '<div id="buphp">';

    //Dumpsettings
    echo '<h6>'.$lang['L_DUMP'].' (PHP)</h6>';

    echo '<div><form name="fm" id="fm" method="post" action="'.$href.'">';
    echo '<input class="Formbutton" name="dump" type="submit" value="';
    echo $lang['L_FM_STARTDUMP'].'"><br>';

    echo '<br><table>';
    echo $tbl_abfrage;
    echo '<tr><td><label for="sel_dump_encoding">'.$lang['L_FM_CHOOSE_ENCODING'].'</label></td>';
    echo '<td><select name="sel_dump_encoding" id="sel_dump_encoding">';
    echo make_options($config['mysql_possible_character_sets'], $dump['sel_dump_encoding']);
    echo '</select></td></tr>';
    echo '<tr><td>'.$lang['L_MYSQL_CONNECTION_ENCODING'].':</td><td><strong>'.$config['mysql_standard_character_set'].'</strong></td></tr>';
    echo '</table>';
    echo '</form><br></div>';

    echo '<h6>'.$lang['L_FM_DUMPSETTINGS'].' (PHP)</h6>';

    echo '<table>';
    echo '<tr><td>'.$lang['L_DB'].':</td><td><strong>';
    if (isset($config['multi_dump']) && (1 == $config['multi_dump'])) {
        echo 'Multidump ('.(is_countable($databases['multi']) ? count($databases['multi']) : 0).' '.$lang['L_DBS'].')</strong>';
        echo '<span class="small">'.$toolboxstring.'</span>';
    } else {
        echo $databases['db_actual'];
        if (isset($databases['Detailinfo'])) {
            echo '&nbsp;&nbsp;<span> ('.$databases['Detailinfo']['tables'].' Tables, '.$databases['Detailinfo']['records'].' Records, '.byte_output($databases['Detailinfo']['size']).')</span>';
        }
        echo '</strong>';
    }
    echo '</td></tr>';

    if ((isset($config['multi_dump']) && (0 == $config['multi_dump'])) && $databases['praefix'][$databases['db_selected_index']] > '') {
        echo '<tr><td>'.$lang['L_PRAEFIX'].':</td><td><strong>';
        echo $databases['praefix'][$databases['db_selected_index']];
        echo '</strong></td></tr>';
    }

    echo '<tr><td>'.$lang['L_GZIP'].':</td><td><strong>'.((isset($config['compression']) && (1 == $config['compression'])) ? $lang['L_ACTIVATED'] : $lang['L_NOT_ACTIVATED']);
    echo '</strong></td></tr>';

    echo '<tr><td>'.$lang['L_MULTI_PART'].':</td><td><strong>'.((isset($config['multi_part']) && (1 == $config['multi_part'])) ? $lang['L_YES'] : $lang['L_NO']);
    echo '</strong></td></tr>';

    if (isset($config['multi_part']) && (1 == $config['multi_part'])) {
        echo '<tr><td>'.$lang['L_MULTI_PART_GROESSE'].':</td><td><strong>'.byte_output($config['multipart_groesse']).'</strong></td></tr>';
    }

    if (isset($config['send_mail']) && (1 == $config['send_mail'])) {
        $t = $config['email_recipient'].((1 == $config['send_mail_dump']) ? $lang['L_WITHATTACH'] : $lang['L_WITHOUTATTACH']);
    }
    echo '<tr><td>'.$lang['L_SEND_MAIL_FORM'].':</td><td><strong>'.((isset($config['send_mail']) && (1 == $config['send_mail'])) ? $t : $lang['L_NOT_ACTIVATED']);
    echo '</strong></td></tr>';

    for ($x = 0; $x < 3; ++$x) {
        if (isset($config['ftp_transfer'][$x]) && $config['ftp_transfer'][$x] > 0) {
            echo table_output($lang['L_FTP_TRANSFER'], sprintf(str_replace('<br>', ' ', (string) $lang['L_FTP_SEND_TO']), $config['ftp_server'][$x], $config['ftp_dir'][$x]), 1, 2);
        }
        if (isset($config['sftp_transfer'][$x]) && $config['sftp_transfer'][$x] > 0) {
            echo table_output($lang['L_SFTP_TRANSFER'], sprintf(str_replace('<br>', ' ', (string) $lang['L_SFTP_SEND_TO']), $config['sftp_server'][$x], $config['sftp_dir'][$x]), 1, 2);
        }
    }
    //echo '</td></tr>';
    echo '</table>';

    echo '<div style="display:none"><img src="'.$config['files']['iconpath'].'progressbar_dump.gif" alt=""><br><img src="'.$config['files']['iconpath'].'progressbar_speed.gif" alt=""></div>';

    echo '</div><div id="buperl" style="display:none;">';

    //crondumpsettings
    echo '<h6>'.$lang['L_DUMP'].' (PERL)</h6>';

    echo '<p><input class="Formbutton" type="Button" name="DoCronscript" value="'.$lang['L_DOCRONBUTTON'].'" onclick="self.location.href=\''.$scriptref.'\'">&nbsp;&nbsp;';
    echo '<input class="Formbutton" type="Button" name="DoPerlTest" value="'.$lang['L_DOPERLTEST'].'" onclick="self.location.href=\''.$sfile.'\'">&nbsp;&nbsp;';
    echo '<input class="Formbutton" type="Button" name="DoSimpleTest" value="'.$lang['L_DOSIMPLETEST'].'" onclick="self.location.href=\''.$simplefile.'\'"></p>';

    echo '<h6>'.$lang['L_FM_DUMPSETTINGS'].' (PERL)</h6>';

    if (-3 == $config['cron_dbindex']) {
        $cron_dbname = $lang['L_MULTIDUMPALL'];
        $cron_dbpraefix = '';
    } elseif (-2 == $config['cron_dbindex']) {
        //$cron_dbname='Multidump ('.count($databases['multi']).' '.$lang['L_DBS'].')';
        $cron_dbname = 'Multidump ('.(is_countable($databases['multi']) ? count($databases['multi']) : 0).' '.$lang['L_DBS'].')</strong>';
        $cron_dbname .= '<span class="small">'.$toolboxstring.'</span>';
        $cron_dbpraefix = '';
    } else {
        $cron_dbname = $databases['Name'][$config['cron_dbindex']];
        $cron_dbpraefix = $databases['praefix'][$config['cron_dbindex']];
    }

    echo '<table>';
    echo '<tr><td>'.$lang['L_DB'].':</td><td><strong>'.$cron_dbname.'</strong></td></tr>';

    if ($cron_dbpraefix > '') {
        echo '<tr><td>'.$lang['L_PRAEFIX'].':</td><td><strong>';
        echo $cron_dbpraefix.'</strong></td></tr>';
    }

    echo '<tr><td>'.$lang['L_GZIP'].':</td><td><strong>'.((isset($config['cron_compression']) && (1 == $config['cron_compression'])) ? $lang['L_ACTIVATED'] : $lang['L_NOT_ACTIVATED']);
    echo '</strong></td></tr>';

    echo '<tr><td>'.$lang['L_MULTI_PART'].':</td><td><strong>'.((isset($config['multi_part']) && (1 == $config['multi_part'])) ? $lang['L_YES'] : $lang['L_NO']);
    echo '</strong></td></tr>';

    if (isset($config['multi_part']) && (1 == $config['multi_part'])) {
        echo '<tr><td>'.$lang['L_MULTI_PART_GROESSE'].':</td><td><strong>'.byte_output($config['multipart_groesse']).'</td></tr>';
    }
    echo '<tr><td>'.$lang['L_CRON_PRINTOUT'].':</td><td><strong>'.((isset($config['cron_printout']) && (1 == $config['cron_printout'])) ? $lang['L_ACTIVATED'] : $lang['L_NOT_ACTIVATED']).'</strong></td></tr>';

    if ((isset($config['send_mail']) && (1 == $config['send_mail'])) && (isset($config['cron_use_mail']) && (2 > $config['cron_use_mail']))) { 
        if (isset($config['cron_use_mail']) && (2 > $config['cron_use_mail'])) {
            $t = $config['email_recipient'].((1 == $config['send_mail_dump']) ? $lang['L_WITHATTACH'] : $lang['L_WITHOUTATTACH']);
        }
        echo '<tr><td>'.$lang['L_SEND_MAIL_FORM'].':</td><td><strong>'.((isset($config['cron_use_mail']) && (2 > $config['cron_use_mail'])) ? $t : $lang['L_NOT_ACTIVATED']).'</strong></td></tr>';
    } else {
        echo '<tr><td>'.$lang['L_SEND_MAIL_FORM'].':</td><td><strong>'. $lang['L_NOT_ACTIVATED'].'</strong></td></tr>';
    }



    for ($x = 0; $x < 3; ++$x) {
        if (isset($config['ftp_transfer'][$x]) && $config['ftp_transfer'][$x] > 0) {
            echo table_output($lang['L_FTP_TRANSFER'], sprintf(str_replace('<br>', ' ', (string) $lang['L_FTP_SEND_TO']), $config['ftp_server'][$x], $config['ftp_dir'][$x]), 1, 2);
        }
        if (isset($config['sftp_transfer'][$x]) && $config['sftp_transfer'][$x] > 0) {
            echo table_output($lang['L_SFTP_TRANSFER'], sprintf(str_replace('<br>', ' ', (string) $lang['L_SFTP_SEND_TO']), $config['sftp_server'][$x], $config['sftp_dir'][$x]), 1, 2);
        }
    }
    //echo '</td></tr>';
    echo '</table>';

    //    Eintraege fuer Perl
    echo '<br><p class="small">'.$lang['L_PERLOUTPUT1'].':<br>&nbsp;&nbsp;&nbsp;&nbsp;<strong>'.$scriptentry.'</strong><br>';
    echo $lang['L_PERLOUTPUT2'].':<br>&nbsp;&nbsp;&nbsp;&nbsp;<strong>'.$scriptref.'</strong><br>';
    echo $lang['L_PERLOUTPUT3'].':<br>&nbsp;&nbsp;&nbsp;&nbsp;<strong>'.$cronref.'</strong></p>';

    echo '</div>';

    break;

case 'restore':
    echo headline(sprintf($lang['L_FM_RESTORE_HEADER'], $databases['db_actual']));
    echo ($msg > '') ? $msg : '';
    echo $autodel;
    echo '<form name="fm" id="fm" method="post" action="'.$href.'">';
    echo '<div>';
    echo '<input class="Formbutton" name="restore" type="submit" value="'.$lang['L_FM_RESTORE'].'" onclick="if (!confirm(\''.$lang['L_FM_ALERTRESTORE1'].' `'.$databases['db_actual'].'`  '.$lang['L_FM_ALERTRESTORE2'].' `\' + GetSelectedFilename() + \'` '.$lang['L_FM_ALERTRESTORE3'].'\')) return false;">';
    echo '<input class="Formbutton" name="restore" type="submit" value="'.$lang['L_RESTORE_OF_TABLES'].'" onclick="document.forms[0].tblfrage.value=1;">';


    echo FileList();
    echo '<input type="hidden" name="tblfrage" value="0">';
    echo '</div></form>';
    break;
case 'files':
    $sysfedit = (isset($_POST['sysfedit'])) ? 1 : 0;
    $sysfedit = $_GET['sysfedit'] ?? $sysfedit;
    echo headline($lang['L_FILE_MANAGE']);
    echo ($msg > '') ? $msg.'<br>' : '';
    echo $autodel;
    echo '<form name="fm" id="fm" method="post" action="'.$href.'">';
    echo '<input class="Formbutton" name="delete" type="submit" value="'.$lang['L_FM_DELETE'].'"	onclick="if (!confirm(\''.$lang['L_FM_ASKDELETE1'].'\n\' + GetSelectedFilename() + \'\n\n'.$lang['L_FM_ASKDELETE2'].'\')) return false;">';
    echo '<input class="Formbutton" name="deleteauto" type="submit" value="'.$lang['L_FM_DELETEAUTO'].'"	onclick="if (!confirm(\''.$lang['L_FM_ASKDELETE3'].'\')) return false;">';
    echo '<input class="Formbutton" name="deleteall" type="submit" value="'.$lang['L_FM_DELETEALL'].'"	onclick="if (!confirm(\''.$lang['L_FM_ASKDELETE4'].'\')) return false;">';
    echo '<input class="Formbutton" name="deleteallfilter" type="submit" value="'.$lang['L_FM_DELETEALLFILTER'].$databases['db_actual'].$lang['L_FM_DELETEALLFILTER2'].'"	onclick="if (!confirm(\''.$lang['L_FM_ASKDELETE5'].$databases['db_actual'].$lang['L_FM_ASKDELETE5_2'].'\')) return false;">';
    echo FileList().'</form>';

    echo '<h6>'.$lang['L_FM_FILEUPLOAD'].'</h6>';
    echo '<div align="left"><form action="'.$href.'" method="POST" enctype="multipart/form-data">';
    echo '<input type="file" name="upfile" class="Formtext" size="60">';
    echo '<input type="submit" name="upload" value="'.$lang['L_FM_FILEUPLOAD'].'" class="Formbutton">';
    echo '<br>'.$lang['L_MAX_UPLOAD_SIZE'].': <strong>'.$config['upload_max_filesize'].'</strong>';
    echo '<br>'.$lang['L_MAX_UPLOAD_SIZE_INFO'];

    echo '</form></div>';

    echo '<h6>Tools</h6><div align="left">';
    echo '<input type="Button" onclick="document.location=\'filemanagement.php?action=convert\'" class="Formbutton" value="'.$lang['L_CONVERTER'].'">';
    echo '</div>';

    break;
case 'convert':
    // Konverter
    echo headline($lang['L_CONVERTER']);
    echo '<br><br><form action="filemanagement.php?action=convert" method="post">';
    echo '<table class="bdr"><tr><th colspan="2">'.$lang['L_CONVERT_TITLE'].'</th></tr>';
    echo '<tr><td>'.$lang['L_CONVERT_FILE'].'</td><td>'.FilelisteCombo($config['paths']['backup'], $selectfile).'</td></tr>';
    echo '<tr><td>'.$lang['L_CONVERT_FILENAME'].':</td><td><input type="text" name="destfile" size="50" value="'.$destfile.'"></td></tr>';
    echo '<tr><td><input type="checkbox" name="compressed" value="1" '.((1 == $compressed) ? 'checked' : '').'>&nbsp;'.$lang['L_COMPRESSED'].'</td>';
    echo '<td><input type="submit" name="startconvert" value=" '.$lang['L_CONVERT_START'].' " class="Formbutton"></td></tr>';
    echo '</table></form><br>';
    if (isset($_POST['startconvert'])) {
        //$destfile.=($compressed==1) ? ".sql.gz" : ".sql";
        echo $lang['L_CONVERTING']." $selectfile ==&gt; $destfile<br>";

        if ('' != $selectfile && file_exists($config['paths']['backup'].$selectfile) && strlen($destfile ?? '') > 2) {
            Converter($selectfile, $destfile, $compressed);
        } else {
            echo $lang['L_CONVERT_WRONG_PARAMETERS'];
        }
    }
}
echo MODFooter();
ob_end_flush();
exit();
