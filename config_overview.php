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

if (!@ob_start('ob_gzhandler')) {
    @ob_start();
}

function print_save_button()
{
    global $lang;
    $t = '<span style="float:right;padding-right:6px;"><input class="Formbutton" type="submit" name="save" value="'.$lang['L_SAVE'].'"></span>';

    return $t;
}

// if new language was selected already before include
if (isset($_POST['save']) && $_POST['language'] != $_POST['lang_old']) {
    $config['language'] = $_POST['language'];
    $temp_lang = $config['language'];
    include_once './inc/header.php'; // Normal prodecure (resets config[language])
    $config['language'] = $temp_lang; // re-set language
    include './language/lang_list.php'; // This re-initializes $lang[] and loads appropiate language files
} else {
    include_once './inc/header.php'; // language not changed, go on as usual
}
require_once './inc/runtime.php';
require_once './inc/functions_sql.php';
require_once './language/'.$config['language'].'/lang_help.php';
require_once './language/'.$config['language'].'/lang_config_overview.php';
require_once './language/'.$config['language'].'/lang_sql.php';

$msg = '';
$sel = $_POST['sel'] ?? 'db';

if (isset($_GET['sel'])) {
    $sel = $_GET['sel'];
}

$old_config_file = $config['config_file'];
if (isset($_GET['config'])) {
    unset($databases);
    $databases = [];
    if (isset($_POST['save'])) {
        unset($_POST['save']);
    }
    if (read_config($_GET['config'])) {
        $config['config_file'] = $_GET['config'];
        $_SESSION['config_file'] = $config['config_file'];
        $msg = '<strong>'.sprintf($lang['L_CONFIG_LOADED'], $config['config_file']).'</strong>';
        $msg .= '<script>parent.MyOOS_Dumper_menu.location.href="menu.php?config='.$config['config_file'].'";</script>';
    } else {
        read_config($old_config_file);
        $msg = '<p class="error">'.sprintf($lang['L_ERROR_LOADING_CONFIGFILE'], $config['config_file']).'</p>';
    }
}

if (isset($_GET['config_delete'])) {
    $del_config = urldecode((string) $_GET['config_delete']);
    if ($del_config == $config['config_file']) {
        // currently selected configuration was deleted
        $config['config_file'] = 'myoosdumper';
        $_SESSION['config_file'] = $config['config_file'];
        read_config($config['config_file']); // Load standard
    }

    $del = @unlink($config['paths']['config'].$del_config.'.php');
    if ($del) {
        $del = @unlink($config['paths']['config'].$del_config.'.conf.php');
    }
    if (false === $del) {
        $msg = '<p class="error">'.sprintf($lang['L_ERROR_DELETING_CONFIGFILE'], $del_config).'</p>';
    } else {
        $msg = '<p class="success">'.sprintf($lang['L_SUCCESS_DELETING_CONFIGFILE'], $del_config).'</p>'.'<script>parent.MyOOS_Dumper_menu.location.href="menu.php?config='.$config['config_file'].'";</script>'; //refresh menu-frame
    }
    $sel = 'configs';
}

require_once './inc/define_icons.php';

$config['files']['parameter'] = $config['paths']['config'].$config['config_file'].'.php';
$config['theme'] = (!isset($config['theme'])) ? 'mod' : $config['theme'];
$config['cron_smtp_port'] = (!isset($config['cron_smtp_port'])) ? 25 : $config['cron_smtp_port'];

$config['other_smtp_encryption'] = (!isset($config['other_smtp_encryption'])) ? 2 : $config['other_smtp_encryption'];
$config['other_smtp_port'] = (!isset($config['other_smtp_port'])) ? 587 : $config['other_smtp_port'];



if (!isset($command)) {
    $command = 0;
}

$checkFTP = [
                '&nbsp;<br><br>&nbsp;<br>&nbsp;',
                '&nbsp;<br><br>&nbsp;<br>&nbsp;',
                '&nbsp;<br><br>&nbsp;<br>&nbsp;',
];
$checkFTP[$i] = '';
$ftptested = -1;

if ((isset($_POST['testFTP0'])) || (isset($_POST['testFTP1'])) || (isset($_POST['testFTP2']))) {
    $config['ftp_transfer'] = [];
    $config['ftp_timeout'] = [];
    $config['ftp_mode'] = [];
    $config['ftp_useSSL'] = [];

    for ($i = 0; $i < 3; ++$i) {
        $config['ftp_transfer'][$i] = $_POST['ftp_transfer'][$i] ?? 0;
        $config['ftp_timeout'][$i] = $_POST['ftp_timeout'][$i] ?? 30;
        $config['ftp_useSSL'][$i] = $_POST['ftp_useSSL'][$i] ?? 0;
        $config['ftp_mode'][$i] = (isset($_POST['ftp_mode'][$i])) ? 1 : 0;
        $config['ftp_server'][$i] = $_POST['ftp_server'][$i] ?? '';
        $config['ftp_port'][$i] = $_POST['ftp_port'][$i] ?? 21;
        $config['ftp_user'][$i] = $_POST['ftp_user'][$i] ?? '';
        $config['ftp_pass'][$i] = $_POST['ftp_pass'][$i] ?? '';
        $config['ftp_dir'][$i] = (isset($_POST['ftp_dir'][$i])) ? stripslashes((string) $_POST['ftp_dir'][$i]) : '/';
        if ('' == $config['ftp_dir'][$i] || (strlen($config['ftp_dir'][$i] ?? '') > 1 && !str_ends_with($config['ftp_dir'][$i], '/'))) {
            $config['ftp_dir'][$i] .= '/';
        }
        if (isset($_POST['testFTP'.$i])) {
            $checkFTP[$i] = '<div class="ssmall">'.$lang['L_TESTCONNECTION'].' FTP-Connection '.($i + 1).'<br><br>'.TesteFTP($i).'</div>';
            $ftptested = $i;
        }
    }
}

// SFTP
$checkSFTP = [
                '&nbsp;<br><br>&nbsp;<br>&nbsp;',
                '&nbsp;<br><br>&nbsp;<br>&nbsp;',
                '&nbsp;<br><br>&nbsp;<br>&nbsp;',
];
$checkSFTP[$i] = '';
$sftptested = -1;
if ((isset($_POST['testSFTP0'])) || (isset($_POST['testSFTP1'])) || (isset($_POST['testSFTP2']))) {
    $config['sftp_transfer'] = [];

    $config['sftp_timeout'] = [];

    for ($i = 0; $i < 3; ++$i) {
        $config['sftp_transfer'][$i] = $_POST['sftp_transfer'][$i] ?? 0;
        $config['sftp_timeout'][$i] = $_POST['sftp_timeout'][$i] ?? 30;
        $config['sftp_server'][$i] = $_POST['sftp_server'][$i] ?? '';
        $config['sftp_port'][$i] = $_POST['sftp_port'][$i] ?? 22;
        $config['sftp_user'][$i] = $_POST['sftp_user'][$i] ?? '';
        $config['sftp_pass'][$i] = $_POST['sftp_pass'][$i] ?? '';
        $config['sftp_dir'][$i] = (isset($_POST['sftp_dir'][$i])) ? stripslashes((string) $_POST['sftp_dir'][$i]) : '/';

        $config['sftp_path_to_private_key'][$i] = (isset($_POST['sftp_path_to_private_key'][$i])) ? stripslashes((string) $_POST['sftp_path_to_private_key'][$i]) : null;
        $config['sftp_secret_passphrase_for_private_key'][$i] = (isset($_POST['sftp_secret_passphrase_for_private_key'][$i])) ? stripslashes((string) $_POST['sftp_secret_passphrase_for_private_key'][$i]) : null;
        $config['sftp_fingerprint'][$i] = (isset($_POST['sftp_fingerprint'][$i])) ? stripslashes((string) $_POST['sftp_fingerprint'][$i]) : null;

        if ('' == $config['sftp_dir'][$i] || (strlen($config['sftp_dir'][$i] ?? '') > 1 && !str_ends_with((string) $config['sftp_dir'][$i], '/'))) {
            $config['sftp_dir'][$i] .= '/';
        }

        if (isset($_POST['testSFTP'.$i])) {
            $checkSFTP[$i] = '<div class="ssmall">'.$lang['L_TESTCONNECTION'].' SFTP-Connection '.($i + 1).'<br><br>'.TesteSFTP($i).'</div>';
            $sftptested = $i;
        }
    }
}

$showVP = false;
$oldtheme = $config['theme'] ?? 'mod';
$oldscposition = $config['interface_server_caption_position'] ?? '';

if ($ftptested > -1) {
    $ftp_server[$ftptested] = $_POST['ftp_server'][$ftptested];
    $ftp_port[$ftptested] = $_POST['ftp_port'][$ftptested];
    $ftp_user[$ftptested] = $_POST['ftp_user'][$ftptested];
    $ftp_pass[$ftptested] = $_POST['ftp_pass'][$ftptested];
    $ftp_dir_s = 'ftp_dir['.$ftptested.']';
    $f = $_POST['ftp_dir'];
    $ftp_dir[$ftptested] = stripslashes((string) $f[$ftptested]);
    // Remember inputs
    $config['ftp_transfer'][$ftptested] = $_POST['ftp_transfer'][$ftptested] ?? 0;
    $config['sftp'][$ftptested] = $_POST['sftp'][$ftptested] ?? 0;
    $config['ftp_timeout'][$ftptested] = $_POST['ftp_timeout'][$ftptested] ?? 30;
    $config['ftp_useSSL'][$ftptested] = $_POST['ftp_useSSL'][$ftptested] ?? 0;
    $config['ftp_mode'][$ftptested] = (isset($_POST['ftp_mode'][$ftptested])) ? 1 : 0;
    $config['ftp_server'][$ftptested] = $ftp_server[$ftptested];
    $config['ftp_port'][$ftptested] = $ftp_port[$ftptested];
    $config['ftp_user'][$ftptested] = $ftp_user[$ftptested];
    $config['ftp_pass'][$ftptested] = $ftp_pass[$ftptested];
    $config['ftp_dir'][$ftptested] = $ftp_dir[$ftptested];

    if ('' == $ftp_dir[$ftptested] || (strlen($ftp_dir[$ftptested] ?? '') > 1 && !str_ends_with($ftp_dir[$ftptested], '/'))) {
        $ftp_dir[$ftptested] .= '/';
    }
    WriteParams();
}

