<?php

return [
  "title" => "Einstellungen",
  "information" => "Informationen",
  "disabled" => "Deaktiviert",
  "enabled" => "Aktiviert",

  # API Key
  "api_key" => "API Schlüssel",
  "valid_until" => "Gültig bis",
  "token" => "Token",
  "request_new_key" => "Neuen Schlüssel anfordern",
  "show_only_active_keys" => "Zeige nur aktive Schlüssel",
  "no_api_key_registered" => "Keine gültigen API-Schlüssel registriert",

  # settings
  "key" => "Schlüssel",
  "value" => "Wert",
  "general" => "Allgemein",
  "mail" => "Mail",
  "captcha" => "Captcha",
  "uncategorized" => "Unkategorisiert",

  # general settings
  "site_name" => "Seitenname",
  "mail_contact" => "Kontakt E-Mailadresse",
  "base_url" => "Basis URL",
  "user_registration_enabled" => "Benutzerregistrierung erlauben",
  "allowed_extensions" => "Erlaubte Dateierweiterungen",
  "trusted_domains" => "Vertraute Ursprungs-Domains (* als Subdomain-Wildcard)",
  "time_zone" => "Zeitzone",
  "mail_contact_gpg_key" => "Kontakt GPG-Schlüssel",
  "no_gpg_key_configured" => "Noch kein GPG-Schlüssel konfiguriert",

  # mail settings
  "mail_enabled" => "E-Mail Versand aktiviert",
  "mail_from" => "Absender E-Mailadresse",
  "mail_host" => "Mail-Server Host",
  "mail_port" => "Mail-Server Port",
  "mail_username" => "Mail-Server Benutzername",
  "mail_password" => "Mail-Server Passwort",
  "mail_footer" => "Pfad zum E-Mail-Footer",
  "mail_async" => "E-Mails asynchron senden (erfordert einen Cron-Job)",
  "mail_address" => "E-Mail Adresse",
  "send_test_email" => "Test E-Mail senden",

  # captcha
  "captcha_provider" => "Captcha Anbieter",
  "captcha_site_key" => "Öffentlicher Captcha Schlüssel",
  "captcha_secret_key" => "Geheimer Captcha Schlüssel",
  "recaptcha" => "Google reCaptcha",
  "hcaptcha" => "hCaptcha",

  # redis
  "rate_limit" => "Rate-Limit",
  "rate_limiting_enabled" => "Rate-Limiting aktiviert",
  "redis_host" => "Redis Host",
  "redis_port" => "Redis Port",
  "redis_password" => "Redis Passwort",
  "redis_test" => "Verbindung testen",
  "redis_test_error" => "Redis-Verbindung fehlgeschlagen, überprüfen Sie die Daten.",
  "redis_test_success" => "Redis-Verbindung erfolgreich aufgebaut.",

  # dialog
  "fetch_settings_error" => "Fehler beim Holen der Einstellungen",
  "save_settings_success" => "Einstellungen erfolgreich gespeichert",
  "save_settings_error" => "Fehler beim Speichern der Einstellungen",
  "send_test_email_error" => "Fehler beim Senden der Test E-Mail",
  "send_test_email_success" => "Test E-Mail erfolgreich versendet, überprüfen Sie Ihren Posteingang!",
  "remove_gpg_key_error" => "Fehler beim Entfernen des GPG-Schlüssels",
  "remove_gpg_key" => "GPG-Schlüssel entfernen",
  "remove_gpg_key_text" => "Möchten Sie wirklich diesen GPG-Schlüssel entfernen?",
  "import_gpg_key_error" => "Fehler beim Importieren des GPG-Schlüssels",
];