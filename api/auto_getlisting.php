<?php
  $time_start = microtime(true);
  // エラーを出力する
  ini_set( 'display_errors', 1 );
  ini_set('error_reporting', E_ALL);

  //gidを取得する。
  $gid = '8202bf49a5e6014189a686ce6cce479ae5f0912a';

  //リフレッシュトークンを取得する
  require_once('../db/db.php');
  $pdo = new PDO('mysql:host='.$host.';dbname='.$db_name, $db_user, $db_pass);
  $refresh_token_sql = $pdo -> prepare('select refresh_token from ebay_info where g_id=?');
  if ($refresh_token_sql -> execute([$gid])) {
    foreach ($refresh_token_sql->fetchAll() as $row) {
      $refresh_token = $row['refresh_token'];
    }
  }else {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    var_dump($pdo->errorInfo());
  }

  //ebay APIのアプリケーション認証情報を記述したファイルを読み込む
  require_once('./ebay_app_info.php');

  $token_url = $base_url_new.'identity/v1/oauth2/token';
  $authorization =  base64_encode($appID.':'.$certID);

  //ユーザートークンの取得要求
  $request_body = [
    'grant_type' => 'refresh_token',
    'refresh_token' => $refresh_token,
  ];

  $request_header = [
    'Content-Type:application/x-www-form-urlencoded',
    'Authorization:Basic '.$authorization
  ];

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
  $json_obj = json_decode($result,true);

  if (isset($json_obj['access_token'])) {
    $token = $json_obj['access_token'];
  }

  //出品リストの同期（並列処理なし）

  // セマフォIDの取得
  $res = sem_get(ftok(__FILE__, 'g'), 1);

  // セマフォを得る
  if (!sem_acquire($res)) {
      throw new Exception('sem_acquire failed');
  }

  //ユーザーのebay出品リストのデータ取得
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => $base_url_old,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n
    <GetMyeBaySellingRequest xmlns=\"urn:ebay:apis:eBLBaseComponents\">    \r\n\t
      <ErrorLanguage>en_US</ErrorLanguage>\r\n\t
      <WarningLevel>High</WarningLevel>\r\n
      <ActiveList>\r\n
        <Sort>TimeLeft</Sort>\r\n
        <Pagination>\r\n
          <EntriesPerPage>100</EntriesPerPage>\r\n
          <PageNumber>1</PageNumber>\r\n
        </Pagination>\r\n
      </ActiveList>\r\n
    </GetMyeBaySellingRequest>",
    CURLOPT_HTTPHEADER => array(
      "Cache-Control: no-cache",
      "Content-Type: application/xml",
      "X-EBAY-API-CALL-NAME: GetMyeBaySelling",
      "x-ebay-api-compatibility-level: 1091",
      "X-EBAY-API-IAF-TOKEN: ".$token,
      "X-EBAY-API-SITEID: 0"
    ),
  ));

  $response_listing = curl_exec($curl);
  $response_listing = simplexml_load_string($response_listing);
  $response_listing = json_encode($response_listing);
  $response_listing = json_decode($response_listing,TRUE);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    echo "cURL Error #:" . $err;
  }

  //変数の定義
  //出品リスト全体
  $listing = $response_listing['ActiveList']['ItemArray']['Item'];
  date_default_timezone_set('GMT');
  $count_listing = count($listing);
  for ($i=0; $i < $count_listing; $i++) {
    //アイテムID
    $ebay_item_id[] = $listing[$i]['ItemID'];
    //画像
    $ebay_item_image[] = str_replace("http", "https", $listing[$i]['PictureDetails']['GalleryURL']);
    //タイトル
    $ebay_item_title[] = $listing[$i]['Title'];
    //価格
    $ebay_item_price[] = $listing[$i]['SellingStatus']['CurrentPrice'];
    //送料
    $ebay_item_shipcost[] = $listing[$i]['ShippingDetails']['ShippingServiceOptions']['ShippingServiceCost'];
    //個数
    $ebay_item_quantity[] = $listing[$i]['QuantityAvailable'];
    //開始時間
    $ebay_item_start[] = new Datetime(str_replace("T", " ", str_replace(".000Z", "", $listing[$i]['ListingDetails']['StartTime'])));
    //開催期間
    if (strpos($listing[$i]['ListingDuration'],"Days_") !== false) {
      $ebay_item_duration[] = intval(str_replace("Days_", "", $listing[$i]['ListingDuration']));
    }else{
      $ebay_item_duration[] = "30";
    }
  }

  //2回目以降の取得が必要かチェックするための変数を定義
  $total_page = intval($response_listing['ActiveList']['PaginationResult']['TotalNumberOfPages']);

  //2ページ目以降の取得
  if ($total_page > 1) {
    for ($i=1; $i < $total_page; $i++) {
      $c =$i+1;
      //ユーザーのebay出品リストのデータ取得
      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => $base_url_old,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n
        <GetMyeBaySellingRequest xmlns=\"urn:ebay:apis:eBLBaseComponents\">    \r\n\t
          <ErrorLanguage>en_US</ErrorLanguage>\r\n\t
          <WarningLevel>High</WarningLevel>\r\n
          <ActiveList>\r\n
            <Sort>TimeLeft</Sort>\r\n
            <Pagination>\r\n
              <EntriesPerPage>100</EntriesPerPage>\r\n
              <PageNumber>".$i."</PageNumber>\r\n
            </Pagination>\r\n
          </ActiveList>\r\n
        </GetMyeBaySellingRequest>",
        CURLOPT_HTTPHEADER => array(
          "Cache-Control: no-cache",
          "Content-Type: application/xml",
          "X-EBAY-API-CALL-NAME: GetMyeBaySelling",
          "x-ebay-api-compatibility-level: 1091",
          "X-EBAY-API-IAF-TOKEN: ".$token,
          "X-EBAY-API-SITEID: 0"
        ),
      ));

      $response_listing = curl_exec($curl);
      $response_listing = simplexml_load_string($response_listing);
      $response_listing = json_encode($response_listing);
      $response_listing = json_decode($response_listing,TRUE);
      $err = curl_error($curl);

      curl_close($curl);

      if ($err) {
        echo "cURL Error #:" . $err;
      }

      //変数の定義
      //出品リスト全体
      $listing = $response_listing['ActiveList']['ItemArray']['Item'];
      date_default_timezone_set('GMT');
      $count_listing = count($listing);
      for ($j=0; $j < $count_listing; $j++) {
        //アイテムID
        $ebay_item_id[] = $listing[$j]['ItemID'];
        //画像
        $ebay_item_image[] = str_replace("http", "https", $listing[$j]['PictureDetails']['GalleryURL']);
        //タイトル
        $ebay_item_title[] = $listing[$j]['Title'];
        //価格
        $ebay_item_price[] = $listing[$j]['SellingStatus']['CurrentPrice'];
        //送料
        $ebay_item_shipcost[] = $listing[$j]['ShippingDetails']['ShippingServiceOptions']['ShippingServiceCost'];
        //個数
        $ebay_item_quantity[] = $listing[$j]['QuantityAvailable'];
        //開始時間
        $ebay_item_start[] = new Datetime(str_replace("T", " ", str_replace(".000Z", "", $listing[$j]['ListingDetails']['StartTime'])));
        //開催期間
        if (strpos($listing[$j]['ListingDuration'],"Days_") !== false) {
          $ebay_item_duration[] = intval(str_replace("Days_", "", $listing[$j]['ListingDuration']));
        }else{
          $ebay_item_duration[] = "30";
        }
      }
    }
  }

  //終了日時を求める
  $count_ebay_item_start = count($ebay_item_start);
  for ($i=0; $i < $count_ebay_item_start; $i++) {
    $ebay_item_end_time = $ebay_item_start[$i]->format('Y-m-d H:i:s');
    $ebay_item_end_time = new DateTime($ebay_item_end_time);
    $ebay_item_end_time -> setTimeZone( new DateTimeZone('Asia/Tokyo'));
    $ebay_item_end_time = $ebay_item_end_time->modify('+'.$ebay_item_duration[$i].' days');
    $ebay_item_end[] = $ebay_item_end_time->format('Y-m-d H:i:s');
  }

  //更新前にテーブルにデータが格納されているかチェックする。
  $sql_listing_check = $pdo->prepare('select count(*) from ebay_user_listing where g_id=?');
  $sql_listing_check->execute([$_SESSION['g_id']]);
  $listing_check = $sql_listing_check->fetchColumn();//全ての件数の総数を取得
  //データが格納されているのなら、テーブルの内容を空にする。
  if ($listing_check > 0) {
    $listing_delete = $pdo->prepare('delete from ebay_user_listing where g_id=?');
    if ($listing_delete->execute([$_SESSION['g_id']])) {
    }else {
      var_dump($listing_delete->errorInfo());
    }
  }

  //
  $sql = $pdo -> prepare("replace into ebay_user_listing
  (item_id, image_url, title, price, shipcost, quantity, end_time, g_id)
  values (:item_id, :image_url, :title, :price, :shipcost, :quantity, :end_time, :g_id)");
  for ($i=0; $i < count($ebay_item_id); $i++) {
    $sql->bindParam(':item_id', $ebay_item_id[$i], PDO::PARAM_INT);
    $sql->bindParam(':image_url', $ebay_item_image[$i], PDO::PARAM_STR);
    $sql->bindParam(':title', $ebay_item_title[$i], PDO::PARAM_STR);
    $sql->bindParam(':price', $ebay_item_price[$i], PDO::PARAM_INT);
    $sql->bindParam(':shipcost', $ebay_item_shipcost[$i], PDO::PARAM_INT);
    $sql->bindParam(':quantity', $ebay_item_quantity[$i], PDO::PARAM_INT);
    $sql->bindParam(':end_time', $ebay_item_end[$i], PDO::PARAM_STR);
    $sql->bindParam(':g_id', $_SESSION['g_id'], PDO::PARAM_STR);
    if ($sql->execute()){
    }else {
      var_dump($pdo->errorInfo());
    }
  }

  if ($_SESSION['g_id'] === '8202bf49a5e6014189a686ce6cce479ae5f0912a') {
    //echo "<pre>";
    //print_r($ebay_item_id);
    //print_r($ebay_item_title);
    //print_r($ebay_item_shipcost);
    //echo "</pre>";
  }

  //ファイルネームの定義
  $file_name = "../info_doc/".$_SESSION['g_id'].'-listing_update.csv';

  //ファイルに書き込む
  $fp = fopen($file_name,'w');
  $update_time = date('Y-m-d H:i:s');
  $update_time = new DateTime($update_time);
  $update_time -> setTimeZone( new DateTimeZone('Asia/Tokyo'));
  $update_time = $update_time->format('Y-m-d H:i:s');
  fwrite($fp,$update_time);
  fclose($fp);

  // セマフォを解放
  if (!sem_release($res)) {
      throw new Exception('sem_release failed');
  }

  $time = microtime(true) - $time_start;
  echo "自動同期プログラム：{$time} 秒";
