--
-- API
--
CREATE TABLE IF NOT EXISTS Language (
  `uid`  int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `code` VARCHAR(5) UNIQUE NOT NULL,
  `name` VARCHAR(32) UNIQUE NOT NULL
);

INSERT INTO Language (`uid`, `code`, `name`) VALUES
  (1, 'en_US', 'American English'),
  (2, 'de_DE', 'Deutsch Standard')
  ON DUPLICATE KEY UPDATE name=name;

CREATE TABLE IF NOT EXISTS User (
  `uid` INTEGER NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(64) UNIQUE DEFAULT NULL,
  `name` VARCHAR(32) UNIQUE NOT NULL,
  `salt` varchar(16) NOT NULL,
  `password` varchar(64) NOT NULL,
  `language_id` int(11) DEFAULT 1,
  PRIMARY KEY (`uid`),
  FOREIGN KEY (`language_id`) REFERENCES `Language` (`uid`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS UserInvitation (
  `email` VARCHAR(64) NOT NULL,
  `token` VARCHAR(36) UNIQUE NOT NULL,
  `valid_until` DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS UserToken (
  `user_id` INTEGER NOT NULL,
  `token` VARCHAR(36) NOT NULL,
  `type` ENUM('password_reset', 'confirmation') NOT NULL,
  `valid_until` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `User` (`uid`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `Group` (
  `gid` INTEGER NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`gid`),
  UNIQUE (`name`)
);

INSERT INTO `Group` (gid, name) VALUES (1, "Default"), (2, "Administrator")
  ON DUPLICATE KEY UPDATE name=name;

CREATE TABLE IF NOT EXISTS UserGroup (
  `uid` INTEGER NOT NULL,
  `gid` INTEGER NOT NULL,
  UNIQUE (`uid`, `gid`),
  FOREIGN KEY (`uid`) REFERENCES `User` (`uid`),
  FOREIGN KEY (`gid`) REFERENCES `Group` (`gid`)
);

CREATE TABLE IF NOT EXISTS Session (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `expires` timestamp NOT NULL,
  `user_id` int(11) NOT NULL,
  `ipAddress` varchar(45) NOT NULL,
  `os` varchar(64) NOT NULL,
  `browser` varchar(64) NOT NULL,
  `data` JSON NOT NULL DEFAULT '{}',
  `stay_logged_in` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`uid`),
  FOREIGN KEY (`user_id`) REFERENCES `User` (`uid`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ApiKey (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `api_key` VARCHAR(64) NOT NULL,
  `valid_until` DATETIME NOT NULL,
  PRIMARY KEY (`uid`),
  FOREIGN KEY (`user_id`) REFERENCES `User` (`uid`)
);

CREATE TABLE IF NOT EXISTS ExternalSiteCache (
  `url` VARCHAR(256) UNIQUE,
  `data` TEXT NOT NULL,
  `expires` DATETIME DEFAULT NULL,
);
