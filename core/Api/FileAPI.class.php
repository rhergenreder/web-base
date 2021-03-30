<?php

namespace Api {

  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Condition\CondNull;
  use Driver\SQL\SQL;
  use External\ZipStream\BufferWriter;
  use External\ZipStream\File;
  use External\ZipStream\ZipStream;

  abstract class FileAPI extends Request {

    protected function checkDirectory($parentId) {
      if ($parentId === null) {
        return true;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("directory")
        ->from("UserFile")
        ->where(new Compare("uid", $parentId))
        ->limit(1)
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (empty($res)) {
          return $this->createError("Parent directory not found");
        } else if(!$res[0]["directory"]) {
          return $this->createError("Parent file is not a directory");
        }
      }

      return $this->success;
    }

    protected function downloadFile($name, $path) {
      if (!file_exists($path)) {
        http_response_code(404);
        die("404 - File does not exist anymore");
      } else if(!is_file($path) || !is_readable($path)) {
        die("403 - Unable to download file.");
      } else {
        $mimeType = @mime_content_type($path);
        if ($mimeType) {
          header("Content-Type: $mimeType");
        }

        $name = trim(preg_replace('/\s\s+/', ' ', $name));
        header("Content-Disposition: attachment; filename=$name");

        //No cache
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        //Define file size
        header('Content-Length: ' . filesize($path));

        ob_clean();
        flush();
        readfile($path);
        exit;
      }
    }

    protected function downloadZip($files) {
      if ($files == null || empty($files) || !is_array($files)) {
        return $this->createError("No files to download");
      }

      header('Content-Disposition: attachment; filename="files.zip"');
      header('Content-Type: application/zip');
      $writer = new BufferWriter();
      $writer->registerCallback(function ($w) { echo $w->read(); });
      $zipStream = new ZipStream($writer);

      foreach ($files as $file) {
        $f = new File($file["name"]);
        $f->loadFromFile($file["path"]);
        $zipStream->saveFile($f);
      }

      $zipStream->close();
      exit;
    }

    protected function &findDirectory(&$files, $id) {

      if ($id !== null) {
        $id = (string)$id;
        if (isset($files[$id])) {
          return $files[$id]["items"];
        } else {
          foreach ($files as &$dir) {
            if ($dir["isDirectory"]) {
              $target =& $this->findDirectory($dir["items"], $id);
              if ($target !== $dir["items"]) {
                return $target;
              }
            }
          }
          return $files;
        }
      } else {
        return $files;
      }
    }

    protected function createFileList($res) {
      $files = array();
      foreach ($res as $row) {
        if ($row["uid"] === null) continue;
        $fileId = $row["uid"];
        $parentId = $row["parentId"];
        $fileName = $row["name"];
        $isDirectory = $row["directory"];
        $fileElement = array("uid" => $fileId, "name" => $fileName, "isDirectory" => $isDirectory);
        if ($isDirectory) {
          $fileElement["items"] = array();
        } else {
          $fileElement["size"] = @filesize($row["path"]);
          $fileElement["mimeType"] = @mime_content_type($row["path"]);
        }

        $dir =& $this->findDirectory($files, $parentId);
        $dir[$fileId] = $fileElement;
        unset($dir);
      }
      return $files;
    }

    protected function filterFiles(SQL $sql, $query, $id, $token = null) {
      if (is_array($id)) {
        $query->where(new CondIn("UserFile.uid", $id));
      } else {
        $query->where(new Compare("UserFile.uid", $id));
      }

      if (is_null($token)) {
        $query->where(new Compare("user_id", $this->user->getId()));
      } else {
        $query->innerJoin("UserFileTokenFile", "UserFile.uid", "file_id")
          ->innerJoin("UserFileToken", "UserFileToken.uid", "token_id")
          ->where(new Compare("token", $token))
          ->where(new CondNull("valid_until"), new Compare("valid_until", $sql->now(), ">="));
      }
    }

    private static function unitToBytes($var) : int {
      if (is_int($var) || is_numeric($var)) {
        return intval($var);
      } else {
        preg_match("/(\\d+)([KMG])/", $var, $re);
        if ($re) {
          $units = ["K","M","G"];
          $value = intval($re[1]);
          $unitIndex = array_search($re[2], $units);
          return $value * pow(1024, $unitIndex + 1);
        } else {
          return -1; // some weird error here
        }
      }
    }

