<?php

namespace Configuration\Patch;

use Configuration\DatabaseScript;
use Driver\SQL\SQL;
use Driver\SQL\Column\Column;
use Driver\SQL\Strategy\CascadeStrategy;
use Driver\SQL\Strategy\UpdateStrategy;

class file_api extends DatabaseScript {

  public static function createQueries(SQL $sql) {

    $queries = array();

    $queries[] = $sql->insert("ApiPermission", array("method", "groups", "description"))
      ->onDuplicateKeyStrategy(new UpdateStrategy(array("method"), array("method" => new Column("method"))))
      ->addRow("File/Download", array(), "Allows users to download files when logged in, or using a given token")
      ->addRow("File/Upload", array(), "Allows users to upload files when logged in, or using a given token")
      ->addRow("File/ValidateToken", array(), "Allows users to validate a given token")
      ->addRow("File/RevokeToken", array(USER_GROUP_ADMIN), "Allows users to revoke a token")
      ->addRow("File/ListFiles", array(), "Allows users to list all files assigned to an account")
      ->addRow("File/ListTokens", array(USER_GROUP_ADMIN), "Allows users to list all tokens assigned to the virtual filesystem of an account")
      ->addRow("File/CreateDirectory", array(), "Allows users to create a virtual directory")
      ->addRow("File/Rename", array(), "Allows users to rename files in the virtual filesystem")
      ->addRow("File/Move", array(), "Allows users to move files in the virtual filesystem")
      ->addRow("File/Delete", array(), "Allows users to delete files in the virtual filesystem")
      ->addRow("File/CreateUploadToken", array(USER_GROUP_ADMIN), "Allows users to create a token to upload files to the virtual filesystem assigned to the users account")
      ->addRow("File/CreateDownloadToken", array(USER_GROUP_ADMIN), "Allows users to create a token to download files from the virtual filesystem assigned to the users account");

    $queries[] = $sql->insert("Route", array("request", "action", "target", "extra"))
      ->onDuplicateKeyStrategy(new UpdateStrategy(array("request"), array("request" => new Column("request"))))
      ->addRow("^/files(/.*)?$", "dynamic", "\\Documents\\Files", NULL);

    $queries[] = $sql->createTable("UserFile")
      ->onlyIfNotExists()
      ->addSerial("uid")
      ->addBool("directory")
      ->addString("name", 64, false)
      ->addString("path", 512, true)
      ->addInt("parent_id", true)
      ->addInt("user_id", true)
      ->primaryKey("uid")
      ->unique("parent_id", "name")
      ->foreignKey("parent_id", "UserFile", "uid", new CascadeStrategy())
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("UserFileToken")
      ->onlyIfNotExists()
      ->addSerial("uid")
      ->addString("token", 36, false)
      ->addDateTime("valid_until", true)
      ->addEnum("token_type", array("download", "upload"))
      ->addInt("user_id")
      # upload only:
      ->addInt("maxFiles", true)
      ->addInt("maxSize", true)
      ->addInt("parent_id", true)
      ->addString("extensions", 64, true)
      ->primaryKey("uid")
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy())
      ->foreignKey("parent_id", "UserFile", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("UserFileTokenFile")
      ->addInt("file_id")
      ->addInt("token_id")
      ->unique("file_id", "token_id")
      ->foreignKey("file_id", "UserFile", "uid", new CascadeStrategy())
      ->foreignKey("token_id", "UserFileToken", "uid", new CascadeStrategy());

    return $queries;
  }
}
