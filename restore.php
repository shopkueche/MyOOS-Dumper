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

define('OOS_VALID_MOD', true);

if (!@ob_start('ob_gzhandler')) {
    @ob_start();
}

session_name('MyOOSDumperID');
session_start();
require './inc/functions.php';
require './inc/functions_restore.php';
require './inc/mysqli.php';
if (isset($_GET['filename'])) {
    // Arrays uebernehmen
    foreach ($_POST as $key => $val) {
        if (is_array($val)) {
            foreach ($val as $key2 => $val2) {
                $restore[$key][$key2] = $val2;
            }
        }
    }
    include './'.$config['files']['parameter'];
    $restore['max_zeit'] = intval($config['max_execution_time'] * $config['time_buffer']);
    if (0 == $restore['max_zeit']) {
        $restore['max_zeit'] = 20;
    }
    $restore['startzeit'] = time();
    $restore['xtime'] = $_POST['xtime'] ?? time();
    $restore['fileEOF'] = false; // Ende des Files erreicht?
    $restore['actual_table'] = (!empty($_POST['actual_table'])) ? $_POST['actual_table'] : 'unbekannt';
    $restore['offset'] = (!empty($_POST['offset'])) ? $_POST['offset'] : 0;
    $restore['aufruf'] = (!empty($_POST['aufruf'])) ? $_POST['aufruf'] : 0;
    $restore['table_ready'] = (!empty($_POST['table_ready'])) ? $_POST['table_ready'] : 0;
    $restore['part'] = $_POST['part'] ?? 0;
    $restore['do_it'] = $_POST['do_it'] ?? false;
    $restore['errors'] = $_POST['err'] ?? 0;
    $restore['notices'] = $_POST['notices'] ?? 0;
    $restore['anzahl_eintraege'] = $_POST['anzahl_eintraege'] ?? 0;
    $restore['anzahl_tabellen'] = $_POST['anzahl_tabellen'] ?? 0;
    $restore['filename'] = (isset($_POST['filename'])) ? urldecode((string) $_POST['filename']) : '';
    if (isset($_GET['filename'])) {
        $restore['filename'] = urldecode((string) $_GET['filename']);
    }
    $restore['actual_fieldcount'] = $_POST['actual_fieldcount'] ?? 0;
    $restore['eintraege_ready'] = $_POST['eintraege_ready'] ?? 0;
    $restore['anzahl_zeilen'] = $_POST['anzahl_zeilen'] ?? $config['minspeed'];
    $restore['summe_eintraege'] = $_POST['summe_eintraege'] ?? 0;
    $restore['erweiterte_inserts'] = $_POST['erweiterte_inserts'] ?? 0;
    $restore['flag'] = $_POST['flag'] ?? -1;
    $restore['EOB'] = false;
    $restore['dump_encoding'] = $_POST['dump_encoding'] ?? 'utf8';
    if (isset($_GET['dump_encoding'])) {
        $restore['dump_encoding'] = $_GET['dump_encoding'];
    }
    $restore['compressed'] = (str_ends_with(strtolower((string) $restore['filename']), 'gz')) ? 1 : 0;
    // wurden nur bestimmte Tabellen ausgwaehlt?
    if (!isset($databases['db_actual_tableselected'])) {
        $databases['db_actual_tableselected'] = '';
    }
    if ('' != $databases['db_actual_tableselected']) {
        $restore['tables_to_restore'] = explode('|', (string) $databases['db_actual_tableselected']);
    } else {
        $restore['tables_to_restore'] = false;
    }
    $_SESSION['config'] = $config;
    $_SESSION['databases'] = $databases;
} else {
    $config = $_SESSION['config'];
    $databases = $_SESSION['databases'];
    $restore = $_SESSION['restore'];
    $restore['startzeit'] = time();
    // some Server limit the number of vars that can be saved in a session
    // if this is the case and we lost the language-var we simply include the configuration again
    // this way the include is skipped on servers with unlimited vars
    if (!isset($config['language'])) {
        include './'.$config['files']['parameter'];
    }
}
require './language/'.$config['language'].'/lang.php';
require './language/'.$config['language'].'/lang_restore.php';
$config['files']['iconpath'] = './css/'.$config['theme'].'/icons/';
$aus = [];
$pageheader = MODheader().headline($lang['L_RESTORE']);
$aus1 = $page_parameter = '';
$RestoreFertig = $eingetragen = $dauer = $filegroesse = 0;
mod_mysqli_connect($restore['dump_encoding'], true, $restore['actual_table']);
@mysqli_select_db($config['dbconnection'], $databases['db_actual']) or exit($lang['L_DB_SELECT_ERROR'].$databases['db_actual'].$lang['L_DB_SELECT_ERROR2']);

