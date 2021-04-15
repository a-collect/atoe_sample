<?php
  //ebay APIのアプリケーション認証情報を記述したファイルを読み込む
  require_once('../api/ebay_app_info.php');

  //カテゴリー情報を登録するデータベースの情報の読み込みと利用準備
  require_once('../db/db.php');

  $pdo = new PDO('mysql:host='.$host.';dbname='.$db_name, $db_user, $db_pass);
  //DBに登録したebayのtokenを取得する
  $addminebay = $pdo->prepare('select * from addmin_ebay_info');
  if ($addminebay->execute([])) {
    foreach ($addminebay->fetchAll() as $row) {
      $token = $row['token'];
    }
  }else {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    var_dump($pdo->errorInfo());
    exit;
  }

  if (!isset($_SESSION['user_apikey_flag']) || $_SESSION['user_apikey_flag'] === 0) {
    // セマフォIDの取得
    $res = sem_get(ftok(__FILE__, 'g'), 1);

    // セマフォを得る
    if (!sem_acquire($res)) {
        throw new Exception('sem_acquire failed');
    }
  }

  //第1カテゴリーを取得する。
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => $base_url_old,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<GetCategoriesRequest xmlns=\"urn:ebay:apis:eBLBaseComponents\">\r\n
      <RequesterCredentials>\r\n
      <eBayAuthToken>".$token."</eBayAuthToken>\r\n
      </RequesterCredentials>\r\n\t
      <ErrorLanguage>en_US</ErrorLanguage>\r\n\t
      <WarningLevel>High</WarningLevel>\r\n
      <CategorySiteID>0</CategorySiteID>\r\n
      <DetailLevel>ReturnAll</DetailLevel>\r\n
      <LevelLimit>1</LevelLimit>\r\n
      </GetCategoriesRequest>",
    CURLOPT_HTTPHEADER => array(
      "Cache-Control: no-cache",
      "Content-Type: application/xml",
      "X-EBAY-API-CALL-NAME: GetCategories",
      "x-ebay-api-compatibility-level: 1091",
      "X-EBAY-API-SITEID: 0"
    ),
  ));

  $categories_response = simplexml_load_string(curl_exec($curl));
  $categories_response = json_encode($categories_response);
  $categories_response = json_decode($categories_response,true);
  $err = curl_error($curl);

  curl_close($curl);

  if (!isset($_SESSION['user_apikey_flag']) || $_SESSION['user_apikey_flag'] === 0) {
    // セマフォを解放
    if (!sem_release($res)) {
        throw new Exception('sem_release failed');
    }
  }

  //APIでの取得にエラーがなければ、カテゴリーIDとカテゴリー名の変数を定義する。、
  if ($err) {
    echo "cURL Error #:" . $err;
  } else {
    //echo "<pre>";
    //print_r($categories_response);
    //echo "</pre>";
    if ($categories_response['Ack'] === "Success") {
      for ($i=0; $i < count($categories_response['CategoryArray']['Category']); $i++) {
        $category_id[] = $categories_response['CategoryArray']['Category'][$i]['CategoryID'];
        $category_name[] = $categories_response['CategoryArray']['Category'][$i]['CategoryName'];
      }
      $category_version = $categories_response['CategoryVersion'];
      $category_count = $categories_response['CategoryCount'];

      //echo "<pre>";
      //print_r($category_id);
      //print_r($category_name);
      //echo "</pre>";
      //更新前にテーブルにデータが格納されているかチェックする。
      $sql_categories_check = $pdo->prepare('select count(*) from ebay_categories');
      $sql_categories_check->execute([]);
      $categories_check = $sql_categories_check->fetchColumn();//全ての件数の総数を取得
      //データが格納されているのなら、テーブルの内容を空にする。
      if ($categories_check > 0) {
        $sql_truncate = $pdo->query('delete from ebay_categories');
      }
      //変数を定義したら、専用DBのカテゴリー情報を更新する。
      $sql=$pdo->prepare('insert into ebay_categories values(?, ?, ?, ?)');
      $regi_check = 0;
      for ($i=0; $i < count($category_id); $i++) {
        $c = $i + 1;
        if ($sql->execute([$c,$category_name[$i],$category_id[$i],$category_version])){
          $regi_check += 1;
        }else {
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
          echo $c."回目：<br>";
          var_dump($pdo->errorInfo());
        }
      }
      //取得したカテゴリー数と登録に成功した数を突き合わせて登録がすべて成功したかチェックする。
      //登録成功数の変数をAPIで取得したカテゴリー数と同じデータ型（string）に変換する。
      $regi_check = strval($category_count);
      if ($category_count === $regi_check) {
        $_SESSION['get_category_mess'] = 'カテゴリー情報の登録に成功しました。';
      }else{
        $_SESSION['get_category_mess'] = 'エラーが発生しています。プログラムのチェックを行ってください。';
      }

      http_response_code(200);
      header('Location: ../admin/get_ebay_data.php');
      exit;
    }
  }
