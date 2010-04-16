<?php
/**
 * Notification management using growl window in frontend
 * @author Prakash Paudel
 * 
 */
class Notification{
	# Notification Categories
	/* 
	 * Product related notifications
	 * Examples
	 * 1. Licence expire
	 * 2. Updates available
	 * 3. Signatures available
	 * 4. New releases
	 * 5. New products launch
	 */
	const PRODUCT_RELATED = 1;
	
	/*
	 * Service/system related notifications
	 * Examples
	 * 1. Service changed status
	 * 2. Stopped working (not working)
	 * 3. Update system is ok
	 * 4. Support connection is ok
	 */
	const SERVICE_RELATED = 2;
	
	/*
	 * Audit related notifications
	 * Examples
	 * 1. All the audit from frontend by the users
	 */	
	const AUDIT_RELATED = 3;
	
	/*
	 * Users related notifications
	 * Examples
	 * 1. Added new incidents
	 * 2. Comments on incidents of related users
	 */
	const USERS_RELATED = 4;
	
	/*
	 * General notifications
	 * Examples
	 * 1. Tips for using products
	 * 2. Other useful information to users
	 */
	const GENERAL_RELATED = 5;
	
	# Notification types	
	const INFO = 'INFO';
	const WARNING = 'WARNING';
	const ERROR = 'ERROR';
	
	/*
	 * Maximum number of notifications to show, if there are a lot of notifications
	 * this will show the specified notifications and others are skipped saying, 
	 * you have (count) number of more notifications, please click to view them all
	 */
	const MAX_SHOW_NOTIFICATIONS = 3;
	
	/*
	 * This is the number of unseen notifications, when greater than this, 
	 * the MAX_SHOW_NOTIFICATIONS is valid. otherwise all notifications will be 
	 * shown gradually... 
	 */
	const WHEN_EXCEEDS = 5;
	
	/*
	 * Cli argument options
	 */
	static $SHORT_OPTIONS = "t:m:s:l:d:c:p:b:f:";
	static $LONG_OPTIONS = array("title:","msg:","sev:","log:","duration:","cat:","persistent:","by:","for:");
	
	/*
	 * CLI conversion
	 */
	static $SHORT_CONVERSION = array("t"=>"title","m"=>"message","s"=>"type","l"=>"log","d"=>"duration","c"=>"category","p"=>"persistent","b"=>"created_by","f"=>"created_for");
	static $LONG_CONVERSION = array("title"=>"title","msg"=>"message","sev"=>"type","log"=>"log","duration"=>"duration","category"=>"category","persistent"=>"persistent","by"=>"created_by","for"=>"created_for");
	
