<?php

namespace Configuration;

use Driver\SQL\SQL;
use \Driver\SQL\Strategy\SetNullStrategy;
use \Driver\SQL\Strategy\CascadeStrategy;

class CreateDatabase {

  // NOTE:
  // explicit serial ids removed due to postgres' serial implementation

  public static function createQueries(SQL $sql) {
    $queries = array();

    // Language
    $queries[] = $sql->createTable("Language")
      ->addSerial("uid")
      ->addString("code", 5)
      ->addString("name", 32)
      ->primaryKey("uid")
      ->unique("code")
      ->unique("name");

    $queries[] = $sql->insert("Language", array("code", "name"))
      ->addRow( "en_US", 'American English')
      ->addRow( "de_DE", 'Deutsch Standard');

    $queries[] = $sql->createTable("User")
      ->addSerial("uid")
      ->addString("email", 64, true)
      ->addString("name", 32)
      ->addString("password", 128)
      ->addInt("language_id", true, 1)
      ->addDateTime("registered_at", false, $sql->currentTimestamp())
      ->primaryKey("uid")
      ->unique("email")
      ->unique("name")
      ->foreignKey("language_id", "Language", "uid", new SetNullStrategy());

    $queries[] = $sql->createTable("Session")
      ->addSerial("uid")
      ->addBool("active", true)
      ->addDateTime("expires")
      ->addInt("user_id")
      ->addString("ipAddress", 45)
      ->addString("os", 64)
      ->addString("browser", 64)
      ->addJson("data", false, '{}')
      ->addBool("stay_logged_in", true)
      ->addString("csrf_token", 16 )
      ->primaryKey("uid", "user_id")
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("UserInvitation")
      ->addString("username",32)
      ->addString("email",32)
      ->addString("token",36)
      ->addDateTime("valid_until");

    $queries[] = $sql->createTable("UserToken")
      ->addInt("user_id")
      ->addString("token", 36)
      ->addEnum("token_type", array("password_reset", "email_confirm"))
      ->addDateTime("valid_until")
      ->addBool("used", false)
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("Group")
      ->addSerial("uid")
      ->addString("name", 32)
      ->addString("color", 10)
      ->primaryKey("uid")
      ->unique("name");

    $queries[] = $sql->insert("Group", array("name", "color"))
      ->addRow(USER_GROUP_MODERATOR_NAME, "#007bff")
      ->addRow(USER_GROUP_SUPPORT_NAME, "#28a745")
      ->addRow(USER_GROUP_ADMIN_NAME, "#dc3545");

    $queries[] = $sql->createTable("UserGroup")
      ->addInt("user_id")
      ->addInt("group_id")
      ->unique("user_id", "group_id")
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy())
      ->foreignKey("group_id", "Group", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("Notification")
      ->addSerial("uid")
      ->addEnum("type", array("default","message","warning"), false, "default")
      ->addDateTime("created_at", false, $sql->currentTimestamp())
      ->addString("title", 32)
      ->addString("message", 256)
      ->primaryKey("uid");

    $queries[] = $sql->createTable("UserNotification")
      ->addInt("user_id")
      ->addInt("notification_id")
      ->addBool("seen", false)
      ->foreignKey("user_id", "User", "uid")
      ->foreignKey("notification_id", "Notification", "uid")
      ->unique("user_id", "notification_id");

    $queries[] = $sql->createTable("GroupNotification")
      ->addInt("group_id")
      ->addInt("notification_id")
      ->addBool("seen", false)
      ->foreignKey("group_id", "Group", "uid")
      ->foreignKey("notification_id", "Notification", "uid")
      ->unique("group_id", "notification_id");

    $queries[] = $sql->createTable("ApiKey")
      ->addSerial("uid")
      ->addInt("user_id")
      ->addBool("active", true)
      ->addString("api_key", 64)
      ->addDateTime("valid_until")
      ->primaryKey("uid")
      ->foreignKey("user_id", "User", "uid");

    $queries[] = $sql->createTable("Visitor")
      ->addInt("month")
      ->addInt("count", false, 1)
      ->addString("cookie", 26)
      ->unique("month", "cookie");

    $queries[] = $sql->createTable("Route")
      ->addSerial("uid")
      ->addString("request", 128)
      ->addEnum("action", array("redirect_temporary", "redirect_permanently", "static", "dynamic"))
      ->addString("target", 128)
      ->addString("extra", 64, true)
      ->addBool("active", true)
      ->primaryKey("uid");

    $queries[] = $sql->insert("Route", array("request", "action", "target", "extra"))
      ->addRow("^/admin(/.*)?$", "dynamic", "\\Documents\\Admin", NULL)
      ->addRow("^/register(/)?$", "dynamic", "\\Documents\\Account", "\\Views\\Account\\Register")
      ->addRow("^/confirmEmail(/)?$", "dynamic", "\\Documents\\Account", "\\Views\\Account\\ConfirmEmail")
      ->addRow("^/acceptInvite(/)?$", "dynamic", "\\Documents\\Account", "\\Views\\Account\\AcceptInvite")
      ->addRow("^/resetPassword(/)?$", "dynamic", "\\Documents\\Account", "\\Views\\Account\\ResetPassword")
      ->addRow("^/$", "static", "/static/welcome.html", NULL);

    $queries[] = $sql->createTable("Settings")
      ->addString("name", 32)
      ->addString("value", 1024, true)
      ->addBool("private", false) // these values are not returned from '/api/settings/get', but can be changed
      ->addBool("readonly", false) // these values are neither returned, nor can be changed from outside
      ->primaryKey("name");

    $settingsQuery = $sql->insert("Settings", array("name", "value", "private", "readonly"))
      // ->addRow("mail_enabled", "0") # this key will be set during installation
      ->addRow("mail_host", "", false, false)
      ->addRow("mail_port", "", false, false)
      ->addRow("mail_username", "", false, false)
      ->addRow("mail_password", "", true, false)
      ->addRow("mail_from", "", false, false)
      ->addRow("message_confirm_email", self::MessageConfirmEmail(), false, false)
      ->addRow("message_accept_invite", self::MessageAcceptInvite(), false, false)
      ->addRow("message_reset_password", self::MessageResetPassword(), false, false);

    (Settings::loadDefaults())->addRows($settingsQuery);
    $queries[] = $settingsQuery;

    $queries[] = $sql->createTable("ContactRequest")
      ->addSerial("uid")
      ->addString("from_name", 32)
      ->addString("from_email", 64)
      ->addString("message", 512)
      ->addDateTime("created_at", false, $sql->currentTimestamp())
      ->primaryKey("uid");

    $queries[] = $sql->createTable("ApiPermission")
      ->addString("method", 32)
      ->addJson("groups", true, '[]')
      ->primaryKey("method");

    $queries[] = $sql->insert("ApiPermission", array("method", "groups"))
      ->addRow("ApiKey/create", array())
      ->addRow("ApiKey/fetch", array())
      ->addRow("ApiKey/refresh", array())
      ->addRow("ApiKey/revoke", array())
      ->addRow("Contact/request", array())
      ->addRow("Groups/fetch", array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN))
      ->addRow("Groups/create", array(USER_GROUP_ADMIN))
      ->addRow("Groups/delete", array(USER_GROUP_ADMIN))
      ->addRow("Language/get", array())
      ->addRow("Language/set", array())
      ->addRow("Notifications/create", array(USER_GROUP_ADMIN))
      ->addRow("Notifications/fetch", array())
      ->addRow("Notifications/seen", array())
      ->addRow("Routes/fetch", array(USER_GROUP_ADMIN))
      ->addRow("Routes/save", array(USER_GROUP_ADMIN))
      ->addRow("sendTestMail", array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN))
      ->addRow("Settings/get", array(USER_GROUP_ADMIN))
      ->addRow("Settings/set", array(USER_GROUP_ADMIN))
      ->addRow("Stats", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT))
      ->addRow("User/create", array(USER_GROUP_ADMIN))
      ->addRow("User/fetch", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT))
      ->addRow("User/get", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT))
      ->addRow("User/info", array())
      ->addRow("User/invite", array(USER_GROUP_ADMIN))
      ->addRow("User/login", array())
      ->addRow("User/logout", array())
      ->addRow("User/register", array())
      ->addRow("User/checkToken", array())
      ->addRow("User/edit", array(USER_GROUP_ADMIN))
      ->addRow("User/delete", array(USER_GROUP_ADMIN));

    return $queries;
  }

  private static function MessageConfirmEmail() : string {
    return "Hello {{username}},<br>" .
      "You recently created an account on {{site_name}}. Please click on the following link to " .
      "confirm your email address and complete your registration. If you haven't registered an " .
      "account, you can simply ignore this email. The link is valid for the next 48 hours:<br><br> " .
      "<a href=\"{{link}}\">{{link}}</a><br><br> " .
      "Best Regards<br> " .
      "{{site_name}} Administration";
  }

  private static function MessageAcceptInvite() : string {
    return "Hello {{username}},<br>" .
      "You were invited to create an account on {{site_name}}. Please click on the following link to " .
      "confirm your email address and complete your registration by choosing a new password. " .
      "If you want to decline the invitation, you can simply ignore this email. The link is valid for the next 48 hours:<br><br>" .
      "<a href=\"{{link}}\">{{link}}</a><br><br>" .
      "Best Regards<br>" .
      "{{site_name}} Administration";
  }

  private static function MessageResetPassword() : string {
    return "Hello {{username}},<br>" .
      "you requested a password reset on {{sitename}}. Please click on the following link to " .
      "choose a new password. If this request was not intended, you can simply ignore the email. The Link is valid for one hour:<br><br>" .
      "<a href=\"{{link}}\">{{link}}</a><br><br>" .
      "Best Regards<br>" .
      "{{site_name}} Administration";
  }
}
