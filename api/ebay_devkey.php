<?php

if (isset($_POST['regi'])) {
  //バリデーション
  //条件を満たさない場合は、エラーメッセージを設定する
  //App ID
  if (isset($_POST['appid']) && !empty($_POST['appid'])) { //appidが存在して、かつ空でない場合
    if (preg_match('/^[a-zA-Z0-9-]+$/', $_POST['appid'])) { //正規表現で半角英数とハイフン以外のものがつかわれていないかチェック
      $appid = $_POST['appid'];
      $appid_flag = true; //最終チェック用フラグ
    }else {
      $ebay_devkey_error[] = 'App IDが正しくない形式です。ご確認ください。';
    }
  }else { //変数が存在しない、または空の場合
    $ebay_devkey_error[] = 'App IDが入力されていません。ご確認ください。';
  }

  //Dev ID
  if (isset($_POST['devid']) && !empty($_POST['devid'])) { //devidが存在して、かつ空でない場合
    if (preg_match('/^[a-z0-9-]+$/', $_POST['devid'])) { //正規表現で半角英字（小文字）と数字とハイフン以外のものがつかわれていないかチェック
      $devid = $_POST['devid'];
      $devid_flag = true; //最終チェック用フラグ
    }else {
      $ebay_devkey_error[] = 'Dev IDが正しくない形式です。ご確認ください。';
    }
  }else { //変数が存在しない、または空の場合
    $ebay_devkey_error[] = 'Dev IDが入力されていません。ご確認ください。';
  }

  //Cert ID
  if (isset($_POST['certid']) && !empty($_POST['certid'])) { //certidが存在して、かつ空でない場合
    if (preg_match('/^[a-zA-Z0-9-]+$/', $_POST['certid'])) { //正規表現で半角英数とハイフン以外のものがつかわれていないかチェック
      $certid = $_POST['certid'];
      $certid_flag = true; //最終チェック用フラグ
    }else {
      $ebay_devkey_error[] = 'Cert IDが正しくない形式です。ご確認ください。';
    }
  }else { //変数が存在しない、または空の場合
    $ebay_devkey_error[] = 'Cert IDが入力されていません。ご確認ください。';
  }

  //RuName
  if (isset($_POST['runame']) && !empty($_POST['runame'])) { //runameが存在して、かつ空でない場合
    if (preg_match('/^[-0-9a-zA-Z_.]+$/', $_POST['runame'])) { //正規表現で半角英数とピリオド、ハイフン以外のものがつかわれていないかチェック
      $runame = $_POST['runame'];
      $runame_flag = true; //最終チェック用フラグ
    }else {
      $ebay_devkey_error[] = 'RuNameが正しくない形式です。ご確認ください。';
    }
  }else { //変数が存在しない、または空の場合
    $ebay_devkey_error[] = 'RuNameが入力されていません。ご確認ください。';
  }

  //エラーメッセージが設定されてい場合
  //エラーメッセージを表示用に定義しなおす。
  //設定ページに戻る。
  if (isset($ebay_devkey_error) && !empty($ebay_devkey_error['0'])) {
    $_SESSION['ebay_devkey_error'] = implode('<br>', $ebay_devkey_error);
    header('Location: ../setting.php');
    exit;
  }

  //最終チェックフラグを確認し、すべて真ならクッキーをセットする
  if ($appid_flag && $devid_flag && $certid_flag && $runame_flag) {
    setcookie("ebay_appid", $appid, time()+15552000, '/', $_SERVER['SERVER_NAME']);
    setcookie("ebay_devid", $devid, time()+15552000, '/', $_SERVER['SERVER_NAME']);
    setcookie("ebay_certid", $certid, time()+15552000, '/', $_SERVER['SERVER_NAME']);
    setcookie("ebay_runame", $runame, time()+15552000, '/', $_SERVER['SERVER_NAME']);
    setcookie("ebay_apikey_expire", date('Y/m/d H:i:s',time()+15552000), time()+15552000, '/', $_SERVER['SERVER_NAME']);
    header('Location: ../setting.php');
    exit;
  }else { //すべて真でなければ、エラーメッセージを添えて設定ページに戻る
    $_SESSION['ebay_devkey_error'] = 'ebay Developer API Keyが正常に登録されませんでした。';
    header('Location: ../setting.php');
    exit;
  }
}

if (isset($_POST['del'])) {
  setcookie("ebay_appid", $appid, time()-3600, '/', $_SERVER['SERVER_NAME']);
  setcookie("ebay_devid", $devid, time()-3600, '/', $_SERVER['SERVER_NAME']);
  setcookie("ebay_certid", $certid, time()-3600, '/', $_SERVER['SERVER_NAME']);
  setcookie("ebay_runame", $runame, time()-3600, '/', $_SERVER['SERVER_NAME']);
  setcookie("ebay_apikey_expire", date('Y/m/d H:i:s',time()+15552000), time()-3600, '/', $_SERVER['SERVER_NAME']);
  header('Location: ../setting.php');
  exit;
}
