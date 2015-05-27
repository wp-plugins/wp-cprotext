<?php
/*
 * Plugin Name: wp-cprotext
 * Plugin URI: https://www.cprotext.com/en/tools.html
 * Description: Integration of the CPROTEXT.COM online service for copy protection of texts published on the web
 * Text Domain: wp-cprotext
 * Domain path: /lang/
 * Version: 1.2.0
 * Author: ASKADICE
 * Author: https://www.cprotext.com
 * License: GPLv2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define("CPTX_PLUGIN_VERSION","1.2.0");

define("CPTX_DB_VERSION","1");

define('CPTX_I18N_DOMAIN','wp-cprotext');



define('CPTX_DEBUG',false);

define('CPTX_DEBUG_QUERIES',false);

define('CPTX_STATUS_NOT_PUBLISHED',0);
define('CPTX_STATUS_PUBLISHED',1);
define('CPTX_STATUS_CHANGED',2);
define('CPTX_STATUS_NOT_CHANGED',0);
define('CPTX_STATUS_CPROTEXTED',4);
define('CPTX_STATUS_NOT_CPROTEXTED',0);
define('CPTX_STATUS_UPDATE_TITLE',8);
define('CPTX_STATUS_UPDATE_CONTENT',16);
define('CPTX_STATUS_WPUPDATES',
  CPTX_STATUS_UPDATE_TITLE+
  CPTX_STATUS_UPDATE_CONTENT);
define('CPTX_STATUS_IE8_CHANGED',32);
define('CPTX_STATUS_IE8_ENABLED',64);
define('CPTX_STATUS_UPDATE_FONT',128);
define('CPTX_STATUS_UPDATE_PLH',256);
define('CPTX_STATUS_UPDATE_KW',512);
define('CPTX_STATUS_UPDATES',
  CPTX_STATUS_WPUPDATES+
  CPTX_STATUS_UPDATE_FONT+
  CPTX_STATUS_UPDATE_PLH+
  CPTX_STATUS_UPDATE_KW);
define('CPTX_STATUS_PROCESSING',1024);

require_once(__DIR__."/nonces/proxynonce.php");
require_once(__DIR__."/nonces/assetsnonce.php");

require_once(__DIR__."/backends/apiproxy.php");
require_once(__DIR__."/backends/css.php");
require_once(__DIR__."/backends/font.php");

/**
 * cptx_updateCheck
 * 
 * When required, create or update the plugin DB schema
 * and initialize plugin option:
 *      cptx_notice: set protection notification style
 *                   values: 0 => simple text notification
 *                           1 => linked notification
 *                           2 => text appended to placeholder
 *                           3 => none
 *
 *      cptx_fonts: list of available fonts in user CPROTEXT account
 *
 *      cptx_font: default font to use with protexted text
 *
 *      cptx_ie8: set IE8 support by default
 *                values: 0 => false
 *                        1 => true
 *
 * @access public
 * @return void
 */
function cptx_updateCheck(){
  global $wpdb;

  $currentPluginVersion=get_option('cptx_version');

  if($currentPluginVersion === CPTX_PLUGIN_VERSION){
    return;
  }

  update_option("cptx_version",CPTX_PLUGIN_VERSION);

  $current_DBVersion=get_option('cptx_db_version'); 
  if ($current_DBVersion != CPTX_DB_VERSION){
    $tableName=$wpdb->prefix."cptx";
    for($v=$current_DBVersion+1; $v<=CPTX_DB_VERSION; $v++){
      $sql=trim(file_get_contents(__DIR__."/sql/cprotextdb_".$v.".sql"));
      $sql=str_replace("__TABLENAME__",$tableName,$sql);
      if(strrpos($sql,";")===(strlen($sql)-1)){
        $sql=substr($sql,0,-1);
      }
      $queries=explode(";",$sql);
      foreach($queries as $q=>$query){
        $result=$wpdb->query($query);
        if(!$result){
          break 2;
        }
      }
    }
    if($v != (CPTX_DB_VERSION+1)){
      if(!$q){
        $v--;
      }
      for(;$v>$current_DBVersion;$v--){
        $sql=trim(file_get_contents(__DIR__."/sql/cprotextdb_".$v.".rollback.sql"));
        $sql=str_replace("__TABLENAME__",$tableName,$sql);
        if(strrpos($sql,";")===(strlen($sql)-1)){
          $sql=substr($sql,0,-1);
        }
        $rollbacks=explode(";",$sql);
        for($r=count($rollbacks)-$q-1;$r>=0; $r--){
          $result=$wpdb->query($rollbacks[$r]);
          if(!$result){
            break 2;
          }
        }
      }
      if($v == $current_DBVersion ){
        // successful rollback
        update_option("cptx_popup",3);
      }else{
        // failed rollback
        update_option("cptx_popup",4);
      }
      return false;
    }
    update_option("cptx_db_version",CPTX_DB_VERSION);
  }

  if($currentPluginVersion===false){
    update_option("cptx_notice",0);
    update_option("cptx_fonts","");
    update_option("cptx_font","");
    update_option("cptx_ie8",1);
    update_option("cptx_popup",1);
  }else{
    if(file_exists(__DIR__."/tpl/release_".CPTX_PLUGIN_VERSION.".en_US.html")){
      update_option("cptx_popup",2);
    }else{
      update_option("cptx_popup",0);
    }
  }
  return true;
}

/**
 * cptx_getTemplate
 * 
 * @param string $tpl template name
 * @access public
 * @return string the content of the template file or null if it does not 
 *                exist
 */
function cptx_getTemplate($tpl){
  global $locale;

  $tplPath=__DIR__."/tpl/$tpl.".(empty($locale)?'en_US':$locale).".html";
  if(!file_exists($tplPath)){
    $tplPath=__DIR__."/tpl/$tpl.en_US.html";
    if(!file_exists($tplPath)){
      return null;
    }
  }
  return file_get_contents($tplPath);
}

/**
 * cptx_popup
 *
 * Display a popup with information about updates
 *
 * @access public
 * @return void
 */
function cptx_popup() {
  switch(get_option('cptx_popup')){
  case 1:
    $content=cptx_getTemplate("firstinstall");
    break;
  case 2:
    $content=cptx_getTemplate("release_".CPTX_PLUGIN_VERSION);
    break;
  case 3:
    $content=cptx_getTemplate("rollback");
    break;
  case 4:
    $content=cptx_getTemplate("fatal");
    break;
  default:
    return;
  };

  echo "<div id='cptxPopUp' style='display:none'>";
  echo $content; 
  echo "</div>";
  update_option("cptx_popup",0);
}

/**
 * cptx_menu
 *
 * add CPROTEXT options in settings menu
 *
 * @access public
 * @return void
 */
function cptx_menu(){
  add_options_page( __('CPROTEXT Options',CPTX_I18N_DOMAIN), 'CPROTEXT', 'manage_options', 'cprotextoptionslug', 'cptx_options' );
}

/**
 * cptx_options
 *
 * display CPROTEXT options page
 *
 * @access public
 * @return void
 */
function cptx_options(){
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.',CPTX_I18N_DOMAIN ) );
  }

  $settings=cptx_getTemplate("settings");

  $targets=array();
  $replacements=array();

  $targets[]="__PLUGINS_URL__";
  $replacements[]=plugins_url();

  $cptx_ie8=(int)get_option("cptx_ie8");
  $posted_cptx_ie8=filter_input(INPUT_POST,"cptx_ie8",
    FILTER_VALIDATE_INT,array("options"=>array("min_range"=>0,"max_range"=>1)));
  if(!$posted_cptx_ie8){
    $posted_cptx_ie8=0;
  }
  if(isset($_POST["mysubmit"]) && $posted_cptx_ie8!==$cptx_ie8){
    update_option("cptx_ie8",$posted_cptx_ie8);
    $cptx_ie8=$posted_cptx_ie8;
  }

  if($cptx_ie8){
    $targets[]="cptx_ie8'/>";
    $replacements[]="cptx_ie8' checked='checked'/>";
  }

  $cptx_notice=(int)get_option("cptx_notice");
  $posted_cptx_notice=filter_input(INPUT_POST,"cptx_notice",
    FILTER_VALIDATE_INT,array("options"=>array("min_range"=>0,"max_range"=>3)));

  if(is_int($posted_cptx_notice) && $posted_cptx_notice!==$cptx_notice){
    update_option("cptx_notice",$posted_cptx_notice);
    $cptx_notice=$posted_cptx_notice;
  }

  $targets[]="cptx_notice' value='".$cptx_notice."'/>";
  $replacements[]="cptx_notice' value='".$cptx_notice."' checked='checked'/>";

  $cptx_fonts=get_option("cptx_fonts");
  $posted_cptx_fontupd=filter_input(INPUT_POST,"cptx_fontupd",FILTER_VALIDATE_BOOLEAN);
  if($posted_cptx_fontupd){
    $posted_cptx_fonts=filter_input(INPUT_POST,"cptx_fonts");
    if(!is_null($posted_cptx_fonts) && $posted_cptx_fonts!==false){
      $cptx_fonts=$posted_cptx_fonts;
      update_option("cptx_fonts",$posted_cptx_fonts);
    }
  }
  $cptx_fonts=json_decode(stripslashes($cptx_fonts),true);

  $cptx_font=get_option("cptx_font");
  $posted_cptx_fontsel=filter_input(INPUT_POST,"cptx_fontsel");
  if(is_string($posted_cptx_fontsel) && $posted_cptx_fontsel!==$cptx_font){
    update_option("cptx_font",$posted_cptx_fontsel);
    $cptx_font=$posted_cptx_fontsel;
  }

  $targets[]="__FONTSEL_OPTIONS__";
  if(empty($cptx_fonts)){
    $replacements[]="<option title='.'>".
      __("no font available",CPTX_I18N_DOMAIN)."</option>";
    $targets[]="cptx_fontsel'>";
    $replacements[]="cptx_fontsel' disabled='disabled'>";
  }else{
    $options="";
    foreach($cptx_fonts as $font){
      $options.="<option value='".$font[0]."'";
      if($font[0]===$cptx_font)
        $options.=" selected='selected'";
      $options.=">".$font[1]."</option>";
    }
    $replacements[]=$options;
  }

  if(get_option("cptx_db_version")!=CPTX_DB_VERSION){
    $targets[]="</h2>";
    $replacements[]="</h2><h2><span style='color:red'>[ ".
      __("Disabled: database version mismatch",CPTX_I18N_DOMAIN).
      " ]</span></h2>";
    $targets[]="<input";
    $replacements[]="<input disabled='disabled'";
    $targets[]="<button";
    $replacements[]="<button disabled='disabled'";
  }

  print str_replace($targets,$replacements,$settings);
}