	public static function add($title='',$message='',$category=3,$type=self::INFO){		
		if($title != ''){		
			//If first parameter is json string			
			if(json_decode($title)){				
				self::pushJson($title);
			}else{
				self::push($title,$message,$category,$type);
			}	
		}
	}	
	public static function getDefaultObject(){
		$notification = new afNotification();
		$notification->setCategory(3);
		$notification->setType(self::INFO);
		$notification->setDuration(20);
		$notification->setPersistent(true);
		$notification->setRemoteIp(self::getRemoteIp());
		$notification->setCreatedBy(self::getUser());
		return $notification;
	}
	public static function push($title,$message,$category=3,$type=self::INFO){
		$notification = self::getDefaultObject();
		$notification->setTitle($title);
		$notification->setMessage($message);
		$notification->setCategory($category);		
		$notification->setType($type);		
		$notification->save();		
	}
	public static function pushJson($json){		
		$json = json_decode($json,true);
		if(!isset($json['title']) || !isset($json['message'])) return;		
		$notification = self::getDefaultObject();		
		foreach($json as $key=>$value){			
			$method = 'set'.sfInflector::camelize($key);
			if(method_exists($notification,$method)){				
				call_user_func(array($notification,$method),$value);
			}
		}
		$notification->save();		
	}
	public static function getPluginSource(){
		$source = '';
		if(sfConfig::get("app_growl_notification_enable")){
			$url = sfConfig::get("app_growl_notification_url");
			$source = 'if(Ext.ux.Notification){var notification = new Ext.ux.Notification();notification.start("'.$url.'");}'."\n";
		}
		return $source;
	}
	private static function getUser(){
		if(sfContext::getInstance()->getUser()->isAuthenticated()){
			return  sfContext::getInstance()->getUser()->getProfile()->getUserId();
		}
		return null;
	}
	public static function notifyObject($new_object,$old_object=null,$user_commit_msg='',$changes=''){		
		$new_object_peer=$new_object->getPeer();
		$new_object_fields=$new_object_peer->getFieldNames(BasePeer::TYPE_NUM);
		$new_object_fields_fieldname=$new_object_peer->getFieldNames(BasePeer::TYPE_FIELDNAME);
		$log = '';
		$title = '';
		$msg = '';
		$ip_string = " from tracked ip ".self::getRemoteIP()."";
		if(is_object($old_object)){
			$title = "Record Updated !";					
			$count = 0;			
			foreach ($new_object_fields as $k=>$new_object_field){			
				if($old_object->getByPosition($new_object_field)!=$new_object->getByPosition($new_object_field)){
					$count++;	
					$log .= "<br><u>".sfInflector::humanize($new_object_fields_fieldname[$k])."</u>:<br><s>".$old_object->getByPosition($new_object_field)."</s><br>".$new_object->getByPosition($new_object_field)."<br>";								
				}
			}
			if($log) $log = "<u>The following is the updates</u><br>".$log;	
			$msg = ($user_commit_msg?("<u>".$user_commit_msg."</u><br>"):"")."Record is updated from ".sfContext::getInstance()->getModuleName()."/".sfContext::getInstance()->getActionName().$ip_string.". Altogether ".$count." fields were modified.";
		}else if($old_object == "new"){
			$title = "Record Created !";					
			$count = 0;			
			foreach ($new_object_fields as $k=>$new_object_field){				
				$count++;	
				$log .= "<br><u>".sfInflector::humanize($new_object_fields_fieldname[$k])."</u>: ".$new_object->getByPosition($new_object_field)."<br>";			
			}
			if($log) $log = "<u>The new record is created with following</u><br>".$log;	
			$msg = ($user_commit_msg?("<u>".$user_commit_msg."</u><br>"):"")."A new record is created from ".sfContext::getInstance()->getModuleName()."/".sfContext::getInstance()->getActionName().$ip_string.". Altogether ".$count." fields are created with record.";
		}else if($old_object == "deleted"){
			$title = "Record Deleted !";			
			$count = 0;			
			foreach ($new_object_fields as $k=>$new_object_field){				
				$count++;	
				$log .= "<br><u>".sfInflector::humanize($new_object_fields_fieldname[$k])."</u>:<br>".$new_object->getByPosition($new_object_field)."<br>";			
			}
			if($log) $log = "<u>The deleted record info is following</u><br>".$log;			
			$msg = ($user_commit_msg?("<u>".$user_commit_msg."</u><br>"):"")."Record is deleted from ".sfContext::getInstance()->getModuleName()."/".sfContext::getInstance()->getActionName().$ip_string;
		
		}		
		$options = array("title"=>$title,"message"=>$msg,"log"=>$log);	
		//for($i=0;$i<5;$i++)	
		self::add(json_encode($options));
	}
	
	/**
	 * This method parses the arguments from cli and converts it into json string to feed to self::add()
	 */
	public static function cli(){
		$options = getopt(self::$SHORT_OPTIONS,self::$LONG_OPTIONS);
		$result = array();
		foreach($options as $key=>$value){
			if(array_key_exists($key,self::$SHORT_CONVERSION)){
				$result[self::$SHORT_CONVERSION[$key]] = $value;
			}else if(array_key_exists($key,self::$LONG_CONVERSION)){
				$result[self::$LONG_CONVERSION[$key]] = $value;
			}
		}
		if(!empty($result)){
			if(!isset($result['category'])) $result['category'] = self::SERVICE_RELATED;
			self::add(json_encode($result));
		}
	}
	/**
	 * Internal function which determines the remote IP address.
	 * If Propel objects are changed via a CLI script (batch) the local
	 * loopback address will be returned.
	 *
	 * @return string
	 */
	private static function getRemoteIP()
	{
		$ip = false; // No IP found

		/**
		 * User is behind a proxy and check that we discard RFC1918 IP addresses.
		 * If these address are behind a proxy then only figure out which IP belongs
		 * to the user.
		 */
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']); // Put the IP's into an array which we shall work with.
			$no = count($ips);
			for ($i = 0 ; $i < $no ; $i++) {

				/**
				 * Skip RFC 1918 IP's 10.0.0.0/8, 172.16.0.0/12 and
				 * 192.168.0.0/16
				 */
				if (!eregi('^(10|172\.16|192\.168)\.', $ips[$i])) {
					$ip = $ips[$i];
					break;
				} // End if

			} // End for

		} // End if
		return ($ip ? $ip : isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1'); // Return with the found IP, the remote address or the local loopback address

	} // End function
	public static function getCategoryName($key){
		switch ($key){
			case 1:
				return "Product Related";
				break;
			case 2:
				return "Service Related";
				break;
			case 3:
				return "Audit Related";
				break;
			case 4:
				return "Users Related";
				break;
			case 5:
				return "General";
				break;
		}
	}
	public static function getTypes(){
		return array("INFO"=>"INFO","WARNING"=>"WARNING","ERROR"=>"ERROR");
	}
	public static function getCategories(){
		$array = array();
		for($i=1;$i<=5;$i++){
			$array[$i] = self::getCategoryName($i);
		}
		return $array;
	}
	public static function getUsers(){
		$users = sfGuardUserPeer::doSelect(new Criteria());
		$arr = array();
		foreach($users as $user){
			$arr[$user->getId()] = $user->getUsername();
		}
		return $arr;
	}
}