if ($sftptested > -1) {
    $sftp_server[$sftptested] = $_POST['sftp_server'][$sftptested];
    $sftp_port[$sftptested] = $_POST['sftp_port'][$sftptested];
    $sftp_user[$sftptested] = $_POST['sftp_user'][$sftptested];
    $sftp_pass[$sftptested] = $_POST['sftp_pass'][$sftptested];
    $sftp_dir_s = 'sftp_dir['.$sftptested.']';
    $f = $_POST['sftp_dir'];
    $sftp_dir[$sftptested] = stripslashes((string) $f[$sftptested]);
    if ('' == $sftp_dir[$sftptested] || (strlen($sftp_dir[$sftptested] ?? '') > 1 && !str_ends_with($sftp_dir[$sftptested], '/'))) {
        $sftp_dir[$sftptested] .= '/';
    }

    $sftp_path_to_private_key[$sftptested] = stripslashes((string) $_POST['sftp_path_to_private_key'][$sftptested]);
    $sftp_secret_passphrase_for_private_key[$sftptested] = stripslashes((string) $_POST['sftp_secret_passphrase_for_private_key'][$sftptested]);
    $sftp_fingerprint[$sftptested] = stripslashes((string) $_POST['sftp_fingerprint'][$sftptested]);

    // Remember inputs
    $config['sftp_transfer'][$sftptested] = $_POST['sftp_transfer'][$sftptested] ?? 0;
    $config['sftp_timeout'][$sftptested] = $_POST['sftp_timeout'][$sftptested] ?? 30;
    $config['sftp_server'][$sftptested] = $sftp_server[$sftptested];
    $config['sftp_port'][$sftptested] = $sftp_port[$sftptested];
    $config['sftp_user'][$sftptested] = $sftp_user[$sftptested];
    $config['sftp_pass'][$sftptested] = $sftp_pass[$sftptested];
    $config['sftp_dir'][$sftptested] = $sftp_dir[$sftptested];
    $config['sftp_path_to_private_key'][$sftptested] = $sftp_path_to_private_key[$sftptested];
    $config['sftp_secret_passphrase_for_private_key'][$sftptested] = $sftp_secret_passphrase_for_private_key[$sftptested];
    $config['sftp_fingerprint'][$sftptested] = $sftp_fingerprint[$sftptested];

    WriteParams();
}

echo MODHeader();

if (isset($_POST['load'])) {
    $msg = SetDefault(true);
    $msg = nl2br((string) $msg).'<br>'.$lang['L_LOAD_SUCCESS'].'<br>';
    echo '<script>parent.MyOOS_Dumper_menu.location.href="menu.php";</script>';
}

