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
//Start SQL-Box
$tpl = new MODTemplate();
$tpl->set_filenames(
    [
    'show' => $config['paths']['root'].'./tpl/sqlbrowser/sqlbox.tpl', ]
);

if (isset($_GET['readfile']) && 1 == $_GET['readfile']) {
    $tpl->assign_block_vars(
        'SQLUPLOAD', [
        'POSTTARGET' => $params,
        'LANG_OPENSQLFILE' => $lang['L_SQL_OPENFILE'],
        'LANG_OPENSQLFILE_BUTTON' => $lang['L_SQL_OPENFILE_BUTTON'],
        'LANG_SQL_MAXSIZE' => $lang['L_MAX_UPLOAD_SIZE'],
        'MAX_FILESIZE' => $config['upload_max_filesize'], ]
    );
}

if (isset($_POST['submit_openfile'])) {
    //open file
    if (!isset($_FILES['upfile']['name']) || empty($_FILES['upfile']['name'])) {
        $aus .= '<span class="error">'.$lang['L_FM_UPLOADFILEREQUEST'].'</span>';
    } else {
        $fn = $_FILES['upfile']['tmp_name'];
        if ('.gz' == strtolower(substr((string) $_FILES['upfile']['name'], -3))) {
            $read__user_sqlfile = gzfile($fn);
        } else {
            $read__user_sqlfile = file($fn);
        }
        $aus .= '<span>geladenes File: <strong>'.$_FILES['upfile']['name'].'</strong>&nbsp;&nbsp;&nbsp;'.byte_output(filesize($_FILES['upfile']['tmp_name'])).'</span>';
        $sql_loaded = implode('', $read__user_sqlfile);
    }
}

// Sind SQL-Befehle in der SQLLib vorhanden?
$sqlcombo = SQL_ComboBox();
if ($sqlcombo > '') {
    $tpl->assign_block_vars(
        'SQLCOMBO', [
        'SQL_COMBOBOX' => $sqlcombo, ]
    );
}

$tpl->assign_vars(
    [
    'LANG_SQL_WARNING' => $lang['L_SQL_WARNING'],
    'ICONPATH' => $config['files']['iconpath'],
    'MYSQL_REF' => $mysql_help_ref,
    'BOXSIZE' => ($config['interface_sqlboxsize'] ?? ''),
    'BOXCONTENT' => ($sql_loaded ?? $sql['sql_statement'].$sql['order_statement']),
    'LANG_SQL_BEFEHLE' => $lang['L_SQL_BEFEHLE'],
    'TABLE_COMBOBOX' => Table_ComboBox(),
    'LANG_SQL_EXEC' => $lang['L_SQL_EXEC'],
    'LANG_RESET' => $lang['L_RESET'],
    'PARAMS' => $params,
    'DB' => $databases['Name'][$dbid],
    'DBID' => $dbid,
    'TABLENAME' => $tablename,
    'ICON_SEARCH' => $icon['search'],
    'ICON_UPLOAD' => $icon['upload'],
    'ICON_MYSQL_HELP' => $icon['mysql_help'],
    'MYSQL_HELP' => $lang['L_TITLE_MYSQL_HELP'],
    'DBID' => $databases['db_selected_index'],
    'LANG_TOOLBOX' => $lang['L_TOOLS_TOOLBOX'],
    'LANG_TOOLS' => $lang['L_TOOLS'],
    'LANG_DB' => $lang['L_DB'],
    'LANG_TABLE' => $lang['L_TABLE'],
    'LANG_SQL_TABLEVIEW' => $lang['L_SQL_TABLEVIEW'],
    'LANG_BACK_TO_DB_OVERVIEW' => $lang['L_SQL_BACKDBOVERVIEW'], ]
);
if ($tablename > '') {
    $tpl->assign_block_vars('TABLE_SELECTED', []);
}

$tpl->pparse('show');
