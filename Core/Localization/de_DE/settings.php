<?php

return [
  "title" => "Einstellungen",
  "information" => "Informationen",

  # API Key
  "api_key" => "API Schlüssel",
  "valid_until" => "Gültig bis",
  "token" => "Token",
  "request_new_key" => "Neuen Schlüssel anfordern",
  "show_only_active_keys" => "Zeige nur aktive Schlüssel",
  "no_api_key_registered" => "Keine gültigen API-Schlüssel registriert",

  # GPG Key
  "gpg_key_placeholder_text" => "GPG-Key im ASCII format reinziehen oder einfügen...",

  # 2fa
  "register_2fa_device" => "Ein 2FA-Gerät registrieren",
  "register_2fa_totp_text" => "Scan den QR-Code mit einem Gerät, das du als Zwei-Faktor-Authentifizierung (2FA) benutzen willst. " .
                              "Unter Android kannst du den Google Authenticator benutzen.",
  "register_2fa_fido_text" => "Möglicherweise musst du mit dem Gerät interagieren, zum Beispiel durch Eingeben einer PIN oder durch Berühren des Geräts",
  "remove_2fa" => "2FA-Token entfernen",
  "remove_2fa_text" => "Gib dein aktuelles Passwort ein um das Entfernen des 2FA-Tokens zu bestätigen",

  # settings
  "key" => "Schlüssel",
  "value" => "Wert",
  "general" => "Allgemein",
  "mail" => "Mail",
  "recaptcha" => "reCaptcha",
  "uncategorized" => "Unkategorisiert",
  "unchanged" => "Unverändert",

  # general settings
  "site_name" => "Seitenname",
  "base_url" => "Basis URL",
  "user_registration_enabled" => "Benutzerregistrierung erlauben",
  "allowed_extensions" => "Erlaubte Dateierweiterungen",
  "time_zone" => "Zeitzone",

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

  # recaptcha
  "recaptcha_enabled" => "Aktiviere Google reCaptcha",
  "recaptcha_public_key" => "reCaptcha öffentlicher Schlüssel",
  "recaptcha_private_key" => "reCaptcha privater Schlüssel",

  # dialog
  "fetch_settings_error" => "Fehler beim Holen der Einstellungen",
  "save_settings_success" => "Einstellungen erfolgreich gespeichert",
  "save_settings_error" => "Fehler beim Speichern der Einstellungen",
  "send_test_email_error" => "Fehler beim Senden der Test E-Mail",
  "send_test_email_success" => "Test E-Mail erfolgreich versendet, überprüfen Sie Ihren Posteingang!",
];