/**
 * cptx_post
 *
 * Add CPROTEXT meta box to post and page editor
 *
 * @access public
 * @return void
 */
function cptx_post(){
  add_meta_box('cptx_box',"CPROTEXT",'cptx_check_html','post');
  add_meta_box('cptx_box','CPROTEXT','cptx_check_html','page');
}

/**
 * cptx_adminInserts
 *
 * Add CSS and javascript to relevant admin pages
 *
 * @param string $page 
 * @access public
 * @return void
 */
function cptx_adminInserts($page){
  if(get_option('cptx_popup')){
    wp_enqueue_style('wp-jquery-ui-dialog');

    wp_register_style('wp-cprotext-admin',plugins_url('wp-cprotext/css/cptx'.(CPTX_DEBUG?'':'.min').'.css'));
    wp_enqueue_style('wp-cprotext-admin');
    wp_add_inline_style('wp-cprotext-admin',
      "#ui-id-1:before{ content: url('".
      plugins_url("wp-cprotext/images/icon16.png").
      "')}"
    );

    wp_register_style('wp-cprotext-popup',plugins_url('wp-cprotext/css/cptxpopup'.(CPTX_DEBUG?'':'.min').'.css'));
    wp_enqueue_style('wp-cprotext-popup');

    wp_enqueue_script('ajax-script',plugins_url('wp-cprotext/js/popup'.(CPTX_DEBUG?'':'.min').'.js'),array('jquery','jquery-ui-dialog'));

    wp_localize_script('ajax-script','cprotext',array(
      "L10N"=>array(
        "closeButton"=>__("Close",CPTX_I18N_DOMAIN)
      )));
    return;
  }

  if('settings_page_cprotextoptionslug'!=$page &&
    'post-new.php'!=$page &&
    'post.php'!=$page)
    return;

  global $wp_version;

  wp_enqueue_style('wp-jquery-ui-dialog');

  wp_register_style('wp-cprotext-admin',plugins_url('wp-cprotext/css/cptx'.(CPTX_DEBUG?'':'.min').'.css'));
  wp_enqueue_style('wp-cprotext-admin');
  wp_add_inline_style('wp-cprotext-admin',
    "#ui-id-1:before{ content: url('".
    plugins_url("wp-cprotext/images/icon16.png").
    "')}".
    ".dary{background-image: url('".
    plugins_url("wp-cprotext/images/waitforit.gif").
    "')}"
  );

  wp_enqueue_script('ajax-script',
    plugins_url('wp-cprotext/js/cptx'.(CPTX_DEBUG?'':'.min').'.js'),
    array('jquery','jquery-ui-dialog','jquery-form'));

  add_filter("nonce_life", "cptx_setInitialProxyNonceLifeSpan");
  $initialNonce=wp_create_nonce(CPTX_WP_PROXYNONCE_ACTION);
  remove_filter("nonce_life", "cptx_setInitialProxyNonceLifeSpan");

  wp_localize_script('ajax-script','cprotext',array(
    "API"=>array(
      "URL"=> admin_url('admin-ajax.php').'?action=cptxProxy&',
      "NONCE_VAR"=> CPTX_WP_PROXYNONCE_VAR,
      "INITIAL_NONCE"=> $initialNonce
    ),
    "STATUS"=>array(
      "NOT_PUBLISHED"=>CPTX_STATUS_NOT_PUBLISHED,
      "PUBLISHED"=>CPTX_STATUS_PUBLISHED,
      "CHANGED"=>CPTX_STATUS_CHANGED,
      "NOT_CHANGED"=>CPTX_STATUS_NOT_CHANGED,
      "CPROTEXTED"=>CPTX_STATUS_CPROTEXTED,
      "NOT_CPROTEXTED"=>CPTX_STATUS_NOT_CPROTEXTED,
      "UPDATE_TITLE"=>CPTX_STATUS_UPDATE_TITLE,
      "UPDATE_CONTENT"=>CPTX_STATUS_UPDATE_CONTENT,
      "WPUPDATES"=>CPTX_STATUS_WPUPDATES,
      "IE8_CHANGED"=>CPTX_STATUS_IE8_CHANGED,
      "IE8_ENABLED"=>CPTX_STATUS_IE8_ENABLED,
      "UPDATE_FONT"=>CPTX_STATUS_UPDATE_FONT,
      "UPDATE_PLH"=>CPTX_STATUS_UPDATE_PLH,
      "UPDATE_KW"=>CPTX_STATUS_UPDATE_KW,
      "UPDATES"=>CPTX_STATUS_UPDATES,
      "PROCESSING"=>CPTX_STATUS_PROCESSING
    ),
    "L10N"=>array(
      "bugReportRequired"=>__("This case should not happen ! Please, disable CPROTEXT for this post and report this bug to wp-dev@cprotext.com .",CPTX_I18N_DOMAIN),
      "resume"=>__("CPROTEXT protection process has been initiated for this text and needs to be resumed.",CPTX_I18N_DOMAIN),
      "commitUpdateContent"=>__("Content",CPTX_I18N_DOMAIN),
      "commitUpdateTitle"=>__("Title",CPTX_I18N_DOMAIN),
      "commitUpdateIE8"=>__("Internet Explorer 8 support",CPTX_I18N_DOMAIN),
      "commitUpdateFont"=>__("Font",CPTX_I18N_DOMAIN),
      "commitUpdatePlh"=>__("Placeholder",CPTX_I18N_DOMAIN),
      "commitUpdateKw"=>__("Keywords",CPTX_I18N_DOMAIN),
      "unpublished"=>__("This text is going to be unpublished.",CPTX_I18N_DOMAIN),
      "attachedToRevision"=>__("The CPROTEXT data associated with this revision will remain available in case it has to be published again.",CPTX_I18N_DOMAIN),
      "detachedFromRevision"=>__("Modifications were made to the protected revision. This new revision will not be protected, and will require a new submission to CPROTEXT service if it happens to be published.",CPTX_I18N_DOMAIN),
      "uselessProtection"=> __("This text is not published: protection seems useless.",CPTX_I18N_DOMAIN),
      "closeButton"=>__("Close",CPTX_I18N_DOMAIN),
      "authenticationRequired"=> __("You need to connect to your CPROTEXT account to commit the following modifications:",CPTX_I18N_DOMAIN),
      "login"=>__("Fill in your CPROTEXT credentials",CPTX_I18N_DOMAIN),
      "email"=>__("Email",CPTX_I18N_DOMAIN),
      "password"=>__("Password",CPTX_I18N_DOMAIN),
      "identifyButton"=>__("Identify",CPTX_I18N_DOMAIN),
      "identifyFail"=>__("Identification failed",CPTX_I18N_DOMAIN),
      "fontlistFail"=>__("Can't retrieve font list.",CPTX_I18N_DOMAIN),
      "fontfile1"=>__("Font",CPTX_I18N_DOMAIN),
      "fontfile2"=>__("successfully uploaded.",CPTX_I18N_DOMAIN),
      "creditsFail"=>__("Can't get current credits.",CPTX_I18N_DOMAIN),
      "credits1"=>__("Your account has currently ",CPTX_I18N_DOMAIN),
      "credits2"=>__("credits.",CPTX_I18N_DOMAIN),
      "credits3"=>__("You are about to use %1 credit%2 for this text.",CPTX_I18N_DOMAIN),
      "credits4"=>__("Do you confirm willing to use CPROTEXT service to protect this text ?",CPTX_I18N_DOMAIN),
      "returnButton"=>__("I'll come back later",CPTX_I18N_DOMAIN),
      "noButton"=>__("No",CPTX_I18N_DOMAIN),
      "yesButton"=>__("Yes",CPTX_I18N_DOMAIN),
      "cancellation"=>__("Operation cancelled",CPTX_I18N_DOMAIN),
      "submitFail"=>__("Error while submitting",CPTX_I18N_DOMAIN),
      "updateFail"=>__("Error while updating",CPTX_I18N_DOMAIN),
      "submitButton"=>__("Resume WordPress publishing process",CPTX_I18N_DOMAIN),
      "statusFail"=>__("Error while getting status",CPTX_I18N_DOMAIN),
      "statusUnknown"=>__("unknown status",CPTX_I18N_DOMAIN),
      "failed"=>__("Failed",CPTX_I18N_DOMAIN),
      "statusError0"=>__("Unknown reason",CPTX_I18N_DOMAIN),
      "statusError1"=>__("Database connection failed",CPTX_I18N_DOMAIN),
      "statusError2"=>__("Internal error: issue has been reported upstream",CPTX_I18N_DOMAIN),
      "statusError3"=>__("font file storage failed",CPTX_I18N_DOMAIN),
      "statusError41"=>__("EOT font generation failed with error code",CPTX_I18N_DOMAIN),
      "statusError42"=>__("Font generation failed with error code",CPTX_I18N_DOMAIN),
      "statusError51"=>__("Can't generate EOT fonts",CPTX_I18N_DOMAIN),
      "statusError52"=>__("Can't generate fonts",CPTX_I18N_DOMAIN),
      "statusError6"=>__("Database access failed",CPTX_I18N_DOMAIN),
      "statusError7"=>__("Font not found",CPTX_I18N_DOMAIN),
      "processing"=>__("Processing",CPTX_I18N_DOMAIN),
      "step"=>__("step",CPTX_I18N_DOMAIN),
      "statusProcess1"=>__("destructuration",CPTX_I18N_DOMAIN),
      "statusProcess2"=>__("disruption",CPTX_I18N_DOMAIN),
      "statusProcess3"=>__("compilation",CPTX_I18N_DOMAIN),
      "statusWaiting"=>__("Waiting to be queued",CPTX_I18N_DOMAIN),
      "statusQueueing"=>__("Queueing",CPTX_I18N_DOMAIN),
      "statusRemaining"=>__("texts to protect before yours.",CPTX_I18N_DOMAIN),
      "statusDone"=>__("Operation succeeded",CPTX_I18N_DOMAIN),
      "pwyw"=>__("This text is entitled to our Pay-What-You-Want policy. Check your account 'Texts' panel, and set the price you consider appropriate for this service. Once done, come back here.",CPTX_I18N_DOMAIN),
      "getInit"=>__("Downloading your protected text",CPTX_I18N_DOMAIN),
      "getSuccess1"=>__("Your text has been successfully protected.",CPTX_I18N_DOMAIN),
      "getSuccess2"=>__("It is now ready to be published.",CPTX_I18N_DOMAIN),
      "getFail"=>__("Error while getting CPROTEXT data",CPTX_I18N_DOMAIN),
      "cancelButton"=>__("Cancel",CPTX_I18N_DOMAIN),
      "okButton"=>__("Ok",CPTX_I18N_DOMAIN)
    )));
}

