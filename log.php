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

require './inc/header.php';
require_once './language/'.$config['language'].'/lang_log.php';
echo MODHeader();

if (isset($_POST['r'])) {
    $r = $_POST['r'];
} else {
    $r = $_GET['r'] ?? 0;
}

$revers = $_GET['revers'] ?? 0;

//loeschen
if (isset($_POST['kill'])) {
    if (0 == $_POST['r']) {
        DeleteLog();
    } elseif (1 == $_POST['r']) {
        @unlink($config['files']['perllog']);
        @unlink($config['files']['perllog'].'.gz');
    } elseif (2 == $_POST['r']) {
        @unlink($config['files']['perllogcomplete']);
        @unlink($config['files']['perllogcomplete'].'.gz');
    } elseif (3 == $_POST['r']) {
        @unlink($config['paths']['log'].'error.log');
        @unlink($config['paths']['log'].'error.log.gz');
    }
    $r = 0;
}

if (0 == $r) {
    $lfile = $config['files']['log'];
    $lcap = 'PHP-Log';
} elseif (1 == $r) {
    $lfile = $config['files']['perllog'];
    $lcap = 'Perl-Log';
} elseif (2 == $r) {
    $lfile = $config['files']['perllogcomplete'];
    $lcap = 'Perl-Complete Log';
} elseif (3 == $r) {
    $lfile = $config['paths']['log'].'error.log';
    $lcap = 'PHP Error-Log';
}

if (isset($config['logcompression']) && 1 == $config['logcompression']) {
    $lfile .= '.gz';
}
if (!file_exists($lfile) && 0 == $r) {
    DeleteLog();
}
$nLogcompression = $config['logcompression'] ?? 0;
$loginfo = LogFileInfo($nLogcompression);

echo headline($lcap);
if (!is_writable($config['paths']['log'])) {
    exit('<p class="error">ERROR !<br>Logdir is not writable</p>');
}

//lesen
$errorbutton = '';
$perlbutton = '';
$perlbutton2 = '';

if (file_exists($loginfo['errorlog'])) {
    $errorbutton = '<td><input class="Formbutton" type="button" onclick="location.href=\'log.php?r=3\'" value="Error-Log"></td>';
}
if (file_exists($loginfo['perllog'])) {
    $perlbutton = '<td><input type="button" onclick="location.href=\'log.php?r=1\'" class="Formbutton" value="Perl-Log"></td>';
}
if (file_exists($loginfo['perllogcomplete'])) {
    $perlbutton2 = '<td><input class="Formbutton" type="button" onclick="location.href=\'log.php?r=2\'" value="Perl-Complete Log"></td>';
}

//anzeigen
echo '<form action="log.php" method="post"><table><tr>';
echo '<td><input class="Formbutton" type="button" onclick="location.href=\'log.php?r=0\'" value="PHP-Log"></td>';
echo "\n".$errorbutton."\n".$perlbutton."\n".$perlbutton2."\n";
echo '</tr></table><br>';

//Status Logfiles
$icon['blank'] ??= $config['files']['iconpath'].'blank.gif';
echo '<div align="left"><table class="bdr"><tr><td><table><tr><td valign="top"><strong>'.$lang['L_LOGFILEFORMAT'].'</strong><br><br>'.((isset($config['logcompression']) && (1 == $config['logcompression'])) ? '<img src="'.$config['files']['iconpath'].'gz.gif" width="32" height="32" alt="compressed" align="left">' : '<img src="'.$icon['blank'].'" width="32" height="32" alt="" align="left">');
echo ''.(((isset($config['logcompression']) && 1 == $config['logcompression'])) ? $lang['L_COMPRESSED'] : $lang['L_NOTCOMPRESSED']).'</td>';
echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td><td valign="top" align="right">';
echo '<a href="'.$loginfo['log'].'">'.substr((string) $loginfo['log'], strrpos((string) $loginfo['log'], '/') + 1).'</a><br>';
echo ($loginfo['errorlog_size'] > 0) ? '<a href="'.$loginfo['errorlog'].'">'.substr((string) $loginfo['errorlog'], strrpos((string) $loginfo['errorlog'], '/') + 1).'</a><br>' : substr((string) $loginfo['errorlog'], strrpos((string) $loginfo['errorlog'], '/') + 1).'<br>';
echo ($loginfo['perllog_size'] > 0) ? '<a href="'.$loginfo['perllog'].'">'.substr((string) $loginfo['perllog'], strrpos((string) $loginfo['perllog'], '/') + 1).'</a><br>' : substr((string) $loginfo['perllog'], strrpos((string) $loginfo['perllog'], '/') + 1).'<br>';
echo ($loginfo['perllogcomplete_size'] > 0) ? '<a href="'.$loginfo['perllogcomplete'].'">'.substr((string) $loginfo['perllogcomplete'], strrpos((string) $loginfo['perllogcomplete'], '/') + 1).'</a><br>' : substr((string) $loginfo['perllogcomplete'], strrpos((string) $loginfo['perllogcomplete'], '/') + 1).'<br>';
echo '<strong>total</strong></td><td valign="top" align="right">'.byte_output($loginfo['log_size']).'<br>'.byte_output($loginfo['errorlog_size']).'<br>'.byte_output($loginfo['perllog_size']).'<br>'.byte_output($loginfo['perllogcomplete_size']).'<br><strong>'.byte_output($loginfo['log_totalsize']).'</strong></td>';
echo '</tr><tr><td colspan="3" align="center"><a class="small" href="log.php?r='.$r.'&amp;revers=0">'.$lang['L_NOREVERSE'].'</a>&nbsp;&nbsp;&nbsp;<a class="small" href="log.php?r='.$r.'&amp;revers=1">'.$lang['L_REVERSE'].'</a></td></tr></table></td></tr></table></div>';

$out = '';
if (2 != $r) {
    $out .= '<pre>';
}

if (file_exists($lfile)) {
    $zeilen = ((isset($config['logcompression']) && 1 == $config['logcompression'])) ? gzfile($lfile) : file($lfile);
    if (30 == $r) {
        echo '<pre>'.print_r($zeilen, true).'</pre>';
        exit();
    }
    if (1 == $revers) {
        $zeilen = array_reverse($zeilen);
    }
    foreach ($zeilen as $zeile) {
        if (2 == $r) {
            $out .= $zeile.'<br>';
        } elseif (3 == $r) {
            $z = explode('|:|', (string) $zeile);
            for ($i = 0; $i < count($z); ++$i) {
                $out .= '<span>'.substr($z[$i], 0, strpos($z[$i], ': ')).'</span> '.substr($z[$i], strpos($z[$i], ': ')).'<br>';
            }
        } else {
            $out .= $zeile;
        }
    }
}
if (2 != $r) {
    $out .= '</pre>';
}

$suchen = [
            '</html>',
            '</body>',
];
$ersetzen = [
                '',
                '',
];
$out = str_replace($suchen, $ersetzen, $out);

if ('' != $out) {
    echo '<div align="left" style="width:100%"><br>';
    echo '<input type="hidden" name="r" value="'.$r.'"><input class="Formbutton" type="submit" name="kill" value="'.$lang['L_LOG_DELETE'].'">';
    echo '<br><br><div id="ilog">'.$out.'</div></div>';
}

echo '</form>';
echo MODFooter();
ob_end_flush();
exit();
