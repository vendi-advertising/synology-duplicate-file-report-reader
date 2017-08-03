<?php

define( 'VENDI_ADMIN_SYNOLOGY_DUPE_FILE', __FILE__ );
define( 'VENDI_ADMIN_SYNOLOGY_DUPE_PATH', dirname( __FILE__ ) );
define( 'VENDI_ADMIN_SYNOLOGY_DUPE_APP_VERSION', '0.0.1' );

require_once VENDI_ADMIN_SYNOLOGY_DUPE_PATH . '/vendor/autoload.php';

$application = new Symfony\Component\Console\Application( 'Vendi Admin - Synology Duplicate File Report Reader', VENDI_ADMIN_SYNOLOGY_DUPE_APP_VERSION );
$application->add( new Vendi\Admin\Synology\DuplicateFiles\DupeLogReader() );
$application->run();