if (isset($_POST['save'])) {
    $save_config = true;
    //Read parameters
    $config['multi_dump'] = $_POST['MultiDBDump'] ?? 0;
    $config['compression'] = $_POST['compression'] ?? 0;
    $config['language'] = $_POST['language'];
    $config['interface_server_caption'] = $_POST['server_caption'] ?? 0;
    $config['interface_server_caption_position'] = $_POST['server_caption_position'] ?? 0;
    $config['interface_sqlboxsize'] = $_POST['sqlboxsize'];
    $config['theme'] = $_POST['theme'];
    $config['interface_table_compact'] = $_POST['interface_table_compact'] ?? 1;

    // if (isset($_POST['selected_config'])) $new_config = $_POST['selected_config'];

    if (isset($_POST['email0'])) {
        $config['email_recipient'] = $_POST['email0'];
    }
    if (isset($_POST['email_recipient_cc'])) {
        $config['email_recipient_cc'] = $_POST['email_recipient_cc'];
    }
    if (isset($_POST['email1'])) {
        $config['email_sender'] = $_POST['email1'];
    }
    $config['send_mail'] = $_POST['send_mail'] ?? 0;
    $config['send_mail_dump'] = $_POST['send_mail_dump'] ?? 0;

    if (isset($_POST['email_maxsize1'])) {
        $config['email_maxsize1'] = $_POST['email_maxsize1'];
    }
    if ('' == $config['email_maxsize1']) {
        $config['email_maxsize1'] = 0;
    }
    if (isset($_POST['email_maxsize2'])) {
        $config['email_maxsize2'] = $_POST['email_maxsize2'];
    }
    $config['email_maxsize'] = $config['email_maxsize1'] * ((1 == $config['email_maxsize2']) ? 1024 : 1024 * 1024);

    if (isset($_POST['memory_limit'])) {
        $config['memory_limit'] = $_POST['memory_limit'];
    }
    if ('' == $config['memory_limit']) {
        $config['memory_limit'] = 0;
    }
    if (isset($_POST['minspeed'])) {
        $config['minspeed'] = $_POST['minspeed'];
    }
    if ($config['minspeed'] < 5) {
        $config['minspeed'] = 5;
    }
    if (isset($_POST['maxspeed'])) {
        $config['maxspeed'] = $_POST['maxspeed'];
    }
    if ($config['maxspeed'] < $config['minspeed']) {
        $config['maxspeed'] = $config['minspeed'] * 2;
    }
    if (isset($_POST['stop_with_error'])) {
        $config['stop_with_error'] = $_POST['stop_with_error'];
    }
    

    $config['ignore_enable_keys'] = isset($_POST['ignore_enable_keys']) ? (int) $_POST['ignore_enable_keys'] : 0;
    $config['update_core'] = isset($_POST['update_core']) ? (int) $_POST['update_core'] : 0;


    $config['multi_part'] = $_POST['multi_part'] ?? 0;
    if (1 == $config['multi_part']) {
        $config['multipartgroesse1'] = isset($_POST['multipartgroesse1']) ? floatval(str_replace(',', '.', (string) $_POST['multipartgroesse1'])) : 0;
        $config['multipartgroesse2'] = isset($_POST['multipartgroesse2']) ? intval($_POST['multipartgroesse2']) : 0;
    }
    if ($config['multipartgroesse1'] < 100 && 1 == $config['multipartgroesse2']) {
        $config['multipartgroesse1'] = 100;
    }
    if ($config['multipartgroesse1'] < 1 && 2 == $config['multipartgroesse2']) {
        $config['multipartgroesse1'] = 1;
    }

    $config['logcompression'] ??= 0;
    $oldlogcompression = $config['logcompression'];
    $config['logcompression'] = (isset($_POST['logcompression']) && 1 == $_POST['logcompression']) ? 1 : 0;
    if (isset($_POST['log_maxsize1'])) {
        $config['log_maxsize1'] = $_POST['log_maxsize1'];
    }
    if ('' == $config['log_maxsize1']) {
        $config['log_maxsize1'] = 0;
    }
    if (isset($_POST['log_maxsize2'])) {
        $config['log_maxsize2'] = $_POST['log_maxsize2'];
    }
    $config['log_maxsize'] = $config['log_maxsize1'] * ((1 == $config['log_maxsize2']) ? 1024 : 1024 * 1024);

    $config['auto_delete'] = $_POST['auto_delete'] ?? 0;
    if (isset($_POST['max_backup_files'])) {
        $config['max_backup_files'] = $_POST['max_backup_files'];
    }

    $config['empty_db_before_restore'] = $_POST['empty_db_before_restore'] ?? 0;
    $config['optimize_tables_beforedump'] = $_POST['optimize_tables'] ?? 0;
    $config['use_binary_container'] = $_POST['binary_container'] ?? 0;
    if (isset($_POST['cron_dbindex'])) {
        $config['cron_dbindex'] = $_POST['cron_dbindex'];
    }
    if (isset($_POST['cron_comment'])) {
        $config['cron_comment'] = $_POST['cron_comment'];
    }

    if (isset($_POST['cron_extender'])) {
        $config['cron_extender'] = $_POST['cron_extender'];
    }
    // cron_select_savepath/
    if (!isset($_POST['cron_select_savepath'])) {
        $_POST['cron_select_savepath'] = $config['config_file'];
    }
    if (isset($_POST['cron_savepath_new']) && !empty($_POST['cron_savepath_new'])) {
        $tmp_configfilename = mb_convert_encoding(trim((string) $_POST['cron_savepath_new']), 'ISO-8859-1');
        if (!preg_match('/^[a-z.-_]+$/i', $tmp_configfilename, $matches)) {
            $save_config = false;
            $msg .= '<p class="error">'.sprintf($lang['L_ERROR_CONFIGFILE_NAME'], $_POST['cron_savepath_new']).'</p>';
        } else {
            $config['config_file'] = $_POST['cron_savepath_new'];
            $config['cron_configurationfile'] = $_POST['cron_savepath_new'].'.conf.php';
        }
    }

    if (isset($_POST['cron_execution_path'])) {
        $config['cron_execution_path'] = $_POST['cron_execution_path'];
    }
    if ('' == $config['cron_execution_path']) {
        $config['cron_execution_path'] = 'mod_cron/';
    }
    if (strlen($config['cron_execution_path'] ?? '') > 1 && !str_ends_with((string) $config['cron_execution_path'], '/')) {
        $config['cron_execution_path'] .= '/';
    }

    if (isset($_POST['cron_use_mail'])) {
        $config['cron_use_mail'] = $_POST['cron_use_mail'];
    }
    if (isset($_POST['cron_sendmail'])) {
        $config['cron_sendmail'] = $_POST['cron_sendmail'];
    }
    $config['cron_smtp'] = $_POST['cron_smtp'] ?? 'localhost';


    $config['other_smtp_host'] = $_POST['other_smtp_host'] ?? '';
    $config['other_smtp_encryption'] = $_POST['other_smtp_encryption'] ?? '2';
    $config['other_smtp_host'] = $_POST['other_smtp_host'] ?? '';
    $config['other_smtp_port'] = $_POST['other_smtp_port'] ?? '587';
    $config['other_smtp_username'] = $_POST['other_smtp_username'] ?? '';
    $config['other_smtp_password'] = $_POST['other_smtp_password'] ?? '';
    $config['other_smtp_auth'] = $_POST['other_smtp_auth'] ?? '';

    $config['cron_printout'] = $_POST['cron_printout'] ?? 0;
    $config['cron_completelog'] = $_POST['cron_completelog'] ?? 0;
    $config['cron_compression'] = $_POST['compression'] ?? 0;
    if (isset($_POST['cron_completelog'])) {
        $config['cron_completelog'] = $_POST['cron_completelog'];
    }

    $databases['multi'] = [];
    $databases['multi_praefix'] = [];
    $databases['multi_commandbeforedump'] = [];
    $databases['multi_commandafterdump'] = [];

    if (isset($databases['Name'][0]) && $databases['Name'][0] > '') {
        for ($i = 0; $i < (is_countable($databases['Name']) ? count($databases['Name']) : 0); ++$i) {
            $databases['praefix'][$i] = $_POST['dbpraefix_'.$i] ?? '';
            $databases['command_before_dump'][$i] = (!isset($_POST['command_before_'.$i])) ? '' : $_POST['command_before_'.$i];
            $databases['command_after_dump'][$i] = (!isset($_POST['command_after_'.$i])) ? '' : $_POST['command_after_'.$i];
            if (isset($_POST['db_multidump_'.$i]) && $_POST['db_multidump_'.$i] == "db_multidump_$i") {
                $databases['multi'][] = $databases['Name'][$i];
                $databases['multi_praefix'][] = $databases['praefix'][$i];
                $databases['multi_commandbeforedump'][] = $databases['command_before_dump'][$i];
                $databases['multi_commandafterdump'][] = $databases['command_after_dump'][$i];
            }
        }
    }
    $databases['multisetting'] = ((is_countable($databases['multi']) ? count($databases['multi']) : 0) > 0) ? implode(';', $databases['multi']) : '';
    $databases['multisetting_praefix'] = ((is_countable($databases['multi']) ? count($databases['multi']) : 0) > 0) ? implode(';', $databases['multi_praefix']) : '';
    $databases['multisetting_commandbeforedump'] = ((is_countable($databases['multi']) ? count($databases['multi']) : 0) > 0) ? implode(';', $databases['multi_commandbeforedump']) : '';
    $databases['multisetting_commandafterdump'] = ((is_countable($databases['multi']) ? count($databases['multi']) : 0) > 0) ? implode(';', $databases['multi_commandafterdump']) : '';


    if (-2 == $config['cron_dbindex']) {
        $datenbanken = is_countable($databases['Name']) ? count($databases['Name']) : 0;
        $cron_db_array = str_replace(';', '|', (string) $databases['multisetting']);
        $cron_dbpraefix_array = str_replace(';', '|', (string) $databases['multisetting_praefix']);
        $cron_db_cbd_array = str_replace(';', '|', (string) $databases['multisetting_commandbeforedump']);
        $cron_db_cad_array = str_replace(';', '|', $databases['multisetting_commandafterdump']);
    } elseif (-3 == $config['cron_dbindex']) {
        $cron_db_array = implode('|', $databases['Name']);
        $cron_dbpraefix_array = implode('|', $databases['praefix']);
        $cron_db_cbd_array = isset($databases['command_before_dump']) && !empty($databases['command_before_dump']) ? implode('|', $databases['command_before_dump']) : '';
        $cron_db_cad_array = isset($databases['command_after_dump']) && !empty($databases['command_after_dump']) ? implode('|', $databases['command_after_dump']) : '';
    }

    $config['ftp_transfer'] = [];
    $config['ftp_timeout'] = [];
    $config['ftp_mode'] = [];
    $config['ftp_useSSL'] = [];

    $config['ftp_server'] = [];
    $config['ftp_port'] = [];
    $config['ftp_user'] = [];
    $config['ftp_pass'] = [];
    $config['ftp_dir'] = [];

    for ($i = 0; $i < 3; ++$i) {
        $checkFTP[$i] = '';
        $config['ftp_transfer'][$i] = $_POST['ftp_transfer'][$i] ?? 0;
        $config['ftp_timeout'][$i] = $_POST['ftp_timeout'][$i] ?? 30;
        $config['ftp_useSSL'][$i] = isset($_POST['ftp_useSSL'][$i]) ? 1 : 0;

        $config['ftp_mode'][$i] = isset($_POST['ftp_mode'][$i]) ? 1 : 0;
        $config['ftp_server'][$i] = $_POST['ftp_server'][$i] ?? '';
        $config['ftp_port'][$i] = $_POST['ftp_port'][$i] ?? 0;
        $config['ftp_user'][$i] = $_POST['ftp_user'][$i] ?? '';
        $config['ftp_pass'][$i] = $_POST['ftp_pass'][$i] ?? '';
        $config['ftp_dir'][$i] = isset($_POST['ftp_dir'][$i]) ? stripslashes((string) $_POST['ftp_dir'][$i]) : '';
        if (0 == $config['ftp_port'][$i]) {
            $config['ftp_port'][$i] = 21;
        }
        if ('' == $config['ftp_dir'][$i] || (strlen($config['ftp_dir'][$i] ?? '') > 1 && !str_ends_with((string) $config['ftp_dir'][$i], '/'))) {
            $config['ftp_dir'][$i] .= '/';
        }
    }

    $config['sftp_transfer'] = [];
    $config['sftp_timeout'] = [];

    $config['sftp_server'] = [];
    $config['sftp_port'] = [];
    $config['sftp_user'] = [];
    $config['sftp_pass'] = [];
    $config['sftp_dir'] = [];

    $config['sftp_path_to_private_key'] = [];
    $config['sftp_secret_passphrase_for_private_key'] = [];
    $config['sftp_fingerprint'] = [];

    for ($i = 0; $i < 3; ++$i) {
        $checkFTP[$i] = '';
        $config['sftp_transfer'][$i] = $_POST['sftp_transfer'][$i] ?? 0;
        $config['sftp_timeout'][$i] = $_POST['sftp_timeout'][$i] ?? 30;

        $config['sftp_server'][$i] = $_POST['sftp_server'][$i] ?? '';
        $config['sftp_port'][$i] = $_POST['sftp_port'][$i] ?? 0;
        $config['sftp_user'][$i] = $_POST['sftp_user'][$i] ?? '';
        $config['sftp_pass'][$i] = $_POST['sftp_pass'][$i] ?? '';
        $config['sftp_dir'][$i] = isset($_POST['sftp_dir'][$i]) ? stripslashes((string) $_POST['sftp_dir'][$i]) : '';

        $config['sftp_path_to_private_key'][$i] = (isset($_POST['sftp_path_to_private_key'][$i])) ? stripslashes((string) $_POST['sftp_path_to_private_key'][$i]) : null;
        $config['sftp_secret_passphrase_for_private_key'][$i] = (isset($_POST['sftp_secret_passphrase_for_private_key'][$i])) ? stripslashes((string) $_POST['sftp_secret_passphrase_for_private_key'][$i]) : null;
        $config['sftp_fingerprint'][$i] = (isset($_POST['sftp_fingerprint'][$i])) ? stripslashes((string) $_POST['sftp_fingerprint'][$i]) : null;

        if (0 == $config['sftp_port'][$i]) {
            $config['sftp_port'][$i] = 22;
        }
        if ('' == $config['sftp_dir'][$i] || (strlen($config['sftp_dir'][$i] ?? '') > 1 && !str_ends_with((string) $config['sftp_dir'][$i], '/'))) {
            $config['sftp_dir'][$i] .= '/';
        }
    }

    $config['bb_width'] = $_POST['bb_width'];
    $config['bb_textcolor'] = $_POST['bb_textcolor'];
    $config['sql_limit'] = $_POST['sql_limit'];

    if ($config['dbhost'] != $_POST['dbhost'] || $config['dbuser'] != $_POST['dbuser'] || $config['dbpass'] != $_POST['dbpass'] || $config['dbport'] != $_POST['dbport'] || $config['dbsocket'] != $_POST['dbsocket']) {
        // new connection parameters
        $show_VP = true;

        // Save old parameters
        $old['dbhost'] = $config['dbhost'];
        $old['dbuser'] = $config['dbuser'];
        $old['dbpass'] = $config['dbpass'];
        $old['dbport'] = $config['dbport'];
        $old['dbsocket'] = $config['dbsocket'];

        //set new
        $config['dbhost'] = $_POST['dbhost'];
        $config['dbuser'] = $_POST['dbuser'];
        $config['dbpass'] = $_POST['dbpass'];
        $config['dbport'] = $_POST['dbport'];
        $config['dbsocket'] = $_POST['dbsocket'];
        if (mod_mysqli_connect()) {
            // new connection data was accepted -> manually delete DB list from other user
            SetDefault();
            $msg .= '<script>parent.MyOOS_Dumper_menu.location.href="menu.php";</script>';
        } else {
            // Get old values
            $config['dbhost'] = $old['dbhost'];
            $config['dbuser'] = $old['dbuser'];
            $config['dbpass'] = $old['dbpass'];
            $config['dbport'] = $old['dbport'];
            $config['dbsocket'] = $old['dbsocket'];
            $msg .= '<p class="error">'.$lang['L_WRONG_CONNECTIONPARS'].'</p>';
        }
    }

    // Manuelles hinzufügen einer Datenbank
    if ($_POST['add_db_manual'] > '') {
        $to_add = trim((string) $_POST['add_db_manual']);
        $found = false;
        // Check if the DB already exists in the list
        if (isset($databases['Name'][0])) {
            foreach ($databases['Name'] as $existing_db) {
                if ($existing_db == $to_add) {
                    $found = true;
                }
            }
        }
        if ($found) {
            $add_db_message = sprintf($lang['L_DB_IN_LIST'], $to_add);
        } else {
            if (mod_mysqli_connect()) {
                $res = mysqli_select_db($config['dbconnection'], $to_add);
                if (false === !$res) {
                    $databases['Name'][] = $to_add;
                    // Refresh menu so that the DB appears in the select list
                    echo '<script>parent.MyOOS_Dumper_menu.location.href="menu.php";</script>';
                } else {
                    $add_db_message = sprintf($lang['L_DB_MANUAL_ERROR'], $to_add);
                }
                $showVP = true;
            }
        }
    }

    // After a transfer of a new configuration remove superfluous indexes before writing
    $number_databases = sizeof($databases['Name']);
    if (sizeof($databases['praefix']) > $number_databases) {
        for ($i = sizeof($databases['praefix']); $i >= $number_databases; --$i) {
            unset($databases['praefix'][$i]);
            unset($databases['command_before_dump'][$i]);
            unset($databases['command_after_dump'][$i]);
        }
        if ($databases['db_selected_index'] >= $number_databases) {
            $databases['db_selected_index'] = 0;
        }
    }

    // and write away
    if ($save_config) {
        if (true == WriteParams(false)) {
            // new language? Then also update menu on the left
            if ($_SESSION['config']['language'] != $config['language'] || $_POST['scaption_old'] != $config['interface_server_caption'] || $oldtheme != $config['theme'] || $oldscposition != $config['interface_server_caption_position']) {
                $msg .= '<script>parent.MyOOS_Dumper_menu.location.href="menu.php?config='.urlencode((string) $config['config_file']).'";</script>';
                if (isset($_POST['cron_savepath_new']) && $_POST['cron_savepath_new'] > '') {
                    $msg .= '<p class="success">'.$lang['L_SUCCESS_CONFIGFILE_CREATED'].'</p>';
                }
            }
            // Load parameters
            read_config($config['config_file']);
            if ($config['logcompression'] != $oldlogcompression) {
                DeleteLog();
            }
            $msg .= '<p class="success">'.sprintf($lang['L_SAVE_SUCCESS'], $config['config_file']).'</p>';
            $msg .= '<script>parent.MyOOS_Dumper_menu.location.href="menu.php?config='.$config['config_file'].'";</script>';
        } else {
            $msg .= '<p class="error">'.$lang['L_SAVE_ERROR'].'</p>';
        }
    }
}

