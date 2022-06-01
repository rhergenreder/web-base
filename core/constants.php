<?php

const USER_GROUP_MODERATOR = 1;
const USER_GROUP_MODERATOR_NAME = "Moderator";
const USER_GROUP_SUPPORT = 2;
const USER_GROUP_SUPPORT_NAME = "Support";
const USER_GROUP_ADMIN = 3;
const USER_GROUP_ADMIN_NAME = "Administrator";

const DEFAULT_GROUPS = array(
  USER_GROUP_MODERATOR, USER_GROUP_SUPPORT, USER_GROUP_ADMIN
);

function GroupName($index) {
  $groupNames = array(
    USER_GROUP_MODERATOR => USER_GROUP_MODERATOR_NAME,
    USER_GROUP_SUPPORT => USER_GROUP_SUPPORT_NAME,
    USER_GROUP_ADMIN => USER_GROUP_ADMIN_NAME,
  );

  return ($groupNames[$index] ?? "Unknown Group");
}

// adapted from https://www.php.net/manual/en/function.http-response-code.php
const HTTP_STATUS_DESCRIPTIONS = [
  100 => 'Continue',
  101 => 'Switching Protocols',
  200 => 'OK',
  201 => 'Created',
  202 => 'Accepted',
  203 => 'Non-Authoritative Information',
  204 => 'No Content',
  205 => 'Reset Content',
  206 => 'Partial Content',
  300 => 'Multiple Choices',
  301 => 'Moved Permanently',
  302 => 'Moved Temporarily',
  303 => 'See Other',
  304 => 'Not Modified',
  305 => 'Use Proxy',
  400 => 'Bad Request',
  401 => 'Unauthorized',
  402 => 'Payment Required',
  403 => 'Forbidden',
  404 => 'Not Found',
  405 => 'Method Not Allowed',
  406 => 'Not Acceptable',
  407 => 'Proxy Authentication Required',
  408 => 'Request Time-out',
  409 => 'Conflict',
  410 => 'Gone',
  411 => 'Length Required',
  412 => 'Precondition Failed',
  413 => 'Request Entity Too Large',
  414 => 'Request-URI Too Large',
  415 => 'Unsupported Media Type',
  418 => 'I\'m a teapot',
  500 => 'Internal Server Error',
  501 => 'Not Implemented',
  502 => 'Bad Gateway',
  503 => 'Service Unavailable',
  504 => 'Gateway Time-out',
  505 => 'HTTP Version not supported',
];