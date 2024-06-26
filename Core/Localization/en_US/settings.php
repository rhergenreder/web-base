<?php

return [
  "title" => "Settings",
  "information" => "Information",
  "disabled" => "Disabled",
  "enabled" => "Enabled",

  # API Key
  "api_key" => "API Key",
  "valid_until" => "Valid until",
  "token" => "Token",
  "request_new_key" => "Request new Key",
  "show_only_active_keys" => "Show only active keys",
  "no_api_key_registered" => "No valid API-Keys registered",

  # settings
  "key" => "Key",
  "value" => "Value",
  "general" => "General",
  "mail" => "Mail",
  "captcha" => "Captcha",
  "uncategorized" => "Uncategorized",

  # general settings
  "site_name" => "Site Name",
  "mail_contact" => "Contact mail address",
  "base_url" => "Base URL",
  "user_registration_enabled" => "Allow user registration",
  "allowed_extensions" => "Allowed file extensions",
  "trusted_domains" => "Trusted origin domains (* as subdomain-wildcard)",
  "time_zone" => "Time zone",
  "mail_contact_gpg_key" => "Contact GPG key",
  "no_gpg_key_configured" => "No GPG key configured yet",

  # mail settings
  "mail_enabled" => "Enable e-mail transport",
  "mail_from" => "Sender e-mail address",
  "mail_host" => "Mail server host",
  "mail_port" => "Mail server port",
  "mail_username" => "Mail server username",
  "mail_password" => "Mail server password",
  "mail_footer" => "Path to e-mail footer",
  "mail_async" => "Send e-mails asynchronously (requires a cron-job)",
  "mail_address" => "Mail address",
  "send_test_email" => "Send test e-mail",

  # captcha
  "captcha_provider" => "Captcha Provider",
  "captcha_site_key" => "Captcha Site Key",
  "captcha_secret_key" => "Secret Captcha Key",
  "recaptcha" => "Google reCaptcha",
  "hcaptcha" => "hCaptcha",

  # redis
  "rate_limit" => "Rate Limiting",
  "rate_limiting_enabled" => "Rate Limiting enabled",
  "redis_host" => "Redis host",
  "redis_port" => "Redis port",
  "redis_password" => "Redis password",
  "redis_test" => "Test Connection",
  "redis_test_error" => "Redis Connection failed, check your credentials.",
  "redis_test_success" => "Redis Connection successfully established.",

  # dialog
  "fetch_settings_error" => "Error fetching settings",
  "save_settings_success" => "Settings saved successfully",
  "save_settings_error" => "Error saving settings",
  "send_test_email_error" => "Error sending test email",
  "send_test_email_success" => "Test email successfully sent. Please check your inbox!",
  "remove_gpg_key_error" => "Error removing GPG key",
  "remove_gpg_key" => "Remove GPG key",
  "remove_gpg_key_text" => "Do you really want to remove this gpg key?",
  "import_gpg_key_error" => "Error importing GPG key",
];