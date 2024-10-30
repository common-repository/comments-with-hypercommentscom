<?php
/*
Plugin Name: HyperComments
Plugin URI: http://hypercomments.com/
Description: HyperComments - New dimension of comments. Hypercomments technology allows commenting a specific word or a piece of text. 
Version: 0.9.6
Author:  Alexandr Bazik, Dmitry Goncharov, Inna Goncharova
Author URI: http://hypercomments.com/
*/
define('HC_DEV',false);
define('HC_URL', 'http://hypercomments.com');
require_once(dirname(__FILE__) . '/export.php');
define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
define('HC_PLUGIN_URL', WP_CONTENT_URL . '/plugins/comments-with-hypercommentscom');
define('HC_XML_PATH',$_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads');
$is_append = false;

register_deactivation_hook(__FILE__,'hc_delete');
register_activation_hook(__FILE__,'hc_active');          
add_action('init', 'hc_request_handler');
add_action('admin_head', 'hc_admin_head');
add_filter('the_content', 'hc_the_content_filter', 50);
add_filter('wp_trim_excerpt', 'hc_the_content_filter', 50); 

add_action('wp_footer', 'hc_count_widget');
add_filter('comments_template', 'hc_comments_template');
add_filter('comments_number', 'hc_comments_text');
add_filter('get_comments_number', 'hc_comments_number');

add_action('admin_menu', 'hc_add_pages', 10);
add_action('admin_notices', 'hc_messages');
/**
 * The event handler
 * @global type $post
 * @global type $wpdb
 * @return type 
 */
function hc_request_handler() {
    global $post;
    global $wpdb; 
    
    if (function_exists('load_plugin_textdomain')) {
         load_plugin_textdomain('hypercomments', 'wp-content/plugins/comments-with-hypercommentscom/locales');
    }

    if (!empty($_GET['hc_action'])) {
        switch ($_GET['hc_action']) {      
            case 'export_comments':           
                if (current_user_can('manage_options')) {                                   
                     require_once(dirname(__FILE__) . '/export.php');
                     $wxr = hc_export_wp();  
                     if($wxr){
                         $file_name = time().'.xml';                    
                         $file_root = HC_XML_PATH.'/'.$file_name;

                         $file_path = WP_CONTENT_URL.'/uploads/'.$file_name;
                         $write_file = file_put_contents($file_root, $wxr);
                         if($write_file){
                             $json_arr = array(
                                 'service'         => 'wordpress',
                                 'widget_id'    => get_option('hc_wid'),
                                 'request_url' => $file_path,                      
                                 'result_url'    => admin_url('index.php').'?hc_action=delete_xml&xml='.$file_name,
                                 'result'           => 'success'
                             );                                             
                             echo json_encode($json_arr);                       
                         }else{
                             echo json_encode(array('result'=>'error','description'=>_e('Error writing XML', 'hypercomments' )));
                         }
                     }else{
                         echo json_encode(array('result'=>'error','description'=>_e('Failed to generate XML', 'hypercomments' )));
                     }
                     die();
                }         
            break;   
            case 'save_wid':
                update_option('hc_wid', $_GET['wid']);
                update_option('hc_access', $_GET['access']);
                echo $_GET['access'];
                die();
            break;
            case 'delete_xml':
                if(isset($_GET['result']) && $_GET['result'] == 'success'){
                    $filename = HC_XML_PATH.'/'.$_GET['xml'];
                    unlink($filename);
                    return json_encode(array('result'=>'success'));
                }else{
                    return json_encode(array('result'=>'error'));
                }
                exit();
            break;
        }
    }

}
/**
 * Include styles and files in the admin
 */
function hc_admin_head(){
    $page = (isset($_GET['page']) ? $_GET['page'] : null);
    if ( $page == 'hypercomments') {
?>
    <link rel='stylesheet' href='<?php echo HC_PLUGIN_URL;?>/css/hypercomments.css'  type='text/css' />
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    <script>jQueryHC = jQuery.noConflict(true);</script>
<?php
    }
}
/**
 * Action by activating the plugin
 */
function hc_active(){  
      update_option('hc_selector', '.hc_counter_comments');
}
/**
 * Action when uninstall plugin
 */
function hc_delete(){  
     delete_option('hc_wid');
     delete_option('hc_access');
     delete_option('hc_selector');
     delete_option('hc_title_widget');
}
/**
 * Changing the template comments
 * @param type $value
 * @return type 
 */
function hc_comments_template($value){     
   return dirname(__FILE__) . '/comments.php';
}

function hc_comments_number($count) {
    global $post;
    return $count;
}
/**
 * Replacement of counters
 * @global type $post
 * @param type $comment_text
 * @return type 
 */
function hc_comments_text($comment_text) {
    global $post;  
    $parse = parse_url($post->guid);
    $url =  str_replace($parse['scheme'].'://'.$parse['host'], get_option('home'), $post->guid);   
    return '<span class="hc_counter_comments" href="'.$url.'">'.$comment_text.'</span>'; 
}
/**
 * Insert widget on the site of the old comments
 * @global type $post 
 */
function hc_show_script() {      
      global $post;      
      global $is_append;
      $parse = parse_url($post->guid);
      $url = str_replace('https://','',str_replace('http://','',str_replace('www.','',str_replace($parse['host'], get_option('home'), $post->guid))));      
      if($is_append === false && $post->comment_status == 'open'){
?>
<div id="hypercomments_widget"></div>
<script type="text/javascript">
var _hcp = _hcp || {};_hcp.widget_id = <?php echo get_option('hc_wid');?>;_hcp.widget = "Stream";_hcp.platform = "wordpress";
_hcp.language = "<?php echo hc_get_language();?>";_hcp.xid = "<?php echo $url?>";
<?php if(HC_DEV) echo '_hcp.hc_test=1;';?>
(function() { 
var hcc = document.createElement("script"); hcc.type = "text/javascript"; hcc.async = true;
hcc.src = ("https:" == document.location.protocol ? "https" : "http")+"://widget.hypercomments.com/apps/js/hc.js";
var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(hcc, s.nextSibling); 
})();
</script>
<?php
      }else{
?>
<div id="hypercomments_widget_newappend"></div>
<script type="text/javascript">
_hcp.append = "#hypercomments_widget_newappend";
</script>
<?php
      }
}
/**
 * Insert widget counters
 */
function hc_count_widget() {       
  if(!is_singular() && !(is_page() && is_single())) {    
?>
<script type="text/javascript">
<?php if(HC_DEV) echo 'HCDeveloper = true';?>   
var _hcp = _hcp || {};_hcp.widget_id = <?php echo get_option('hc_wid');?>;_hcp.widget = "Bloggerstream";_hcp.selector='<?php echo get_option('hc_selector');?>';
_hcp.platform = "wordpress";_hcp.language = "<?php echo hc_get_language();?>";
<?php
if(get_option('hc_title_widget')){
    echo '_hcp.selector_widget = ".hc_content_comments";';
}
?>
<?php
      if(hc_enableParams()){
            echo '_hcp.enableParams=true;';
       }
 ?>
(function() { 
var hcc = document.createElement("script"); hcc.type = "text/javascript"; hcc.async = true;
hcc.src = ("https:" == document.location.protocol ? "https" : "http")+"://widget.hypercomments.com/apps/js/hc.js";
var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(hcc, s.nextSibling); 
})();
</script>
<?php
  }
}
/**
 * Include manage file
 */
function hc_options_page() {
     if( $_POST['hc_form_counter_sub'] == 'Y' ) {
         update_option( 'hc_selector',  $_POST['hc_form_selector'] );
         if(isset($_POST['hc_title_widget'])){
            update_option( 'hc_title_widget',  $_POST['hc_title_widget'] );
         }else{
             delete_option('hc_title_widget');
         }
         echo '<div class="updated"><p><strong>'.__('Options saved', 'hypercomments').'</strong></p></div>';
     }
     include_once(dirname(__FILE__) . '/manage.php');
}
/**
 * Insert menu in the Comments section
 */
function hc_add_pages() {
     add_submenu_page(
         'edit-comments.php',
         'HyperComments',
         'HyperComments',
         'moderate_comments',
         'hypercomments',
         'hc_options_page'
     );
}
/**
 * Notice of setting the widget
 */
function hc_messages() {
    $page = (isset($_GET['page']) ? $_GET['page'] : null);
    if ( !get_option('hc_wid') && $page != 'hypercomments') {
       echo '<div class="updated"><p><b>'.__('You must <a href="edit-comments.php?page=hypercomments">configure the plugin</a> to enable HyperComments.', 'hypercomments').'</b></p></div>';
    }
}
/**
 * Consider GET-params?
 * @global type $wpdb
 * @return type 
 */
function hc_enableParams()
{
     global $wpdb;
     $results = $wpdb->get_results( "SELECT guid FROM $wpdb->posts WHERE post_type !='revision' AND post_status = 'publish' LIMIT 1");
     foreach ( $results as $result ) {
       $link = $result->guid;
     }
     return strstr($link,'?');
}
/**
 * Returns the locale
 * @return type 
 */
function hc_get_language()
{
    $local = get_locale();
    $local_lang = explode('_',$local);     
    return $local_lang[0];  
}
/**
 * Filter content
 * @global type $post
 * @param type $content
 * @return string 
 */
function hc_the_content_filter( $content ) { 
    global $post;      
    global $is_append;    
    $parse = parse_url($post->guid);
    $url = str_replace('https://','',str_replace('http://','',str_replace('www.','',str_replace($parse['host'], get_option('home'), $post->guid))));  
    if(get_option('hc_title_widget')){
        if($post->comment_status == 'open'){  
            if ( !is_singular()){          
                $content = sprintf(
                    '%s<div class="hc_content_comments" data-xid="'.$url.'"></div>',
                  $content          
                );
            }
        }
    }   
   if( is_singular()){
    if($post->comment_status == 'open'){   
	$is_append = true;
	$wid = '<div id="hypercomments_widget"></div>
	<script type="text/javascript">
	var _hcp = _hcp || {};_hcp.widget_id = '.get_option('hc_wid').';_hcp.widget = "Stream";_hcp.platform="wordpress";
	_hcp.language = "'.hc_get_language().'";_hcp.xid = "'.$url.'";         
	(function() { 
	  var hcc = document.createElement("script"); hcc.type = "text/javascript"; hcc.async = true;
	  hcc.src = ("https:" == document.location.protocol ? "https" : "http")+"://widget.hypercomments.com/apps/js/hc.js";
	  var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(hcc, s.nextSibling); 
	  })();
	  </script>';
	  $content = $content.$wid;     
    }
   }
    return $content;
}

?>
