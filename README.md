# atoe_sample
＊4/20に、学びながら完成したいこと
Notifications_API request

私（羽鳥）が理解していないこと
１./public_key/{public_key_id}　波括弧の中身は具体的に何が入るのか理解できていません。
ebay explorerで試したこと

ググって、エンドポイントの例を拾ってきた｛public_key_id｝を入れてリクエストしました。
Web Service URI (endpoint)
https://api.ebay.com/commerce/notification/v1/public_key/9936261a-7d7b-4621-a0f1-96ccb428af49

request結果：Call Response Status: 200 OK

どうやって、エクスプローラーと同じ結果が出るPHPファイルをつくればいいのか？が
分かりません。

$request_body = [
 'どんな中身を作成すればいいのか？',
];

$request_header = [
 'どんな中身を作成すればいいのか？',
];

２.ebay_auth.phpを実行し、print_r($result);した結果が下記です。
下記も時間があれば、解決したいことです。

"error": "invalid_grant",
"error_description": "the provided authorization grant code is invalid or was issued to another client"

参考URLを見ても解決できませんでした。
https://stackoverflow.com/questions/61633389/ebay-oauth-invalid-grant-when-trying-to-get-access-token


用意したファイル＝ebay_app_info.php
ebay developer programの下記各種IDが記載してあるファイルです。
DBのuser_apikey_flagが、「１」ならsetting.phpにブラウザ入力された下記IDを
読み込む。としています。「０」なら、ファイルに記述したIDを読み込む。

  $appID  = '';   // different from prod keys
  $devID  = '';   // insert your devID
  $certID = '';   // need three keys and one token
  $runame = '';  // runame


用意したファイル＝ebay_auth.php
ebay user nameの取得はできていますが、上記２.エラーが解決できません。

用意したファイル＝auto_getlisting.php
ebay userのアクティブ出品リストを取得するファイルです。取得はできています。

用意したファイル＝auto_getlisting.php
ebay userのアクティブ出品リストを取得するファイルです。取得はできています。

用意したファイル＝get_category.php
ebay USの旧カテゴリーを取得する記述です。
取得後はDBに保存していました。（No,category name, category id, version ）
2020/10月頃に新しくなった新カテゴリーが取得できないです。





