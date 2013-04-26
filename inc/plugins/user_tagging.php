<?php
/**
 * User Tagging
 * Jeremiah Johnson
 * http://jwjdev.com/
 */

if(!defined("IN_MYBB"))
{
    die("You Cannot Access This File Directly");
}

$plugins->add_hook("datahandler_post_insert_post", "user_tagging_datahandler_post_insert_post");
$plugins->add_hook("datahandler_post_insert_thread_post", "user_tagging_datahandler_post_insert_thread_post");
$plugins->add_hook("datahandler_post_update", "user_tagging_datahandler_post_update");

function user_tagging_info()
{
return array(
        "name"  => "User Tagging",
        "description"=> "Adds the ability to tag other users in posts. Also sends PM from tagging user to tagged user.",
        "website"        => "http://jwjdev.com/",
        "author"        => "Jeremiah Johnson",
        "authorsite"    => "http://jwjdev.com/",
        "version"        => "1.2.1",
        "guid"             => "498ebe70a8844739b14163bd42ac24eb",
        "compatibility" => "16*"
    );
}

function user_tagging_is_installed()
{
   global $db;
 
   $query = $db->simple_select("settinggroups", "name", "name='user_tagging'");
    
   $result = $db->fetch_array($query);

   if($result) {
	return 1;
   } else {
	return 0;
   }
	
}

function user_tagging_install()
{	
   global $db;
   $setting_group = array(
		'gid'			=> 'NULL',
		'name'			=> 'user_tagging',
		'title'			=> 'User Tagging',
		'description'	=> 'Settings for User Tagging.',
		'disporder'		=> "1",
		'isdefault'		=> 'no',
	);

   $db->insert_query('settinggroups', $setting_group);
   $gid = $db->insert_id();
	
   $myplugin_setting = array(
		'name'			=> 'user_tagging_on',
		'title'			=> 'On/Off',
		'description'	=> 'Turn User Tagging On or Off',
		'optionscode'	=> 'yesno', //this will be a yes/no select box
		'value'			=> '1', //default value is yes, use 0 for no
		'disporder'		=> 1,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_tagging_styling',
		'title'			=> 'Tag Styling',
		'description'	=> 'Styles to apply to the text for tags using MyCode.<br /><span style="font-weight:bold;">Example:</span> [b]{tag}[/b]<br /><span style="font-weight:bold;color:red;">MUST CONTAIN {tag} TO WORK</span>',
		'optionscode'	=> 'text',
		'value'			=> '{tag}',
		'disporder'		=> 2,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);
   
   $myplugin_setting = array(
		'name'			=> 'user_tagging_forums',
		'title'			=> 'Allowed Forums',
		'description'	=> 'A comma separated list of forum IDs where User Tagging should be enabled.<br />Leave blank for all forums.',
		'optionscode'	=> 'text',
		'value'			=> '',
		'disporder'		=> 3,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);
   
   $myplugin_setting = array(
		'name'			=> 'user_tagging_groups',
		'title'			=> 'Allowed Groups',
		'description'	=> 'A comma separated list of groups allowed to use User Tagging.<br />Leave blank for all groups.',
		'optionscode'	=> 'text',
		'value'			=> '',
		'disporder'		=> 4,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);
   
   $myplugin_setting = array(
		'name'			=> 'user_tagging_pm_on',
		'title'			=> 'PM Enabled',
		'description'	=> 'Enable or disable sending a PM to the tagged user.',
		'optionscode'	=> 'yesno', //this will be a yes/no select box
		'value'			=> '1', //default value is yes, use 0 for no
		'disporder'		=> 5,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);
   
   $myplugin_setting = array(
		'name'			=> 'user_tagging_subject',
		'title'			=> 'PM Subject',
		'description'	=> 'The subject line for the PM sent to the tagged user.',
		'optionscode'	=> 'text',
		'value'			=> 'I tagged you!',
		'disporder'		=> 6,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_tagging_body',
		'title'			=> 'PM Body',
		'description'	=> 'The message body for the PM sent to the tagged user. To specify the thread they were tagged in, use {thread}.',
		'optionscode'	=> 'textarea',
		'value'			=> 'I tagged you here: {thread}',
		'disporder'		=> 7,
		'gid'			=> intval($gid),
	);
	
	$db->insert_query('settings', $myplugin_setting);
	
   rebuild_settings();
}

function user_tagging_activate() {
}

function user_tagging_deactivate() {
}
	  
function user_tagging_uninstall()
{
	global $db;
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN ('user_tagging_on','user_tagging_subject','user_tagging_body','user_tagging_pm_on','user_tagging_styling','user_tagging_forums','user_tagging_groups')");
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='user_tagging'");
	rebuild_settings(); 
}


