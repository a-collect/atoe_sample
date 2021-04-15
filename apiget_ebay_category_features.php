<?php
//該当カテゴリーのConditionIDを取得する
$curl = curl_init();
if (strpos($ebay_item_category_name, 'eBay Motors') !== false) {
  $api_site_id = 100;
}else {
  $api_site_id = 0;
}

curl_setopt_array($curl, array(
  CURLOPT_URL => $base_url_old,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n
  <GetCategoryFeaturesRequest xmlns=\"urn:ebay:apis:eBLBaseComponents\">\r\n
    <CategoryID>$ebay_item_category_id</CategoryID>\r\n
    <DetailLevel>ReturnAll</DetailLevel>\r\n
    <ViewAllNodes>true</ViewAllNodes>\r\n
  </GetCategoryFeaturesRequest>",
  CURLOPT_HTTPHEADER => array(
    "cache-control: no-cache",
    "content-type: application/xml",
    "x-ebay-api-call-name: GetCategoryFeatures",
    "x-ebay-api-compatibility-level: 1091",
    "x-ebay-api-iaf-token:".$_SESSION['ebay_token'],
    'X-EBAY-C-ENDUSERCTX:affiliateCampaignId=5338182814',
    "x-ebay-api-siteid: ".$api_site_id
  ),
));

$GetCategoryFeatures_response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  $GetCategoryFeatures_response = simplexml_load_string($GetCategoryFeatures_response);
  $GetCategoryFeatures_response = json_encode($GetCategoryFeatures_response);
  $GetCategoryFeatures_response = json_decode($GetCategoryFeatures_response,true);
}

//echo "<pre>";
//print_r($GetCategoryFeatures_response);
//echo "</pre>";

if (isset($GetCategoryFeatures_response['Category']['ConditionEnabled'])) {
  //ConditionIDとConditionNameの取得
  $category_ConditionEnabled = $GetCategoryFeatures_response['Category']['ConditionEnabled'];
  $GetCategoryFeatures_condition =$GetCategoryFeatures_response['Category']['ConditionValues']['Condition'];
  $max_features = count($GetCategoryFeatures_condition);
  if (isset($GetCategoryFeatures_condition['0'])) {
    for ($i=0; $i < $max_features; $i++) {
      $category_features_id[] = $GetCategoryFeatures_condition[$i]['ID'];
      $category_features_name[] = $GetCategoryFeatures_condition[$i]['DisplayName'];
    }
  }else {
    $category_features_id[] = $GetCategoryFeatures_condition['ID'];
    $category_features_name[] = $GetCategoryFeatures_condition['DisplayName'];
  }
}
