<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once(__DIR__."/../nonces/assetsnonce.php");

function cptx_postCSS(){
  global $wpdb;

  $action=filter_input(INPUT_GET,'action');
  if($action!=="cptxCSS"){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }

  $nonce=filter_input(INPUT_GET,CPTX_WP_ASSETSNONCE_VAR,FILTER_VALIDATE_REGEXP,
    array("options"=>array("regexp"=>"/^[0-9a-f]+$/")));

  add_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");
  if(empty($nonce) ||
    !check_ajax_referer(CPTX_WP_CSSNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR,false)
  ){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }
  remove_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");

  cptx_checkInputs(array(
    "_GET"=>array(CPTX_WP_ASSETSNONCE_VAR,"id","o"),
    "_POST"=>array(),
    "_FILES"=>array(),
    "_COOKIE"=>null
  ));

  $id=filter_input(INPUT_GET,"id",FILTER_VALIDATE_INT);
  $postOrder=filter_input(INPUT_GET,'o',FILTER_VALIDATE_INT);
  $referer=filter_var($_SERVER['HTTP_REFERER'],FILTER_VALIDATE_URL);

  if($referer){
    $referer=parse_url($referer,PHP_URL_HOST);
  }

  if(is_null($id) || $id===false || 
    is_null($postOrder) || $postOrder===false ||
    is_null($referer) || $referer===false){
      header("HTTP/1.1 404 Not Found");
      exit();
    }

  $sql=$wpdb->prepare(
    "SELECT css, ie8enabled FROM ".
    $wpdb->prefix."cptx where id=%d",$id);
  $css=$wpdb->get_var($sql);
  $values=array_values(get_object_vars($wpdb->last_result[0]));
  $IE8enabled=$values[1];
  $css=stripslashes($css);
  if($IE8enabled){
    add_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");
    $url=wp_nonce_url(
      admin_url("admin-ajax.php")."?action=cptxFont&",
      CPTX_WP_FONTNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR);
    remove_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");

    $css=str_replace("wpcxX-e.eot?",$url.'&id='.$id.'&t=e#',$css);
    $css=str_replace("wpcxX-s.eot?",$url.'&id='.$id.'&t=s#',$css);
  }else{
    $result=preg_replace("/@font-face\{font-family:[^ ]+ format\('embedded-opentype'\)\}/","",$css);
    if(!is_null($result)){
      $css=$result;
    }
  }
  $css=str_replace("wpcxX","wpcx$postOrder",$css);

  header('Content-Type: text/css');

  echo $css;
  die();
}
?>