/**
 * cptx_check_html
 *
 * Display the CPROTEXT meta box
 *
 * @param WP_Post $post 
 * @access public
 * @return void
 */
function cptx_check_html($post){
  global $wpdb;

  $metabox=cptx_getTemplate("metabox");

  $targets=array();
  $replacements=array();

  $cptx_fonts=json_decode(stripslashes(get_option("cptx_fonts")),true);
  $cptx_font=get_option("cptx_font");

  $version=0;
  $textId="";
  $cptx_ie8=get_option("cptx_ie8");
  $cptx_fontsel=$cptx_font;
  $plh="";
  $kw="";
  $statusChange='';

  if($revisions=wp_get_post_revisions($post->ID)){
    // grab the last revision, but not an autosave
    foreach($revisions as $revision){
      if (false!==strpos($revision->post_name,"{$revision->post_parent}-revision")){
        $last_revision=$revision;
        break;
      }
    }
    $cptxed=$wpdb->get_var("select enabled,font,textId,version,plh,ie8enabled from ".$wpdb->prefix."cptx ".
      "where id=".$last_revision->ID);
    if(!is_null($cptxed)){
      $values=array_values(get_object_vars($wpdb->last_result[0]));
      $cptx_fontsel=$values[1];
      $textId=$values[2];
      $version=$values[3];
      $plh=$values[4];
      $cptx_ie8=$values[5];
      unset($values);
      $kw=get_post_meta($last_revision->ID,'cptx_kw',true);
    }

    $meta=get_post_meta($last_revision->ID,"cptx_waiting",true);
    if(!empty($meta)){
      $cptxed=true;
      $textId=$meta[0];
      $cptx_fontsel=$meta[1];
      $plh=$meta[2];
      $kw=$meta[3];
      $cptxçie8=$meta[4];
      $version=0;
      $statusChange=$meta[5];
    }

  }else{
    $cptxed=null;
  }

  $targets[]="__CPTXED__";
  $replacements[]=(string)(!is_null($cptxed));

  if($cptx_ie8){
    $targets[]="id='cptx_ie8'";
    $replacements[]="id='cptx_ie8' checked=\"checked\"";
  }
 
  if($cptxed){
    $targets[]="id=\"cptx_check\"";
    $replacements[]="id=\"cptx_check\" checked=\"checked\"";
    $targets[]="__TEXTID__";
    if(!$version)
      $textId="W".$textId; // W as in "Waiting for the real thing"
    $replacements[]=$textId;
    $targets[]="__STATUS__";
    $replacements[]=$statusChange;
  }else{
    $targets[]="id='cptx_ie8'";
    $replacements[]="id='cptx_ie8' disabled='disabled'";
    $targets[]="id='cptx_fontsel'";
    $replacements[]="id='cptx_fontsel' disabled='disabled'";
    $targets[]="id='cptx_plh'";
    $replacements[]="id='cptx_plh' disabled='disabled'";
    $targets[]="id='cptx_kw'";
    $replacements[]="id='cptx_kw' disabled='disabled'";
    $targets[]="__TEXTID__";
    if(is_null($cptxed))
      $replacements[]="";
    else
      $replacements[]=$textId;
  }

  $targets[]="__FONTSEL_OPTIONS__";
  $options="";
  if(empty($cptx_fonts))
    $options="<option title='.'>".__("no font available",CPTX_I18N_DOMAIN)."</option>";
  else{ 
    foreach($cptx_fonts as $font){
      $options.= "<option value='".$font[0]."'";
      if($font[0]===$cptx_fontsel)
        $options.= " selected='selected'";
      if(!$cptxed)
        $options.= " disabled='disabled'";
      $options.= ">".$font[1]."</option>";
    }
  }
  $replacements[]=$options;

  $targets[]="__POST_TYPE__";
  $replacements[]=$post->post_type;

  $targets[]="__PLH__";
  if(!is_null($cptxed))
    $replacements[]=$plh;
  else
    $replacements[]="";

  $targets[]="__KW__";
  if(!is_null($cptxed))
    $replacements[]=$kw;
  else
    $replacements[]="";

  if(get_option("cptx_db_version")!=CPTX_DB_VERSION ||
    !get_option('cptx_fonts')
  ){
    $targets[]="</noscript>";
    if(get_option("cptx_db_version")!=CPTX_DB_VERSION){
      $replacement=__("Disabled: database version mismatch",CPTX_I18N_DOMAIN);
    }elseif(!get_option('cptx_fonts')){
      $replacement=__("Disabled: you must first synchronize the WordPress CPROTEXT Settings with your CPROTEXT.COM account",CPTX_I18N_DOMAIN);
    }
    $replacements[]="</noscript><div style='color:red'>[ ".$replacement." ]</div>";
    $targets[]="<input";
    $replacements[]="<input disabled='disabled'";
    $targets[]="<textarea";
    $replacements[]="<textarea disabled='disabled'";
  }

  print str_replace($targets,$replacements,$metabox);
}

define("CPTX_MSG_ERROR",1);
define("CPTX_MSG_WARNING",2);
define("CPTX_MSG_DEBUG",3);

/**
 * cptx_msg
 *
 * Register messages to display after text submission
 * and store it in wordpress option cptx_msgs
 *
 * @param int $type 
 * @param string $msg 
 * @param int $line 
 * @access public
 * @return void
 */
function cptx_msg($type,$msg,$line){
  $messages=get_option('cptx_msgs',array());
  $messages[]=array($type,$msg,$line);
  update_option('cptx_msgs',$messages);
}

/**
 * cptx_error
 *
 * Register error messages
 *
 * @param string $msg 
 * @param int $line 
 * @access public
 * @return void
 */
function cptx_error($msg,$line){
  cptx_msg(CPTX_MSG_ERROR,$msg,$line);
}

/**
 * cptx_warning
 *
 * Register warning messages
 *
 * @param string $msg 
 * @param int $line 
 * @access public
 * @return void
 */
function cptx_warning($msg,$line){
  cptx_msg(CPTX_MSG_WARNING,$msg,$line);
}

/**
 * cptx_debug
 *
 * Register debug messages
 *
 * @param string $msg 
 * @param int $line 
 * @access public
 * @return void
 */
function cptx_debug($msg,$line){
  if(CPTX_DEBUG)
    cptx_msg(CPTX_MSG_DEBUG,$msg,$line);
}

/**
 * cptx_getInputData
 *
 * Grab, test and validate data from $_POST
 *
 * @param bool $check check if data are valid 
 * @access public
 * @return array
 */
