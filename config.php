<?php

// MyOOS [Dumper] Configuration

// Host-Adress, default 'localhost'
$config['dbhost'] = 'localhost';
// port - if empty, mysql uses default
$config['dbport'] = '';
// socket - if empty, mysql uses default
$config['dbsocket'] = '';

// Username
$config['dbuser'] = 'root';
//User-Pass. For no Password leave empty
$config['dbpass'] = '';

//Speed Values between 50 and 1000000
//use low values if you have bad connection or slow machines
$config['minspeed'] = 100;
$config['maxspeed'] = 50000;

// Interface language and style
$config['language'] = 'en';
$config['theme'] = 'mod';

//Shows the Serveradress if 1
$config['interface_server_caption'] = 1;
$config['interface_server_captioncolor'] = '#ff9966';
//Position of the Serveradress 0=left, 1=right
$config['interface_server_caption_position'] = 0;

//Height of the SQL-Box in Mini-SQL in pixel
$config['interface_sqlboxsize'] = 70;
$config['interface_table_compact'] = 0;

// Determine the maximum Amount for Memory Use in Bytes, 0 for no limit
$config['memory_limit'] = 100000;

// For gz-Compression set to 1, without compression set to 0
$config['compression'] = 1;

//Refreshtime for MySQL processlist in msec, use any value >1000
$config['processlist_refresh'] = 3000;

$config['empty_db_before_restore'] = 0;
$config['optimize_tables_beforedump'] = 0;
$config['use_binary_container'] = 0;
$config['stop_with_error'] = 1;
$config['ignore_enable_keys'] = 0;

// For sending a mail after backup set send_mail to 1, otherless set to 0
$config['send_mail'] = 0;
// Attach the backup 0=no  1=yes
$config['send_mail_dump'] = 0;
// set the recieve adress for the mail
$config['email_recipient'] = '';
$config['email_recipient_cc'] = '';
// set the sender adress (the script)
$config['email_sender'] = '';

//max. Size of Email-Attach, here 3 MB
$config['email_maxsize1'] = 3;
$config['email_maxsize2'] = 2;

// FTP Server Configuration for Transfer
$config['ftp_transfer'][0] = 0;
$config['ftp_timeout'][0] = 30;
$config['ftp_useSSL'][0] = 0;
$config['ftp_mode'][0] = 0;
$config['ftp_server'][0] = ''; // Adress of FTP-Server
$config['ftp_port'][0] = '21'; // Port
$config['ftp_user'][0] = ''; // Username
$config['ftp_pass'][0] = ''; // Password
$config['ftp_dir'][0] = ''; // Upload-Directory

$config['ftp_transfer'][1] = 0;
$config['ftp_timeout'][1] = 30;
$config['ftp_useSSL'][1] = 0;
$config['ftp_mode'][1] = 0;
$config['ftp_server'][1] = '';
$config['ftp_port'][1] = '21';
$config['ftp_user'][1] = '';
$config['ftp_pass'][1] = '';
$config['ftp_dir'][1] = '';

$config['ftp_transfer'][2] = 0;
$config['ftp_timeout'][2] = 30;
$config['ftp_useSSL'][2] = 0;
$config['ftp_mode'][2] = 0;
$config['ftp_server'][2] = '';
$config['ftp_port'][2] = '21';
$config['ftp_user'][2] = '';
$config['ftp_pass'][2] = '';
$config['ftp_dir'][2] = '';

// SFTP Server Configuration for Transfer
$config['sftp_transfer'][0] = 0;
$config['sftp_timeout'][0] = 30;
$config['sftp_server'][0] = ''; // Adress of SFTP-Server
$config['sftp_port'][0] = '22'; // Port
$config['sftp_user'][0] = ''; // Username
$config['sftp_pass'][0] = ''; // Password
$config['sftp_dir'][0] = ''; // Upload-Directory
$config['sftp_path_to_private_key'][0] = null; // private key (optional, default: null) can be used instead of password, set to null if password is set
$config['sftp_secret_passphrase_for_private_key'][0] = null;  // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
$config['sftp_fingerprint'][0] = null; // host fingerprint (optional, default: null),

$config['sftp_transfer'][1] = 0;
$config['sftp_timeout'][1] = 30;
$config['sftp_server'][1] = '';
$config['sftp_port'][1] = '22';
$config['sftp_user'][1] = '';
$config['sftp_pass'][1] = '';
$config['sftp_dir'][1] = '';
$config['sftp_path_to_private_key'][1] = null;
$config['sftp_secret_passphrase_for_private_key'][1] = null;
$config['sftp_fingerprint'][1] = null;

$config['sftp_transfer'][2] = 0;
$config['sftp_timeout'][2] = 30;
$config['sftp_server'][2] = '';
$config['sftp_port'][2] = '22';
$config['sftp_user'][2] = '';
$config['sftp_pass'][2] = '';
$config['sftp_dir'][2] = '';
$config['sftp_path_to_private_key'][2] = null;
$config['sftp_secret_passphrase_for_private_key'][2] = null;
$config['sftp_fingerprint'][2] = null;

//Multipart 0=off 1=on
$config['multi_part'] = 0;
$config['multipartgroesse1'] = 1;
$config['multipartgroesse2'] = 2;
$config['multipart_groesse'] = 0;

//Auto-Delete 0=off 1=on
$config['auto_delete'] = 0;
$config['max_backup_files'] = 3;

//configuration file
$config['cron_configurationfile'] = 'myoosdumper.conf.php';
//path to perl, for windows use e.g. C:perlbinperl.exe
$config['cron_perlpath'] = '/usr/bin/perl';
//mailer use sendmail(1) or SMTP(0) or other SMPT(3) or PHP Default(4)
$config['cron_use_mail'] = 1;
//path to sendmail
$sendmail_path = ini_get('sendmail_path');
$config['cron_sendmail'] = $sendmail_path > '' ? $sendmail_path : '/usr/lib/sendmail -t -oi -oem';

//adress of smtp-server
$config['cron_smtp'] = 'localhost';
//smtp-port
$config['cron_smtp_port'] = 25;

$config['other_smtp_host'] = '';
// non = 0; SSL = 1  TLS = 2
$config['other_smtp_encryption'] = '2';
$config['other_smtp_port'] = '587';
$config['other_smtp_username'] = '';
$config['other_smtp_password'] = '';
$config['other_smtp_auth'] = '';



$config['cron_extender'] = 0;
$config['cron_compression'] = 1;
$config['cron_printout'] = 1;
$config['cron_completelog'] = 1;
$config['cron_comment'] = '';
$config['multi_dump'] = 0;
$config['logcompression'] = 1;
$config['log_maxsize1'] = 1;
$config['log_maxsize2'] = 2;
$config['log_maxsize'] = 1_048_576;