// open backup file
$restore['filehandle'] = (1 == $restore['compressed']) ? gzopen($config['paths']['backup'].$restore['filename'], 'r') : fopen($config['paths']['backup'].$restore['filename'], 'r');
if ($restore['filehandle']) {
    //nur am Anfang Logeintrag
    if (0 == $restore['offset'] && 0 == $restore['anzahl_tabellen']) {
        // Statuszeile auslesen
        $restore['part'] = 0;
        $statusline = (1 == $restore['compressed']) ? gzgets($restore['filehandle']) : fgets($restore['filehandle']);
        $sline = ReadStatusline($statusline);

        $restore['anzahl_tabellen'] = $sline['tables'];
        $restore['anzahl_eintraege'] = $sline['records'];
        if ('MP_0' != $sline['part']) {
            $restore['part'] = 1;
        } //substr($sline['part'],3);
        if (1 == $config['empty_db_before_restore']) {
            EmptyDB($databases['db_actual']);
        }
        $restore['tablelock'] = 0;
        $restore['erweiterte_inserts'] = 0;

        if ('-1' == $sline['tables']) {
            ($restore['compressed']) ? gzseek($restore['filehandle'], 0) : fseek($restore['filehandle'], 0);
        }
        if ($restore['part'] > 0) {
            WriteLog('Start Multipart-Restore \''.$restore['filename'].'\'');
        } else {
            WriteLog('Start Restore \''.$restore['filename'].'\'');
        }
    } else {
        if (0 == $restore['compressed']) {
            $filegroesse = filesize($config['paths']['backup'].$restore['filename']);
        }
        // Dateizeiger an die richtige Stelle setzen
        ($restore['compressed']) ? gzseek($restore['filehandle'], $restore['offset']) : fseek($restore['filehandle'], $restore['offset']);

        // Jetzt basteln wir uns mal unsere Befehle zusammen...
        $a = 0;
        $dauer = 0;
        $restore['EOB'] = false;
        // Disable Keys of actual table to speed up restoring
        if (is_array($restore['tables_to_restore']) && sizeof($restore['tables_to_restore']) > 0 && in_array($restore['actual_table'], $restore['tables_to_restore'])) {
            @mysqli_query($config['dbconnection'], '/*!40000 ALTER TABLE `'.$restore['actual_table'].'` DISABLE KEYS */;');
        } elseif (!is_array($restore['tables_to_restore']) 
            && (is_array($restore['tables_to_restore']) && 0 == sizeof($restore['tables_to_restore'])) 
            && ($restore['actual_table'] > '' && 'unbekannt' != $restore['actual_table'])
        ) {
            @mysqli_query($config['dbconnection'], '/*!40000 ALTER TABLE `'.$restore['actual_table'].'` DISABLE KEYS */;');
        }

        while (($a < $restore['anzahl_zeilen']) && (!$restore['fileEOF']) && ($dauer < $restore['max_zeit']) && !$restore['EOB']) {
            $sql_command = get_sqlbefehl();
            if ($sql_command > '') {
                //WriteLog(htmlspecialchars($sql_command));
                $res = mysqli_query($config['dbconnection'], $sql_command);
                if (false === !$res) {
                    $anzsql = mysqli_affected_rows($config['dbconnection']);
                    // Anzahl der eingetragenen Datensaetze ermitteln (Indexaktionen nicht zaehlen)
                    $command = strtoupper(substr((string) $sql_command, 0, 7));
                    if ('INSERT ' == $command) {
                        $anzsql = mysqli_affected_rows($config['dbconnection']);
                        if ($anzsql > 0) {
                            $restore['eintraege_ready'] += $anzsql;
                        }
                    }
                } else {
                    // Bei MySQL-Fehlern sofort abbrechen und Info ausgeben
                    $meldung = mysqli_error($config['dbconnection']);
                    if ('' != $meldung) {
                        if ('duplicate entry' == strtolower(substr($meldung, 0, 15))) {
                            ErrorLog('RESTORE', $databases['db_actual'], $sql_command, $meldung, 1);
                            ++$restore['notices'];
                        } else {
                            if (0 == $config['stop_with_error']) {
                                Errorlog('RESTORE', $databases['db_actual'], $sql_command, $meldung);
                                ++$restore['errors'];
                            } else {
                                Errorlog('RESTORE', $databases['db_actual'], $sql_command, 'Restore failed: '.$meldung, 0);
                                SQLError($sql_command, $meldung);
                                exit($sql_command.' -> '.$meldung);
                            }
                        }
                    }
                }
            }
            ++$a;
            $dauer = time() - $restore['startzeit'];
        }
        $eingetragen = $a - 1;
    }

    $restore['offset'] = ($restore['compressed']) ? gztell($restore['filehandle']) : ftell($restore['filehandle']);
    $restore['compressed'] ? gzclose($restore['filehandle']) : fclose($restore['filehandle']);
    ++$restore['aufruf'];
    if (!$restore['compressed']) {
        $prozent = ($filegroesse > 0) ? ($restore['offset'] * 100) / $filegroesse : 0;
    } else {
        if ($restore['anzahl_eintraege'] > 0) {
            $prozent = $restore['eintraege_ready'] * 100 / $restore['anzahl_eintraege'];
        } else {
            $prozent = 0;
        }
    }
    if ($prozent > 100) {
        $prozent = 100;
    }

    if ('' != $aus1) {
        $aus[] = '<br>'.$aus1.'<br><br>';
    }
    $aus[] = sprintf($lang['L_RESTORE_DB'], $databases['db_actual'], $config['dbhost']).'<br>'.$lang['L_FILE'].': <b>'.$restore['filename'].'</b><br>'.$lang['L_CHARSET'].': <strong>'.$restore['dump_encoding'].'</strong><br>';
    if ($restore['part'] > 0) {
        $aus[] = '<br>Multipart File <strong>'.$restore['part'].'</strong><br>';
    }
    $tabellen_fertig = ($restore['table_ready'] > 0) ? $restore['table_ready'] : '0';
    $to_do = ($restore['anzahl_tabellen'] > 0) ? $restore['anzahl_tabellen'] : $lang['L_UNKNOWN'];
    if ($restore['anzahl_tabellen'] > 0) {
        $aus[] = sprintf($lang['L_RESTORE_TABLES_COMPLETED'], $tabellen_fertig, $to_do);
    } else {
        $aus[] = sprintf($lang['L_RESTORE_TABLES_COMPLETED0'], $tabellen_fertig);
    }
    $done = number_format($restore['eintraege_ready'], 0, ',', '.');
    $to_do = number_format($restore['anzahl_eintraege'], 0, ',', '.');
    if ($restore['anzahl_eintraege'] > 0) {
        $aus[] = sprintf($lang['L_RESTORE_RUN1'], $done, $to_do);
    } else {
        $aus[] = sprintf($lang['L_RESTORE_RUN0'], $done);
    }
    $aus[] = sprintf($lang['L_RESTORE_RUN2'], $restore['actual_table']).$lang['L_PROGRESS_OVER_ALL'].'<br>';

    //Fortschrittsbalken
    $prozentbalken = (round($prozent, 0) * 3);
    if ($restore['anzahl_eintraege'] > 0 && false === $restore['tables_to_restore']) {
        $aus[] = '<table border="0" width="440"><tr>';
        if ($prozentbalken >= 3) {
            $aus[] = '<td width="'.$prozentbalken.'" nowrap="nowrap">
		<img src="'.$config['files']['iconpath'].'progressbar_restore.gif" name="restorebalken" alt="" width="'.$prozentbalken.'" height="16" border="0"></td>'.'<td width="'.(round(100 - $prozent, 0) * 3).'">&nbsp;</td>'.'<td width="80" align="right" nowrap="nowrap"><b>'.(number_format($prozent, 2, ',', '.')).' %</b></td></tr></table>';
        }
    } else {
        $aus[] = ' <b>'.$lang['L_UNKNOWN_NUMBER_OF_RECORDS'].'</b><br><br>';
    }

    //Speed-Anzeige
    $fw = ($config['maxspeed'] == $config['minspeed']) ? 300 : round(($restore['anzahl_zeilen'] - $config['minspeed']) / ($config['maxspeed'] - $config['minspeed']) * 300, 0);
    $aus[] = '<br><table border="0" cellpadding="0" cellspacing="0"><tr>'.'<td width="60" valign="top" align="center" style="color:#990000; font-size:10px;" >'.'<strong>Speed</strong><br>'.$restore['anzahl_zeilen'].'</td><td width="300">'.'<table border="0" width="100%" cellpadding="0" cellspacing="0"><tr>'.'<td align="left"class="small" width="300" nowrap="nowrap">'.'<img src="'.$config['files']['iconpath'].'progressbar_speed.gif" name="speedbalken" alt="" width="'.$fw.'" height="12" border="0" vspace="0" hspace="0">'.'</td></tr></table><table border="0" width="100%" cellpadding="0" cellspacing="0">'.'<tr style="padding:0;margin:0;"><td align="left" nowrap="nowrap" style="font-size:10px;" >'.$config['minspeed'].'</td>'.'<td style="text-align:right;font-size:10px;" nowrap="nowrap">'.$config['maxspeed'].'</td>'.'</tr></table></td></tr></table>';

    //Status-Text
    $aus[] = '<p class="small">'.zeit_format(time() - $restore['xtime']).', '.$restore['aufruf'].' '.$lang['L_PAGE_REFRESHS'].(($restore['part'] > 0) ? ', file '.$restore['part'] : '').(($restore['errors'] > 0) ? ', <span class="error">'.$restore['errors'].' errors</span>' : '').(($restore['notices'] > 0) ? ', <span class="notice">'.$restore['notices'].' notices</span>' : '').'</p>';
    $restore['summe_eintraege'] += $eingetragen;

    //Zeitanpassung
    if ($dauer < $restore['max_zeit']) {
        $restore['anzahl_zeilen'] = $restore['anzahl_zeilen'] * $config['tuning_add'];
        // wenn wir mehr als die Haelfte der Zeit noch haetten nutzen koennen: Anzahl direkt um fast das Doppelte erhoehen
        if ($dauer < $restore['max_zeit'] / 2) {
            $restore['anzahl_zeilen'] = $restore['anzahl_zeilen'] * 1.8;
        }
        if ($restore['anzahl_zeilen'] > $config['maxspeed']) {
            $restore['anzahl_zeilen'] = $config['maxspeed'];
        }
    } else {
        $restore['anzahl_zeilen'] = $restore['anzahl_zeilen'] * $config['tuning_sub'];
        if ($restore['anzahl_zeilen'] < $config['minspeed']) {
            $restore['anzahl_zeilen'] = $config['minspeed'];
        }
    }
    $restore['anzahl_zeilen'] = intval($restore['anzahl_zeilen']);
    if ($restore['fileEOF'] && 0 == $restore['part']) {
        $restore['EOB'] = true;
    }
    if ($restore['EOB']) {
        // Uff, geschafft! Jetzt darf die Leitung wieder abkuehlen. :-)
        unset($aus);
        $aus = [];
        $restore['xtime'] = time() - $restore['xtime'];
        WriteLog("Restore '".$restore['filename']."' finished in ".zeit_format($restore['xtime']).'.');
        $aus[] = $lang['L_RESTORE_TOTAL_COMPLETE'].'<br>';
        $aus[] = $lang['L_FILE'].': <b>'.$restore['filename'].'</b><br><br>';
        $aus[] = sprintf($lang['L_RESTORE_COMPLETE'], $restore['table_ready']).'<br>';
        $aus[] = sprintf($lang['L_RESTORE_COMPLETE2'], number_format($restore['eintraege_ready'], 0, ',', '.'));
        $aus[] = '<p class="small">'.zeit_format($restore['xtime']).', '.$restore['aufruf'].' '.$lang['L_PAGE_REFRESHS'].' </p>';
        if ($restore['errors'] > 0) {
            $aus[] = $lang['L_ERRORS'].': '.$restore['errors'].'  <a href="log.php?r=3">&raquo; '.$lang['L_VIEW'].'</a><br>';
        }
        if ($restore['notices'] > 0) {
            $aus[] = $lang['L_NOTICES'].': '.$restore['notices'].'  <a href="log.php?r=3">&raquo; '.$lang['L_VIEW'].'</a><br>';
        }
        $aus[] = '<br>&nbsp;&nbsp;&nbsp;<input class="Formbutton" type="button" value="'.$lang['L_BACK_TO_MINISQL']."\" onclick=\"self.location.href='sql.php'\">";
        $aus[] = '&nbsp;&nbsp;&nbsp;<input class="Formbutton" type="button" value="'.$lang['L_BACK_TO_OVERVIEW']."\" onclick=\"self.location.href='main.php?action=db&dbid=".$databases['db_selected_index']."#dbid'\">";
        $RestoreFertig = 1;
    } else {
        if ($restore['fileEOF']) {
            //Multipart-Restore
            $restore['fileEOF'] = false;
            $nextfile = NextPart($restore['filename'], 0, true);
            if (!file_exists($config['paths']['backup'].$nextfile)) {
                $done = number_format($restore['eintraege_ready'], 0, ',', '.');
                $to_do = number_format($restore['anzahl_eintraege'], 0, ',', '.');
                $aus = [];
                $aus[] = '<h3>'.$lang['L_RESTORE'].'</h3>';
                $aus[] = sprintf($lang['L_RESTORE_DB'], $databases['db_actual'], $config['dbhost']);
                $aus[] = '<p class="error">'.$lang['L_MULTI_PART'].': '.$lang['L_FILE_MISSING'].' \''.$nextfile.'\' !</p>';
                $aus[] = sprintf($lang['L_RESTORE_RUN1'], $done, $to_do);
                $aus[] = sprintf($lang['L_RESTORE_RUN2'], $restore['actual_table']);
                $aus[] = '<p class="small">'.zeit_format(time() - $restore['xtime']).', '.$restore['aufruf'].' '.$lang['L_PAGE_REFRESHS'];
                $aus[] = ($restore['part'] > 0) ? ', '.$lang['L_FILE'].' '.$restore['part'] : '';
                $aus[] = ($restore['errors'] > 0) ? ', <span class="error">'.$restore['errors'].' errors</span>' : '';
                $aus[] = '</p>';
                WriteLog('Restore unsuccessful: Cannot find Multipartfile \''.$nextfile.'\'');
                $RestoreFertig = 1;
            } else {
                $restore['filename'] = $nextfile;
                $restore['offset'] = 0;
                ++$restore['part'];
                WriteLog("Continue Multipart-Restore with File '".$restore['filename']."'");
            }
        }
    }
} else {
    $aus[] = $config['paths']['backup'].$restore['filename'].' :  '.$lang['L_FILE_OPEN_ERROR'];
}

$pagefooter = (1 == $RestoreFertig) ? MODFooter() : '</div></BODY></HTML>';
// formerly all parameters were submitted via POST; now we use a session but we need the form to do the js-selfcall
$page_parameter = '<form action="restore.php?MyOOSDumperID='.session_id().'" method="POST" name="restore"></form>';
if (1 == $RestoreFertig) {
    $complete_page = $pageheader.(('' != $aus) ? implode("\n", $aus) : '').$pagefooter;
} else {
    $aus[] = $page_parameter;
    $_SESSION['restore'] = $restore;
    $selbstaufruf = '<script>setTimeout("document.restore.submit()",10);</script>';
    $complete_page = $pageheader.(('' != $aus) ? implode("\n", $aus) : '').$selbstaufruf.$pagefooter;
}
echo $complete_page;
ob_end_flush();
exit();