function user_tagging_send_pm($subject, $msg, $toname, $fromid) {
   require_once MYBB_ROOT."inc/datahandlers/pm.php";
   global $db, $mybb, $lang; ;
   
   //if PMs are not enabled, exit
   if(!$mybb->settings['user_tagging_pm_on']) {
      return;
   }
   
   $pm_handler = new PMDataHandler();
   $pm_handler->admin_override = true;
   $pm = array(

   	   "subject" => $subject,

   	   "message" => $msg,

	   "fromid" => $fromid,

	   "options" => array(
"savecopy" => "0"),
	   );


   $pm['to'] = array($toname);
   $pm_handler->set_data($pm);
   
   if(!$pm_handler->validate_pm())
   {
      //bad pm. oops. lol
   } else {
      $pm_handler->insert_pm();
   }
}

function user_tagging_check_permissions() {
   global $mybb;
   $allowed = false;
   if(strlen(trim($mybb->settings['user_tagging_groups'])) > 0) { //if allowed groups is set
      $allowedGroups = explode(",", $mybb->settings['user_tagging_groups']);
      for($i = 0; $i < sizeof($allowedGroups); $i++) { //trim allowed groups
         $allowedGroups[$i] = trim($allowedGroups[$i]);
      }
	  $userGroup = $mybb->user['usergroup'];
	  if(in_array($userGroup, $allowedGroups)) { //check primary usergroups
	     $allowed = true;
	  } else { //check additional user groups
	     $addGroups = explode(",", $mybb->user['additionalgroups']);
		 foreach($addGroups as $checkGroup) {
		    if(in_array($checkGroup, $allowedGroups)) {
			   $allowed = true;
			}
		 }
      }
   } else {
      $allowed = true;
   }
   return $allowed;
}

function user_tagging_datahandler_post_insert_post(&$post) {
   global $mybb;
   //pull vars from object
   $msg = $post->post_insert_data['message'];
   $tid = $post->post_insert_data['tid'];
   $time = $post->post_insert_data['dateline'];

   //if they have tagging disabled, do nothing
   if(!$mybb->settings['user_tagging_on']) {
      return $msg;
   }

   //if user is not allowed to tag, or forum is disabled for tagging
   if(!user_tagging_check_permissions()) {
      return $msg;
   }

   //if PMs are enabled
   if($mybb->settings['user_tagging_pm_on']) {
      //build the pm from user settings
      $pmBody = $mybb->settings['user_tagging_body'];
      $pmBody = str_replace('{thread}', "[url=" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "]" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "[/url]", $pmBody);
   } else {
      $pmBody = "";
   }
   $msg = user_tagging_tag($time, $pmBody, $msg);
   
   $post->post_insert_data['message'] = $msg;
   return $post;
}

function user_tagging_datahandler_post_insert_thread_post(&$post) {
   global $mybb;
   //pull vars from object
   $msg = $post->post_insert_data['message'];
   $tid = $post->post_insert_data['tid'];
   $time = $post->post_insert_data['dateline'];

   //if they have tagging disabled, do nothing
   if(!$mybb->settings['user_tagging_on']) {
      return $msg;
   }
   
   //if user is not allowed to tag, or forum is disabled for tagging
   if(!user_tagging_check_permissions()) {
      return $msg;
   }

   //if PMs are enabled
   if($mybb->settings['user_tagging_pm_on']) {
      //build the pm from user settings
      $pmBody = $mybb->settings['user_tagging_body'];
      $pmBody = str_replace('{thread}', "[url=" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "]" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "[/url]", $pmBody);
   } else {
      $pmBody = "";
   }
   $msg = user_tagging_tag($time, $pmBody, $msg);
   
   $post->post_insert_data['message'] = $msg;
   return $post;
}

function user_tagging_datahandler_post_update(&$post) {
    global $mybb;
   //pull vars from object
   $msg = $post->post_update_data['message'];
   $tid = $post->data['tid'];
   $pid = $post->data['pid'];
   $time = $post->post_update_data['edittime'];

   //if they have tagging disabled, do nothing
   if(!$mybb->settings['user_tagging_on']) {
      return $msg;
   }
   
   //if user is not allowed to tag, or forum is disabled for tagging
   if(!user_tagging_check_permissions()) {
      return $msg;
   }
   
   //if PMs are enabled
   if($mybb->settings['user_tagging_pm_on']) {
      //build the pm from user settings
      $pmBody = $mybb->settings['user_tagging_body'];
      $pmBody = str_replace('{thread}', "[url=" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "&pid=" . $pid . "#" . $pid . "]" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "&pid=" . $pid . "#" . $pid . "[/url]", $pmBody);
   } else {
      $pmBody = "";
   }
   $msg = user_tagging_tag($pmBody, $pmBody, $msg);

   $post->post_update_data['message'] = $msg;
   return $post;
}