function cptx_getInputData($check=true){
  $posted_contentId=filter_input(INPUT_POST,"cptx_contentId",
    FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>'/^[0-9a-f]+$/')));
  if($check && !$posted_contentId){
    cptx_debug("getInputData:  wrong contentId (".
      ($posted_contentId===false?"invalid":"missing").")");
    return false;
  }

  $posted_contentVer=filter_input(INPUT_POST,"cptx_contentVer",
    FILTER_VALIDATE_INT);
  if($check && !$posted_contentVer){
    cptx_debug("getInputData:  wrong contentVer (".
      ($posted_contentVer===false?"invalid":"missing").")",__LINE__);
    return false;
  }

  $posted_fontsel=filter_input(INPUT_POST,"cptx_fontsel",
    FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"/^[0-9a-f]+$/")));
  if($check && !$posted_fontsel){
    cptx_debug("getInputData:  wrong fontsel (".
      ($posted_fontsel===false?"invalid":"missing").")",__LINE__);
    return false;
  }

  $posted_contentCSS=filter_input(INPUT_POST,"cptx_contentCSS");
  if($check && !$posted_contentCSS){
    cptx_debug("getInputData:  wrong contentCSS (".
      ($posted_contentCSS===false?"invalid":"missing").")",__LINE__);
    return false;
  }

  $posted_plh=filter_input(INPUT_POST,"cptx_plh");
  if($posted_plh===false){
    cptx_debug("getInputData:  invalid plh");
    return false;
  }

  $posted_contentHTML=filter_input(INPUT_POST,"cptx_contentHTML");
  if($check && !$posted_contentHTML){
    cptx_debug("getInputData:  wrong contentHTML (".
      ($posted_contentHTML===false?"invalid":"missing").")",__LINE__);
    return false;
  }

  $posted_ie8=filter_input(INPUT_POST,"cptx_ie8");
  if($check && !in_array($posted_ie8,array("on","off"))){
    cptx_debug("getInputData:  wrong cptx_ie8",__LINE__);
    return false;
  }
  if($posted_ie8==="on"){
    $posted_ie8=true;
  }else{
    $posted_ie8=false;
  }

  $posted_contentEOTE=filter_input(INPUT_POST,"cptx_contentEOTE");
  if($check && !$posted_contentEOTE){
    if($posted_contentEOTE===false){
      cptx_debug("getInputData:  wrong contentEOTE (invalid)",__LINE__);
      return false;
    }
    cptx_debug("getInputData:  wrong contentEOTE (missing)",__LINE__);
  }
  if(!empty($posted_contentEOTE)){
    $contentEOTE=base64_decode($posted_contentEOTE,true);
    if($check && $contentEOTE===false){
      cptx_debug("getInputData:  wrong contentEOTE (corrupted)");
      return false;
    }
  }else{
    $contentEOTE="NULL";
  }

  $posted_contentEOTS=filter_input(INPUT_POST,"cptx_contentEOTS");
  if($check && !$posted_contentEOTS){
    if($posted_contentEOTS===false){
      cptx_debug("getInputData:  wrong contentEOTS (invalid)",__LINE__);
      return false;
    }
    cptx_debug("getInputData:  wrong contentEOTS (missing)",__LINE__);
  }
  if(!empty($posted_contentEOTS)){
    $contentEOTS=base64_decode($posted_contentEOTS,true);
    if($check && $contentEOTS===false){
      cptx_debug("getInputData:  wrong contentEOTS (corrupted)");
      return false;
    }
  }else{
    $contentEOTS="NULL";
  }

  return array(
    $posted_contentId,
    $posted_contentVer,
    $posted_fontsel,
    $posted_plh,
    $posted_contentCSS,
    $posted_contentHTML,
    $posted_ie8,
    $contentEOTE,
    $contentEOTS);

}

/**
 * cptx_db_null_value
 *
 * Replace the quoted string 'NULL' by the non quoted string NULL
 * This allows wordpress to set a field to NULL in a SQL statement;
 *
 * @param string $query 
 * @access public
 * @return string
 */
function cptx_db_null_value($query){
  return str_replace("'NULL'","NULL",$query); 
}

/**
 * cptx_upsertdb
 *
 * Update or insert a DB wp_cptx row 
 *
 * @param int $postId 
 * @param int $parentId 
 * @param bool $insert
 * @access public
 * @return bool
 */
function cptx_upsertdb($postId,$parentId,$insert=false){
  global $wpdb;

  $inputs=cptx_getInputData();
  if(!$inputs){
    cptx_error(__("Invalid input data"));
    return false;
  }
 
  list(
    $posted_contentId,
    $posted_contentVer,
    $posted_fontsel,
    $posted_plh,
    $posted_contentCSS,
    $posted_contentHTML,
    $posted_ie8,
    $contentEOTE,
    $contentEOTS)=$inputs;

  $inserted=$updated=true;
  add_filter('query','cptx_db_null_value');
  if($insert){
    $inserted=$wpdb->insert($wpdb->prefix."cptx",
    array(
      "id"=>$postId,
      "enabled"=>true,
      "parentId"=>$parentId,
      "version"=>$posted_contentVer,
      "font"=>$posted_fontsel,
      "plh"=>$posted_plh,
      "css"=>preg_replace("/cprotext[0-9a-f]{8}/","wpcxX",
      str_replace('\\','\\\\',$posted_contentCSS)),
      "html"=>preg_replace("/cprotext[0-9a-f]{8}-/","wpcxX-",
      str_replace('\\','\\\\',$posted_contentHTML)),
      "ie8enabled"=>$posted_ie8,
      "eote"=>$contentEOTE,
      "eots"=>$contentEOTS,
      "textId"=>$posted_contentId));
  }else{
    $updated=$wpdb->update($wpdb->prefix."cptx",
      array(
        "id"=>$postId,
        "enabled"=>true,
        "parentId"=>$parentId,
        "version"=>$posted_contentVer,
        "font"=>$posted_fontsel,
        "plh"=>$posted_plh,
        "css"=>preg_replace("/cprotext[0-9a-f]{8}/","wpcxX",
        str_replace('\\','\\\\',$posted_contentCSS)),
        "html"=>preg_replace("/cprotext[0-9a-f]{8}-/","wpcxX-",
        str_replace('\\','\\\\',$posted_contentHTML)),
        "ie8enabled"=>$posted_ie8,
        "eote"=>$contentEOTE,
        "eots"=>$contentEOTS,
        "textId"=>$posted_contentId),
      array("id"=>$postId));
  }
  remove_filter('query','cptx_db_null_value');

  if($inserted===false || !$updated){
    $lasterror=$wpdb->last_error;
    if($insert){
      cptx_error(__("Failing to insert CPROTEXT data for post id",CPTX_I18N_DOMAIN)." $postId",
      __LINE__);
    }else{
      if($updated===false){
        cptx_error(__("Failing to update CPROTEXT data for post id",CPTX_I18N_DOMAIN)." $postId",
          __LINE__);
      }else{
        cptx_warning(__("Nothing to update CPROTEXT data for post id",CPTX_I18N_DOMAIN)." $postId",
          __LINE__);
      }
    }
    cptx_debug("wpdb error: ".$lasterror,__LINE__);
    return false;
  }

  if($insert)
    cptx_debug("Successfully insert CPROTEXT data for post id $postId",__LINE__);
  else
    cptx_debug("Successfully update CPROTEXT data for post id $postId",__LINE__);

  $meta=get_post_meta($postId,'cptx_waiting',true);
  if(!empty($meta)){
    if(!delete_metadata("post",$postId,"cptx_waiting")){
      cptx_error(__("Failing to delete waiting metadata for",CPTX_I18N_DOMAIN)." ".$postId,
        __LINE__);
      return false;
    }
    cptx_debug("Successfuly delete waiting metadata for $postId.",__LINE__);
  }else{
    cptx_debug("No waiting metadata to delete",__LINE__);
  }

  return true;
}

/**
 * cptx_updateKeywords
 * 
 * update meta data keywords
 *
 * @param int $postId 
 * @access public
 * @return bool
 */
function cptx_updateKeywords($postId){
  $backtrace=debug_backtrace();
  if($backtrace[1]["function"]==="cptx_updatePost"){
    $line=$backtrace[1]["line"];
  }else{
    $line=$backtrace[0]["line"];
  }

  $posted_kw=filter_input(INPUT_POST,"cptx_kw");
  if($posted_kw===false){
    cptx_debug("SAVEPOST: invalid kw",$line);
    break;
  }

  if($posted_kw===get_post_meta($postId,'cptx_kw',true) ||
    // because update_metadata return false when newdata==olddata
    update_metadata('post',$postId,'cptx_kw',$posted_kw))
    cptx_debug("Save keywords for $postId",$line);
  else{
    cptx_error(__("Failing to save keywords for",CPTX_I18N_DOMAIN).$postId,$line);
    return false;
  }
  return true;
}

/**
 * cptx_preUpdatePost
 *
 * Save data prior when an asynchronous update occurs
 *
 * @param mixed $postId 
 * @param mixed $parentId 
 * @param mixed $posted_statusChange 
 * @access public
 * @return bool
 */
function cptx_preUpdatePost($postId,$parentId,$posted_statusChange){
  if(!($posted_statusChange & CPTX_STATUS_PROCESSING)){
    cptx_debug("PreUpdating not required");
    return false;
  }

  $inputs=cptx_getInputData(false);
  if(!$inputs){
    cptx_error(__("Invalid input data"));
    return false;
  }
  
  list(
    $posted_contentId,
    $posted_contentVer,
    $posted_fontsel,
    $posted_plh,
    $posted_contentCSS,
    $posted_contentHTML,
    $posted_ie8,
    $contentEOTE,
    $contentEOTS)=$inputs;

  if(!$posted_contentId){
    cptx_debug("cptx_preUpdatePost:  wrong contentId (".
      ($posted_contentId===false?"invalid":"missing").")",__LINE__);
    return false;
  }

  if(!$posted_fontsel){
    cptx_debug("cptx_preUpdatePost:  wrong fontsel (".
      ($posted_fontsel===false?"invalid":"missing").")",__LINE__);
    return false;
  }
  
  if($posted_plh===false){
    cptx_debug("cptx_preUpdatePost:  invalid plh",__LINE__);
    return false;
  }

  $posted_kw=filter_input(INPUT_POST,"cptx_kw");
  if($posted_kw===false){
    cptx_debug("cptx_preUpdatePost: invalid kw",__LINE__);
    return false;;
  }

  if(!in_array($posted_ie8,array("on","off"))){
    cptx_debug("cptx_preUpdatePost:  invalid cptx_ie8",__LINE__);
    return false;
  }

  // When a protection process has been initiated and asynchronized, the relevant
  // data are stored in the cpxt_waiting metadata so that on the next visit 
  // into the corresponding revision, the CPROTEXT form is correctly filled in.
  if(array($posted_contentId,$posted_fontsel,$posted_plh,$posted_kw,$posted_ie8,$posted_statusChange)!==
    get_post_meta($postId,'cptx_waiting',true) &&
    // because update_metadata return false when newdata==olddata
    !update_metadata("post",$postId,"cptx_waiting",array(
      $posted_contentId,
      $posted_fontsel,
      $posted_plh,
      $posted_kw,
      $posted_ie8,
      $posted_statusChange))
  ){
    cptx_error(__("Failing to save waiting metadata for",CPTX_I18N_DOMAIN)." ".$postId,
      __LINE__);
    return false;
  }

  cptx_debug("Successfully save waiting metadata for $postId",__LINE__);
  return true;
}

