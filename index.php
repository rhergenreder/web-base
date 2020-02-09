<?php

// Autoload function
// function __autoload($class) {
//   $parts = explode('\\', $class);
//   $path = implode(DIRECTORY_SEPERATOR, $parts);
//   require "$path.php";
// }

function getClassPath($class, $suffix=true) {
  $path = str_replace('\\', '/', $class);
  $suffix = ($suffix ? ".class" : "");
  return "core/$path$suffix.php";
}

function getWebRoot() {
  return dirname(__FILE__);
}

spl_autoload_extensions(".php");
spl_autoload_register(function($class) {
  $full_path = getClassPath($class);
  if(file_exists($full_path))
    include_once $full_path;
  else
    include_once getClassPath($class, false);
});

include_once 'core/core.php';
include_once 'core/datetime.php';
include_once 'core/constants.php';

$config = new Configuration\Configuration();
$installation = (!$config->load());
$user   = new Objects\User($config);

if ($installation) {
  $document = new Documents\Install($user);
} else {
  print("DON'T INSTALL");
}

die($document->getCode());

// if(!file_exists($configPath)) {
//   require_once 'core/objects/User.php';
//   require_once 'core/documents/install.php';
//
//   // $user = new CUser(null);
//   // $installPage = new CDocumentInstall($user);
//   // die($installPage->getCode());
// } else {
//   $perms = fileperms($configPath);
//   if($perms != 0x8600) {
//     die("<b>Invalid conf file permissions</b>. expected permissions: 8600, got: $perms");
//   }
//
//   require_once $configPath;
//   // require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/php/pages/home.php';
//   // CDocument::createDocument(CDocumentHome::class);
// }
?>