function user_tagging_tag($time, $pmBody, $msg) {
   global $db, $mybb;
   $maxlength = $mybb->settings['maxnamelength'];
   $delimiter = ' ' . crypt($msg, $time) . ' ';
   $tagged = array(); //array to hold tagged users, incase of multiple tagging
   
   //build the pm from user settings
   $pmSubject = $mybb->settings['user_tagging_subject'];
   
   //break msg down to only spaces, then create array
   $msgNoNewLines = str_replace("\\n", $delimiter, $msg); //change all newlines into spaces for parsing method
   $msgParts = explode(' ', $msgNoNewLines);
   
   for($i = 0; isset($msgParts[$i]); ++$i) {
      //if it starts with @ and hasn't been tagged before
	  if(substr($msgParts[$i], 0, 1) == '@') {
	  
	     for($j = 1; ($i + $j - 1) < sizeof($msgParts); $j++) {
	        $size = 0;
			$message = $msgParts[$i];
	        //get the current size and message
	        for($k = 0; ($k + $i) < sizeof($msgParts) && $k < $j; $k++) {
	           $size += strlen($msgParts[$i + $k]);
			   if($k > 0) {
			     $message .= ' ' . $msgParts[$i + $k];
			   }
			}
			//if we are over max name length + 1(allow for @ and for punctuation at end
			if(($size + 2) > $maxlength) {
	           break;
			}

            $search = substr($message, 1);
		    $search = $db->escape_string($search);

	        if(preg_match('/\W/', substr($message, -1))) //if the last character is non-word ie punctuation of some sort
            {
	           $search2 = substr($message, 1, (strlen($message) - 2)); //get between @ and last char
	           $search2 = $db->escape_string($search2);
	        } else {
	           $search2 = "";
	        }

	        $query = $db->simple_select("users", "uid,username", "username='{$search}'");
    
            $user = $db->fetch_array($query);

			if($user) {
			   $preStyle = "";
			   $postStyle = "";
			   if(stristr($mybb->settings["user_tagging_styling"], "{tag}") !== FALSE) {
			      $styleParts = explode("{tag}", $mybb->settings["user_tagging_styling"]);
			      if(sizeof($styleParts == 2)) {
			         $preStyle = $styleParts[0];
			         $postStyle = $styleParts[1];
				  }
			   }
			   //put the url tags around it
			   $msgParts[$i] = '[url=' . $mybb->settings["bburl"] . '/member.php?action=profile&uid=' . $user['uid'] . ']'. $preStyle . $msgParts[$i];
               $msgParts[$i + $j - 1] = $msgParts[$i + $j - 1] . $postStyle . '[/url]';
			   if(!in_array($user['uid'], $tagged)) //if first tag in post, send pm
	           {
			      array_push($tagged, $user['uid']);
				  //send the pm
				  user_tagging_send_pm($pmSubject, $pmBody, $user['username'], $mybb->user['uid']);
			   }
			} else if($search2) { //no match try second search if exists
			   $query = $db->simple_select("users", "uid,username", "username='{$search2}'");
			   $user = $db->fetch_array($query);
             
			   if($user) {
			      $preStyle = "";
				  $postStyle = "";
				  if(stristr($mybb->settings["user_tagging_styling"], "{tag}") !== FALSE) {
				     $styleParts = explode("{tag}", $mybb->settings["user_tagging_styling"]);
					 if(sizeof($styleParts == 2)) {
					    $preStyle = $styleParts[0];
						$postStyle = $styleParts[1];
				     }
				  }
			      $msgParts[$i] = '[url=' . $mybb->settings["bburl"] . '/member.php?action=profile&uid=' . $user['uid'] . ']'. $preStyle . $msgParts[$i];
                  $msgParts[$i + $j - 1] = substr($msgParts[$i + $j - 1], 0, (strlen($msgParts[$i + $j - 1]) - 1)) . $postStyle . '[/url]' . substr($msgParts[$i + $j - 1], -1);
          
				  if(!in_array($user['uid'], $tagged)) //if first tag in post, send pm
				  {
				     array_push($tagged, $user['uid']);
					 //send the pm
					 user_tagging_send_pm($pmSubject, $pmBody, $user['username'], $mybb->user['uid']);
				  }
			   }
			}
		 }
	  }
   }
   
   //put the message back together
   $msg = implode(" ", $msgParts);
   $msg = str_replace($delimiter, "\\n", $msg);

   return $msg;
}
?>