    protected function getMaxFileSizePHP() : int {
      $uploadMaxFilesize = $this->unitToBytes(ini_get("upload_max_filesize"));
      $postMaxSize = $this->unitToBytes(ini_get("post_max_size"));
      return min($uploadMaxFilesize, $postMaxSize);
    }

    protected function getMaxFiles() : int {
      return intval(ini_get("max_file_uploads"));
    }
  }
}

namespace Api\File {

  use Api\FileAPI;
  use Api\Parameter\ArrayType;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Condition\CondNull;
  use Objects\User;

  class ValidateToken extends FileAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'token' => new StringType('token', 36, false)
      ));
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $token = $this->getParam("token");
      $res = $sql->select("UserFile.uid", "valid_until", "token_type",
          "maxFiles", "maxSize", "extensions", "name", "path", "directory", "UserFile.parent_id as parentId")
        ->from("UserFileToken")
        ->leftJoin("UserFileTokenFile", "UserFileToken.uid", "token_id")
        ->leftJoin("UserFile", "UserFile.uid", "file_id")
        ->where(new Compare("token", $token))
        ->where(new CondNull("valid_until"), new Compare("valid_until", $sql->now(), ">="))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (empty($res)) {
          return $this->createError("Invalid Token");
        } else {
          $row = $res[0];
          $this->result["token"] = array(
            "type" => $row["token_type"],
            "valid_until" => $row["valid_until"]
          );

          $this->result["files"] = $this->createFileList($res);
          if ($row["token_type"] === "upload") {
            $maxFiles = ($row["maxFiles"] ?? 0);
            $maxSize  = ($row["maxSize"] ?? 0);

            $this->result["restrictions"] = array(
              "maxFiles" => ($maxFiles <= 0 ? $this->getMaxFiles() : min($this->getMaxFiles(), $maxFiles)),
              "maxSize"  => ($maxSize <= 0 ? $this->getMaxFileSizePHP() : min($this->getMaxFileSizePHP(), $maxSize)),
              "extensions" => $row["extensions"] ?? "",
              "parentId" => $row["parentId"] ?? 0
            );
          }
        }
      }

      return $this->success;
    }
  }

  class GetRestrictions extends FileAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->csrfTokenRequired = false;
      $this->loginRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $this->result["restrictions"] = array(
        "maxFiles" => $this->getMaxFiles(),
        "maxSize" => $this->getMaxFileSizePHP()
      );

      return true;
    }
  }

  class RevokeToken extends FileAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "token" => new StringType("token", 36)
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $token = $this->getParam("token");
      $res = $sql->select($sql->count())
        ->from("UserFileToken")
        ->where(new Compare("user_id", $this->user->getId()))
        ->where(new Compare("token", $token))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if(empty($res)) {
          return $this->createError("Invalid token");
        } else {
          $res = $sql->update("UserFileToken")
            ->set("valid_until", new \DateTime())
            ->where(new Compare("user_id", $this->user->getId()))
            ->where(new Compare("token", $token))
            ->execute();
          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }

  class ListFiles extends FileAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "directory" => new Parameter("directory", Parameter::TYPE_INT, true, null)
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select(
          "UserFile.uid", "UserFile.directory", "UserFile.path", "UserFile.name",
          "UserFile.user_id", "parentTable.uid as parentId", "parentTable.name as parentName")
        ->from("UserFile")
        ->leftJoin("UserFile", "UserFile.parent_id", "parentTable.uid", "parentTable")
        ->where(new Compare("UserFile.user_id", $this->user->getId()))
        ->orderBy("UserFile.parent_id")
        ->ascending()
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $files = $this->createFileList($res);

        $directoryId = $this->getParam("directory");
        if (!is_null($directoryId)) {
          $wantedDir =& $this->findDirectory($files, $directoryId);
          if ($files === $wantedDir) {
            $files = array();
          } else {
            $files = $wantedDir;
          }
        }

        $this->result["files"] = $files;
      }

      return $this->success;
    }
  }

  class ListTokens extends FileAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("uid","token","valid_until","token_type", "maxFiles", "maxSize", "extensions")
        ->from("UserFileToken")
        ->orderBy("valid_until")
        ->descending()
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["tokens"] = array();
        foreach ($res as $row) {
          $tokenType = $row["token_type"];
          if ($tokenType !== "upload") {
            unset($row["maxFiles"]);
            unset($row["maxSize"]);
            unset($row["extensions"]);
          }
          unset($row["token_type"]);
          $row["type"] = $tokenType;
          $this->result["tokens"][] = $row;
        }
      }

      return $this->success;
    }
  }

  class CreateDirectory extends FileAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'name' => new StringType('name', 32),
        'parentId' => new Parameter('parentId', Parameter::TYPE_INT, true, null)
      ));
      $this->loginRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $name = $this->getParam('name');
      $parentId = $this->getParam("parentId");
      if (!$this->checkDirectory($parentId)) {
        return $this->success;
      }

      $res = $sql->insert("UserFile", array("directory", "name", "user_id", "parent_id"))
        ->addRow(true, $name, $this->user->getId(), $parentId)
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      return $this->success;
    }
  }

  class Rename extends FileAPI { }

  class Move extends FileAPI { }

  class Upload extends FileAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'parentId' => new Parameter('parentId', Parameter::TYPE_INT, true, null),
        'token' => new StringType('token', 36, true, null)
      ));
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $token = $this->getParam("token");
      if (!$this->user->isLoggedIn() && is_null($token)) {
        return $this->createError("Permission denied (expected token)");
      }

      $sql = $this->user->getSQL();
      $parentId = $this->getParam("parentId");
      if (!is_null($token) && !is_null($parentId)) {
        return $this->createError("Cannot upload to parent directory using token");
      }

      if (!is_null($parentId) && !$this->checkDirectory($parentId)) {
        return $this->success;
      }

      $fileKeys = array_keys($_FILES);
      $numFilesUploaded = count($fileKeys);

      if (!is_null($token)) {

        $res = $sql->select("uid",  "token_type", "maxFiles", "maxSize", "extensions", "user_id", "parent_id")
          ->from("UserFileToken")
          ->where(new Compare("token", $token))
          ->where(new CondNull("valid_until"), new Compare("valid_until", $sql->now(), ">="))
          ->limit(1)
          ->execute();

        $this->success = ($res !== false);
        $this->lastError = $sql->getLastError();
        if (!$this->success) {
          return false;
        }

        if (empty($res) || $res[0]["token_type"] !== "upload") {
          return $this->createError("Permission denied (token)");
        }

        $parentId = $res[0]["parent_id"];
        $tokenId = $res[0]["uid"];
        $maxFiles = $res[0]["maxFiles"] ?? 0;
        $maxSize = $res[0]["maxSize"] ?? 0;
        $userId = $res[0]["user_id"];
        $extensions = explode(",", trim(strtolower($res[0]["extensions"] ?? "")));
        $extensions = array_filter($extensions);

        $res = $sql->select($sql->count())
          ->from("UserFileTokenFile")
          ->where(new Compare("token_id", $tokenId))
          ->execute();

        $this->success = ($res !== false);
        $this->lastError = $sql->getLastError();
        if (!$this->success) {
          return false;
        }

        $count = $res[0]["count"];
        if ($maxFiles > 0 && $numFilesUploaded > 0 && $numFilesUploaded + $count > $maxFiles) {
          return $this->createError("File limit exceeded. Currently uploaded $count / $maxFiles files");
        }

        if ($maxSize > 0 || !empty($extensions)) {
          foreach ($_FILES as $file) {
            $name = $file["name"];
            if ($maxSize > 0 && $file["size"] > $maxSize) {
              return $this->createError("File Size limit of $maxSize bytes exceeded for file $name");
            }

            $dotPos = strrpos($name, ".");
            $ext = ($dotPos !== false ? strtolower(substr($name, $dotPos + 1)) : false);
            if (!empty($extensions) && $ext !== false && !in_array($ext, $extensions)) {
              return $this->createError("File '$name' has prohibited extension. Allowed extensions: " . implode(",", $extensions));
            }
          }
        }
      } else {
        $userId = $this->user->getId();
      }

      if ($numFilesUploaded === 0) {
        return $this->createError("No file uploaded");
      }

      $uploadDir = realpath($_SERVER["DOCUMENT_ROOT"] . "/files/uploaded/");
      if (!is_writable($uploadDir)) {
        return $this->createError("Upload directory is not writable");
      }

      $fileIds = array();
      foreach ($_FILES as $key => $file) {
        $fileName = $file["name"];
        $tmpPath = $file["tmp_name"];
        if (!$tmpPath) {
          return $this->createError("Error uploading file: $fileName");
        }

        $md5Hash = @hash_file('md5', $tmpPath);
        $sha1Hash = @hash_file('sha1', $tmpPath);
        $filePath =  $uploadDir . "/" . $md5Hash . $sha1Hash;
        if (move_uploaded_file($tmpPath, $filePath)) {

          $res = $sql->insert("UserFile", array("name", "directory", "path", "user_id", "parent_id"))
            ->addRow($fileName, false, $filePath, $userId, $parentId)
            ->returning("uid")
            ->execute();

          if ($res === false) {
            $this->lastError = $sql->getLastError();
            $this->success = false;
            return false;
          } else {
            $fileIds[] = $sql->getLastInsertId();
          }
        } else {
          return $this->createError("Could not create file: " . $fileName);
        }
      }

      if (!is_null($token)) {
        $query = $sql->insert("UserFileTokenFile", array("file_id", "token_id"));
        foreach ($fileIds as $fileId) {
          $query->addRow($fileId, $tokenId);
        }

        $res = $query->execute();
        $this->success = ($res !== false);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }
  }

  class Download extends FileAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "id" => new ArrayType("id", Parameter::TYPE_INT, true),
        "token" => new StringType("token", 36, true, null)
      ));
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $token = $this->getParam("token");
      if (!$this->user->isLoggedIn() && is_null($token)) {
        return $this->createError("Permission denied (expected token)");
      }

      $sql = $this->user->getSQL();
      $fileIds = array_unique($this->getParam("id"));
      $query =  $sql->select("UserFile.uid", "path", "name", "directory")->from("UserFile");
      $this->filterFiles($sql, $query, $fileIds, $token);

      $res = $query->execute();
      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $foundFiles = array();
        foreach ($res as $row) {
          $foundFiles[$row["uid"]] = $row;
        }

        $filesToDownload = array();
        foreach ($fileIds as $fileId) {
          if (!array_key_exists($fileId, $foundFiles)) {
            if (is_null($token)) {
              return $this->createError("File not found: $fileId");
            } else {
              return $this->createError("Permission denied (token)");
            }
          } else if (!$foundFiles[$fileId]["directory"]) {
            $filesToDownload[] = $foundFiles[$fileId];
          }
        }

        if (empty($filesToDownload)) {
          return $this->createError("No file selected");
        } else if (count($filesToDownload) === 1) {
          $file = array_shift($filesToDownload);
          $path = $file["path"];
          $name = $file["name"];
          $this->downloadFile($name, $path);
        } else {
          $this->downloadZip($filesToDownload);
        }
      }

      return $this->success;
    }
  }

  class Delete extends FileAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "id" => new ArrayType("id", Parameter::TYPE_INT, true),
        "token" => new StringType("token", 36, true, null)
      ));
      $this->csrfTokenRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $token = $this->getParam("token");
      if (!$this->user->isLoggedIn() && is_null($token)) {
        return $this->createError("Permission denied (expected token)");
      }

      $sql = $this->user->getSQL();
      $fileIds = array_unique($this->getParam("id"));

      $query =  $sql->select("UserFile.uid", "path", "name")->from("UserFile");
      $this->filterFiles($sql, $query, $fileIds, $token);
      $res = $query->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (count($res) !== count($fileIds)) {
          $foundFiles = array_map(function ($row) { return $row["uid"]; }, $res);
          foreach($fileIds as $fileId) {
            if (!in_array($fileId, $foundFiles)) {
              return $this->createError("File not found: $fileId");
            }
          }
        } else {
          $res = $sql->delete("UserFile")
            ->where(new CondIn("uid", $fileIds))
            ->execute();

          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }

  class CreateUploadToken extends FileAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "maxFiles" => new Parameter("maxFiles", Parameter::TYPE_INT, true, 1),
        "maxSize"  => new Parameter("maxSize", Parameter::TYPE_INT, true, null),
        "extensions" => new StringType("extensions", 64, true, null),
        "durability" => new Parameter("durability", Parameter::TYPE_INT, true, 60*24*2),
        "parentId" => new Parameter("parentId", Parameter::TYPE_INT, true, null)
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $maxFiles = $this->getParam("maxFiles");
      $maxSize  = $this->getParam("maxSize");
      $extensions = $this->getParam("extensions");
      $durability = $this->getParam("durability");
      $parentId   = $this->getParam("parentId");

      if (!is_null($maxFiles) && $maxFiles < 0) {
        return $this->createError("Invalid number of maximum files.");
      }

      if (!is_null($maxSize) && $maxSize < 0) {
        return $this->createError("Invalid maximum size for uploaded files.");
      }

      if (!is_null($durability) && $durability < 0) {
        return $this->createError("Invalid durability.");
      }

      if (!is_null($extensions)) {
        $extensions = explode(",",$extensions);
        foreach ($extensions as $i => $ext) {
          if (strlen($ext) === 0 || (strlen($ext) === 1 && $ext[0] === ".")) {
            unset ($extensions[$i]);
          } else if ($ext[0] === ".") {
            $extensions[$i] = substr($ext, 1);
          }
        }
        $extensions = implode(",", $extensions);
      }

      if (!$this->checkDirectory($parentId)) {
        return $this->success;
      }

      $sql = $this->user->getSQL();
      $token = generateRandomString(36);
      $validUntil = $durability == 0 ? null : (new \DateTime())->modify("+$durability MINUTES");
      $res = $sql->insert("UserFileToken",
          array("token", "token_type", "maxSize", "maxFiles", "extensions", "valid_until", "user_id", "parent_id"))
        ->addRow($token, "upload", $maxSize, $maxFiles, $extensions, $validUntil, $this->user->getId(), $parentId)
        ->returning("uid")
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["token"] = $token;
        $this->result["tokenId"] = $sql->getLastInsertId();
      }

      return $this->success;
    }
  }

  class CreateDownloadToken extends FileAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "durability" => new Parameter("durability", Parameter::TYPE_INT, true, 60*24*2),
        "files" => new Parameter("files", Parameter::TYPE_ARRAY, false)
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $durability = $this->getParam("durability");
      $fileIds = $this->getParam("files");

      if (!is_null($durability) && $durability < 0) {
        return $this->createError("Invalid durability.");
      }

      foreach ($fileIds as $fileId) {
        if (!is_int($fileId) && is_numeric($fileId)) {
          $fileId = intval($fileId);
        }
        if (!is_int($fileId) || $fileId < 1) {
          return $this->createError("Invalid file id: $fileId");
        }
      }

      $fileIds = array_unique($fileIds);

      // Check for files:
      $sql = $this->user->getSQL();
      $res = $sql->select("uid")
        ->from("UserFile")
        ->where(new CondIn("uid", $fileIds))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      if (count($res) !== count($fileIds)) {
        $foundFiles = array_map(function ($row) { return $row["uid"]; }, $res);
        foreach ($fileIds as $fileId) {
          if (!in_array($fileId, $foundFiles)) {
            return $this->createError("File not found: $fileId");
          }
        }
      }

      // Insert
      $token = generateRandomString(36);
      $validUntil = $durability == 0 ? null : (new \DateTime())->modify("+$durability MINUTES");
      $res = $sql->insert("UserFileToken", array("token_type", "valid_until", "user_id", "token"))
        ->addRow("download", $validUntil, $this->user->getId(), $token)
        ->returning("uid")
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      $tokenId = $sql->getLastInsertId();
      $query = $sql->insert("UserFileTokenFile", array("token_id", "file_id"));
      foreach ($fileIds as $fileId) {
        $query->addRow($tokenId, $fileId);
      }

      $res = $query->execute();
      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["token"] = $token;
      }

      return $this->success;
    }
  }
}
