<?php
  //ebay APIのアプリケーション認証情報を記述したファイルを読み込む
  require_once('./ebay_app_info.php');

  //メンテナンスモードのファイル読み込み
  require_once('../maintenance/setting.php');

  $_SESSION['code'] = $_GET['code'];
  $token_url = $base_url_new.'identity/v1/oauth2/token';
  $authorization =  base64_encode($appID.':'.$certID);

  //ユーザートークンの取得要求
  $request_body = [
    'grant_type' => 'authorization_code',
    'code' => $_SESSION['code'],
    'redirect_uri' => $runame
  ];

  $request_header = [
    'Content-Type:application/x-www-form-urlencoded',
    'Authorization:Basic '.$authorization
  ];


  if (!isset($_SESSION['user_apikey_flag']) || $_SESSION['user_apikey_flag'] === 0) {
    // セマフォIDの取得
    $res = sem_get(ftok(__FILE__, 'g'), 1);

    // セマフォを得る
    if (!sem_acquire($res)) {
      throw new Exception('sem_acquire failed');
    }
  }

  $curl = curl_init();

  curl_setopt($curl, CURLOPT_URL, $token_url);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($request_body));
  curl_setopt($curl, CURLOPT_HTTPHEADER, $request_header);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HEADER, true);

  $response = curl_exec($curl);

  $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
  $response_header = substr($response, 0, $header_size);
  $response_body = substr($response, $header_size);
  $result = $response_body;

  curl_close($curl);
  // echo "<pre>";
  // echo '結果：';
  // print_r($result);
  // echo "</pre>";

    if ($json_obj = json_decode($result,true)) {
      // if ($debug) {
      //   echo "json_decodeできたよ！";
      // }
    }else {
      if ($debug) {
        echo "json_decodeできなかったよ……";
      }
    }

    if (isset($json_obj['access_token'])) {
      $_SESSION['ebay_token'] = $json_obj['access_token'];
      $_SESSION['ebay_expires_date'] = date('Y-m-d H:i:s', strtotime($json_obj['expires_in'].'second'));
      $refresh_token = $json_obj['refresh_token'];
      $refresh_token_expires_in = date('Y-m-d H:i:s', strtotime($json_obj['refresh_token_expires_in'].'second'));
    }else {
      $_SESSION['ebay_token'] = 'ユーザートークン取得できてないよ';
    }
    
    //ユーザー名の取得
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $base_url_old,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => "<?xml version=\"1.0\" encoding=\"utf-8\"?> \r\n
      <GetUserRequest xmlns=\"urn:ebay:apis:eBLBaseComponents\">\r\n\t
      <ErrorLanguage>en_US</ErrorLanguage>\r\n\t
      <WarningLevel>High</WarningLevel>\r\n
      <DetailLevel>ReturnAll</DetailLevel>\r\n
      </GetUserRequest> ",
      CURLOPT_HTTPHEADER => array(
        "Cache-Control: no-cache",
        "Content-Type: application/xml",
        "X-EBAY-API-CALL-NAME: GetUser",
        "x-ebay-api-compatibility-level: 1091",
        "X-EBAY-API-IAF-TOKEN: ".$_SESSION['ebay_token'],
        "X-EBAY-API-SITEID: 0"
      ),
    ));

    $user_response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      $user_response = simplexml_load_string($user_response);
      $user_response = json_encode($user_response);
      $user_response = json_decode($user_response,true);
    }

    // echo "<pre>";
    // print_r($user_response);
    // echo "</pre>";

    if (isset($user_response)) {
      $ebay_id = $user_response['User']['UserID'];
    }else{
      header("Location: ../setting.php");
      exit;
    }

  if (!isset($_SESSION['user_apikey_flag']) || $_SESSION['user_apikey_flag'] === 0) {
    // セマフォを解放
    if (!sem_release($res)) {
      throw new Exception('sem_release failed');
    }
  }

  //DB情報の読み込みと使用準備
  require_once('../db/db.php');
  $pdo = new PDO('mysql:host='.$host.';dbname='.$db_name, $db_user, $db_pass);

  if (isset($_SESSION['g_id'])) {
    //ユーザー側のebayアカウントの登録
    $user_name_sql=$pdo->prepare('SELECT family_name,given_name from userData where g_id=:g_id');
    $user_name_sql->bindParam(":g_id", $_SESSION['g_id']);
    if ($user_name_sql->execute()) {
      foreach ($user_name_sql->fetchAll() as $row) {
        $user_name = $row['family_name'].'　'.$row['given_name'];
      }
    }

    //変数を定義したら、専用DBのカテゴリー情報を更新する。
    $sql=$pdo->prepare('REPLACE into ebay_info values(?, ?, ?, ?, null)');
    if ($sql->execute([$_SESSION['g_id'], $ebay_id, $refresh_token, $refresh_token_expires_in])){
      //現在時刻の取得と整形
      $log_time = date("Y-m-d H:i:s");
      // ファイルのパスを変数に格納
      $filename = '../info_doc/ebay_log/'.$_SESSION['g_id'].'.csv';

      if (!file_exists($filename)) {
        $fp = fopen($filename, 'w');
        fwrite($fp, $user_name.'、'.$log_time.'、'.$ebay_id."\n");
        // ファイルを閉じる
        fclose($fp);
      }else {
        // fopenでファイルを開く（'a'は追記モードで開く）
        $fp = fopen($filename, 'a');
        // fwriteで文字列を書き込む
        fwrite($fp, $user_name.'、'.$log_time.'、'.$ebay_id."\n");
        // ファイルを閉じる
        fclose($fp);
      }

      header("Location: ../setting.php");
      exit;
    }else {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      var_dump($pdo->errorInfo());
    }
  }else {
    if ($maintenance) {
      header("Location: https://atoe.a-collect.life/maintenance_login.php");
    }else {
      header("Location: https://atoe.a-collect.life/");
    }
    exit;
  }
