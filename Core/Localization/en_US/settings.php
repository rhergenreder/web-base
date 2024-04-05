<?php

return [
  "title" => "Settings",
  "information" => "Information",

  # API Key
  "api_key" => "API Key",
  "valid_until" => "Valid until",
  "token" => "Token",
  "request_new_key" => "Request new Key",
  "show_only_active_keys" => "Show only active keys",
  "no_api_key_registered" => "No valid API-Keys registered",

  # GPG Key
  "gpg_key_placeholder_text" => "Paste or drag'n'drop your GPG-Key in ASCII format...",

  # 2fa
  "register_2fa_device" => "Register a 2FA-Device",
  "register_2fa_totp_text" => "Scan the QR-Code with a device you want to use for Two-Factor-Authentication (2FA). " .
    "On Android, you can use the Google Authenticator.",
  "register_2fa_fido_text" => "You may need to interact with your Device, e.g. typing in your PIN or touching to confirm the registration.",
  "remove_2fa" => "Remove 2FA Token",
  "remove_2fa_text" => "Enter your current password to confirm the removal of your 2FA Token",

  # settings
  "key" => "Key",
  "value" => "Value",
  "general" => "General",
  "mail" => "Mail",
  "recaptcha" => "reCaptcha",
  "uncategorized" => "Uncategorized",
  "unchanged" => "Unchanged",

  # general settings
  "site_name" => "Site Name",
  "base_url" => "Base URL",
  "user_registration_enabled" => "Allow user registration",
  "allowed_extensions" => "Allowed file extensions",
  "time_zone" => "Time zone",

  # mail settings
  "mail_enabled" => "Enable e-mail transport",
  "mail_from" => "Sender e-mail address",
  "mail_host" => "Mail server host",
  "mail_port" => "Mail server port",
  "mail_username" => "Mail server username",
  "mail_password" => "Mail server password",
  "mail_footer" => "Path to e-mail footer",

  # recaptcha
  "recaptcha_enabled" => "Enable Google reCaptcha",
  "recaptcha_public_key" => "reCaptcha Public Key",
  "recaptcha_private_key" => "reCaptcha Private Key",

  # dialog
  "fetch_settings_error" => "Error fetching settings",
  "save_settings_success" => "Settings saved successfully",
  "save_settings_error" => "Error saving settings",
];