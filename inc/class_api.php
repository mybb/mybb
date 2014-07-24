<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class api {

	protected $act;
	
	public function run()
	{
		$act = $_GET['act'];
		
		//define valid actions
		//Todo: add functions for verification, password change, email change
		$valid_actions = array('register_user');

		//see if we have a valid action defined
		if (!in_array($act, $valid_actions))
		{
			//we do not have a valid action defined, prevent this script from running
			die("Unauthorized. You did not provide a valid action."); 
		}
		
		$this->act = $act;
		
		echo $this->act;
		
		return $this->$act();
		
		/*$this->step = $step;
            
        $method = strtolower(Request::getMethod());
        $action = $method.'_'.$step;
            
        return $this->$action();*/
		
	}
	
	public function register_user()
	{
		$username = $_GET['username'];
		$password = $_GET['password'];
		$email = $_GET['email'];
	
		//make sure we have a username, password, and email
		if (!isset($username) || !isset($password) || !isset($email))
		{
			//we do not have a username, password, or email
			die("Unauthorized. Either a username, password, or email was not provided.");
		}
		else
		{
			require_once  MYBB_ROOT."inc/datahandlers/user.php";
			$userhandler = new UserDataHandler("insert");
			$user = array(
				"username" => $username,
				"password" => $password,
				"password2" => $password,
				"email" => $email,
				"email2" => $email,    
				"usergroup" => 2,
			);
			$userhandler->set_data($user);
			if($userhandler->validate_user()) {
				$newuser = $userhandler->insert_user();
				//Todo: Ouptut json message that says it was successfull
				echo $newuser;
			} 
			else
			{
			  //Todo: output json error message saying what failed  
			}
		}
	}
	
}


?>
