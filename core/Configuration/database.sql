--
-- API
--
CREATE TABLE IF NOT EXISTS User (
  `uid` INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(256) UNIQUE DEFAULT NULL,
  `name` VARCHAR(32) UNIQUE NOT NULL,
  `salt` varchar(16) NOT NULL,
  `password` varchar(64) NOT NULL,
  `uidLanguage` int(11) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS UserInvitation (
  `email` VARCHAR(256) NOT NULL,
  `token` VARCHAR(36) UNIQUE NOT NULL,
  `valid_until` DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS `Group` (
  `gid` INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(32) NOT NULL
);

INSERT INTO `Group` (gid, name) VALUES (1, "Default"), (2, "Administrator")
  ON DUPLICATE KEY UPDATE name=name;

CREATE TABLE IF NOT EXISTS UserGroup (
  `uid` INTEGER NOT NULL,
  `gid` INTEGER NOT NULL,
  UNIQUE(`uid`, `gid`)
);

CREATE TABLE Session IF NOT EXISTS (
  `uid`  int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `expires` timestamp NOT NULL,
  `uidUser` int(11) NOT NULL,
  `ipAddress` varchar(45) NOT NULL,
  `os` varchar(64) NOT NULL,
  `browser` varchar(64) NOT NULL
);

CREATE TABLE IF NOT EXISTS ApiKey (
  `uid` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `uidUser` int(11) NOT NULL,
  `api_key` VARCHAR(64) NOT NULL,
  `valid_until` DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS Language (
  `uid`  int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `code` VARCHAR(5) UNIQUE NOT NULL,
  `name` VARCHAR(32) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS ExternalSiteCache (
  `url` VARCHAR(256) PRIMARY KEY,
  `data` TEXT NOT NULL,
  `expires` TIMESTAMP DEFAULT NULL
);