/**
 * cptx_updatePost
 * 
 * @param int $postId 
 * @param int $parentId 
 * @param bool $kw 
 * @param bool $insert 
 * @access public
 * @return bool
 */
function cptx_updatePost($postId,$parentId,$kw=false,$insert=false){
  $posted_statusChange=filter_input(INPUT_POST,"cptx_statusChange",
    FILTER_VALIDATE_INT,array("options"=>array("min_range"=>0,"max_range"=>2047)));

  if(is_null($posted_statusChange)){
    cptx_debug("StatusChange undefined, protection not requested",__LINE__);
    // this revision was not submitted for cprotextion
    return false;
  }

  $result=cptx_upsertdb($postId,$parentId,$insert,$posted_statusChange);
  if($result){
    if($posted_statusChange & CPTX_STATUS_PROCESSING)
      cptx_debug("Successful initial submission:".$postId,debug_backtrace()[0]["line"]);
    else
      cptx_debug("Successfully resumed submission:".$postId,debug_backtrace()[0]["line"]);
  }else{
    cptx_error(__("Failing to update CPROTEXT data for post id",
      CPTX_I18N_DOMAIN)." $postId",debug_backtrace()[0]["line"]);
    return false;
  }

  if($kw){
    return cptx_updateKeywords($postId);
  }

  return true;
}

/**
 * cptx_setProtectionStatus
 * 
 * @param int $id 
 * @param bool $enabled 
 * @access public
 * @return bool
 */
function cptx_setProtectionStatus($id,$enabled){
  global $wpdb;

  if($enabled){
    $status="enabled";
  }else{
    $status="disabled";
  }

  $updated=$wpdb->update($wpdb->prefix."cptx",
    array("enabled"=>$enabled),
    array("id"=>$id));

  if($updated===false){
    cptx_error(__("Failing to update CPROTEXT data for ",
      CPTX_I18N_DOMAIN).$id,debug_backtrace()[0]["line"]);
    return false;
  }else{
    if(!$updated){
      cptx_debug("CPROTEXT data were already $status for $id",
        debug_backtrace()[0]["line"]);
    }else{
      cptx_debug("CPROTEXT data successfully $status for $id",
        debug_backtrace()[0]["line"]);
    }
  }
  return true;
}

/**
 * cptx_transferPost
 * 
 * @param int $fromId 
 * @param int $toId 
 * @access public
 * @return bool
 */
function cptx_transferPost($fromId,$toId){
  global $wpdb;

  $updated=$wpdb->update($wpdb->prefix."cptx",
    array(
      "id"=>$toId,
      "enabled"=>true),
    array("id"=>$fromId));
  if($updated===false){
    cptx_error(__("Failing to update CPROTEXT data for ",
      CPTX_I18N_DOMAIN).$fromId,debug_backtrace()[0]["line"]);
    return false;
  }else{
    if(!$updated){
      cptx_debug("CPROTEXT were already transfered and enabled to ".
        $toId,debug_backtrace()[0]["line"]);
    }else{
      cptx_debug("CPROTEXT data successfully transfered and enabled from ".
        $fromId." to ".$toId.".",debug_backtrace()[0]["line"]);
    }
  }
  if(update_metadata('post',$fromId,'cptx_id',$toId))
    cptx_debug("Associate $fromId with CPROTEXT data from $toId.",
     debug_backtrace()[0]["line"]);
  else{
    cptx_error(sprintf(__("Failing to associate %u with CPROTEXT data from %u.",
      CPTX_I18N_DOMAIN),$fromId,$toId),debug_backtrace()[0]["line"]);
    return false;
  }

  $updated=$wpdb->update($wpdb->prefix."postmeta",
    array("meta_value"=>$toId),
    array(
      "meta_key"=>"cptx_id",
      "meta_value"=>$fromId
    ));
  if($updated===false){
    cptx_error(sprintf(
      __("Failing to transfer other posts associated with %u CPROTEXT data to %u",
      CPTX_I18N_DOMAIN),$fromId,$toId),debug_backtrace()[0]["line"]);
    return false;
  }else{
    if(!$updated){
      cptx_debug("No other associated posts to transfer to ".
        $toId,debug_backtrace()[0]["line"]);
    }else{
      cptx_debug("Other associated posts successfully transfered to ".$toId,
        debug_backtrace()[0]["line"]);
    }
  }
  return true;
}

function cptx_postHasIE8SupportData($postId){
  global $wpdb;

  $contentEOTS=$wpdb->get_var("select eots from ".$wpdb->prefix."cptx ".
        "where id=".$postId);

  if(empty($contentEOTS)){
    return false;
  }
  
  return true;
}

function cptx_setIE8Support($postId,$enabled){
  global $wpdb;

  if($enabled){
    $status="enabled";
  }else{
    $status="disabled";
  }

  $updated=$wpdb->update($wpdb->prefix."cptx",
    array("ie8enabled"=>$enabled),
    array("id"=>$postId));

  if($updated===false){
    cptx_error(__("Failing to update IE8 support for ",
      CPTX_I18N_DOMAIN).$id,debug_backtrace()[0]["line"]);
    return false;
  }else{
    if(!$updated){
      cptx_debug("IE8 support was already $status for $postId",
        debug_backtrace()[0]["line"]);
    }else{
      cptx_debug("IE8 support successfully $status for $postId",
        debug_backtrace()[0]["line"]);
    }
  }
  return true;
}

/**
 * cptx_savePost
 *
 * Associate or dissociate a posted text with its protected equivalent
 * generated by CPROTEXT, depending on its publication status
 *
 * @param int $postId 
 * @param int $post 
 * @access public
 * @return void
 */
