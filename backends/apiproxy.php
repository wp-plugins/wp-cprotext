<?php
if ( ! defined( 'ABSPATH' ) ) exit;

define("CPTX_API_PROTOCOL","https");
define("CPTX_API_HOST","www.cprotext.com");
define("CPTX_API_VERSION","1.0");
define("CPTX_API_PATH","/api/".CPTX_API_VERSION."/");

require_once(__DIR__."/../nonces/proxynonce.php");

function cptx_buildProxyOutput($callback,$result){
  $output="";

  if(!empty($callback)){
    $output.=$callback."(";
  }

  $output.=$result;

  if(!empty($callback)){
    $output.=")";
  }

  return $output;
}

function cptx_APIProxy(){

  $action=filter_input(INPUT_GET,'action');
  if($action!=="cptxProxy"){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }

  $nonce=filter_input(INPUT_GET,CPTX_WP_PROXYNONCE_VAR,FILTER_VALIDATE_REGEXP,
    array("options"=>array("regexp"=>"/^[0-9a-f]+$/")));

  $function=filter_input(INPUT_GET,"f");
  $next=filter_input(INPUT_GET,"n");

  if($function==="token"){
    $nonceFilter="cptx_setInitialProxyNonceLifeSpan";
  }else{
    $nonceFilter="cptx_setProxyNonceLifeSpan";
  }

  add_filter("nonce_life",$nonceFilter);
  if((empty($nonce) || 
    !check_ajax_referer(CPTX_WP_PROXYNONCE_ACTION,CPTX_WP_PROXYNONCE_VAR,false))
  ){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }
  remove_filter("nonce_life",$nonceFilter);

  if(!current_user_can('edit_posts')){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }

  cptx_checkInputs(array(
    "_GET"=>array("callback",CPTX_WP_PROXYNONCE_VAR,"_","f","n","e","p","t","tid","ft","ie"),
    "_POST"=>array("c","ti","plh"),
    "_FILES"=>array("fontFile"),
    "_COOKIE"=>null
  ));

  $callback=filter_input(INPUT_GET,"callback");
  $jsonStart=strlen($callback."(");

  $queryString=str_replace(CPTX_WP_PROXYNONCE_VAR."=$nonce","",$_SERVER["QUERY_STRING"]);
  $queryString=str_replace("action=cptxProxy","",$queryString);
  $queryString=str_replace("&&","&",$queryString);

  // Detect IE version: this is plain wrong and ugly, but unavoidable
  // in order to support font upload for IE 9
  // source: https://github.com/malsup/form/issues/302
  preg_match('/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'], $matches);
  $ieVersion=0;
  if(count($matches)>1){
    $ieVersion=$matches[1];
  }

  if($ieVersion && $ieVersion<=9){
    header("Content-Type: text/plain; charset=utf-8");
  }else{
    header("Content-Type: text/javascript; charset=utf-8");
  }

  $url = CPTX_API_PROTOCOL."://".CPTX_API_HOST.CPTX_API_PATH."?".$queryString;

  if($_SERVER["REQUEST_METHOD"]!=="POST" || (empty($_POST) && empty($_FILES))){
    $result=@file_get_contents($url);
    if($result===false){
      $result=json_encode(array("error"=>
        __("CPROTEXT server unreachable.",CPTX_I18N_DOMAIN)));
    }else{
      $result=substr($result,$jsonStart,-1);
      if(!empty($next)){
        $result=cptx_insertWPNonce($result);
      }
    }
  }else{
    $params = array('http' => array(
      'method' => 'POST'
    ));

    $content="";
    $file=null;
    if(isset($_FILES["fontFile"])){
      define('MULTIPART_BOUNDARY', '--------------------------'.microtime(true));
      $file=$_FILES["fontFile"];

      if($file["error"]!==UPLOAD_ERR_OK){
        if(isset($file["tmp_name"]) && @file_exists($file["tmp_name"])){
          @unlink($file["tmp_name"]);
        }

        $error=__("WordPress hosting server issue",CPTX_I18N_DOMAIN).": ";

        switch($file["error"]){
        case UPLOAD_ERR_INI_SIZE:
          $error.=sprintf(__("File size exceeds PHP configured limit (%s).",CPTX_I18N_DOMAIN),
            ini_get("upload_max_filesize"));
          break;
        case UPLOAD_ERR_PARTIAL:
          $error.=__("Upload interrupted.",CPTX_I18N_DOMAIN);
          break;
        case UPLOAD_ERR_NO_FILE:
          $error.=__("No file submitted.",CPTX_I18N_DOMAIN);
          break;
        case UPLOAD_ERR_NO_TMP_DIR:
          $error.=__("Temporary folder unreachable.",CPTX_I18N_DOMAIN);
          break;
        case UPLOAD_ERR_CANT_WRITE:
          $error.=__("Can't write file in temporary folder.",CPTX_I18N_DOMAIN);
          break;
        case UPLOAD_ERR_EXTENSION:
          $error.=__("A PHP extension stopped the file upload.",CPTX_I18N_DOMAIN);
          break;
        default:
          $error.=__("Unidentified issue.",CPTX_I18N_DOMAIN);
          break;
        }

        $result=json_encode(array("error"=>$error)); 
        echo cptx_buildProxyOutput($callback,$result);
        exit();
      }

      $params['http']['header']='Content-Type: multipart/form-data; boundary='.MULTIPART_BOUNDARY;

      $content="--".MULTIPART_BOUNDARY."\r\n".
        "Content-Disposition: form-data; ".
        "name=\"fontFile\"; ".
        "filename=\"".basename($file["name"])."\"\r\n".
        "Content-Type: ".$file["type"]."\r\n\r\n".
        @file_get_contents($file["tmp_name"])."\r\n";

      if(isset($file["tmp_name"]) && @file_exists($file["tmp_name"])){
        @unlink($file["tmp_name"]);
      }

      foreach($_POST as $key=>$value){
        $content.="--".MULTIPART_BOUNDARY."\r\n".
          "Content-Disposition: form-data; name=\"$key\"\r\n\r\n".
          "$value\r\n";
      }
      $content .= "--".MULTIPART_BOUNDARY."--\r\n";
    }else{
      $content=http_build_query($_POST);
    }

    $params["http"]["content"]=$content;

    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp || 
      ($result=@stream_get_contents($fp))===false
    ){
      $result=json_encode(array("error"=>
        __("WordPress hosting server issue",CPTX_I18N_DOMAIN).": ".
        __("Unidentified issue.",CPTX_I18N_DOMAIN)));
    }else{
      if(!empty($callback)){
        $result=substr($result,$jsonStart,-1);
      }
      if(!empty($next)){
        $result=cptx_insertWPNonce($result);
      }
    }

  }

  echo cptx_buildProxyOutput($callback,$result);
  die();
}
?>