ReadSQL();
?>
<script>
    function hide_pardivs() {
        document.getElementById("db").style.display = 'none';
        document.getElementById("global1").style.display = 'none';
        document.getElementById("global2").style.display = 'none';
        document.getElementById("global3").style.display = 'none';
        document.getElementById("transfer1").style.display = 'none';
        document.getElementById("transfer2").style.display = 'none';
        document.getElementById("transfer3").style.display = 'none';
        document.getElementById("cron").style.display = 'none';
        document.getElementById("configs").style.display = 'none';
        for(i = 0; i < 8; i++) {
            document.getElementById("command"+i).className  ='ConfigButton';
        }
    }

    function SwitchVP(objid) {
        if (!document.getElementById(objid)) objid = 'VP';
        if(document.getElementById(objid).style.display == 'none')
            document.getElementById(objid).style.display = 'block';
        else
            document.getElementById(objid).style.display = 'none'
    }

    function show_pardivs(lab) {
        hide_pardivs();
        switch(lab) {
                case "db":
                        document.getElementById("db").style.display = 'block';
                        document.getElementById("command1").className ='ConfigButtonSelected';
                        break;
                case "global1":
                        document.getElementById("global1").style.display = 'block';
                        document.getElementById("command2").className ='ConfigButtonSelected';
                        break;
                case "global2":
                        document.getElementById("global3").style.display = 'block';
                        document.getElementById("command3").className ='ConfigButtonSelected';
                        break;
                case "global3":
                        document.getElementById("global2").style.display = 'block';
                        document.getElementById("command4").className ='ConfigButtonSelected';
                        break;
                case "transfer1":
                        document.getElementById("transfer1").style.display = 'block';
                        document.getElementById("command5").className ='ConfigButtonSelected';
                        break;
                case "transfer2":
                        document.getElementById("transfer2").style.display = 'block';
                        document.getElementById("command6").className ='ConfigButtonSelected';
                        break;
                case "transfer3":
                        document.getElementById("transfer3").style.display = 'block';
                        document.getElementById("command11").className ='ConfigButtonSelected';
                        break;            
            
            
                case "cron":
                        document.getElementById("cron").style.display = 'block';
                        document.getElementById("command7").className ='ConfigButtonSelected';
                        break;
                case "configs":
                        document.getElementById("configs").style.display = 'block';
                        document.getElementById("command0").className ='ConfigButtonSelected';
                        break;
                case "all":
                        document.getElementById("db").style.display = 'block';
                        document.getElementById("global1").style.display = 'block';
                        document.getElementById("global2").style.display = 'block';
                        document.getElementById("global3").style.display = 'block';
                        document.getElementById("transfer1").style.display = 'block';
                        document.getElementById("transfer2").style.display = 'block';
                        document.getElementById("transfer3").style.display = 'block';
                        document.getElementById("cron").style.display = 'block';
                        document.getElementById("configs").style.display = 'block';
                        document.getElementById("command8").className ='ConfigButtonSelected';
                        break;
                default:
                        document.getElementById("db").style.display = 'block';
                        document.getElementById("command1").className ='ConfigButtonSelected';
                        break;
            }
            document.getElementById("sel").value=lab;
    }

    function WriteMem() {
        document.getElementById("mlimit").value=<?php
        echo round($config['php_ram'] * 1024 * 1024 * 0.9, 0);
        ?>;
    }
</script>
<?php
if (!isset($config['email_maxsize1'])) {
            $config['email_maxsize1'] = 0;
}
if (!isset($config['email_maxsize2'])) {
    $config['email_maxsize2'] = 1;
}
if (!isset($databases['multisetting'])) {
    $databases['multisetting'] = '';
}
$databases['multi'] = explode(';', (string) $databases['multisetting']);