function cptx_savePost($postId,$post){
  global $wpdb;

  $posted_statusChange=filter_input(INPUT_POST,"cptx_statusChange",
    FILTER_VALIDATE_INT,array("options"=>array("min_range"=>0,"max_range"=>2047)));

  if(is_null($posted_statusChange)){
    cptx_debug("StatusChange undefined, protection not requested",__LINE__);
    // this revision was not submitted for cprotextion
    return;
  }

  cptx_debug("StatusChange $posted_statusChange for id $postId",__LINE__);

  if($posted_statusChange===false){
    cptx_debug("StatusChange: invalid",__LINE__);
    // this revision was not submitted for cprotextion
    return;
  }

  cptx_debug("We have the job !",__LINE__);

  $parentId=null;
  switch($posted_statusChange & 
    (CPTX_STATUS_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_CPROTEXTED)){
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_NOT_CHANGED + CPTX_STATUS_CPROTEXTED):
    cptx_debug("Publish => Protected Publish",__LINE__);

    if($parentId=wp_is_post_revision($postId)){
      // WordPress sends the modified revision of a post
      if(!($posted_statusChange&CPTX_STATUS_WPUPDATES)){
        // Stop everything before apocalypse !
        cptx_warning(__("This should be a modified revision, but it seems it's not !",
          CPTX_I18N_DOMAIN),__LINE__);
        cptx_warning(__("CPROTEXT data integration cancelled.",
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      cptx_debug(sprintf("Revision %u for parent %u",$postId,$parentId),
        __LINE__);

      // If exists, store the previously protected revision id for this post,
      // otherwise, store null
      $prevRevId=$wpdb->get_var("select id from ".$wpdb->prefix."cptx ".
        "where parentId=".$parentId." ORDER BY id DESC");
      if(is_null($prevRevId)){
        cptx_debug("Parent $parentId has no previously protected revision.",
          __LINE__);
      }else{
        $prevRevId=(int)$prevRevId;
        cptx_debug("Parent $parentId previous protected revision is $prevRevId",
          __LINE__);
      }
      
      if(!update_post_meta($parentId,'cptx_prevProtectedRev',$prevRevId)){
        cptx_error(sprintf(
          __("Failing to save previously protected revision %u for parent %u",
          CPTX_I18N_DOMAIN),$prevRevId,$parentId),
        __LINE__);
        break;
      }

      $last_revision=null;
      if($revisions=wp_get_post_revisions($parentId)){
        // grab the previous revision before the one currently submitted,
        // but not an autosave
        $previous=false;
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,"{$revision->post_parent}-revision")){
            if($previous){
              $last_revision=$revision;
              break;
            }else{
              $previous=true;
            }
          }
        }
      }else{
        cptx_warning(__("This post does not have any revision: not good !",
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      if(is_null($last_revision)){
        cptx_warning(sprintf(__("Can't find Parent %u previous revision",
          CPTX_I18N_DOMAIN),$parentId),
        __LINE__);
        break;
      }else{
        cptx_debug("Parent $parentId previous revision is ".$last_revision->ID,
          __LINE__);
      }

      if(!update_post_meta($parentId,'cptx_prevRevProtected',
        ($prevRevId===$last_revision->ID)?1:0)
      ){
        cptx_error(sprintf(
          __("Failing to save previously protected state of previous revision for parent %u",
          CPTX_I18N_DOMAIN),$parentId),
        __LINE__);
        break;
      }
      break;
    }else{
      // WordPress updates the parent revision of a post
      // So, if we've been through the above block before, it's a modified 
      // revision of a post;
      // otherwise, it's the same revision of a post that may or may not be 
      // associated with CPROTEXT data which may have to be updated
      $parentId=$postId;
      if($revisions=wp_get_post_revisions($parentId)){
        // grab the last revision, but not an autosave
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,"{$revision->post_parent}-revision")){
            $last_revision=$revision;
            break;
          }
        }
      }else{
        cptx_warning(__("This post does not have any revision: not good !",
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }
      $postId=$last_revision->ID;

      // set single to false so that we can differentiate a non existing value 
      // (empty array) from an empty value (array with one empty element)
      $prevProtectedRevId=get_post_meta($parentId,'cptx_prevProtectedRev',false);
      if(empty($prevProtectedRevId)){
        $prevProtectedRevId=null;
      }else{
        if(is_null($prevProtectedRevId[0])){
          $prevProtectedRevId=false;
        }else{
          $prevProtectedRevId=(int)$prevProtectedRevId[0];
        }
        if(!($posted_statusChange & CPTX_STATUS_PROCESSING) &&
          !delete_post_meta($parentId,'cptx_prevProtectedRev')
        ){
          cptx_error(__("Failing to delete previous protected revision id metadata",
            CPTX_I18N_DOMAIN),__LINE__);
          break;
        }
      }

      // set single to false so that we can differentiate a non existing value 
      // (empty array) from an empty value (array with one empty element)
      $prevRevProtected=get_post_meta($parentId,'cptx_prevRevProtected',false);
      if(empty($prevRevProtected)){
        $prevRevProtected=null;
      }else{
        $prevRevProtected=((int)$prevRevProtected[0]===1?true:false);
        if(!($posted_statusChange & CPTX_STATUS_PROCESSING) &&
          !delete_post_meta($parentId,'cptx_prevRevProtected')
        ){
          cptx_error(__("Failing to delete protected state of previous revision",
            CPTX_I18N_DOMAIN),__LINE__);
          break;
        }
      }

      // find this post last revision associated with CPROTEXT data
      $protectedPostId=$wpdb->get_var("select id from ".$wpdb->prefix."cptx ".
        "where parentId=".$parentId." ORDER BY id DESC");
      if(!is_null($protectedPostId)){
        $protectedPostId=(int)$protectedPostId;
      }
      
      /* 
       * postId = current revision
       * 
       * prevRevProtected = is previous revision to the current one associated 
       *                    with CPROTEXT data or not ?
       *                    if prevRevProtected===null
       *                      => same revision of published revision
       *                         with or without CPROTEXT data [A|B]
       *                    if prevRevProtected===true
       *                      => modified revision of published revision
       *                         with CPROTEXT data [C]
       *                    if prevRevProtected===false
       *                      => modified revision of published revision
       *                         without CPROTEXT data [D]
       * 
       * prevProtectedRevId = previous revision with protection before dealing 
       *                      with the current revision
       *                      if prevProtectedRevId===null
       *                        => same revision of published revision
       *                           with or without CPROTEXT data [A|B]
       *                      if prevProtectedRevId===false
       *                        => modified revision of published version
       *                           without CPROTEXT data [D]
       *                      if prevProtectedRevId===id
       *                        => modified revision of a published revision
       *                           with or without CPROTEXT data [C|D]
       * 
       * protectedPostId = last revision with protection after dealing with
       *                   the current revision
       *                  if protectedPostId === postId
       *                    => same revision of published revision
       *                       with CPROTEXT data or
       *                       modified revision of published revision
       *                       with or without CPROTEXT data [A|C|D] 
       *                  if protectedPostId === null
       *                    => same revision of published revision
       *                       without CPROTEXT data [B]
       *                  if protectedPostId !== null && protectedPostId !== postId
       *                    => same revision of published revision
       *                       without CPROTEXT data [B]
       *
       * case A : update CPROTEXT data with posted CPROTEXT data
       * case B : insert posted CPROTEXT data
       * case C : if CPTX_STATUS_UPDATE_CONTENT
       *            => insert posted CPROTEXT data
       *          if CPTX_STATUS_UPDATE_TITLE && !CPTX_STATUS_UPDATE_CONTENT
       *            => attach previous CPROTEXT data to new revision
       * case D : insert CPROTEXT data
       */ 

      cptx_debug(sprintf("Parent %u for revision %u",$parentId,$postId),
        __LINE__);

      if(!is_null($prevRevProtected)){
        assert(!is_null($prevProtectedRevId));
        assert($protectedPostId === $postId);
        if($prevRevProtected){
          // case C
          assert($prevProtectedRevId!==false);
          if($posted_statusChange & CPTX_STATUS_UPDATE_CONTENT ||
            // new content means new contentCSS and contentEOTS
            $posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
            // new font means new contentCSS and contentEOTS)
            $posted_statusChange&CPTX_STATUS_UPDATE_PLH
            // new PLH means new contentCSS and contentEOTE
          ){
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
              break;
            }else{
              if(!cptx_setProtectionStatus($prevProtectedRevId,false))
                break;
              if(!cptx_updatePost($postId,$parentId,true,true))
                break;
            }
          }else{
            assert($posted_statusChange & CPTX_STATUS_UPDATE_TITLE);
            // The CPROTEXT data must be associated with the currently protected
            // revision when it exists, or the last protected revision otherwise.
            // Since this modified revision seems to have only a change in the
            // title and potentially in CPROTEXT keywords, the CPROTEXT data must be
            // associated with it and the previous revision annotated in 
            // order to keep a trace of its association with the same CPROTEXT data.
            // Moreover CPROTEXT data are enabled whatever its previous state.

            // If IE8 support was disabled or reenabled, there is no
            // need to deal with CPTX_STATUS_PROCESSING since the
            // wordpress process is not connected to what happens on the
            // cprotext account

            if($posted_statusChange&CPTX_STATUS_IE8_CHANGED){
              if($posted_statusChange&CPTX_STATUS_IE8_ENABLED){
                if(!cptx_postHasIE8SupportData($prevProtectedRevId)){
                  if($posted_statusChange & CPTX_STATUS_PROCESSING){
                    cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
                    break;
                  }else{
                    if(!cptx_transferPost($prevProtectedRevId,$postId))
                      break;
                    if(!cptx_updatePost($postId,$parentId,true,false))
                      break;
                  }
                }else{
                  if(!cptx_transferPost($prevProtectedRevId,$postId))
                    break;
                  if(!cptx_setIE8Support($postId,true))
                    break;
                }
              }else{
                //disable ie8 support but keep contentEOTE and contentEOTS
                if(!cptx_transferPost($prevProtectedRevId,$postId))
                  break;
                if(!cptx_setIE8Support($postId,false))
                  break;
              }
            }else{
              if(!cptx_transferPost($prevProtectedRevId,$postId))
                break;
	    }

            if(!cptx_updateKeywords($postId))
              break;
          }
        }else{
          // case D
          assert($prevProtectedRevId===false);
          if($posted_statusChange & CPTX_STATUS_PROCESSING){
            cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
          }else{
            cptx_updatePost($postId,$parentId,true,true);
          }
          break;
        }
      }else{
        //case A|B
        assert(is_null($prevProtectedRevId));
        if($protectedPostId === $postId){
          // case A
          if($posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
            // new font means new contentCSS and contentEOTS)
            $posted_statusChange&CPTX_STATUS_UPDATE_PLH || 
            // new PLH means new contentCSS and contentEOTE
            ($posted_statusChange&CPTX_STATUS_IE8_CHANGED &&
            $posted_statusChange&CPTX_STATUS_IE8_ENABLED &&
            !cptx_postHasIE8SupportData($postId))
            // means new contentEOTE and contentEOTS 
          ){
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
            }else{
              cptx_updatePost($postId,$parentId,true,false);
            }
            break;
          }else{
            if($posted_statusChange&CPTX_STATUS_IE8_CHANGED){
              if($posted_statusChange&CPTX_STATUS_IE8_ENABLED){
                assert(cptx_postHasIE8SupportData($postId));
                if(!cptx_setIE8Support($postId,true))
                  break;
              }else{
                if(!cptx_setIE8Support($postId,false))
                  break;
              }
            }

            if(!cptx_setProtectionStatus($postId,true))
              break;
            if(!cptx_updateKeywords($postId))
              break;
          }
        }else{
          // case B
          if($posted_statusChange & CPTX_STATUS_PROCESSING){
            cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
          }else{
            if(!cptx_updatePost($postId,$parentId,true,true))
              break;
            if(!cptx_updateKeywords($postId))
              break;
          }
          break;
        }
      }
    }
    break;
  case (CPTX_STATUS_NOT_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_CPROTEXTED):
    cptx_debug("Not published => Protected Publish",__LINE__);

    if($parentId=wp_is_post_revision($postId)){
      // WordPress sends the modified revision of a post
      if(!($posted_statusChange&CPTX_STATUS_WPUPDATES)){
        // Stop everything before apocalypse !
        cptx_warning(__("This should be a modified revision, but it seems it's not !",
          CPTX_I18N_DOMAIN),__LINE__);
        cptx_warning(__("CPROTEXT data integration cancelled.",
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      cptx_debug(sprintf("Revision %u for parent %u",$postId,$parentId),
        __LINE__);

      // if this new revision alter the text content, CPROTEXT data is saved
      if($posted_statusChange & CPTX_STATUS_UPDATE_CONTENT){
        if($posted_statusChange & CPTX_STATUS_PROCESSING){
          cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
        }else{
          cptx_updatePost($postId,$parentId,false,true);
        }
      }else{
        cptx_debug("Title change only.",__LINE__);
      }
      break;
    }else{
      // WordPress updates the parent revision of a post
      // So, if we've been through the above block before, it's a modified 
      // revision of a post;
      // otherwise, it's the same revision of a post that may or may not be 
      // associated with CPROTEXT data which may have to be updated

      $parentId=$postId;
      if($revisions=wp_get_post_revisions($parentId)){
        // grab the last revision, but not an autosave
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,"{$revision->post_parent}-revision")){
            $last_revision=$revision;
            break;
          }
        }
      }else{
        cptx_warning(__("This post does not have any revision: not good !",
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      $postId=$last_revision->ID;

      if(is_null($postId)){
        cptx_error(__("Failing to find revision id for post",
          CPTX_I18N_DOMAIN)." $parentId",__LINE__);
        break;
      }

      cptx_debug(sprintf("Parent %u for revision %s",$parentId,$postId),
        __LINE__);

      // check if we are resuming an asynchronous submission of a modified revision
      $meta=get_post_meta($last_revision->ID,"cptx_waiting",true);
      if(!($posted_statusChange & CPTX_STATUS_PROCESSING) &&
        !empty($meta) &&
        ($posted_statusChange & CPTX_STATUS_UPDATE_CONTENT) &&
        // yes we do, so let's associate this post with CPROTEXT data
        !cptx_updatePost($postId,$parentId,false,true))
        break;

      $enabled=$wpdb->get_var("select enabled from ".$wpdb->prefix."cptx "."
        where id=".$postId);

      if($posted_statusChange & CPTX_STATUS_PROCESSING &&
        !empty($meta)
      ){
        $enabled=true;
      }

      if(is_null($enabled)){
        // No CPROTEXT data associated with this revision,
        // therefore this does not occur after the 
        // "WordPress sends the modified revision of a post" block
        // and we can conclude it is the same revision of a post that is asked 
        // to be protected [case D],
        // or it does and we can conclude that it is a modified revision because 
        // of a changed title that is asked to be protected, so if previous revision 
        // was associated with CPROTEXT data, and none of them has been 
        // modified, we transfer the CPROTEXT data [case E], otherwise, we insert new 
        // CPROTEXT data [case F and G]
        if($posted_statusChange&CPTX_STATUS_UPDATE_CONTENT){
          cptx_error(__("This should have the same content, but it seems it does not !",
            CPTX_I18N_DOMAIN),__LINE__);
          break;
        }

        if($posted_statusChange&CPTX_STATUS_UPDATE_TITLE){
          // case E F or G
          cptx_debug("Modified revision, add protection",__LINE__);
          $last_revision=null;
          if($revisions=wp_get_post_revisions($parentId)){
            // grab the previous revision before the one currently submitted,
            // but not an autosave
            $previous=false;
            foreach($revisions as $revision){
              if (false!==strpos($revision->post_name,"{$revision->post_parent}-revision")){
                if($previous){
                  $last_revision=$revision;
                  break;
                }else{
                  $previous=true;
                }
              }
            }
          }else{
            cptx_warning(__("This post does not have any revision: not good !",
              CPTX_I18N_DOMAIN),__LINE__);
            break;
          }

          if(is_null($last_revision)){
            cptx_warning(sprintf(__("Can't find Parent %u previous revision",
              CPTX_I18N_DOMAIN),$parentId),
            __LINE__);
            //todo: what if this is a new post with only a title? this correpond 
            //to this case, doesnt it ? then if no content, protection request must be 
            //denied !
            break;
          }

          cptx_debug("Parent $parentId previous revision is ".$last_revision->ID,
            __LINE__);
          
          $enabled=$wpdb->get_var("select enabled from ".$wpdb->prefix."cptx "."
            where id=".$last_revision->ID);
          if(is_null($enabled)){
            // case G
            cptx_debug("No previous protected data, add protection",__LINE__);
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
            }else{
              cptx_updatePost($postId,$parentId,true,true);
            }
            break;
          }else{
            cptx_debug("Previous protected data exists",__LINE__);
            assert(!$enabled);
            if($posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
              // new font means new contentCSS and contentEOTS)
              $posted_statusChange&CPTX_STATUS_UPDATE_PLH
              // new PLH means new contentCSS and contentEOTE
            ){
              // case F
              if($posted_statusChange & CPTX_STATUS_PROCESSING){
                cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
              }else{
                if($posted_statusChange&CPTX_STATUS_UPDATE_FONT){
                  // case F1
                  cptx_updatePost($postId,$parentId,true,true);
                }else{
                  // case F2
                  if(!cptx_transferPost($last_revision->ID,$postId))
                    break;
                  cptx_updatePost($postId,$parentId,true,false);
                }
              }
              break;
            }else{
              // case E
              cptx_debug("Same CPROTEXT data, transfer and enable protection",__LINE__);
              // If IE8 support was disabled or reenabled, there is no
              // need to deal with CPTX_STATUS_PROCESSING since the
              // wordpress process is not connected to what happens on the
              // cprotext account

              if($posted_statusChange&CPTX_STATUS_IE8_CHANGED){
                if($posted_statusChange&CPTX_STATUS_IE8_ENABLED){
                  if(!cptx_postHasIE8SupportData($last_revision->ID)){
                    if($posted_statusChange & CPTX_STATUS_PROCESSING){
                      cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
                      break;
                    }else{
                      if(!cptx_transferPost($last_revision->ID,$postId))
                        break;
                      if(!cptx_updatePost($postId,$parentId,true,false))
                        break;
                    }
                  }else{
                    if(!cptx_transferPost($last_revision->ID,$postId))
                      break;
                    if(!cptx_setIE8Support($postId,true))
                      break;
                  }
                }else{
                  //disable ie8 support but keep contentEOTE and contentEOTS
                  if(!cptx_transferPost($last_revision->ID,$postId))
                    break;
                  if(!cptx_setIE8Support($postId,false))
                    break;
                }
              }

              if(!cptx_updateKeywords($postId))
                break;
            }
          } 
        }else{
          // case D
          cptx_debug("Same revision, add protection",__LINE__);
          if($posted_statusChange & CPTX_STATUS_PROCESSING){
            cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
          }else{
            cptx_updatePost($postId,$parentId,true,true);
          }
          break;
        }
      }else{
        // CPROTEXT data are associated with this revision,
        // therefore there are 3 possibilities for a draft asked to be 
        // published:
        // A/ data have been inserted in the "WordPress sends the modified
        //    revision of a post" block asynchronously or not
        // B/ data went through the sequence:
        //         Published/Protected -> Draft/Unprotected ->
        //    and are now submitted to be Published/protected for the same
        //    revision and potentially different CPROTEXT data
        //
        // C/ the draft about to be published is protected, which should not be allowed
        if(!$enabled){
          if($posted_statusChange&CPTX_STATUS_UPDATES){
            if(!($posted_statusChange&CPTX_STATUS_WPUPDATES))
              // case B requiring CPROTEXT data update
              cptx_debug("Same revision, with different CPROTEXT data: ".
              "update CPROTEXT data and enable protection.",__LINE__);
            else{
              // This is not any of case A, B or C, but it should not happen !
              // Stop everything before apocalypse !
              cptx_warning(__("This should be the same revision, ".
                "but it does not seem to be so !",
                CPTX_I18N_DOMAIN),__LINE__);
              cptx_warning(__("CPROTEXT data integration cancelled."),__LINE__);
              break;
            }
          }else{
            // case B
            cptx_debug("Same revision, same CPROTEXT data: enable protection",__LINE__);
          }

          if($posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
            // new font means new contentCSS and contentEOTS)
            $posted_statusChange&CPTX_STATUS_UPDATE_PLH
            // new PLH means new contentCSS and contentEOTE
          ){
            // update CPROTEXT data and enable protection
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
            }else{
              cptx_updatePost($postId,$parentId,true,false);
            }
            break;
          }else{
            // enable protection
            if($posted_statusChange&CPTX_STATUS_IE8_CHANGED){
              if($posted_statusChange&CPTX_STATUS_IE8_ENABLED){
                if(!cptx_postHasIE8SupportData($prevProtectedRevId)){
                  if($posted_statusChange & CPTX_STATUS_PROCESSING){
                    cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
                    break;
                  }else{
                    cptx_updatePost($postId,$parentId,true,false);
                    break;
                  }
                }else{
                  if(!cptx_setProtectionStatus($postId,true))
                    break;
                  if(!cptx_setIE8Support($postId,true))
                    break;
                }
              }else{
                //disable ie8 support but keep contentEOTE and contentEOTS
                if(!cptx_setProtectionStatus($postId,true))
                  break;
                if(!cptx_setIE8Support($postId,false))
                  break;
              }
            }

            if(!cptx_updateKeywords($postId))
              break;
          }
        }else{
          if(!($posted_statusChange&CPTX_STATUS_WPUPDATES)){
            //case C
            // Stop everything before apocalypse !
            cptx_warning(__("How can this about-to-be-published draft be protected ?",
              CPTX_I18N_DOMAIN),__LINE__);
            cptx_warning(__("CPROTEXT data integration cancelled.",
              CPTX_I18N_DOMAIN),__LINE__);
            break;
          }

          // case A
          cptx_debug("This seems to be a modified revision.",__LINE__);

          if(!cptx_updateKeywords($postId))
            break;

          cptx_debug("Job done:".$postId,__LINE__);
          break;
        }
      }
    }
    break;
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_NOT_CHANGED + CPTX_STATUS_NOT_CPROTEXTED):
    cptx_debug("Protected Publish => Publish",__LINE__);
    $announced=true;
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_NOT_CPROTEXTED):
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_CPROTEXTED):
    if(!$announced)
      cptx_debug("Protected Publish => Draft or Pending",__LINE__);

    if($parentId=wp_is_post_revision($postId)){
      // Modified revision
      cptx_debug(sprintf("Revision %u for parent %u",$postId,$parentId),
        __LINE__);
      $updated=$wpdb->update($wpdb->prefix."cptx",
        array("enabled"=>false),
        array("parentId"=>$parentId));
      if($updated===false){
        cptx_error(__("Failing to disable CPROTEXT data for parent id",
          CPTX_I18N_DOMAIN)." $parentId",__LINE__);
        break;
      }else{
        if(!$updated){
          cptx_debug("CPROTEXT data were already disabled for parent id $parentId",
            __LINE__);
        }else{
          cptx_debug("CPROTEXT data were successfully disabled for parent id $parentId",
            __LINE__);
        }
      }
    }else{
      // Modified revision if we've been through the above block before
      // Same revision otherwise
      $parentId=$postId;

      if($revisions=wp_get_post_revisions($parentId)){
        // grab the last revision, but not an autosave
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,"{$revision->post_parent}-revision")){
            $last_revision=$revision;
            break;
          }
        }
      }else{
        cptx_warning(__("This post does not have any revision: not good !",
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      $postId=$last_revision->ID;

      if(is_null($postId)){
        cptx_error(__("Failing to find revision id for post",
          CPTX_I18N_DOMAIN)." $parentId",__LINE__);
        break;
      }

      cptx_debug(sprintf("Parent %u for revision %u",$parentId,$postId),
        __LINE__);

      $enabled=$wpdb->get_var("select enabled from ".$wpdb->prefix."cptx "."
        where id=".$postId);

      if(is_null($enabled)){
        cptx_debug("Modified revision, no protection",__LINE__);
      }else{
        cptx_debug("Same revision, disable protection",__LINE__);
        if(!cptx_setProtectionStatus($postId,false))
          break;
      }
    }
    break;
  }
}

/**
 * cptx_messagesHandler
 *
 * Display registered messages and flush cptx_msgs option
 *
 * @access public
 * @return void
 */
function cptx_messagesHandler(){
  $messages=get_option('cptx_msgs',array());
  if(empty($messages))
    return;
  foreach($messages as $message){
    switch($message[0]){
    case CPTX_MSG_ERROR: $class='error'; break;
    case CPTX_MSG_WARNING: $class='warning'; break;
    case CPTX_MSG_DEBUG: $class='debug'; break;
    }
    echo "<div class='cptxmsg cptx$class'>";
    echo "<p>";
    echo "WP-CPROTEXT [$class]: ".$message[1]." (L".$message[2].")";
    echo "</p></div>";
  }
  update_option('cptx_msgs',array());
}

/**
 * cptx_delete
 *
 * Delete DB wp_cptx row
 *
 * @param mixed $postId 
 * @access public
 * @return void
 */
function cptx_delete($postId){
  global $wpdb;
  $wpdb->delete($wpdb->prefix."cptx",array('parentId'=>$postId));
}

/**
 * cptx_postsJoin
 *
 * Alter the join part of the wp_query so that protected version of the
 * corresponding text can be appended to the results
 *
 * @param string $join 
 * @access public
 * @return string
 */
function cptx_postsJoin($join){
  global $wpdb;
  $tableName=$wpdb->prefix."cptx";
  $join.=" LEFT JOIN $tableName ON ".$wpdb->posts.".ID = ".$tableName.".parentId ";
  $join.=" AND ".$tableName.".enabled=1 ";
  return $join;
}

/**
 * cptx_postsRequest
 * 
 * Display queries when CPTX_DEBUG_QUERIES is true
 *
 * @param string $request 
 * @access public
 * @return string
 */
function cptx_postsRequest($request){
  if(CPTX_DEBUG_QUERIES)
    print_r($request);
  return $request;
}

/**
 * cptx_postClass
 *
 * Append cprotexted class to single post pageviews when appropriate
 *
 * @param string[] $classes 
 * @access public
 * @return string[]
 */
function cptx_postClass($classes=array()){
  global $post;
  if(empty($post->cptx_html))
    return $classes;
  $classes[]='cprotexted';
  return $classes;
}

/**
 * cptx_postsFields
 * 
 * Alter the wp_query so that protected version data of the
 * corresponding text are appended to the results
 *
 * @param string $fields 
 * @access public
 * @return string
 */
function cptx_postsFields($fields){
  global $wpdb;
  $tableName=$wpdb->prefix."cptx";
  $fields.=", `".$tableName."`.id as cptx_id ";
  $fields.=", `".$tableName."`.enabled as cptx_enabled ";
  $fields.=", `".$tableName."`.ie8enabled as cptx_IE8enabled ";
  $fields.=", `".$tableName."`.html as cptx_html ";
  $fields.=", `".$tableName."`.css as cptx_css ";
  return $fields;
}

/**
 * cptx_content
 *
 * Display the HTML of the protected version of a content instead of the 
 * original text when appropriate
 *
 * @param string $content 
 * @access public
 * @return string
 */
function cptx_content($content){
  global $post;
  global $posts;

  if(!$post->cptx_enabled)
    return $content;

  $postid=-1;
  foreach ($posts as $i=>$p)
    if($posts[$i]->ID===$post->ID){
      $postid=$i;
      break;
    }
  if($postid===-1)
    return $content;

  $result=str_replace("cxX-","cx$postid-",stripslashes($post->cptx_html));

  $cptx_notice=(int)get_option('cptx_notice');
  $noticeText=__('Copyrighted text protected by CPROTEXT',CPTX_I18N_DOMAIN);
  $notice="<span style='vertical-align:middle'>".$noticeText."</span>";
  switch($cptx_notice){
  case 3:
    break;
  case 2:
    $result.='<span class="wpcx'.$postid.'-c">[ ';
    $result.='Copyrighted text protected by ';
    $result.='<a href="https://www.cprotext.com">CPROTEXT</a>';
    $result.=' ]</span>';
    break;
  case 1: 
    $notice="<a href='https://www.cprotext.com' title='".__("CPROTEXT web site",CPTX_I18N_DOMAIN)."'>$notice</a>";
  case 0:
    $cptx_notice="<div style='font-size: 0.8em;' ";
    $cptx_notice.="title='".__("Copyright protection for online texts",CPTX_I18N_DOMAIN)." ( https://www.cprotext.com )'>";
    $cptx_notice.="<img src='".plugins_url('wp-cprotext/images/icon16.png')."' ";
    $cptx_notice.="alt='https://www.cprotext.com' ";
    $cptx_notice.="style='vertical-align:middle'/> ";
    $cptx_notice.=$notice;
    $cptx_notice.="</div>";
    $result=$cptx_notice.$result;
    break;
  }

  return $result;
}

/**
 * cptx_pageInserts
 *
 * Insert header related data of the protected texts to display
 *
 * @access public
 * @return void
 */
function cptx_pageInserts(){
  global $post,$wp_version;
  $keywords="";
  $postid=0;
  if (have_posts()){
    $enableie8=false;
    while (have_posts()){
      the_post();
      if(!empty($post->cptx_css)){
        echo '<link rel="stylesheet" type="text/css" ';
        add_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");
        $url=wp_nonce_url(
          admin_url("admin-ajax.php")."?action=cptxCSS",
          CPTX_WP_CSSNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR).
          "&amp;id=".$post->cptx_id."&amp;o=".$postid;
        remove_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");

        echo 'href="'.$url.'"/>';
      }
      $kw=get_post_meta($post->cptx_id,'cptx_kw',true);
      if(!empty($kw))
        $keywords.=(!empty($keywords)?",":"").$kw;
      $postid++;
      if($post->cptx_IE8enabled){
        $enableie8=true;
      }
    }
    if($enableie8){
      echo '<!--[if IE 8]>';
      echo '<script type="text/javascript">';
      @readfile(__DIR__."/js/cptxie8fix".(CPTX_DEBUG?'':'.min').".js");
      echo '</script>';
      echo '<![endif]-->';
    }
    rewind_posts();
    if(is_single() && !empty($keywords))
      echo '<meta name="keywords" content="'.$keywords.'"/>';
  }
}

/**
 * cptx_checkInputs
 *
 * check that only white listed inputs are received
 *
 * @param mixed $allowedInputs 
 * @access public
 * @return void
 */
function cptx_checkInputs($allowedInputs){
  // add input required by wordpress
  if(!in_array("action",$allowedInputs["_GET"])){
    $allowedInputs["_GET"][]="action";
  }

  foreach(array_keys($allowedInputs) as $inputType){
    if(is_null($allowedInputs[$inputType])){
      continue;
    }
    foreach(array_keys($GLOBALS[$inputType]) as $var){
      if(!in_array($var,$allowedInputs[$inputType])){
        header("HTTP/1.1 404 Not Found");
        exit;
      }
    }
  }
}

/**
 * cptx_init
 * 
 * Initialize plugin localisation
 *
 * @access public
 * @return void
 */
function cptx_init() {
  $plugin_dir = dirname(plugin_basename(__FILE__));
  load_plugin_textdomain(CPTX_I18N_DOMAIN, false, $plugin_dir."/lang/" );
}

add_action('plugins_loaded', 'cptx_init');

register_activation_hook(__FILE__,'cptx_updateCheck');
add_action( 'admin_menu', 'cptx_menu' );
add_action('admin_enqueue_scripts','cptx_adminInserts');
add_action('add_meta_boxes','cptx_post');
add_action('save_post','cptx_savePost',10,2);
add_action('edit_form_top','cptx_messagesHandler');
add_action('admin_footer', 'cptx_popup');
add_filter('posts_fields','cptx_postsFields');
add_filter('posts_join','cptx_postsJoin');
add_filter('posts_request','cptx_postsRequest');
add_filter('post_class','cptx_postClass');
add_action('wp_enqueue_scripts','cptx_pageInserts');
add_action('deleted_post','cptx_delete');
add_filter('the_content','cptx_content');
add_action('wp_ajax_cptxProxy','cptx_APIProxy');
add_action('wp_ajax_nopriv_cptxCSS','cptx_postCSS');
add_action('wp_ajax_cptxCSS','cptx_postCSS');
add_action('wp_ajax_nopriv_cptxFont','cptx_postFont');
add_action('wp_ajax_cptxFont','cptx_postFont');
?>
