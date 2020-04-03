<?php

function setTimezone($default) {
  $timezone = "";
  if (is_link("/etc/localtime")) {
    $filename = readlink("/etc/localtime");
    $pos = strpos($filename, "zoneinfo");
    if ($pos) {
      $timezone = substr($filename, $pos + strlen("zoneinfo/"));
    } else {
      $timezone = $default;
    }
  } else {
    $timezone = file_get_contents("/etc/timezone");
    if (!strlen($timezone)) {
      $timezone = $default;
    }
  }
  date_default_timezone_set($timezone);
}

setTimezone("UTC");

function getFirstWeekDayOfMonth($d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  $dt = new DateTime($d);
  $dt->modify('first day of this month');
  return intval($dt->format('N'));
}

function getDayCount($d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  $dt = new DateTime($d);
  $dt->modify('last day of this month');
  return intval($dt->format('j'));
}

function prevMonth($d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  $dt = new DateTime($d);
  $dt->modify('previous month');
  return $dt->format('Y-m-d H:i:s');
}

function prevDay($d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  $dt = new DateTime($d);
  $dt->modify('previous day');
  return $dt->format('Y-m-d H:i:s');
}

function nextDay($d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  $dt = new DateTime($d);
  $dt->modify('next day');
  return $dt->format('Y-m-d H:i:s');
}

function nextMonth($d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  $dt = new DateTime($d);
  $dt->modify('next month');
  return $dt->format('Y-m-d H:i:s');
}

function formatPeriod($d1, $d2) {
  $timeStr = dateFunction('d.m.Y H:i', $d1);
  if($timeStr == dateFunction('d.m.Y H:i', $d2)) {
    if(dateFunction('H:i', $d1) === '00:00') {
      return dateFunction('d.m.Y', $d1);
    }
  } else if(dateFunction('d.m.Y', $d1) !== dateFunction('d.m.Y', $d2)) {
    $timeStr .= " - " . dateFunction('d.m.Y H:i', $d2);
  } elseif(dateFunction('H:i', $d1) !== dateFunction('H:i', $d2)) {
    $timeStr .= " - " . dateFunction('H:i', $d2);
  }
  return $timeStr;
}

function now() { return new DateTime(); }
function getTime($d = NULL) { return dateFunction('H:i', $d); }
function getHour($d = NULL) { return dateFunction('H', $d); }
function getMinute($d = NULL) { return dateFunction('i', $d); }
function getYear($d = NULL) { return intval(dateFunction('Y', $d)); }
function getMonth($d = NULL) { return intval(dateFunction('n', $d)); }
function getDay($d = NULL) { return intval(dateFunction('d', $d)); }
function getDayOfWeek($d = NULL) { return intval(dateFunction('N', $d)); }
function getDayNumberOfWeek($d = NULL) { return intval(dateFunction('N', $d)); }
function getDateTime($d = NULL) { return dateFunction('Y-m-d H:i:s', $d); }
function getFirstDay($d = NULL) { return dateFunction('Y-m-01', $d); }
function getLastDay($d = NULL) { return dateFunction('t', $d); }
function getPrevMonth($d = NULL) { return dateFunction("-1 month", $d); }
function getLocalizedDateTime($d = NULL) { return dateFunction(L('Y/m/d H:i'), $d); }

function getJavascriptDate($d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  if(!is_a($d, "DateTime")) $d = new DateTime($d);
  $year = getYear($d);
  $month = getMonth($d) - 1;
  $day = getDay($d);
  $hour = getHour($d);
  $minute = getMinute($d);

  return "new Date($year, $month, $day, $hour, $minute)";
}

function getWeekDayName($day) {
  $aWeekDays = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
  return L($aWeekDays[$day - 1]);
}

function getMonthName($month) {
  $aMonthNames = array("January", "February", "March", "April", "May", "Juni", "July", "August", "September", "October", "November", "December");
  return L($aMonthNames[$month - 1]);
}

function isInPast($d) {
  $now = date('Y-m-d H:i:s');
  if(is_a($d, "DateTime")) $d = $d->format('Y-m-d H:i:s');
  return (strtotime($d) < strtotime($now));
}

function datetimeDiff($d1, $d2) {
  if(!($d1 instanceof DateTime)) $d1 = new DateTime($d1);
  if(!($d2 instanceof DateTime)) $d2 = new DateTime($d2);
  return $d2->getTimestamp() - $d1->getTimestamp();
}

function durationIn($d1, $d2, $str) {
  if(!($d1 instanceof DateTime)) $d1 = new DateTime($d1);
  if(!($d2 instanceof DateTime)) $d2 = new DateTime($d2);
  $interval = $d1->diff($d2);
  return intval($interval->format($str));
}

function dateFunction($str, $d = NULL) {
  if(is_null($d)) $d = date('Y-m-d H:i:s');
  if(!is_a($d, "DateTime")) $d = new DateTime($d);
  return $d->format($str);
}

function getPeriodString($d) {
  if(!is_a($d, "DateTime")) $d = new DateTime($d);
  $diff = datetimeDiff(new DateTime(), $d);
  $diff = abs($diff);


  if ($diff < 60) {
    $str = "< %d min";
    $diff = 1;
  } else if($diff < 60*60) {
    $diff = intval($diff / 60);
    $str = "%d min.";
  } else if($diff < 60*60*24) {
    $diff = intval($diff / (60*60));
    $str = "%d h.";
  } else {
    $diff = intval($diff / (60*60*24));
    $str = "%d d.";
  }

  return L(sprintf($str, $diff));
}
