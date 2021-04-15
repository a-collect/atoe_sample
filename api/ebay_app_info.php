<?php

$debug = true; //デバックモード用のキー（デバッグ時にはtrue）

if (!isset($_SESSION['user_apikey_flag']) || $_SESSION['user_apikey_flag'] !== 1) {
  $appID  = 'Acollect-ATOE-PRD-1786e1828-9c1d3dd6';   // different from prod keys atoeinfo@gmail.com
  $devID  = '79d1b115-dd1d-4baf-a978-f439432862ff';   // insert your devID for sandbox
  $certID = 'PRD-786e18280aa5-e807-41ac-8f7e-f547';   // need three keys and one token
  $runame = 'A-collect_Co._L-Acollect-ATOE-P-fbknz';  // sandbox runame
}elseif (isset($_SESSION['user_apikey_flag']) && $_SESSION['user_apikey_flag'] === 1) {
  if (isset($_COOKIE["ebay_appid"]) && !empty($_COOKIE["ebay_appid"])) {
    $appID  = $_COOKIE["ebay_appid"];   // different from prod keys
  }else {
    $appID  = '';
  }
  if (isset($_COOKIE["ebay_devid"]) && !empty($_COOKIE["ebay_devid"])) {
    $devID  = $_COOKIE["ebay_devid"];   // insert your devID for sandbox
  }else {
    $devID  = '';
  }
  if (isset($_COOKIE["ebay_certid"]) && !empty($_COOKIE["ebay_certid"])) {
    $certID = $_COOKIE["ebay_certid"];   // need three keys and one token
  }else {
    $certID  = '';
  }
  if (isset($_COOKIE["ebay_runame"]) && !empty($_COOKIE["ebay_runame"])) {
    $runame = $_COOKIE["ebay_runame"];  // runame
  }else {
    $runame  = '';
  }
}else {
  $appID  = '';   // different from prod keys
  $devID  = '';   // insert your devID for sandbox
  $certID = '';   // need three keys and one token
  $runame = '';  // sandbox runame
}

$runame_for_admin = 'A-collect_Co._L-Acollect-ATOE-P-twgacq';
$base_url_new = 'https://api.ebay.com/'; //新API用
$base_url_old = 'https://api.ebay.com/ws/api.dll'; //旧API用 Trading API
$category_baseurl = 'https://api.ebay.com/commerce/taxonomy/v1_beta/'; //新APIから
$catalog_url = 'http://open.api.ebay.com/shopping'; //旧APIから
$search_base_url_old = "https://svcs.ebay.com/services/search/FindingService/v1";
$newcategory_baseurl = 'https://api.ebay.com/commerce/taxonomy/v1/category_tree/'; // 2020/10/15 New APIから
$notificationUrl = 'https://api.ebay.com/commerce/notification/v1/public_key/'; //2021/04/20 Notifications api request
