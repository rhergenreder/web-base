<?php

const USER_GROUP_DEFAULT = 1;
const USER_GROUP_DEFAULT_NAME = "Default";
const USER_GROUP_ADMIN = 2;
const USER_GROUP_ADMIN_NAME = "Administrator";

function GroupName($index) {
  $groupNames = array(
    USER_GROUP_DEFAULT => USER_GROUP_DEFAULT_NAME,
    USER_GROUP_ADMIN => USER_GROUP_ADMIN_NAME,
  );

  return ($groupNames[$index] ?? "Unknown Group");
}