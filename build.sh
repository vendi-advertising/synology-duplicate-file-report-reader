#!/usr/bin/env php
<?php

define( 'APP_DIR',                  './releases/' );
define( 'APP_NAME',                 'dupe-log-reader' );
define( 'APP_VERSION',              '0.0.3' );
define( 'APP_EXT',                  'phar' );
define( 'APP_ROOT_FILE',            'app.php' );


define( 'APP_FILE',                 APP_NAME . '.' . APP_VERSION . '.' . APP_EXT );
define( 'APP_ABS',                  APP_DIR . APP_FILE );
define( 'APP_CHECKSUM_ABS',         APP_ABS . '.sha256' );
define( 'APP_CHECKSUM_SIGN_ABS',    APP_CHECKSUM_ABS . '.asc' );

require_once dirname( __FILE__ ) . '/vendor/autoload.php';

use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;

if( ! is_dir( APP_DIR ) )
{
    mkdir( APP_DIR );
}

if( is_file( APP_ABS ) )
{
    unlink( APP_ABS );
}

//https://stackoverflow.com/a/1674175/231316
function prompt_silent( $prompt = "Enter Password:" )
{
    if (preg_match( '/^win/i', PHP_OS ) )
    {
        die( 'Building on Windows is not supported.' . "\n" );
    }

    $command = "/usr/bin/env bash -c 'echo OK'";
    if( rtrim( shell_exec( $command ) ) !== 'OK' )
    {
        die( 'Can\'t invoke bash' . "\n" );
    }

    $command = "/usr/bin/env bash -c 'read -s -p \""
      . addslashes( $prompt )
      . "\" mypassword && echo \$mypassword'";
    $password = rtrim( shell_exec( $command ) );

    echo "\n";

    return $password;
}

// function get_private_key( $key_file = '~/.signing-keys/private.pem' )
// {
//     //Get the private key password
//     $private_key_password = prompt_silent();

//     $private_key_resource = openssl_pkey_get_private( file_get_contents( Path::canonicalize( $key_file ) ), $private_key_password );
//     $private_key = '';
//     $result = @openssl_pkey_export( $private_key_resource, $private_key );
//     if( ! $result )
//     {
//         die( 'Unable to read/decrypt/find private key' . "\n" );
//     }

//     return $private_key;
// }


$phar = new \Phar( APP_ABS, 0, APP_FILE );
$phar->compressFiles( \Phar::GZ );
$phar->setSignatureAlgorithm( \Phar::SHA256 );

// $private_key = get_private_key();
// $phar->setSignatureAlgorithm(Phar::OPENSSL, $private_key );

// PHP files
$finder = new Finder();
$finder
    ->files()
    ->ignoreVCS( true )
    ->name( '*.php' )
    ->in( './src' )
    ->in( './vendor' )
    ->exclude('test')
    ->exclude('tests')
    ->exclude('Test')
    ->exclude('Tests')
    ->exclude('symfony/finder')
    ;

$files = array();
$files[ APP_ROOT_FILE ] = './' . APP_ROOT_FILE;

foreach ( $finder as $file )
{
    $files[ substr( $file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename(), 2 ) ] = $file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename();
}

$APP_FILE = APP_FILE;
$APP_ROOT_FILE = APP_ROOT_FILE;

$phar->buildFromIterator( new ArrayIterator( $files ) );
$phar->setStub( <<<EOB
#!/usr/bin/env php
<?php
Phar::mapPhar();
define( 'VENDI_CLI_ROOT_PHAR', 'phar://${APP_FILE}' );
include VENDI_CLI_ROOT_PHAR . '/${APP_ROOT_FILE}';
__HALT_COMPILER();
?>
EOB
);

$phar = null;

$APP_ABS                = escapeshellarg( APP_ABS );
$APP_CHECKSUM_ABS       = escapeshellarg( APP_CHECKSUM_ABS );
$APP_CHECKSUM_SIGN_ABS  = escapeshellarg( APP_CHECKSUM_SIGN_ABS );

$command = "chmod +x ${APP_ABS}";
shell_exec( $command );

$command = "sha256sum ${APP_ABS} > ${APP_CHECKSUM_ABS}";
shell_exec( $command );

$command = "gpg --output ${APP_CHECKSUM_SIGN_ABS} -b ${APP_CHECKSUM_ABS}";
shell_exec( $command );
