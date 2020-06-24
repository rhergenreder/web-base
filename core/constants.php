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