// Output parts
$aus['formstart'] = headline($lang['L_CONFIG_HEADLINE'].': '.$config['config_file']);
$aus['formstart'] .= '<form name="frm_config" method="POST" action="config_overview.php"><input type="hidden" name="sel" id="sel" value="db">'.$nl;
$aus['formstart'] .= '<div id="configleft">';
$aus['formstart'] .= '<input type="Button" id="command1" onclick="show_pardivs(\'db\');" value="'.$lang['L_DBS'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command2" onclick="show_pardivs(\'global1\');" value="'.$lang['L_GENERAL'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command3" onclick="show_pardivs(\'global2\');" value="'.$lang['L_CONFIG_INTERFACE'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command4" onclick="show_pardivs(\'global3\');" value="'.$lang['L_CONFIG_AUTODELETE'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command5" onclick="show_pardivs(\'transfer1\');" value="'.$lang['L_CONFIG_EMAIL'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command6" onclick="show_pardivs(\'transfer2\');" value="'.$lang['L_FTP'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command11" onclick="show_pardivs(\'transfer3\');" value="'.$lang['L_SFTP'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command7" onclick="show_pardivs(\'cron\');" value="'.$lang['L_CONFIG_CRONSCRIPT'].'" class="ConfigButton"><br>'.$nl;
$aus['formstart'] .= '<input type="Button" id="command0" onclick="show_pardivs(\'configs\');" value="'.$lang['L_CONFIGFILES'].'" class="ConfigButton"><br>'.$nl;
//$aus['formstart'] .= '<input type="Button" id="command8" onclick="show_pardivs(\'all\');" value="'.$lang['L_ALLPARS'].'" class="ConfigButton"><br>'.$nl;

//$aus['formstart'] .= '<input class="Formbutton" type="reset" name="reset" value="'.$lang['L_RESET'].'">';
$aus['formstart'] .= '<br><input class="Formbutton" type="submit" name="save" value="'.$lang['L_SAVE'].'"><br><br>'.$nl;
$aus['formstart'] .= '<input class="Formbutton" type="Submit" name="load" value="'.$lang['L_LOAD'].'" onclick="if (!confirm(\''.$lang['L_CONFIG_ASKLOAD'].'\')) return false;">'.$nl;
//$aus['formstart'] .= '<input class="Formbutton" type="button" value="'.$lang['L_INSTALL'].'" onclick="parent.location.href=\'install.php\'">'.$nl;
$aus['formstart'] .= '</div><div id="configright">'.$msg.$nl;

// Configuration files
$aus['conf'] = '<div id="configs"><fieldset><legend>'.$lang['L_CONFIGFILES'].'</legend>'.$nl.$nl;

$aus['conf'] .= '<table><tr class="dbrow">';
$aus['conf'] .= '<td style="vertical-align:middle">'.$lang['L_CREATE_CONFIGFILE'].':</td>';
$aus['conf'] .= '<td style="vertical-align:middle"><input type="text" class="text" style="width:300px;" name="cron_savepath_new" value=""></td>';
$aus['conf'] .= '<td colspan="2">'.print_save_button().'</td>';
$aus['conf'] .= '</tr></table>';

$aus['conf'] .= '<br><table class="bdr"><tr class="thead"><th>#</th><th>'.$lang['L_CONFIGFILE'].' / '.$lang['L_MYSQL_DATA'].'</th>';
$aus['conf'] .= '<th>'.$lang['L_CONFIGURATIONS'].'</th><th>'.$lang['L_ACTION'].'</th></tr>';

$i = 0;
$old_config = $config;
$configs = get_config_filenames();

if (sizeof($configs) > 0) {
    foreach ($configs as $c) {
        ++$i;
        unset($databases);
        read_config($c);
        $aus['conf'] .= '<tr class="';
        if ($old_config['config_file'] == $c) {
            $aus['conf'] .= 'dbrowsel';
        } else {
            $aus['conf'] .= ($i % 2) ? 'dbrow' : 'dbrow1';
        }
        $aus['conf'] .= '">';

        $aus['conf'] .= '<td><a name="config'.sprintf('%03d', $i).'" style="text-decoration:none;">'.$i.'.</a></td>';

        // Settings
        $aus['conf'] .= '<td>';

        $aus['conf'] .= '<table>';
        $aus['conf'] .= '<tr><td>'.$lang['L_NAME'].':</td><td><strong>'.$c.'</strong></td></tr>'; // filename

        $aus['conf'] .= '<tr><td>'.$lang['L_DB_HOST'].':</td><td><strong>'.$config['dbhost'].'</strong></td></tr>';
        $aus['conf'] .= '<tr><td>'.$lang['L_DB_USER'].':</td><td><strong>'.$config['dbuser'].'</strong></td></tr>';
        $aus['conf'] .= '<tr><td>';

        $aus['conf'] .= $lang['L_DBS'].':</td><td>';
        $aus['conf'] .= '<a href="#config'.sprintf('%03d', $i).'" onclick="SwitchVP(\'show_db'.sprintf('%03d', $i).'\');">';
        $aus['conf'] .= $icon['search'].'<strong>'.sizeof($databases['Name']).'</strong></a>';
        $aus['conf'] .= '</td></tr>';

        // Show database list
        $aus['conf'] .= '<tr><td colspan="2">';
        $aus['conf'] .= '<div id="show_db'.sprintf('%03d', $i).'" style="padding:0;margin:0;display:none;">';
        $a = 1;
        $aus['conf'] .= '<table  class="bdr">';
        if (isset($databases['Name'])) {
            foreach ($databases['Name'] as $d) {
                $aus['conf'] .= '<tr class="'.(($a % 2) ? 'dbrow' : 'dbrow1').'"><td style="text-align:right;">';
                $aus['conf'] .= $a.'.&nbsp;</td><td>';
                $aus['conf'] .= '<a href="sql.php?db='.urlencode((string) $d).'">';
                $aus['conf'] .= $d.'</a></td></tr>';
                ++$a;
            }
        }
        $aus['conf'] .= '</table></div></td></tr>';

        $aus['conf'] .= '</table></td>';

        $aus['conf'] .= '<td><table>';

        // Build string from multidump DBs
        $toolboxstring = '';
        $databases['multi'] = [];
        if (isset($databases['multisetting'])) {
            $databases['multi'] = explode(';', (string) $databases['multisetting']);
        }
        $multi_praefixe = [];
        if (isset($databases['multisetting_praefix'])) {
            $multi_praefixe = explode(';', (string) $databases['multisetting_praefix']);
        }
        if (is_array($databases['multi'])) {
            for ($x = 0; $x < sizeof($databases['multi']); ++$x) {
                if ($x > 0) {
                    $toolboxstring .= ', ';
                }
                $toolboxstring .= $databases['multi'][$x];
                if (isset($multi_praefixe[$x]) && $multi_praefixe[$x] > '') {
                    $toolboxstring .= ' (<i>\''.$multi_praefixe[$x].'\'</i>)';
                }
            }
        }

        // DB list for PHP
        if (isset($config['multi_dump']) && (1 == $config['multi_dump'])) { // Multidump
            $aus['conf'] .= table_output($lang['L_BACKUP_DBS_PHP'], $toolboxstring);
        } else {
            // Current DB
            $text = $databases['db_actual'] ?? '';
            if (isset($databases['db_selected_index']) && isset($databases['praefix'][$databases['db_selected_index']]) && $databases['praefix'][$databases['db_selected_index']] > '') {
                $text .= " ('<i>".$databases['praefix'][$databases['db_selected_index']]."</i>')";
            }
            $aus['conf'] .= table_output($lang['L_BACKUP_DBS_PHP'], $text);
        }

        // DB list for Perl
        // Fallback if index is not yet set from old configuration files -> save all DBs
        if (!isset($config['cron_dbindex'])) {
            $config['cron_dbindex'] = -3;
        }
        if (-2 == $config['cron_dbindex']) {
            $aus['conf'] .= table_output($lang['L_BACKUP_DBS_PERL'], $toolboxstring);
        } elseif (-3 == $config['cron_dbindex']) {
            $text = $lang['L_ALL'];
            $aus['conf'] .= table_output($lang['L_BACKUP_DBS_PERL'], $text);
        } else {
            $text = $databases['Name'][$config['cron_dbindex']] ?? '';
            if (isset($databases['praefix'][$config['cron_dbindex']]) && $databases['praefix'][$config['cron_dbindex']] > '') {
                $text .= " ('<i>".$databases['praefix'][$config['cron_dbindex']]."</i>')";
            }
            $aus['conf'] .= table_output($lang['L_BACKUP_DBS_PERL'], $text);
        }

        if (isset($config['multi_part']) && (1 == $config['multi_part'])) { // Multipart
            $aus['conf'] .= table_output($lang['L_MULTI_PART'], $lang['L_YES'].', '.$lang['L_FILESIZE'].' '.byte_output($config['multipart_groesse']));
        }

        if (isset($config['send_mail']) && (1 == $config['send_mail'])) { // Email
            $aus['conf'] .= table_output($lang['L_SEND_MAIL_FORM'], $lang['L_YES'].', '.$lang['L_EMAIL_ADRESS'].': '.$config['email_recipient']);
            if ($config['email_recipient_cc'] > '') {
                $aus['conf'] .= table_output($lang['L_EMAIL_CC'], $config['email_recipient_cc']);
            }
            $text = $lang['L_YES'].', '.$lang['L_MAX_UPLOAD_SIZE'].': ';
            $bytes = $config['email_maxsize1'] * 1024;
            if (2 == $config['email_maxsize2']) {
                $bytes = $bytes * 1024;
            }
            $text .= byte_output($bytes);
            if (1 == $config['send_mail_dump']) {
                $aus['conf'] .= table_output($lang['L_SEND_MAIL_DUMP'], $text);
            }
        }

        for ($x = 0; $x < 3; ++$x) {
            // FTP
            if (isset($config['ftp_transfer'][$x]) && $config['ftp_transfer'][$x] > 0) {
                $aus['conf'] .= table_output($lang['L_FTP'], sprintf($lang['L_FTP_SEND_TO'], $config['ftp_server'][$x], $config['ftp_dir'][$x]));
            }
            // SFTP
            if (isset($config['sftp_transfer'][$x]) && $config['sftp_transfer'][$x] > 0) {
                $aus['conf'] .= table_output($lang['L_SFTP'], sprintf($lang['L_SFTP_SEND_TO'], $config['sftp_server'][$x], $config['sftp_dir'][$x]));
            }
        }
        $aus['conf'] .= '</table></td><td>';
        $aus['conf'] .= '<a href="config_overview.php?config='.urlencode((string) $c).'">'.$icon['edit'].'</a>';

        if ('myoosdumper' != $c) { // && $old_config['config_file']!= $c)
            $aus['conf'] .= '<a href="config_overview.php?config_delete='.urlencode((string) $c).'" onclick="if(!confirm(\''.sprintf($lang['L_CONFIRM_CONFIGFILE_DELETE'], $c).'\')) return false;">'.$icon['delete'].'</a>';
        } else {
            $aus['conf'] .= '&nbsp;';
        }

        $aus['conf'] .= '</td></tr>';
    }
}

$configfile = $old_config['config_file'];
$config = $old_config;
unset($databases);
$databases = [];
read_config($configfile);

$aus['conf'] .= '</table>';
$aus['conf'] .= '</fieldset></div>'.$nl.$nl;

// Access data
$aus['db'] = '<div id="db"><fieldset><legend>'.$lang['L_CONNECTIONPARS'].'</legend>'.$nl.$nl;
$aus['db'] .= '<div id="VP" style="display:none;"';
$aus['db'] .= '><table><tr><td>Host / User / Passwort:</td>';
$aus['db'] .= '<td><input class="text" type="text" name="dbhost" value="'.$config['dbhost'].'">&nbsp;&nbsp;/&nbsp;&nbsp;';
$aus['db'] .= '<input class="text" type="text" name="dbuser" value="'.$config['dbuser'].'" size="20">&nbsp;&nbsp;/&nbsp;&nbsp;';
$aus['db'] .= '<input class="text" type="password" name="dbpass" value="'.$config['dbpass'].'" size="20"></td></tr>';
$aus['db'] .= '<tr><td colspan="2"><strong>'.$lang['L_EXTENDEDPARS'].'</strong></td></tr>';
$aus['db'] .= '<tr><td>Port / Socket:</td><td><input class="text" type="text" name="dbport" value="'.$config['dbport'].'">&nbsp;&nbsp;/&nbsp;&nbsp;';
$aus['db'] .= '<input class="text" type="text" name="dbsocket" value="'.$config['dbsocket'].'"></td></tr>';

$aus['db'] .= '<tr><td>'.$lang['L_ADD_DB_MANUALLY'].':</td>';
$aus['db'] .= '<td><input class="text" type="text" name="add_db_manual" value=""></td></tr>';

if (isset($add_db_message)) {
    $aus['db'] .= '<tr><td colspan="2" class="error">'.$add_db_message;
    $aus['db'] .= '</td></tr>';
}
$aus['db'] .= '<tr><td colspan="2">'.print_save_button().'</td></tr>';

$aus['db'] .= '</table></div><div><a class="small" href="#" onclick="SwitchVP();">'.$lang['L_FADE_IN_OUT'].'</a></div></fieldset><fieldset><legend>'.$lang['L_DB_BACKUPPARS'].'</legend>';

$aus['db'] .= '<table>';

// If databases are available
if (isset($databases['Name'][0]) && $databases['Name'][0] > '') {
    if (!isset($databases['multi']) || (!is_array($databases['multi']))) {
        $databases['multi'] = [];
    }
    if (1 == (is_countable($databases['Name']) ? count($databases['Name']) : 0)) {
        $databases['db_actual'] = $databases['Name'][0];
        $databases['db_selected_index'] = 0;
        $aus['db'] .= '<tr><td>'.Help($lang['L_HELP_DB'], 'conf1').$lang['L_LIST_DB'].'</td>';
        $aus['db'] .= '<td><strong>'.$databases['db_actual'].'</strong></td></tr>';
        $aus['db'] .= '<tr><td>'.Help($lang['L_HELP_PRAEFIX'], 'conf2').$lang['L_PRAEFIX'].'</td><td><input type="text" class="text" name="dbpraefix_'.$databases['db_selected_index'].'" size="10" value="'.$databases['praefix'][$databases['db_selected_index']].'"></td></tr>';
        $aus['db'] .= '<tr><td>'.Help($lang['L_HELP_COMMANDS'], '').'Command before Dump</td><td>'.ComboCommandDump(0, $databases['db_selected_index']).'</td></tr>';
        $aus['db'] .= '<tr><td>'.Help($lang['L_HELP_COMMANDS'], '').'Command after Dump</td><td>'.ComboCommandDump(1, $databases['db_selected_index']).'</td>';
        $aus['db'] .= '<td><a href="sql.php?context=1">'.$lang['L_SQL_BEFEHLE'].'</a></td>';
        $aus['db'] .= '</tr>';
    } else {
        $disabled = '';
        if (in_array($databases['db_actual'], $dontBackupDatabases)) {
            $disabled = ' disabled="disabled"';
        }

        $aus['db'] .= '<tr><td>'.Help($lang['L_HELP_DB'], 'conf1').$lang['L_LIST_DB'].'</td><td><input type="checkbox" class="checkbox" name="MultiDBDump" value="1" '.((isset($config['multi_dump']) && (1 == $config['multi_dump'])) ? 'CHECKED' : '').'>&nbsp;'.$lang['L_ACTIVATE_MULTIDUMP'].'</td>';
        $aus['db'] .= '<tr><td colspan="2"><table class="bdr">';
        $aus['db'] .= '<tr class="thead"><th>'.$lang['L_DB'].'</th><th>Multidump<br><span class="ssmall">(<a href="javascript:SelectMD(true,'.(is_countable($databases['Name']) ? count($databases['Name']) : 0).')" class="small">'.$lang['L_ALL'].'</a>&nbsp;<a href="javascript:SelectMD(false,'.(is_countable($databases['Name']) ? count($databases['Name']) : 0).')" class="small">'.$lang['L_NONE'].'</a>)</span></th>';
        $aus['db'] .= '<th>'.Help($lang['L_HELP_PRAEFIX'], 'conf2').$lang['L_PRAEFIX'].'</th><th>'.Help($lang['L_HELP_COMMANDS'], '', 11).'Command before Dump</th><th>'.Help($lang['L_HELP_COMMANDS'], '', 11).'Command after Dump</th><th>'.$lang['L_SQL_BEFEHLE'].'</th></tr>';

        //erst die aktuelle DB
        $aus['db'] .= '<tr class="dbrowsel"><td><strong>'.$databases['db_actual'].'</strong></td>';
        $aus['db'] .= '<td align="center"><input type="checkbox" class="checkbox" name="db_multidump_'.$databases['db_selected_index'].'" value="db_multidump_'.$databases['db_selected_index'].'" '.((in_array($databases['db_actual'], $databases['multi'])) ? 'CHECKED' : '');
        $aus['db'] .= $disabled.'></td>';
        $aus['db'] .= '<td><input type="text" class="text" name="dbpraefix_'.$databases['db_selected_index'].'" size="10" value="'
                .$databases['praefix'][$databases['db_selected_index']].'"'.$disabled.'></td>';
        $aus['db'] .= '<td>'.ComboCommandDump(0, $databases['db_selected_index'], $disabled)
               .'</td><td>'.ComboCommandDump(1, $databases['db_selected_index'], $disabled).'</td>';
        $aus['db'] .= '<td><a href="sql.php?context=1">'.$lang['L_SQL_BEFEHLE'].'</a></td>';
        $aus['db'] .= '</tr>';

        $dbacombo = $dbbcombo = '';
        $j = 0;
        for ($i = 0; $i < (is_countable($databases['Name']) ? count($databases['Name']) : 0); ++$i) {
            if ($i != $databases['db_selected_index']) {
                ++$j;
                $disabled = '';
                if (in_array($databases['Name'][$i], $dontBackupDatabases)) {
                    $disabled = ' disabled="disabled"';
                }
                if (!isset($databases['praefix'][$i])) {
                    $databases['praefix'][$i] = '';
                }
                $aus['db'] .= '<tr class="'.(($i % 2) ? 'dbrow' : 'dbrow1').'"><td>'.$databases['Name'][$i].'</td>';
                $aus['db'] .= '<td align="center"><input type="checkbox" class="checkbox" name="db_multidump_'.$i.'" value="db_multidump_'.$i.'" '.((in_array($databases['Name'][$i], $databases['multi'])) ? 'CHECKED' : '');
                $aus['db'] .= $disabled.'></td>';
                $aus['db'] .= '<td><input type="text" class="text" name="dbpraefix_'.$i.'" size="10" value="'
                        .$databases['praefix'][$i].'"';

                $aus['db'] .= $disabled.'></td><td>'.ComboCommandDump(0, $i, $disabled).'</td><td>'
                        .ComboCommandDump(1, $i, $disabled).'</td>';
                $aus['db'] .= '<td><a href="sql.php?context=1">'.$lang['L_SQL_BEFEHLE'].'</a></td>';
                $aus['db'] .= '</tr>';
            }
        }
    }
} else {
    $aus['db'] .= '<tr><td>'.$lang['L_NO_DB_FOUND'].'</td></tr>';
}
$aus['db'] .= '</table></td></tr>';
$aus['db'] .= '</table></fieldset></div>';

// sonstige Einstellungen
$aus['global1'] = '<div id="global1"><fieldset><legend>'.$lang['L_GENERAL'].'</legend><table>';

$aus['global1'] .= '<tr><td>'.Help('', '').'Logfiles:&nbsp;</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="logcompression" '.(($config['zlib']) ? '' : 'disabled').((isset($config['logcompression']) && (1 == $config['logcompression'])) ? ' checked' : '').'>&nbsp;'.$lang['L_COMPRESSED'].'<br>';
$aus['global1'] .= ''.$lang['L_MAXSIZE'].':&nbsp;&nbsp;<input type="text" class="text" name="log_maxsize1" size="3" maxlength="3" style="text-align:right;" value="'.($config['log_maxsize1'] ?? '').'">&nbsp;&nbsp;';
$aus['global1'] .= '<select name="log_maxsize2"><option value="1" '.((isset($config['log_maxsize2']) && (1 == $config['log_maxsize2'])) ? ' SELECTED' : '').'>Kilobytes</option>';
$aus['global1'] .= '<option value="2" '.((isset($config['log_maxsize2']) && (2 == $config['log_maxsize2'])) ? ' SELECTED' : '').'>Megabytes</option></select>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_MEMORYLIMIT'], '').$lang['L_MEMORY_LIMIT'].':&nbsp;&nbsp;</td>';
$aus['global1'] .= '<td><input type="text" class="text" size="10" id="mlimit" name="memory_limit" maxlength="10" style="text-align:right;font-size:11px;" value="'.($config['memory_limit'] ?? '').'"> Bytes&nbsp;&nbsp;&nbsp;<a href="#" onclick="WriteMem();" class="small">'.$lang['L_AUTODETECT'].'</a>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_SPEED'], '').$lang['L_SPEED'].':&nbsp;</td>';
$aus['global1'] .= '<td><input type="text" class="text" size="6" name="minspeed" maxlength="6" style="text-align:right;" value="'.($config['minspeed'] ?? '').'">&nbsp;'.$lang['L_TO'].'&nbsp;<input type="text" class="text" size="6" name="maxspeed" maxlength="9" style="text-align:right;" value="'.($config['maxspeed'] ?? '').'"></td></tr>';

$aus['global1'] .= '</table></fieldset><fieldset><legend>'.$lang['L_UPDATE'].'</legend><table>';

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_ZIP'], 'conf3').$lang['L_UPDATE_CORE'].':&nbsp;</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="update_core" '.((isset($config['update_core']) && (1 == $config['update_core'])) ? ' checked' : '').'>';
$aus['global1'] .= '</td></tr>';


$aus['global1'] .= '</table></fieldset><fieldset><legend>'.$lang['L_DUMP'].'</legend><table>';

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_ZIP'], 'conf3').$lang['L_GZIP'].':&nbsp;</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="compression" '.((isset($config['zlib']) && (1 == $config['zlib'])) ? '' : 'disabled').((isset($config['compression']) && (1 == $config['compression'])) ? ' checked' : '').'>';
$aus['global1'] .= '</td></tr>';
//Multipart-Backup -->

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_MULTIPART'], '').$lang['L_MULTI_PART'].':&nbsp;</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="multi_part" onclick="obj_disable(\'multipartgroesse1\', this.checked);obj_disable(\'multipartgroesse2\', this.checked);" '.((isset($config['multi_part']) && (1 == $config['multi_part'])) ? ' checked' : '').'>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_MULTIPARTGROESSE'], '').$lang['L_MULTI_PART_GROESSE'].':&nbsp;</td>';
$aus['global1'] .= '<td><input type="text" class="text" id="multipartgroesse1" name="multipartgroesse1" size="3" maxlength="8" style="text-align:right;" value="'.($config['multipartgroesse1'] ?? '').'"';
if (isset($config['multi_part']) && (0 == $config['multi_part'])) {
    $aus['global1'] .= ' disabled';
}

$aus['global1'] .= '>&nbsp;&nbsp;';
$aus['global1'] .= '<select id="multipartgroesse2" name="multipartgroesse2"';
if (isset($config['multi_part']) && (0 == $config['multi_part'])) {
    $aus['global1'] .= ' disabled';
}
$aus['global1'] .= '><option value="1" '.((1 == $config['multipartgroesse2']) ? 'SELECTED' : '').'>Kilobytes</option><option value="2" '.((2 == $config['multipartgroesse2']) ? 'SELECTED' : '').'>Megabytes</option></select>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_OPTIMIZE'], '').$lang['L_OPTIMIZE'].':</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="optimize_tables" '.((isset($config['optimize_tables_beforedump']) && (1 == $config['optimize_tables_beforedump'])) ? ' checked' : '').'>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_BINARY'], '').$lang['L_BINARY'].':</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="binary_container" '.((isset($config['use_binary_container']) && (1 == $config['use_binary_container'])) ? ' checked' : '').'>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '</table></fieldset><fieldset><legend>'.$lang['L_RESTORE'].'</legend><table>';
$aus['global1'] .= '<tr><td>'.Help($lang['L_HELP_EMPTY_DB_BEFORE_RESTORE'], 'conf4').$lang['L_EMPTY_DB_BEFORE_RESTORE'].':&nbsp;</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="empty_db_before_restore" '.((isset($config['empty_db_before_restore']) && (1 == $config['empty_db_before_restore'])) ? ' checked' : '').'>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '<tr><td>'.Help('', '').$lang['L_ERRORHANDLING_RESTORE'].':</td>';
$aus['global1'] .= '<td><input type="radio" class="radio" name="stop_with_error" value="0" '.((isset($config['stop_with_error']) && (0 == $config['stop_with_error'])) ? ' checked' : '').'>&nbsp;'.$lang['L_EHRESTORE_CONTINUE'];
$aus['global1'] .= '&nbsp;&nbsp;&nbsp;<input type="radio" class="radio" name="stop_with_error" value="1" '.((isset($config['stop_with_error']) && (1 == $config['stop_with_error'])) ? ' checked' : '').'>&nbsp;'.$lang['L_EHRESTORE_STOP'];
$aus['global1'] .= '</td></tr>';

if (!isset($config['ignore_enable_keys'])) {
    $config['ignore_enable_keys'] = 0;
}
$aus['global1'] .= '<tr><td>Ignore "ENABLE KEYS":</td>';
$aus['global1'] .= '<td><input type="checkbox" class="checkbox" name="ignore_enable_keys" value="1" '.((isset($config['ignore_enable_keys']) && (1 == $config['ignore_enable_keys'])) ? ' checked' : '').'>';
$aus['global1'] .= '</td></tr>';

$aus['global1'] .= '</table></fieldset>';
$aus['global1'] .= print_save_button();
$aus['global1'] .= '</div>';

//Interface -->
$aus['global3'] = '<div id="global3"><fieldset><legend>'.$lang['L_CONFIG_INTERFACE'].'</legend><table>';
$aus['global3'] .= '<tr><td>'.Help($lang['L_HELP_LANG'], 'conf11').$lang['L_LANGUAGE'].':&nbsp;</td>';
$aus['global3'] .= '<td><select name="language">'.GetLanguageCombo('op');

$config['interface_server_caption'] ??= '';
$aus['global3'] .= '</select><input type="hidden" name="lang_old" value="'.$config['language'].'"><input type="hidden" name="scaption_old" value="'.$config['interface_server_caption'].'"></td></tr>';

$aus['global3'] .= '<tr><td>'.Help($lang['L_HELP_SERVERCAPTION'], '').$lang['L_SERVERCAPTION'].':</td>';
$aus['global3'] .= '<td><input type="checkbox" class="checkbox" value="1" name="server_caption" '.((isset($config['interface_server_caption']) && (1 == $config['interface_server_caption'])) ? ' checked' : '').'><br>';
$aus['global3'] .= '<input type="radio" class="radio" name="server_caption_position" value="1" '.((isset($config['interface_server_caption_position']) && (1 == $config['interface_server_caption_position'])) ? 'checked' : '').'>&nbsp;'.$lang['L_IN_MAINFRAME'].'&nbsp;&nbsp;<input type="radio" class="radio" name="server_caption_position" value="0" '.((isset($config['interface_server_caption_position']) && (0 == $config['interface_server_caption_position'])) ? 'checked' : '').'>&nbsp;'.$lang['L_IN_LEFTFRAME'].'';
$aus['global3'] .= '</td></tr>';

$aus['global3'] .= '<tr><td>'.Help('', '').'Theme:</td><td><select name="theme">'.GetThemes().'</select></td></tr>';

$aus['global3'] .= '</table></fieldset><fieldset><legend>'.$lang['L_SQL_BROWSER'].'</legend><table>';
$aus['global3'] .= '<tr><td>'.Help('', '').$lang['L_SQLBOXHEIGHT'].':&nbsp;</td>';

$config['interface_sqlboxsize'] ??= '';
$aus['global3'] .= '<td><input type="text" class="text" name="sqlboxsize" style="text-align:right;" value="'.$config['interface_sqlboxsize'].'" size="3" maxlength="3">&nbsp;Pixel</td></tr>';

$aus['global3'] .= '<tr><td>'.Help('', '').$lang['L_SQLLIMIT'].':&nbsp;</td>';
$aus['global3'] .= '<td><input type="text" class="text" name="sql_limit" style="text-align:right;" value="'.$config['sql_limit'].'" size="3" maxlength="6">&nbsp;</td></tr>';
$aus['global3'] .= '<tr><td>'.Help('', '').$lang['L_BBPARAMS'].':&nbsp;</td>';
$aus['global3'] .= '<td>';
$aus['global3'] .= '<table><tr><td>'.$lang['L_WIDTH'].':</td><td><input type="text" class="text" name="bb_width" style="text-align:right;" value="'.$config['bb_width'].'" size="3" maxlength="3">&nbsp;pixel</td></tr>';
$aus['global3'] .= '<tr><td>'.$lang['L_BBTEXTCOLOR'].':&nbsp;</td>';
$aus['global3'] .= '<td><select name="bb_textcolor">
<option value="#000000" style="color :#000000;" '.(('#000000' == $config['bb_textcolor'] ? ' selected="selected"' : '')).'>&nbsp;Textcolor&nbsp;</option>
<option value="#000066" style="color :#000066;" '.(('#000066' == $config['bb_textcolor'] ? ' selected="selected"' : '')).'>&nbsp;Textcolor&nbsp;</option>
<option value="#800000" style="color :#800000;" '.(('#800000' == $config['bb_textcolor'] ? ' selected="selected"' : '')).'>&nbsp;Textcolor&nbsp;</option>
<option value="#990000" style="color :#990000;" '.(('#990000' == $config['bb_textcolor'] ? ' selected="selected"' : '')).'>&nbsp;Textcolor&nbsp;</option>
<option value="#006600" style="color :#006600;" '.(('#006600' == $config['bb_textcolor'] ? ' selected="selected"' : '')).'>&nbsp;Textcolor&nbsp;</option>
<option value="#996600" style="color :#996600;" '.(('#996600' == $config['bb_textcolor'] ? ' selected="selected"' : '')).'>&nbsp;Textcolor&nbsp;</option>
<option value="#999999" style="color :#999999;" '.(('#999999' == $config['bb_textcolor'] ? ' selected="selected"' : '')).'>&nbsp;Textcolor&nbsp;</option>
</select></td></tr></table>';
$aus['global3'] .= '</td></tr><tr><td>'.Help('', '').'SQL-Grid:&nbsp;</td>';
$aus['global3'] .= '<td><input type="radio" class="radio" name="interface_table_compact" value="0" '.((isset($config['interface_table_compact']) && (0 == $config['interface_table_compact'])) ? 'checked' : '').'>&nbsp;normal&nbsp;&nbsp;&nbsp;';
$aus['global3'] .= '<input type="radio" class="radio" name="interface_table_compact" value="1" '.((isset($config['interface_table_compact']) && (1 == $config['interface_table_compact'])) ? 'checked' : '').'>&nbsp;compact</td></tr>';

$aus['global3'] .= '</table></fieldset>'.print_save_button().'</div>';

//automatic delete-->
$aus['global2'] = '<div id="global2"><fieldset><legend>'.$lang['L_CONFIG_AUTODELETE'].'</legend><table>';
$aus['global2'] .= '<tr><td>'.Help($lang['L_HELP_AD1'], 'conf8').$lang['L_AUTODELETE'].':&nbsp;</td>';
$aus['global2'] .= '<td><input type="checkbox" class="checkbox" value="1" name="auto_delete" '.((isset($config['auto_delete']) && (1 == $config['auto_delete'])) ? ' checked' : '').'>';
$aus['global2'] .= '</td></tr>';

$aus['global2'] .= '<tr><td>'.Help($lang['L_HELP_AD3'], 'conf10').$lang['L_NUMBER_OF_FILES_FORM'].':&nbsp;</td>';
$aus['global2'] .= '<td><input type="text" class="text" size="3" name="max_backup_files" style="text-align:right;" value="'.($config['max_backup_files'] ?? '').'">   ';
$aus['global2'] .= '</td></tr></table></fieldset>'.print_save_button().'</div>';

//Email-->
if (!isset($config['email_recipient_cc'])) {
    $config['email_recipient_cc'] = '';
} // backwards compatibility if field is undefined
$aus['transfer1'] = '<div id="transfer1"><fieldset><legend>'.$lang['L_EMAIL_NOTIFICATION'].'</legend><table>';
$aus['transfer1'] .= '<tr><td>'.$lang['L_SEND_MAIL_FORM'].':&nbsp;</td>';
$aus['transfer1'] .= '<td><input type="checkbox" class="checkbox" value="1" name="send_mail" '.((isset($config['send_mail']) && (1 == $config['send_mail'])) ? ' checked' : '').'>';
$aus['global3'] .= '</td></tr>';

$aus['transfer1'] .= '<tr><td>'.$lang['L_EMAIL_ADRESS'].':&nbsp;</td><td><input type="text" class="text" name="email0" value="'.($config['email_recipient'] ?? '').'" size="30"></td></tr>';
$aus['transfer1'] .= '<tr><td>'.$lang['L_EMAIL_CC'].':&nbsp;</td><td><input type="text" class="text" name="email_recipient_cc" value="'.($config['email_recipient_cc'] ?? '').'" size="60" maxlength="255">';
$aus['global3'] .= '</td></tr>';

$aus['transfer1'] .= '<tr><td>'.$lang['L_EMAIL_SENDER'].':&nbsp;</td><td><input type="text" class="text" name="email1" value="'.($config['email_sender'] ?? '').'" size="30">';
$aus['global3'] .= '</td></tr>';

$aus['transfer1'] .= '<tr><td>'.$lang['L_SEND_MAIL_DUMP'].':&nbsp;</td><td>';
$aus['transfer1'] .= '<input type="checkbox" class="checkbox" value="1" name="send_mail_dump" '.((isset($config['send_mail_dump']) && (1 == $config['send_mail_dump'])) ? ' checked' : '').'>';
$aus['global3'] .= '</td></tr>';

$aus['transfer1'] .= '<tr><td>'.$lang['L_EMAIL_MAXSIZE'].':&nbsp;</td><td>';
$aus['transfer1'] .= '<input type="text" class="text" name="email_maxsize1" size="3" maxlength="3" style="text-align:right;" value="'.($config['email_maxsize1'] ?? '').'">&nbsp;&nbsp;';
$aus['transfer1'] .= '<select name="email_maxsize2"><option value="1" '.((1 == $config['email_maxsize2']) ? ' SELECTED' : '').'>Kilobytes</option>';
$aus['transfer1'] .= '<option value="2" '.((2 == $config['email_maxsize2']) ? ' SELECTED' : '').'>Megabytes</option></select></td></tr>';

$aus['transfer1'] .= '<tr><td>'.$lang['L_CRON_MAILPRG'].':&nbsp;</td>';
$aus['transfer1'] .= '<td><table><tr><td><input type="radio" class="radio" name="cron_use_mail" value="1" '.((isset($config['cron_use_mail']) && (1 == $config['cron_use_mail'])) ? ' checked' : '').'>&nbsp;sendmail</td><td><input type="text" class="text" size="30" name="cron_sendmail" value="'.($config['cron_sendmail'] ?? '').'"></td><td></td></tr>';
$aus['transfer1'] .= '<tr><td><input type="radio" class="radio" name="cron_use_mail" value="0" '.((isset($config['cron_use_mail']) && (0 == $config['cron_use_mail'])) ? ' checked' : '').'>&nbsp;SMTP</td><td><input type="text" class="text" size="30" name="cron_smtp" value="'.($config['cron_smtp'] ?? '').'"></td><td></td></tr><tr><td>&nbsp;</td><td>SMTP-Port: <strong>'.$config['cron_smtp_port'].'</strong></td><td></td></tr>';

$aus['transfer1'] .= '<tr><td><input type="radio" class="radio" name="cron_use_mail" value="2" '.((isset($config['cron_use_mail']) && (2 == $config['cron_use_mail'])) ? ' checked' : '').'>&nbsp;Other SMTP</td>

<td>SMTP HOST</td><td><input type="text" class="text" size="30" name="other_smtp_host" value="'.($config['other_smtp_host'] ?? 'smtp.example.com').'"></td></tr>
<tr><td></td>
<td>Art der Verschlüsselung</td><td>
	<input type="radio" class="radio" name="other_smtp_encryption" value="0" '.((isset($config['other_smtp_encryption']) && (0 == $config['other_smtp_encryption'])) ? ' checked' : '').'>&nbsp;Keine&nbsp;
	<input type="radio" class="radio" name="other_smtp_encryption" value="1" '.((isset($config['other_smtp_encryption']) && (1 == $config['other_smtp_encryption'])) ? ' checked' : '').'>&nbsp;SSL&nbsp;
	<input type="radio" class="radio" name="other_smtp_encryption" value="2" '.((isset($config['other_smtp_encryption']) && (2 == $config['other_smtp_encryption'])) ? ' checked' : '').'>&nbsp;TLS&nbsp;
</td></tr>

<tr><td></td>
<td>SMTP-Port</td><td>
	<input type="text" class="text" size="30" name="other_smtp_port" value="'.($config['other_smtp_port'] ?? '').'">
</td></tr>

<tr><td></td>
<td>SMTP-Authentifizierung</td><td>
		<input type="checkbox" class="checkbox" value="1" name="other_smtp_auth" '.((isset($config['other_smtp_auth']) && (1 == $config['other_smtp_auth'])) ? ' checked' : '').'>
</td></tr>

<tr><td></td>
<td>SMTP-Benutzername</td><td>
	<input type="text" class="text" size="30" name="other_smtp_username" value="'.($config['other_smtp_username'] ?? '').'">&nbsp;&nbsp;
</td></tr>
<tr><td></td>
<td>SMTP-Passwort</td><td>
	<input type="text" class="text" size="30" name="other_smtp_password" value="'.($config['other_smtp_password'] ?? '').'">&nbsp;&nbsp;
</td></tr>';


$aus['global3'] .= '</td></tr>';
$aus['transfer1'] .= '<tr><td><input type="radio" class="radio" name="cron_use_mail" value="3" '.((isset($config['cron_use_mail']) && (3 == $config['cron_use_mail'])) ? ' checked' : '').'>&nbsp;PHP Default</td><td>(none)</td><td></td></tr>';
$aus['transfer1'] .= '</table></td></tr></table></fieldset>'.print_save_button().'</div>';

//FTP-->
$aus['transfer2'] = '<div id="transfer2"><fieldset><legend>'.$lang['L_CONFIG_FTP'].'</legend>';
for ($i = 0; $i < 3; ++$i) {
    $aus['transfer2'] .= '<fieldset><legend>FTP-Connection '.($i + 1).'</legend><table>';

    $aus['transfer2'] .= '<tr><td>'.Help($lang['L_HELP_FTPTRANSFER'], '').$lang['L_FTP_TRANSFER'].':&nbsp;</td>';
    $aus['transfer2'] .= '<td><input type="checkbox" class="checkbox" value="1" name="ftp_transfer['.$i.']" '.((!extension_loaded('ftp')) ? 'disabled ' : '').((isset($config['ftp_transfer'][$i]) && (1 == $config['ftp_transfer'][$i])) ? ' checked' : '').'></td></tr>';

    $aus['transfer2'] .= '<tr><td>'.Help($lang['SFTP'], '').$lang['L_FTP_TIMEOUT'].':&nbsp;</td>';
    $aus['transfer2'] .= '<td><input type="text" class="text" size="10" name="ftp_timeout['.$i.']" maxlength="3" style="text-align:right;" value="'.($config['ftp_timeout'][$i] ?? '').'">&nbsp;sec</td></tr>';

    $aus['transfer2'] .= '<tr><td>'.Help($lang['L_HELP_FTP_MODE'], '').$lang['L_FTP_CHOOSE_MODE'].':&nbsp;</td>';
    $aus['transfer2'] .= '<td><input type="checkbox" class="checkbox" name="ftp_mode['.$i.']" value="1" '.((isset($config['ftp_mode'][$i]) && (1 == $config['ftp_mode'][$i])) ? 'checked' : '').'>&nbsp;';
    $aus['transfer2'] .= $lang['L_FTP_PASSIVE'].'</td></tr><tr><td colspan="2">';

    $aus['transfer2'] .= '<tr><td>'.Help($lang['L_HELP_FTPSSL'], '').$lang['L_FTP_SSL'].':&nbsp;</td>';
    $aus['transfer2'] .= '<td><input type="checkbox" class="checkbox" name="ftp_useSSL['.$i.']" value="1" '.((isset($config['ftp_useSSL'][$i]) && (1 == $config['ftp_useSSL'][$i])) ? 'checked' : '').' '.((!extension_loaded('openssl')) ? 'disabled ' : '').'>';
    $aus['transfer2'] .= '&nbsp;<span '.((!extension_loaded('openssl')) ? 'style="color:#999999;"' : '').'>'.$lang['L_FTP_USESSL'].'</span></td></tr><tr><td colspan="2">';

    $aus['transfer2'] .= '<tr><td><input type="submit" name="testFTP'.$i.'" value="'.$lang['L_TESTCONNECTION'].'" class="Formbutton"><br>'.$checkFTP[$i].'</td><td><table>';
    $aus['transfer2'] .= '<tr><td class="small">'.Help($lang['L_HELP_FTPSERVER'], 'conf14', 12).$lang['L_FTP_SERVER'].':&nbsp;</td><td><input class="text" type="text" size="30" name="ftp_server['.$i.']" value="'.($config['ftp_server'][$i] ?? '').'"></td></tr>';
    $aus['transfer2'] .= '<tr><td class="small">'.Help($lang['L_HELP_FTPPORT'], 'conf15', 12).$lang['L_FTP_PORT'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="ftp_port['.$i.']" style="text-align:right;" value="'.($config['ftp_port'][$i] ?? '').'"></td></tr>';
    $aus['transfer2'] .= '<tr><td class="small">'.Help($lang['L_HELP_FTPUSER'], 'conf16', 12).$lang['L_FTP_USER'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="ftp_user['.$i.']" value="'.($config['ftp_user'][$i] ?? '').'"></td></tr>';
    $aus['transfer2'] .= '<tr><td class="small">'.Help($lang['L_HELP_FTPPASS'], 'conf17', 12).$lang['L_FTP_PASS'].':&nbsp;</td><td class="small"><input class="text" type="password" size="30" name="ftp_pass['.$i.']" value="'.($config['ftp_pass'][$i] ?? '').'"></td></tr>';
    $aus['transfer2'] .= '<tr><td class="small">'.Help($lang['L_HELP_FTPDIR'], 'conf18', 12).$lang['L_FTP_DIR'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="ftp_dir['.$i.']" value="'.($config['ftp_dir'][$i] ?? '').'"></td></tr>';
    $aus['transfer2'] .= '</table></td></tr></table>'.print_save_button().'</fieldset>';
}
$aus['transfer2'] .= '</fieldset></div>';

// SFTP-->
$aus['transfer3'] = '<div id="transfer3"><fieldset><legend>'.$lang['L_CONFIG_SFTP'].'</legend>';
for ($i = 0; $i < 3; ++$i) {
    $aus['transfer3'] .= '<fieldset><legend>SFTP-Connection '.($i + 1).'</legend><table>';

    $aus['transfer3'] .= '<tr><td>'.Help($lang['L_HELP_SFTPTRANSFER'], '').$lang['L_SFTP_TRANSFER'].':&nbsp;</td>';
    $aus['transfer3'] .= '<td><input type="checkbox" class="checkbox" value="1" name="sftp_transfer['.$i.']" '.((!extension_loaded('ftp')) ? 'disabled ' : '').((isset($config['sftp_transfer'][$i]) && (1 == $config['sftp_transfer'][$i])) ? ' checked' : '').'></td></tr>';
    $aus['transfer3'] .= '<tr><td>'.Help($lang['SFTP'], '').$lang['L_SFTP_TIMEOUT'].':&nbsp;</td>';
    $aus['transfer3'] .= '<td><input type="text" class="text" size="10" name="sftp_timeout['.$i.']" maxlength="3" style="text-align:right;" value="'.($config['sftp_timeout'][$i] ?? '').'">&nbsp;sec</td></tr>';

    $aus['transfer3'] .= '<tr><td><input type="submit" name="testSFTP'.$i.'" value="'.$lang['L_TESTCONNECTION'].'" class="Formbutton"><br>'.$checkSFTP[$i].'</td><td><table>';
    $aus['transfer3'] .= '<tr><td class="small">'.Help($lang['L_HELP_SFTPSERVER'], 'conf14', 12).$lang['L_SFTP_SERVER'].':&nbsp;</td><td><input class="text" type="text" size="30" name="sftp_server['.$i.']" value="'.($config['sftp_server'][$i] ?? '').'"></td></tr>';
    $aus['transfer3'] .= '<tr><td class="small">'.Help($lang['L_HELP_SFTPPORT'], 'conf15', 12).$lang['L_SFTP_PORT'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="sftp_port['.$i.']" style="text-align:right;" value="'.($config['sftp_port'][$i] ?? '').'"></td></tr>';
    $aus['transfer3'] .= '<tr><td class="small">'.Help($lang['L_HELP_SFTPUSER'], 'conf16', 12).$lang['L_SFTP_USER'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="sftp_user['.$i.']" value="'.($config['sftp_user'][$i] ?? '').'"></td></tr>';
    $aus['transfer3'] .= '<tr><td class="small">'.Help($lang['L_HELP_SFTPPASS'], 'conf17', 12).$lang['L_SFTP_PASS'].':&nbsp;</td><td class="small"><input class="text" type="password" size="30" name="sftp_pass['.$i.']" value="'.($config['sftp_pass'][$i] ?? '').'"></td></tr>';
    $aus['transfer3'] .= '<tr><td class="small">'.Help($lang['L_HELP_SFTPDIR'], 'conf18', 12).$lang['L_SFTP_DIR'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="sftp_dir['.$i.']" value="'.($config['sftp_dir'][$i] ?? '').'"></td></tr>';

    $aus['transfer3'] .= '<tr><td class="small">'.$lang['L_SFTP_SFTP_PATH_TO_PRIVATE_KEY'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="sftp_path_to_private_key['.$i.']" value="'.($config['sftp_path_to_private_key'][$i] ?? '').'"> (optional, default: null)</td></tr>';

    $aus['transfer3'] .= '<tr><td class="small">'.$lang['L_SFTP_SECRET_PASSPHRASE_FOR_PRIVATE_KEY'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="sftp_secret_passphrase_for_private_key['.$i.']" value="'.($config['sftp_secret_passphrase_for_private_key'][$i] ?? '').'"> (optional, default: null)</td></tr>';

    $aus['transfer3'] .= '<tr><td class="small">'.$lang['L_SFTP_FINGERPRINT'].':&nbsp;</td><td class="small"><input class="text" type="text" size="30" name="sftp_fingerprint['.$i.']" value="'.($config['sftp_fingerprint'][$i] ?? '').'"> (optional, default: null)</td></tr>';

    $aus['transfer3'] .= '</table></td></tr></table>'.print_save_button().'</fieldset>';
}
$aus['transfer3'] .= '</fieldset></div>';

// Crondump
$aus['cron'] = '<div id="cron"><fieldset><legend>'.$lang['L_CONFIG_CRONPERL'].'</legend><table>';
$aus['cron'] .= '<tr><td>'.Help($lang['L_HELP_CRONEXTENDER'], '').$lang['L_CRON_EXTENDER'].':&nbsp;</td>';
$aus['cron'] .= '<td><input type="radio" class="radio" value="0" name="cron_extender" '.((isset($config['cron_extender']) && (0 == $config['cron_extender'])) ? ' checked' : '').'>&nbsp;.pl';
$aus['cron'] .= '&nbsp;&nbsp;&nbsp;<input type="radio" class="radio" value="1" name="cron_extender" '.((isset($config['cron_extender']) && (1 == $config['cron_extender'])) ? ' checked' : '').'>&nbsp;.cgi';

$aus['cron'] .= '</tr><tr><td>'.Help($lang['L_HELP_CRONEXECPATH'], '').$lang['L_CRON_EXECPATH'].':&nbsp;</td>';
$aus['cron'] .= '<td><input type="text" class="text" size="30" name="cron_execution_path" value="'.$config['cron_execution_path'].'"></td>';

$aus['cron'] .= '</tr><tr><td>'.Help($lang['L_HELP_CRONPRINTOUT'], '').$lang['L_CRON_PRINTOUT'].':&nbsp;</td>';
$aus['cron'] .= '<td><input type="checkbox" class="checkbox" value="1" name="cron_printout" '.((isset($config['cron_printout']) && (1 == $config['cron_printout'])) ? ' checked' : '').'>';

$aus['cron'] .= '<tr><td>'.Help($lang['L_HELP_CRONCOMPLETELOG'], '').$lang['L_CRON_COMPLETELOG'].':&nbsp;</td>';
$aus['cron'] .= '<td><input type="checkbox" class="checkbox" value="1" name="cron_completelog" '.((isset($config['cron_completelog']) && (1 == $config['cron_completelog'])) ? ' checked' : '').'>';

$aus['cron'] .= '<tr><td>'.Help($lang['L_HELP_CRONDBINDEX'], 'conf14').$lang['L_CRON_CRONDBINDEX'].':&nbsp;</td>';
$aus['cron'] .= '<td><select name="cron_dbindex" id="cron_dbindex">';
$aus['cron'] .= '<option value="-3" ';
if (-3 == $config['cron_dbindex']) {
    $aus['cron'] .= 'SELECTED';
}
$aus['cron'] .= '>'.$lang['L_MULTIDUMPALL']."</option>\n";
$aus['cron'] .= '<option value="-2" ';
if (-2 == $config['cron_dbindex']) {
    $aus['cron'] .= 'SELECTED';
}
$aus['cron'] .= '>'.$lang['L_MULTIDUMPCONF']."</option>\n";

if (isset($databases['Name'][0]) && $databases['Name'][0] > '') {
    $datenbanken = is_countable($databases['Name']) ? count($databases['Name']) : 0;
    for ($i = 0; $i < $datenbanken; ++$i) {
        $aus['cron'] .= '<option value="'.$i.'"';
        if ($i == $config['cron_dbindex']) {
            $aus['cron'] .= ' selected="selected"';
        }
        if (in_array($databases['Name'][$i], $dontBackupDatabases)) {
            $aus['cron'] .= ' disabled="disabled"';
        }
        $aus['cron'] .= '>'.$databases['Name'][$i]."</option>\n";
    }
} else {
    $config['cron_dbindex'] = 0;
}

$aus['cron'] .= '</select>'."\n";
$aus['cron'] .= '</td></tr>';

// comment
$config['cron_comment'] ??= '';
$aus['cron'] .= '<tr><td>'.$lang['L_CRON_COMMENT'].':&nbsp;</td>';
$aus['cron'] .= '<td><input type="text" class="text" name="cron_comment" size="30" maxlength="100" value="'.htmlspecialchars((string) $config['cron_comment']).'"></td></tr>';
$aus['cron'] .= '</table></fieldset>'.print_save_button().'</div>';

//Formular-Buttons -->
$aus['formende'] = '</div></form><br style="clear:both;">';

// AUSGABE
echo $aus['formstart'];
echo $aus['db'];
echo $aus['global1'];
echo $aus['global2'];
echo $aus['global3'];
echo $aus['transfer1'];
echo $aus['transfer2'];
echo $aus['transfer3'];
echo $aus['cron'];
echo $aus['conf'];

echo $aus['formende'];

echo '<script>show_pardivs("'.$sel.'");';

// If something is wrong when changing a user or no DB was found or a DB could
// not be added --> poke user with nose and show connection data
if (($showVP) || (!isset($databases['Name'])) || (isset($databases['name']) && (is_countable(0 == $databases['Name']) ? count(0 == $databases['Name']) : 0)) || (isset($add_db_message))) {
    echo 'SwitchVP();';
}
?>
// Eine Funktion definieren, die den Portwert basierend auf der Verschlüsselung ändert
function changePort() {
  // Das ausgewählte Optionsfeld finden
  var encryption = document.querySelector('input[name="other_smtp_encryption"]:checked');
  // Den entsprechenden Portwert zuweisen
  var port = encryption.value === "1" ? 465 : encryption.value === "2" ? 587 : 25;
  // Den Wert des Port-Eingabefeldes aktualisieren
  document.querySelector('input[name="other_smtp_port"]').value = port;
}

// Einen Event-Listener hinzufügen, der die Funktion aufruft, wenn sich ein Optionsfeld ändert
var radios = document.querySelectorAll('input[name="other_smtp_encryption"]');
for (var i = 0; i < radios.length; i++) {
  radios[i].addEventListener('change', changePort);
}
<?php
echo '</script>';
echo MODFooter();
$_SESSION['config'] = $config;
ob_end_flush();
exit();
