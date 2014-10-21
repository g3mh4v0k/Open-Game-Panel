<?php
/*
 *
 * OGP - Open Game Panel
 * Copyright (C) Copyright (C) 2008 - 2013 The OGP Development Team
 *
 * http://www.opengamepanel.org/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

require_once("includes/database.php");

class OGPDatabaseMySQL extends OGPDatabase
{
	private $link;

	private $table_prefix;

	function __construct()
	{

	}

	function __destruct() {
		if ( $this->link )
			mysqli_close($this->link);
	}

	/// \return TRUE if connection was created successfully.
	/// \return -1 When host is invalid.
	/// \return -11 When connection to database could not be established
	/// \return -12 When database was not valid.
	/// \return -99 When mysql php module is not available.
	public function connect($db_host, $db_user, $db_pass, $db_name, $table_prefix = NULL) {
		if ( !extension_loaded("mysqli") )
			return -99;

		$this->table_prefix = $table_prefix;

		/// \todo We might want to do other checks here as well?
		if ( $db_host === NULL )
			return -1;

		$this->link = mysqli_connect( $db_host, $db_user, $db_pass, $db_name );

		if ( $this->link === FALSE )
			return -11;

		return TRUE;
	}

	private function listQuery($query) {
		if ( !$this->link ) return FALSE;

		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		if ( mysqli_errno($this->link) > 0 )
			print mysqli_error($this->link);

		if ( $result === FALSE )
			return FALSE;

		if ( mysqli_num_rows($result) == 0 )
			return FALSE;

		$results = array();

		while ( $row = mysqli_fetch_assoc( $result ) )
			array_push($results,$row);

		return $results;
	}

	public function getSettings() {
		if ( !$this->link ) return;
		$query = sprintf("SELECT * FROM `%ssettings`",
			$this->table_prefix);
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		$results = array();

		while ( $row = mysqli_fetch_assoc($result) )
			$results[$row['setting']] = $row['value'];

		return $results;
	}

	public function getSetting($name) {
		if ( !$this->link ) return;
		$query = sprintf("SELECT `value` FROM `%ssettings`
			WHERE `setting` = '%s'",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$name));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		if ( mysqli_affected_rows($this->link) !== 1 )
			return FALSE;

		$results = mysqli_fetch_assoc($result);

		return $results["value"];
	}

	public function setSettings($settings) {
		if ( !$this->link ) return FALSE;
		if ( !is_array($settings) ) return FALSE;

		foreach ( $settings as $s_key => $s_value )
		{
			$query = sprintf('INSERT INTO `%1$ssettings` (`setting`,`value`)
				VALUES(\'%2$s\', \'%3$s\') ON DUPLICATE KEY
				UPDATE value=\'%3$s\'',
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$s_key),
				mysqli_real_escape_string($this->link,$s_value));
			++$this->queries_;
			mysqli_query($this->link,$query);
		}
		return TRUE;
	}

	public function getUser($username) {
		if ( !$this->link ) return array();
		$query = sprintf("SELECT * FROM `%susers` WHERE `users_login` = '%s';",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$username));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		return mysqli_fetch_assoc($result);
	}

	public function getUserById($user_id) {
		if ( !$this->link ) return array();
		$query = sprintf("SELECT * FROM `%susers` WHERE `user_id` = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$user_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		return mysqli_fetch_assoc($result);
	}

	public function getUsersByHomeId($home_id) {
		$query = sprintf('SELECT *
			FROM %1$susers
			NATURAL JOIN %1$suser_homes
			WHERE home_id = %2$s',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		return $this->listQuery($query);
	}
	
	public function getGroupUsersByHomeId($home_id) {
		$query = sprintf('SELECT *
			FROM %1$susers
			NATURAL JOIN %1$suser_groups
			NATURAL JOIN %1$suser_group_homes
			WHERE home_id = %2$s',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		return $this->listQuery($query);
	}
	
	public function getGroupsForHome($home_id) {
		$query = sprintf('SELECT *
			FROM %1$suser_group_homes
			WHERE home_id = %2$s',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		return $this->listQuery($query);
	}
	
	public function getUserByEmail($email) {
		if ( !$this->link ) return FALSE;
		$query = sprintf("SELECT * FROM `%susers` WHERE `users_email` LIKE '%s';",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$email));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if ( mysqli_num_rows($result) != 1 ) return FALSE;
		return mysqli_fetch_assoc($result);
	}

	public function updateUsersPassword($user_id, $new_password)
	{
		if ( !$this->link ) return FALSE;
		$query = sprintf("UPDATE %susers SET users_passwd='%s'
			WHERE user_id = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$new_password),
			mysqli_real_escape_string($this->link,$user_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if( mysqli_affected_rows($this->link) == '0' )
			return FALSE;
		return TRUE;
	}

	public function getGroupById($group_id) {
		if ( !$this->link ) return array();
		$query = sprintf("SELECT * FROM `%suser_group_info` WHERE `group_id` = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		return mysqli_fetch_assoc($result);
	}

	public function getUserList() {
		if ( !$this->link ) return;
		$query = sprintf("SELECT user_id,users_login,users_lang,
			users_role,users_fname,users_lname,users_email,user_expires,users_parent
			FROM %susers",
			$this->table_prefix);

		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		$results = array();

		while ( $row = mysqli_fetch_assoc( $result ) )
			array_push($results,$row);

		return $results;
	}

	public function getGroupList() {
		$query = sprintf("SELECT group_id,group_name
			FROM %suser_group_info",
			$this->table_prefix);
		return $this->listQuery($query);
	}

	public function getUsersGroups($user_id) {
		$query = sprintf("SELECT *
			FROM %suser_groups
			WHERE `user_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$user_id));
		return $this->listQuery($query);
	}
	
	public function getUserGroupList($main_user_id) {
		$query = sprintf("SELECT *
			FROM %suser_group_info
			WHERE `main_user_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$main_user_id));
		return $this->listQuery($query);
	}

	public function addUser($username,$password,$user_role,$user_email = "",$user_parent=NULL){
		if ( !$this->link ) return false;
		$replaceString = "INSERT INTO `%susers` (`users_login`,`users_passwd`,
			`users_lang`,`user_expires`,`users_role`,`users_email`, `users_parent`)
			VALUES('%s', MD5('%s'),'%s', 'X', '%s', '%s', ";
			
		if(is_null($user_parent)){
			$replaceString .= "NULL)";
		}else{
			$replaceString .= "'%d')";
		}
		$query = sprintf($replaceString,
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$username),
				mysqli_real_escape_string($this->link,$password),
				@$GLOBALS['panel_language'],
				mysqli_real_escape_string($this->link,$user_role),
				mysqli_real_escape_string($this->link,$user_email),
				mysqli_real_escape_string($this->link,$user_parent));
				
		++$this->queries_;
		mysqli_query($this->link,$query);
		
		$user_id = mysqli_insert_id($this->link);
		if( !$user_id )
		{
			echo mysqli_errno($this->link) . ": " . mysqli_error($this->link);
			return false;
		}
		else
		{
			$widgets = $this->resultQuery("SELECT * FROM `".$this->table_prefix."widgets`");
			$query = "INSERT INTO `".$this->table_prefix."widgets_users` (`user_id`, `widget_id`, `column_id`, `sort_no`, `collapsed`, `title`) VALUES";
			foreach($widgets as $widget){
				$query .= "(" . $user_id . ", " . $widget['id'] . ", " . $widget['column_id'] . ", " . $widget['sort_no'] . ", " . $widget['collapsed'] . ", '" . $widget['title'] . "'),";
			}
			$query = substr($query, 0, -1);
			$query .= ";";
			++$this->queries_;
			mysqli_query($this->link,$query);
			return true;
		}
	}
	
	public function editUser($fields,$user_id){
		$query = "UPDATE `".$this->table_prefix."users` SET ";
		
		foreach($fields as $key => $value)
		{
			$query .= "`$key`='".mysqli_real_escape_string($this->link,$value)."',";
		}
		
		$query = rtrim($query, ',');
		
		$query .= " WHERE `user_id`=".mysqli_real_escape_string($this->link,$user_id).";";
				
		++$this->queries_;
		
		mysqli_query($this->link,$query);
				
		if( mysqli_errno($this->link) != 0 )
			return false;

		return true;
	}

	public function addUsertoGroup($user_id,$group_id){
		if ( !$this->link ) return false;

		$query = sprintf("INSERT INTO `%suser_groups` (`user_id`,`group_id`) VALUES('%d', '%d')",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$user_id),
			mysqli_real_escape_string($this->link,$group_id));

		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return false;

		return true;
	}

	public function addServertoGroup($rserver_id,$group_id){
		if ( !$this->link ) return false;

		$query = sprintf("INSERT INTO `%suser_group_remote_servers` (`remote_server_id`,`group_id`) VALUES('%d', '%d')",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$rserver_id),
			mysqli_real_escape_string($this->link,$group_id));
		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return false;

		return true;
	}

	public function addGroup($group,$main_user_id){
		if ( !$this->link ) return false;

		$query = sprintf("INSERT INTO `%suser_group_info` (`group_id`, `group_name`,`main_user_id` ) VALUES('', '%s', '%d')",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group),
			mysqli_real_escape_string($this->link,$main_user_id));
		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return false;

		return true;
	}

	public function delGroup($group_id)
	{
		$queries = array( "DELETE FROM `%suser_group_info` WHERE `group_id` = %d",
			"DELETE FROM `%suser_groups` WHERE `group_id` = %d",
			"DELETE FROM `%suser_group_homes` WHERE `group_id` = %d",
			"DELETE FROM `%suser_group_remote_servers` WHERE `group_id` = %d" );

		foreach ($queries as $query_template)
		{
			$query = sprintf($query_template,
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$group_id));
			++$this->queries_;
			mysqli_query($this->link,$query)
				or die("Failed to delete group from user_groups: ". mysqli_error($this->link));
		}

		return TRUE;
	}

	public function delUserFromGroup($user_id, $group_id)
	{
		$query = sprintf("DELETE FROM `%suser_groups` WHERE `group_id` = '%d'
			AND `user_id` = '%d'",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group_id),
			mysqli_real_escape_string($this->link,$user_id));
		++$this->queries_;
		mysqli_query($this->link,$query)
			or die("Failed to delete user from group:".mysqli_error($this->link));
		if ( mysqli_affected_rows($this->link) != 1 )
			return FALSE;
		return TRUE;
	}

	public function delServerFromGroup($rserver_id, $group_id)
	{
		$query = sprintf("DELETE FROM `%suser_group_remote_servers` WHERE `group_id` = '%d'
			AND `remote_server_id` = '%d'",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group_id),
			mysqli_real_escape_string($this->link,$rserver_id));

		++$this->queries_;
		mysqli_query($this->link,$query)
			or die("Failed to delete server from group:".mysqli_error($this->link));
		if( mysqli_affected_rows($this->link) != 1 )
			return FALSE;
		return TRUE;
	}

	public function getAvailableUsersForGroup($group_id)
	{
		$query = sprintf('SELECT * FROM `%1$susers`
			WHERE `user_id` NOT IN (
				SELECT `user_id` FROM `%1$suser_groups`
				WHERE `group_id` = %2$d
			) AND `users_parent` IS NULL;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group_id));
		return $this->listQuery($query);
	}
	
	public function getAvailableSubUsersForGroup($group_id, $userID)
	{
		$query = sprintf('SELECT * FROM `%1$susers`
			WHERE `user_id` NOT IN (
				SELECT `user_id` FROM `%1$suser_groups`
				WHERE `group_id` = %2$d
			) AND `users_parent` = %3$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group_id),
			mysqli_real_escape_string($this->link,$userID)
			);
		return $this->listQuery($query);
	}

	public function listUsersInGroup($group_id)
	{
		$query = sprintf("SELECT `user_id` FROM `%suser_groups` WHERE `group_id` = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group_id));
		return $this->listQuery($query);
	}
	
	public function listSubUsersByParent($parent_id)
	{
		$query = sprintf("SELECT `user_id` FROM `%susers` WHERE `users_parent` = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$parent_id));
		return $this->listQuery($query);
	}
	
	public function getNumberOfOwnedServersPerUser($userID){
		if($this->isAdmin($userID)){
			$query = sprintf('SELECT COUNT(`home_id`)
			FROM `%1$sserver_homes`;',
			$this->table_prefix);
		}else{
			$query = sprintf('SELECT COUNT(`home_id`)
			FROM `%1$sserver_homes` WHERE `user_id_main` = %2$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$userID));
		}
			
		$result = mysqli_query($this->link,$query) or die("Query failed".mysqli_error($this->link));
			
		if ( $row = mysqli_fetch_row( $result ) ){
			return $row[0];
		}
		
		return 0;
	}

	public function listServersInGroup($group_id)
	{
		$query = sprintf('SELECT `remote_server_id`,`remote_server_name`
			FROM `%1$suser_group_remote_servers` NATURAL JOIN `%1$sremote_servers`
			WHERE `group_id` = %2$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group_id));
		return $this->listQuery($query);
	}

	public function delUser($user_id)
	{
		// Get list of subusers with current user as parent
		$subusers = $this->listSubUsersByParent($user_id);
		if( $subusers !== false )
		{
			foreach ($subusers as $subuser) {
				$this->delUser($subuser['user_id']);
			}
		}
		
		// Optimization...
		$user_id = mysqli_real_escape_string($this->link,$user_id);

		$query = sprintf("SELECT user_id FROM `%susers` WHERE `user_id` = $user_id",
			$this->table_prefix);

		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if( mysqli_affected_rows($this->link) == 0 )
			return false;

		$query = sprintf("DELETE FROM `%susers` WHERE `user_id` = $user_id",
			$this->table_prefix);
		++$this->queries_;
		mysqli_query($this->link,$query) or die("Failed to delete user:".mysqli_error($this->link));
		
		// Deletes any sub-user that may reference this parent username
		$query = sprintf("DELETE FROM `%susers` WHERE users_parent = $user_id;",
			$this->table_prefix);
		++$this->queries_;
		mysqli_query($this->link,$query) or die("Failed to delete subuser:".mysqli_error($this->link));
		
		 // Deletes entries from user_groups 
		$query = sprintf("DELETE FROM `%suser_groups` WHERE user_id = $user_id;",
			$this->table_prefix);
		++$this->queries_;
		mysqli_query($this->link,$query) or die("Failed to delete user:".mysqli_error($this->link));
		
		// Deletes group owned by user only if the subuser module is enabled
		$subUsersEnabled = $this->isModuleInstalled("subusers");
		if($subUsersEnabled){
			$query = sprintf("DELETE FROM `%suser_group_info` WHERE main_user_id = $user_id;",
				$this->table_prefix);
			++$this->queries_;
			mysqli_query($this->link,$query) or die("Failed to delete group information:".mysqli_error($this->link));
		}
		
		$query = sprintf("DELETE FROM `%swidgets_users` WHERE `user_id` = $user_id",
			$this->table_prefix);
		++$this->queries_;
		mysqli_query($this->link,$query) or die("Failed to delete user:".mysqli_error($this->link));

		$query = sprintf("DELETE FROM `%suser_homes` WHERE user_id = $user_id;",
			$this->table_prefix);
		++$this->queries_;
		mysqli_query($this->link,$query) or die("Failed to delete user:".mysqli_error($this->link));

		return true;
	}

	public function isAdmin($user_id)
	{
		if ( !$this->link ) return false;
		$query = sprintf("SELECT `users_role` FROM `%susers` WHERE `user_id` = %d AND `users_role` = 'admin'",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$user_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query) or die("Query failed".mysqli_error($this->link));

		if(mysqli_affected_rows($this->link) == 0)
			return false;

		return true;
	}
	
	public function getAdmins()
	{
		if ( !$this->link ) return false;
		$query = sprintf("SELECT * FROM `%susers` WHERE `users_role` = 'admin'",
			$this->table_prefix);
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		$results = array();

		while ( $row = mysqli_fetch_assoc( $result ) )
			array_push($results,$row);

		return $results;
	}
	
	public function isSubUser($user_id)
	{
		if ( !$this->link ) return false;
		$query = sprintf("SELECT `users_role` FROM `%susers` WHERE `user_id` = %d AND `users_role` = 'subuser'",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$user_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query) or die("Query failed".mysqli_error($this->link));

		if(mysqli_affected_rows($this->link) == 0)
			return false;
			
		return true;
	}

	public function addModule($module_title,$module,$module_version,$db_version)
	{
		if ( !$this->link ) return false;
		$query = sprintf("INSERT INTO `%smodules` VALUES('NULL','%s','%s','%s', '%d');",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$module_title),
			mysqli_real_escape_string($this->link,$module),
			mysqli_real_escape_string($this->link,$module_version),
			mysqli_real_escape_string($this->link,$db_version));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		return mysqli_insert_id($this->link);
	}

	public function addModuleMenu($module_id,$subpage,$group,$name)
	{
		if ( !$this->link ) return false;
		$query = sprintf("INSERT INTO `%smodule_menus` VALUES( '%d','%s','%s','%s','0');",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$module_id),
			mysqli_real_escape_string($this->link,$subpage),
			mysqli_real_escape_string($this->link,$group),
			mysqli_real_escape_string($this->link,$name));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return false;

		return true;
	}

	public function delModuleMenu($module_id)
	{
		if ( !$this->link ) return FALSE;
		$query = sprintf("DELETE FROM `%smodule_menus` WHERE `module_id` = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$module_id));

		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		return TRUE;
	}

	public function delModule($module_id)
	{
		if ( !$this->link ) return FALSE;
		$query = sprintf("DELETE FROM `%smodules` WHERE `id` = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$module_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		$query = sprintf("DELETE FROM `%smodule_menus` WHERE `module_id` = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$module_id));

		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		return TRUE;
	}

	public function getMenusForGroup($group)
	{
		$query = sprintf('SELECT `folder` as module, `subpage`, `menu_name`, `pos`, `module_id`
			FROM `%1$smodules`, `%1$smodule_menus`
			WHERE `group` = \'%2$s\'
			AND `id` = `module_id`
			ORDER BY `pos` ASC;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$group));
		return $this->listQuery($query);
	}
	
	public function changeMenuPosition( $module_id, $new_pos )
	{
		$query = sprintf("UPDATE `%smodule_menus` 
						  SET pos='%d'
						  WHERE module_id = '%d';",
						  $this->table_prefix,
						  mysqli_real_escape_string($this->link,$new_pos),
						  mysqli_real_escape_string($this->link,$module_id) );
		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return FALSE;

		return true;
	}

	public function addGameModCfg($game_id,$mod_key,$mod_name)
	{
		$query = sprintf('INSERT INTO `%1$sconfig_mods` (`mod_cfg_id`, `home_cfg_id`, `mod_key`, `mod_name`)
			VALUES(NULL, \'%2$s\', \'%3$s\', \'%4$s\') ON DUPLICATE KEY UPDATE mod_name=\'%4$s\';',
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$game_id),
				mysqli_real_escape_string($this->link,$mod_key),
				mysqli_real_escape_string($this->link,$mod_name));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
	}

	public function clearGameCfgs($clear_all)
	{
		if ( $clear_all == TRUE )
		{
			++$this->queries_;
			mysqli_query($this->link,"TRUNCATE `".$this->table_prefix."config_homes`;");
			++$this->queries_;
			mysqli_query($this->link,"TRUNCATE `".$this->table_prefix."config_mods`;");
		}
		// mysqli_query($this->link,"TRUNCATE config_homes;");
	}

	public function addGameCfg($config)
	{
		/// \todo Escape the required values and add on duplicate key update.
		$query = "INSERT INTO `".$this->table_prefix."config_homes` (`home_cfg_id`,
			`game_key`, `game_name`, `home_cfg_file` ) VALUES ".
			"(NULL, '".$config->game_key."', '".
			$config->game_name."', '".$config->home_cfg_file."' )
			ON DUPLICATE KEY UPDATE game_name=VALUES(game_name),
				home_cfg_file=VALUES(home_cfg_file);";
		++$this->queries_;

		$result = mysqli_query($this->link,$query);

		if ( !$result )
			return FALSE;

		$query = sprintf('SELECT `home_cfg_id` FROM `%1$sconfig_homes` WHERE `game_key` = \'%2$s\';',
			$this->table_prefix,
			$config->game_key);

		++$this->queries_;
		$id_result = mysqli_query($this->link,$query);
		$id_result = mysqli_fetch_assoc($id_result);
		$config_id = $id_result['home_cfg_id'];

		// Adding mods.
		foreach ( $config->mods->mod as $mod )
		{
			$this->addGameModCfg($config_id,$mod['key'],$mod->name);
		}

		return TRUE;
	}

	public function getGameCfgs()
	{
		$query = sprintf('SELECT * FROM `%sconfig_homes`
			ORDER BY `game_name` ASC',
			$this->table_prefix);
		return $this->listQuery($query);
	}
	
	public function getGameCfg($home_cfg_id)
	{
		$query = sprintf('SELECT * FROM `%sconfig_homes`
			WHERE `home_cfg_id` = %d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_cfg_id));
		
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		// If there are no servers then we can stop here.
		if ( mysqli_num_rows($result) != 1 )
			return FALSE;
		
		return mysqli_fetch_assoc($result);
	}
	
	public function delGameCfgAndMods($home_cfg_id)
	{
		$home_cfg_id = mysqli_real_escape_string($this->link,$home_cfg_id);
		
		$queries = array("DELETE FROM `%sconfig_mods` WHERE `home_cfg_id` = %d",
						 "DELETE FROM `%sconfig_homes` WHERE `home_cfg_id` = %d");

		foreach ( $queries as $query )
		{
			$query = sprintf($query,$this->table_prefix,$home_cfg_id);
			++$this->queries_;
			$result = mysqli_query($this->link,$query);
			$return = (  mysqli_affected_rows($this->link) >= 1 ) ? TRUE : FALSE;
			if($return === FALSE)
				break;
		}
		return $return;
	}
	
	public function getCfgMods($home_cfg_id)
	{
		$query = sprintf('SELECT * FROM `%sconfig_mods`
			WHERE `home_cfg_id` = %d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_cfg_id));
		return $this->listQuery($query);
	}
	
	public function updateHomeCfgId($home_id, $new_home_cfg_id)
	{
		$query = sprintf("UPDATE `%sserver_homes` 
						  SET home_cfg_id='%d'
						  WHERE home_id = '%d';",
						  $this->table_prefix,
						  mysqli_real_escape_string($this->link,$new_home_cfg_id),
						  mysqli_real_escape_string($this->link,$home_id) );
		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return FALSE;

		return true;
	}
	/// \brief Used to make plain query to the database.
	/// \return true if success and false otherwise.
	/// When false is returned user can check error with getError() function.
	public function query( $query )
	{
		if ( !$this->link ) return FALSE;

		$query = preg_replace( "/".OGP_DB_PREFIX."/", "$this->table_prefix", $query );

		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
		{
			return FALSE;
		}

		return TRUE;
	}

	/// \brief This query return array of values or false on failure.
	public function resultQuery( $query ) {
		$query = preg_replace( "/".OGP_DB_PREFIX."/", "$this->table_prefix", $query );
		return $this->listQuery($query);
	}
	
	public function resultInsertId( $table, $fields )
	{
		if ( !$this->link ) return FALSE;
		$keys = "";
		$values = "";
		foreach($fields as $key => $val)
		{
			$keys .= "`$key`,";
			$values .= "'".mysqli_real_escape_string($this->link,$val)."',";
		}
		$keys = rtrim($keys,',');
		$values = rtrim($values,',');
		$query = "INSERT INTO `".$this->table_prefix."$table`( ";
		$query .= $keys;
		$query .= " ) VALUES ( ";
		$query .= $values;
		$query .= " );";
				
		mysqli_query($this->link,$query);
		
		if ( mysqli_affected_rows($this->link) != 1 )
			return FALSE;
		$insert_id = mysqli_insert_id($this->link);
		return $insert_id;
	}

	/// \brief Returns the last error message
	public function getError() {
		if ( !$this->link ) return;
		return mysqli_error($this->link);
	}

	// Server module functions
	/// \brief Adds remote server to database.
	public function addRemoteServer($rhost_ip,$rhost_name,$rhost_user_name,$rhost_port,$rhost_ftp_ip,$rhost_ftp_port,$encryption_key,$rhost_timeout,$use_nat)
	{
		$rhost_ip = trim($rhost_ip);
		$rhost_port = trim($rhost_port);
		$rhost_user_name = trim($rhost_user_name);
		$rhost_ftp_ip = trim($rhost_ftp_ip);
		$rhost_ftp_port = trim($rhost_ftp_port);
		$encryption_key = trim($encryption_key);
		$rhost_timeout = trim($rhost_timeout);
		$use_nat = trim($use_nat);

		if ( empty($rhost_ip) )
			return false;
		else if ( empty($rhost_port) )
			return false;
		else if ( empty($rhost_user_name) )
			return false;

		$rhost_name = trim($rhost_name);
		$query = sprintf("INSERT INTO `%sremote_servers` (`agent_ip`,remote_server_name,ogp_user,agent_port,ftp_ip,ftp_port,`encryption_key`,timeout,use_nat)
			VALUES('%s','%s','%s','%d','%s','%s','%s','%s','%s');",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$rhost_ip),
				mysqli_real_escape_string($this->link,$rhost_name),
				mysqli_real_escape_string($this->link,$rhost_user_name),
				mysqli_real_escape_string($this->link,$rhost_port),
				mysqli_real_escape_string($this->link,$rhost_ftp_ip),
				mysqli_real_escape_string($this->link,$rhost_ftp_port),
				mysqli_real_escape_string($this->link,$encryption_key),
				mysqli_real_escape_string($this->link,$rhost_timeout),
				mysqli_real_escape_string($this->link,$use_nat));
		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
		{
			return false;
		}

		return mysqli_insert_id($this->link);
	}

	public function getRemoteServer($id) {
		if ( !$this->link ) return FALSE;

		$query = sprintf("SELECT * FROM `%sremote_servers` WHERE `remote_server_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$id));

		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		// If there are no servers then we can stop here.
		if ( mysqli_num_rows($result) != 1 )
			return FALSE;

		return mysqli_fetch_assoc( $result );
	}

	/// \brief Get Remote servers
	public function getRemoteServers(){
		$query = sprintf("SELECT * FROM %sremote_servers;",
			$this->table_prefix);
		return $this->listQuery($query);
	}

	public function removeRemoteServer($remote_server_id) {
		$remote_server_id = mysqli_real_escape_string($this->link,$remote_server_id);

		$return = TRUE;

		$queries = array("DELETE FROM `%sremote_servers` WHERE remote_server_id = %d;",
			"DELETE FROM `%sremote_server_ips` WHERE remote_server_id = %d;",
			'DELETE FROM `%1$sgame_mods` WHERE home_id IN
			(SELECT home_id FROM `%1$sserver_homes` WHERE remote_server_id = %2$d);',
			'DELETE FROM %1$shome_ip_ports WHERE `home_id` IN
			(SELECT home_id FROM `%1$sserver_homes` WHERE remote_server_id = %2$d);',
			"DELETE FROM `%sserver_homes` WHERE remote_server_id = %d;");

		foreach ( $queries as $query )
		{
			$query = sprintf($query,$this->table_prefix,$remote_server_id);
			++$this->queries_;
			$result = mysqli_query($this->link,$query);
			$return = ($result === FALSE) ? FALSE : $return;
		}
		return $return;
	}

	public function addRemoteServerIP($remote_server_id, $ip)
	{
		$query = sprintf("INSERT INTO `%sremote_server_ips`
			VALUES (null ,'%d','%s');",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$remote_server_id),
				mysqli_real_escape_string($this->link,$ip) );

		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return FALSE;

		return true;
	}
	
	public function editRemoteServerIPs($ip_id, $ip)
	{
		$query = sprintf("UPDATE `%sremote_server_ips` 
						 SET ip='%s'
						 WHERE ip_id = '%d';",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$ip),
				mysqli_real_escape_string($this->link,$ip_id) );

		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return FALSE;

		return true;
	}

	/// \brief Get remote server IP's
	public function getRemoteServerIPs($server_id){
		$query = sprintf("SELECT ip_id,ip FROM `%sremote_server_ips` WHERE remote_server_id = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$server_id));
		return $this->listQuery($query);
	}

	public function removeRemoteServerIPs($ip_id) {
		$query = sprintf("DELETE FROM `%sremote_server_ips` WHERE ip_id = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip_id) );
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function changeRemoteServerSettings($server_id,
		$agent_ip,$agent_port,$remote_server_name,$remote_server_user_name,$remote_host_ftp_ip,$remote_host_ftp_port,$encryption_key,$remote_timeout,$use_nat)
	{
		$query = sprintf("UPDATE %sremote_servers SET agent_ip='%s',
			agent_port='%s', encryption_key='%s',
			remote_server_name='%s',
			ogp_user='%s',
			ftp_ip='%s',
			ftp_port='%s',
			timeout='%s',
			use_nat='%s'
			WHERE remote_server_id = %d;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$agent_ip),
			mysqli_real_escape_string($this->link,$agent_port),
			mysqli_real_escape_string($this->link,$encryption_key),
			mysqli_real_escape_string($this->link,$remote_server_name),
			mysqli_real_escape_string($this->link,$remote_server_user_name),
			mysqli_real_escape_string($this->link,$remote_host_ftp_ip),
			mysqli_real_escape_string($this->link,$remote_host_ftp_port),
			mysqli_real_escape_string($this->link,$remote_timeout),
			mysqli_real_escape_string($this->link,$use_nat),
			mysqli_real_escape_string($this->link,$server_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	// Gamemanager functions
	public function getHomeIpPorts($home_id){
		$query = sprintf("SELECT ip_id,ip,port,force_mod_id
			FROM %shome_ip_ports NATURAL JOIN %sremote_server_ips
			WHERE home_id = %d;",
			$this->table_prefix,
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		return $this->listQuery($query);
	}

	public function getHomesFor($id_type,$assign_id){
		if ( $id_type == "user" )
		{
			$template = 'SELECT %1$sserver_homes.*, %1$suser_homes.access_rights,
				%1$sremote_servers.*, %1$sconfig_homes.*
				FROM %1$sremote_servers NATURAL JOIN %1$suser_homes
				NATURAL JOIN %1$sserver_homes NATURAL JOIN %1$sconfig_homes
				WHERE %1$suser_homes.user_id = %2$d;';
		}
		else if ( $id_type == "group" )
		{
			$template = 'SELECT %1$sserver_homes.*, %1$suser_group_homes.access_rights,
				%1$sremote_servers.*, %1$sconfig_homes.*
				FROM %1$sremote_servers NATURAL JOIN %1$suser_group_homes
				NATURAL JOIN %1$sserver_homes NATURAL JOIN %1$sconfig_homes
				WHERE %1$suser_group_homes.group_id = %2$d;';
		}
		else if ( $id_type == "user_and_group" )
		{
			$template = 'SELECT %1$sserver_homes.*, %1$sremote_servers.*,
						"user" as id_type, %1$sconfig_homes.*
						FROM %1$sremote_servers
						NATURAL JOIN %1$sserver_homes 
						NATURAL JOIN %1$sconfig_homes
						WHERE %1$sserver_homes.home_id
						IN(
							SELECT %1$suser_group_homes.home_id
							FROM  %1$suser_group_homes
							WHERE %1$suser_group_homes.group_id
							IN(
								SELECT %1$suser_groups.group_id
								FROM %1$suser_groups
								WHERE %1$suser_groups.user_id=%2$d
							)
						)
						OR %1$sserver_homes.home_id
						IN(
							SELECT %1$suser_homes.home_id
							FROM %1$suser_homes
							WHERE %1$suser_homes.user_id=%2$d
						)';

		}
		else
		{
			return FALSE;
		}

		$query = sprintf($template,
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$assign_id) );

		return $this->listQuery($query);
	}

	public function getHomeMods($home_id) {
		$query = sprintf('SELECT %1$sgame_mods.*, %1$sconfig_homes.game_key as gametype,
			%1$sconfig_mods.mod_name
			FROM %1$sgame_mods NATURAL JOIN %1$sconfig_mods NATURAL JOIN %1$sserver_homes
			NATURAL JOIN %1$sconfig_homes
			WHERE %1$sgame_mods.home_id = %2$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id) );
		return $this->listQuery($query);
	}

	public function isIpPortOwnedByUser( $user_id, $ip, $port ) {
		$query = sprintf('SELECT *
			FROM `%1$shome_ip_ports` NATURAL JOIN `%1$sserver_homes`
			NATURAL JOIN `%1$suser_homes`
			WHERE `ip` = \'%2$s\'
			AND `port` = %3$d
			AND `user_id` = %4$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip),
			mysqli_real_escape_string($this->link,$port),
			mysqli_real_escape_string($this->link,$user_id) );

		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		// If there are no servers then we can stop here.
		if ( mysqli_num_rows($result) != 1 )
			return FALSE;

		$info = mysqli_fetch_assoc($result);

		return $info['home_id'];
	}

	public function getCfgHomeById($cfgid){
		$query = sprintf("SELECT *
			FROM `%sconfig_homes`
			WHERE `home_cfg_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$cfgid));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		if ( mysqli_num_rows($result) != 1 )
			return FALSE;

		return mysqli_fetch_assoc($result);
	}
	
	public function getRemoteServerById($remote_server_id){
		$query = sprintf("SELECT `agent_ip`, `agent_port`, `encryption_key`, `timeout`
			FROM `%sremote_servers`
			WHERE `remote_server_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$remote_server_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if ( mysqli_num_rows($result) != 1 )
			return FALSE;

		return mysqli_fetch_assoc($result);
	}

	public function getIpPortsForUser($user_id) {
		$query = sprintf('SELECT %1$sremote_server_ips.*,%1$shome_ip_ports.*,%1$sserver_homes.*,
			%1$sremote_servers.*,
			%1$sconfig_homes.*,
			%1$sconfig_mods.*,
			%1$sgame_mods.*
			FROM `%1$shome_ip_ports`
			NATURAL JOIN `%1$sremote_servers`
			NATURAL JOIN `%1$sserver_homes`
			NATURAL JOIN `%1$sconfig_homes`
			NATURAL JOIN `%1$sremote_server_ips`
			NATURAL JOIN `%1$sconfig_mods`
			NATURAL JOIN `%1$sgame_mods`
			WHERE `home_id` IN
			(
				SELECT `home_id`
				FROM `%1$suser_homes`
				WHERE `user_id` = %2$d
				UNION
				SELECT `home_id`
				FROM `%1$suser_groups`
				NATURAL JOIN `%1$suser_group_homes`
				WHERE `user_id` = %2$d
			) 
			AND `force_mod_id` IN
			(
				SELECT `force_mod_id`
				FROM `%1$shome_ip_ports`
				WHERE `force_mod_id` = %1$sgame_mods.mod_id OR `force_mod_id` = "0"
			) ORDER BY %1$shome_ip_ports.home_id ASC;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$user_id) );
							
		return $this->listQuery($query);
	}
	
	public function getIpPorts( $ip_id = 0 ) {
		
		$ip_id_and = $ip_id == 0 ? "" : "`ip_id`='".$ip_id."' AND ";
		$query = sprintf('SELECT %1$sremote_server_ips.*,%1$shome_ip_ports.*,%1$sserver_homes.*,
			%1$sremote_servers.*,
			%1$sconfig_homes.*,
			%1$sconfig_mods.*,
			%1$sgame_mods.*
			FROM `%1$shome_ip_ports`
			NATURAL JOIN `%1$sremote_servers`
			NATURAL JOIN `%1$sserver_homes`
			NATURAL JOIN `%1$sconfig_homes`
			NATURAL JOIN `%1$sremote_server_ips`
			NATURAL JOIN `%1$sconfig_mods`
			NATURAL JOIN `%1$sgame_mods` 
			WHERE `force_mod_id` IN
			(
				SELECT `force_mod_id`
				FROM `%1$shome_ip_ports`
				WHERE '.$ip_id_and.'(`force_mod_id` = %1$sgame_mods.mod_id OR `force_mod_id` = "0")
			) ORDER BY %1$shome_ip_ports.home_id ASC;',
			$this->table_prefix );

		return $this->listQuery($query);
	}

	// Module manager functions

	/// \brief Returns the installed modules.
	public function getInstalledModules() {
		$query = sprintf("SELECT `id`,`title`,`folder`,`version`,`db_version` FROM `%smodules`",
			$this->table_prefix);
		return $this->listQuery($query);
	}
	
	public function getModule($id) {
		$query = sprintf("SELECT `id`,`title`,`folder`,`version`,`db_version` FROM `%smodules` WHERE `id` = '%d'",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$id));
		$result = $this->listQuery($query);
		return $result[0];
	}

	public function isModuleInstalled($module_folder)
	{
		$query = sprintf('SELECT * FROM `%smodules`
			WHERE `folder`="%s";',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$module_folder) );
		++$this->queries_;
		mysqli_query($this->link,$query);
		if ( mysqli_affected_rows($this->link) != 1 )
			return FALSE;

		return TRUE;
	}
	
	public function updateModule($id, $version, $db_version)
	{
		$query = sprintf("UPDATE `%smodules` 
						 SET `version`='%s', `db_version`='%d'
						 WHERE `id` = '%d';",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$version),
				mysqli_real_escape_string($this->link,$db_version),
				mysqli_real_escape_string($this->link,$id) );

		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return FALSE;

		return true;
	}

	// User game functions

	/// \brief Assignes a game home to user.
	public function assignHomeTo($id_type,$assign_id,$home_id,$access_rights)
	{
		if ( $id_type == "user" )
		{
			$template = "INSERT INTO `%suser_homes` ( `user_id`, `home_id`, `access_rights` )
				VALUES (%d,%d,'%s')";
		}
		else if ( $id_type == "group")
		{
			$template = "INSERT INTO `%suser_group_homes` ( `group_id`, `home_id`, `access_rights` )
				VALUES (%d,%d,'%s')";
		}
		else
		{
			return FALSE;
		}

		$query = sprintf($template,
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$assign_id),
			mysqli_real_escape_string($this->link,$home_id),
			mysqli_real_escape_string($this->link,$access_rights));

		++$this->queries_;
		mysqli_query($this->link,$query);

		if ( mysqli_affected_rows($this->link) != 1 )
			return FALSE;

		return TRUE;
	}

	public function unassignHomeFrom($id_type, $assign_id, $home_id)
	{
		if ( $id_type == "user" )
		{
			$template = "DELETE FROM `%suser_homes` WHERE `user_id` = %d
				AND `home_id` = %d;";
		}
		else if ( $id_type == "group" )
		{
			$template = "DELETE FROM `%suser_group_homes` WHERE `group_id` = %d
				AND `home_id` = %d;";
		}
		else
		{
			return FALSE;
		}

		$query = sprintf($template,
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$assign_id),
			mysqli_real_escape_string($this->link,$home_id));

		++$this->queries_;
		mysqli_query($this->link,$query);

		if ( mysqli_affected_rows($this->link) != 1 )
			return FALSE;

		return TRUE;
	}

	/// \brief Adds game home to database.
	/// \return FALSE if failure
	/// \return id of the home in case of success.
	public function addGameHome($rserver_id,$user_id_main,$home_cfg_id,$game_path,$server_name,$control_password,$ftp_password){
		$query = sprintf("INSERT INTO `%sserver_homes`
			( `home_id`, `remote_server_id`, `user_id_main`, `home_cfg_id`, `home_path`, `home_name`,`control_password`,`ftp_password`)
			VALUES('', '%d', '%d', '%d', '%s', '%s', '%s', '%s')",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$rserver_id),
				mysqli_real_escape_string($this->link,$user_id_main),
				mysqli_real_escape_string($this->link,$home_cfg_id),
				mysqli_real_escape_string($this->link,$game_path),
				mysqli_real_escape_string($this->link,$server_name),
				mysqli_real_escape_string($this->link,$control_password),
				mysqli_real_escape_string($this->link,$ftp_password));
		++$this->queries_;
		mysqli_query($this->link,$query);
		if ( mysqli_affected_rows($this->link) != 1 )
			return FALSE;
		$homeid = mysqli_insert_id($this->link);
		$this->changeHomePath($homeid,$game_path.$homeid);
		return $homeid;
	}

	public function getGameHome($home_id) {
		$query = sprintf('SELECT *
			FROM `%1$sremote_servers` 
			NATURAL JOIN `%1$sserver_homes` 
			NATURAL JOIN `%1$sconfig_homes` 
			NATURAL JOIN `%1$sconfig_mods` 
			NATURAL JOIN `%1$sgame_mods`
			WHERE `home_id` = %2$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if ( mysqli_num_rows($result) == 0 )
			return FALSE;

		// If the home is assigned to user and group at the same time
		// we need to merge the access right flags to get proper
		// rights for the user shown.
		// NOTE: If there is same access rights for user and group(s) mysql
		// returns only one line.
		$result_line = mysqli_fetch_assoc($result);

		while ( $tmp_line = mysqli_fetch_assoc($result) )
		{
			@$result_line['access_rights'] .= $tmp_line['access_rights'];
		}

		// Add mods to home.
		$query = sprintf('SELECT *
			FROM `%1$sgame_mods` NATURAL JOIN `%1$sconfig_mods`
			WHERE `home_id` = %2$d',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		$mods_array = array();

		if ( mysqli_num_rows($result) != 0 )
		{
			while ($mod_line = mysqli_fetch_assoc($result))
			{
				$mods_array[$mod_line['mod_id']] = $mod_line;
			}
		}
		$result_line['access_rights'] = "ufpe";
		$result_line['mods'] = $mods_array;

		// Return the line with merged rights.
		return $result_line;
	}
	
	public function getGameHomeWithoutMods($home_id) {
		$query = sprintf('SELECT *
			FROM `%1$sremote_servers` 
			NATURAL JOIN `%1$sserver_homes` 
			NATURAL JOIN `%1$sconfig_homes`
			WHERE `home_id` = %2$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if ( mysqli_num_rows($result) == 0 )
			return FALSE;

		$result_line = mysqli_fetch_assoc($result);

		return $result_line;
	}
	
	public function getHomeByFtpLogin($remote_server_id,$ftp_login) {
		$query = sprintf('SELECT *
			FROM `%1$sremote_servers` 
			NATURAL JOIN `%1$sserver_homes` 
			NATURAL JOIN `%1$sconfig_homes`
			WHERE `remote_server_id` = "%2$d" AND `ftp_login` = "%3$s";',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$remote_server_id),
			mysqli_real_escape_string($this->link,$ftp_login));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if ( mysqli_num_rows($result) == 0 )
		{
			$query = sprintf('SELECT *
				FROM `%1$sremote_servers` 
				NATURAL JOIN `%1$sserver_homes` 
				NATURAL JOIN `%1$sconfig_homes`
				WHERE `home_id` = %2$d;',
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$ftp_login));
			++$this->queries_;
			$result = mysqli_query($this->link,$query);
			if ( mysqli_num_rows($result) == 0 )
				return FALSE;
		}

		$result_line = mysqli_fetch_assoc($result);

		return $result_line;
	}
	
	public function getGameHomeByIP($ip, $port){
		$query = sprintf('SELECT *
			FROM `%1$sremote_servers` 
			NATURAL JOIN `%1$sserver_homes` 
			NATURAL JOIN `%1$sconfig_homes` 
			NATURAL JOIN `%1$sconfig_mods` 
			NATURAL JOIN `%1$sgame_mods`
			NATURAL JOIN `%1$sremote_server_ips`
			NATURAL JOIN `%1$shome_ip_ports`
			WHERE `ip` = \'%2$s\' AND `port` = \'%3$s\';',

			$this->table_prefix,

			mysqli_real_escape_string($this->link,$ip),

			mysqli_real_escape_string($this->link,$port));

		++$this->queries_;

		$result = mysqli_query($this->link,$query);

		if ( mysqli_num_rows($result) == 0 )

			return FALSE;



		// If the home is assigned to user and group at the same time
		// we need to merge the access right flags to get proper
		// rights for the user shown.
		// NOTE: If there is same access rights for user and group(s) mysql
		// returns only one line.

		$result_line = mysqli_fetch_assoc($result);

		while ( $tmp_line = mysqli_fetch_assoc($result) )
		{
			@$result_line['access_rights'] .= $tmp_line['access_rights'];
		}

		// Add mods to home.
		$home_id =  $result_line['home_id'];

		$query = sprintf('SELECT *
			FROM `%1$sgame_mods` NATURAL JOIN `%1$sconfig_mods`
			WHERE `home_id` = %2$d',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));

		++$this->queries_;

		$result = mysqli_query($this->link,$query);

		$mods_array = array();

		if ( mysqli_num_rows($result) != 0 )
		{
			while ($mod_line = mysqli_fetch_assoc($result))
			{
				$mods_array[$mod_line['mod_id']] = $mod_line;
			}
		}

		$result_line['access_rights'] = "ufpe";
		$result_line['mods'] = $mods_array;

		// Return the line with merged rights.
		return $result_line;
	}

	public function getUserGameHome($user_id, $home_id) {
		$query = sprintf('SELECT %1$suser_homes.access_rights as access_rights, %1$sremote_servers.*,
			%1$sserver_homes.*, %1$sconfig_homes.*,%1$sgame_mods.*
			FROM `%1$sremote_servers` 
			NATURAL JOIN `%1$sserver_homes`
			NATURAL JOIN `%1$sconfig_homes` 
			NATURAL JOIN `%1$suser_homes`
			NATURAL JOIN `%1$sgame_mods`
			WHERE `home_id` = %2$d
			AND `user_id` = %3$d
			UNION
			SELECT %1$suser_group_homes.access_rights as access_rights, %1$sremote_servers.*,
			%1$sserver_homes.*, %1$sconfig_homes.*,%1$sgame_mods.*
			FROM `%1$sremote_servers` 
			NATURAL JOIN `%1$sserver_homes`
			NATURAL JOIN `%1$sconfig_homes` 
			NATURAL JOIN `%1$suser_group_homes`
			NATURAL JOIN `%1$suser_groups`
			NATURAL JOIN `%1$sgame_mods`
			WHERE `home_id` = %2$d
			AND `user_id` = %3$d;',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id),
			mysqli_real_escape_string($this->link,$user_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		if ( mysqli_num_rows($result) == 0 )
			return FALSE;

		// If the home is assigned to user and group at the same time
		// we need to merge the access right flags to get proper
		// rights for the user shown.
		// NOTE: If there is same access rights for user and group(s) mysql
		// returns only one line.
		$result_line = mysqli_fetch_assoc($result);

		while ( $tmp_line = mysqli_fetch_assoc($result) )
		{
			$result_line['access_rights'] .= $tmp_line['access_rights'];
		}

		// Add mods to home.
		$query = sprintf('SELECT *
			FROM `%1$sgame_mods` NATURAL JOIN `%1$sconfig_mods`
			WHERE `home_id` = %2$d',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query);

		$mods_array = array();

		if ( mysqli_num_rows($result) != 0 )
		{
			while ($mod_line = mysqli_fetch_assoc($result))
			{
				$mods_array[$mod_line['mod_id']] = $mod_line;
			}
		}
		$result_line['mods'] = $mods_array;

		// Return the line with merged rights.
		return $result_line;
	}


	/// \brief Deletes the game home.
	public function deleteGameHome($home_id){
		$home_id = mysqli_real_escape_string($this->link,$home_id);
		$return = TRUE;

		$queries = array("DELETE FROM `%suser_homes` WHERE `home_id` = %d",
			"DELETE FROM `%sserver_homes` WHERE `home_id` = %d",
			"DELETE FROM `%sgame_mods` WHERE `home_id` = %d",
			"DELETE FROM `%shome_ip_ports` WHERE `home_id` = %d",
			"DELETE FROM `%suser_group_homes` WHERE `home_id` = %d");

		foreach ( $queries as $query )
		{
			$query = sprintf($query,$this->table_prefix,$home_id);
			++$this->queries_;
			$result = mysqli_query($this->link,$query);
			$return = ($result === FALSE) ? FALSE : $return;
		}
		return $return;
	}

	/// \brief Adds game mod to home.
	public function addModToGameHome($home_id, $mod_cfg_id){
		$query = sprintf("INSERT INTO `%sgame_mods` (`mod_id`,`home_id`, `mod_cfg_id`)
			VALUES('','%d','%d')",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$home_id),
				mysqli_real_escape_string($this->link,$mod_cfg_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;
		$mod_id = mysqli_insert_id($this->link);
		return $mod_id;
	}

	public function delGameMod($mod_id){
		$query = sprintf("DELETE FROM `%sgame_mods` WHERE `mod_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$mod_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function changeHomePath($home_id,$path) {
		$query = sprintf("UPDATE `%sserver_homes` SET `home_path` = '%s' WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$path),
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function changeUserIdMain($home_id,$userid) {
		$query = sprintf("UPDATE `%sserver_homes` SET `user_id_main` = '%s' WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$userid),
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function changeFtpLogin($home_id,$ftp_login) {
		$query = sprintf("UPDATE `%sserver_homes` SET `ftp_login` = '%s' WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ftp_login),
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function changeFtpPassword($home_id,$password) {
		$query = sprintf("UPDATE `%sserver_homes` SET `ftp_password` = '%s' WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$password),
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function changeFtpStatus ($status,$home_id) {
		$status_val = $status == "enabled" ? 1 : 0;
		$query = sprintf("UPDATE `%sserver_homes` SET `ftp_status` = '%d' WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$status_val),
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function IsFtpEnabled($home_id) {
		$query = sprintf("SELECT `ftp_status` FROM `%sserver_homes` WHERE `home_id` = %d AND `ftp_status` = 1",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		$result = mysqli_query($this->link,$query) or die("Query failed".mysqli_error($this->link));

		if(mysqli_affected_rows($this->link) == 0)
			return false;

		return true;
	}
	
	public function setMasterServer($action,$home_id,$home_cfg_id,$remote_server_id) {
		if($action == "add")
		{
			$query = sprintf("INSERT INTO `%smaster_server_homes` (`home_id`,`home_cfg_id`, `remote_server_id`) VALUES('%d','%d','%d')",
					$this->table_prefix,
					mysqli_real_escape_string($this->link,$home_id),
					mysqli_real_escape_string($this->link,$home_cfg_id),
					mysqli_real_escape_string($this->link,$remote_server_id));
		}
		elseif($action == "remove")
		{
			$query = sprintf("DELETE FROM `%smaster_server_homes` WHERE `home_id` = %d AND `home_cfg_id` = %d AND `remote_server_id` = %d",
					$this->table_prefix,
					mysqli_real_escape_string($this->link,$home_id),
					mysqli_real_escape_string($this->link,$home_cfg_id),
					mysqli_real_escape_string($this->link,$remote_server_id));
		}
		++$this->queries_;
			
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function getMasterServer( $remote_server_id, $home_cfg_id ){
		$query = sprintf("SELECT home_id FROM `%smaster_server_homes` WHERE `home_cfg_id` = %d AND `remote_server_id` = %d",
					$this->table_prefix,
					mysqli_real_escape_string($this->link,$home_cfg_id),
					mysqli_real_escape_string($this->link,$remote_server_id));

		$retval = $this->listQuery($query);
		if( empty( $retval ) )
		{
			print_r($this->getError());
			return FALSE;
		}
		return $retval[0]['home_id'];
	}
	
	/// \brief get available mods for game home.
	public function getAvailableModsForGameHome($home_id){
		$query = sprintf('SELECT `mod_cfg_id`, `mod_key`,`mod_name`
			FROM `%1$sserver_homes` NATURAL JOIN `%1$sconfig_homes` NATURAL JOIN `%1$sconfig_mods`
			WHERE `home_id` = %2$d
			ORDER BY `mod_name` ASC',
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));
		$retval = $this->listQuery($query);
		print_r($this->getError());
		return $retval;
	}

	public function updateGameModParams($max_players,$extra_params,$cpu_affinity,$nice,$home_id,$mod_cfg_id) {
		$max_players = mysqli_real_escape_string($this->link,$max_players);
		$extra_params = mysqli_real_escape_string($this->link,$extra_params);
		$cpu_affinity = mysqli_real_escape_string($this->link,$cpu_affinity);
		$nice = mysqli_real_escape_string($this->link,$nice);
		$home_id = mysqli_real_escape_string($this->link,$home_id);
		$mod = mysqli_real_escape_string($this->link,$mod_cfg_id);
		$query = "UPDATE `".$this->table_prefix."game_mods` SET `max_players` = '$max_players',
			`extra_params` = '$extra_params', `cpu_affinity` = '$cpu_affinity', `nice` = $nice
			WHERE `home_id` = $home_id
			AND `mod_cfg_id` = $mod_cfg_id;";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function addGameIpPort($home_id, $ip, $port) {
		$home_id = mysqli_real_escape_string($this->link,$home_id);
		$ip = mysqli_real_escape_string($this->link,$ip);
		$port = mysqli_real_escape_string($this->link,$port);
		$query = "INSERT INTO `".$this->table_prefix."home_ip_ports` (`ip_id`, `port`, `home_id` )
			VALUES ( '$ip', '$port', '$home_id' );";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function delGameIpPort($home_id, $ip, $port) {
		$home_id = mysqli_real_escape_string($this->link,$home_id);
		$ip = mysqli_real_escape_string($this->link,$ip);
		$port = mysqli_real_escape_string($this->link,$port);
		$query = "DELETE FROM `".$this->table_prefix."home_ip_ports`
			WHERE `ip_id` = '$ip' AND `port` = '$port' AND `home_id` = '$home_id'";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function forceModAtAddress($ip_id, $port, $force_mod_id) {
		$force_mod_id = mysqli_real_escape_string($this->link,$force_mod_id);
		$ip_id = mysqli_real_escape_string($this->link,$ip_id);
		$port = mysqli_real_escape_string($this->link,$port);
		$query = "UPDATE `".$this->table_prefix."home_ip_ports` SET `force_mod_id` = '$force_mod_id'
				  WHERE `ip_id` = '$ip_id' AND `port` = '$port'";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function changeHomeName($home_id, $name) {
		$home_id = mysqli_real_escape_string($this->link,$home_id);
		$name = mysqli_real_escape_string($this->link,$name);
		$query = "UPDATE `".$this->table_prefix."server_homes` SET `home_name` = '$name'
			WHERE `home_id` = $home_id";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function changeHomeControlPassword($home_id, $control_password)
	{
		$home_id = mysqli_real_escape_string($this->link,$home_id);
		$control_password = mysqli_real_escape_string($this->link,$control_password);
		$query = "UPDATE `".$this->table_prefix."server_homes` SET `control_password` = '$control_password'
			WHERE `home_id` = $home_id";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}

	public function getAvailableHomesFor($id_type, $assign_id) {
		if ( $id_type == "user" )
		{
			$template = 'SELECT * FROM `%1$sserver_homes`
				WHERE `home_id` NOT IN
				(
					SELECT `home_id` FROM `%1$suser_homes`
					WHERE `user_id` = %2$d
				);';
		}
		else if ( $id_type == "group" )
		{
			$template = 'SELECT * FROM `%1$sserver_homes`
				WHERE `home_id` NOT IN
				(
					SELECT `home_id` FROM `%1$suser_group_homes`
					WHERE `group_id` = %2$d
				);';
		}
		else
		{
			return FALSE;
		}

		$query = sprintf($template,
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$assign_id));

		return $this->listQuery($query);
	}
	
	public function getAvailableUserHomesFor($id_type, $assign_id, $user_id) {
		if ( $id_type == "group" )
		{
			$template ='SELECT * FROM `%1$sserver_homes`
						WHERE
						`home_id` IN
						(
							SELECT `home_id` FROM `%1$suser_homes`
							WHERE `user_id` = %3$d
						)
						AND
						`home_id` NOT IN
						(
							SELECT `home_id` FROM `%1$suser_group_homes`
							WHERE `group_id` = %2$d
						)';
		}
		else
		{
			return FALSE;
		}

		$query = sprintf($template,
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$assign_id),
			mysqli_real_escape_string($this->link,$user_id));

		return $this->listQuery($query);
	}

	public function getGameHomes(){
		$query = sprintf('SELECT %1$sserver_homes.*,%1$sremote_servers.*, %1$sconfig_homes.game_name
			FROM `%1$sserver_homes` NATURAL JOIN `%1$sconfig_homes` NATURAL JOIN `%1$sremote_servers`;',
			$this->table_prefix);
		return $this->listQuery($query);
	}
	
	public function changeLastParam($home_id,$json) {
		$query = sprintf("UPDATE `%sserver_homes` SET `last_param` = '%s' WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$json),
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function getLastParam($home_id) {
		if ( !$this->link ) return FALSE;

		$query = sprintf("SELECT `last_param` FROM `%sserver_homes` WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));

		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		
		if ( mysqli_num_rows($result) != 1 )
			return FALSE;
			
		$result = mysqli_fetch_assoc( $result );

		return $result['last_param'];
	}
	
	public function saveServerStatusCache($ip_id,$port,$status) {
		$query = sprintf("SELECT * FROM `%sstatus_cache` WHERE `ip_id` = %s AND `port` = %s;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip_id),
			mysqli_real_escape_string($this->link,$port));

		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		
		if ( mysqli_num_rows($result) > 0 )
		{
			$query = sprintf("DELETE FROM `%sstatus_cache` WHERE `ip_id` = %s AND `port` = %s;",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$ip_id),
				mysqli_real_escape_string($this->link,$port));

			++$this->queries_;
			mysqli_query($this->link,$query);	
		}
			
		$now = time();
		$json = json_encode($status);
		$query = sprintf("INSERT INTO `%sstatus_cache` ( `date_timestamp`, `ip_id`, `port`, `server_status_cache` ) VALUES ( '%s', '%s', '%s', '%s' );",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$now),
			mysqli_real_escape_string($this->link,$ip_id),
			mysqli_real_escape_string($this->link,$port),
			mysqli_real_escape_string($this->link,$json));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function getServerStatusCache($ip_id,$port) {
		if ( !$this->link ) return FALSE;

		$query = sprintf("SELECT * FROM `%sstatus_cache` WHERE `ip_id` = %s AND `port` = %s;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip_id),
			mysqli_real_escape_string($this->link,$port));

		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		
		if ( mysqli_num_rows($result) != 1 )
			return FALSE;
			
		$result = mysqli_fetch_assoc( $result );
		
		$cache = json_decode( $result['server_status_cache'], True);
		
		$cache['date_timestamp'] = $result['date_timestamp'];

		return $cache;
	}
	
	public function delServerStatusCache($ip_id,$port) {
		if ( !$this->link ) return FALSE;

		$query = sprintf("DELETE FROM `%sstatus_cache` WHERE `ip_id` = %s AND `port` = %s;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip_id),
			mysqli_real_escape_string($this->link,$port));

		++$this->queries_;
		$result = mysqli_query($query, $this->link);
		
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;
		
		return TRUE;
	}


	public function is_valid_login($user_id,$password)
	{
		++$this->queries_;
		$query = sprintf("SELECT *
			FROM `%susers`
			WHERE `user_id` = %d AND
			`users_passwd` = MD5('%s');",
				$this->table_prefix,
				mysqli_real_escape_string($this->link,$user_id),
				mysqli_real_escape_string($this->link,$password));

		$result = mysqli_query($this->link,$query);

		if (mysqli_affected_rows($this->link) == 1)
			return TRUE;

		return FALSE;
	}
	
	public function addAdminExternalLink($name, $url, $user_id) {
		$name = mysqli_real_escape_string($this->link,$name);
		$url = mysqli_real_escape_string($this->link,$url);
		$user_id = mysqli_real_escape_string($this->link,$user_id);
		$query = "INSERT INTO `".$this->table_prefix."adminExternalLinks` (	`link_id`, `name`, `url`, `user_id` )
			VALUES ( NULL, '$name', '$url', '$user_id' );";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function getAdminExternalLinks($user_id) {
		if ( !$this->link ) return;
		$query = sprintf("SELECT * FROM `%sadminExternalLinks` WHERE user_id=".$user_id,
			$this->table_prefix);
		return $this->listQuery($query);
	}
	
	public function delAdminExternalLink($link_id, $user_id){
		$user_id = mysqli_real_escape_string($this->link,$user_id);
		$link_id = mysqli_real_escape_string($this->link,$link_id);
		$query = "DELETE FROM `".$this->table_prefix."adminExternalLinks`
			WHERE `link_id` = '$link_id' AND `user_id` = '$user_id'";
		
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function addRconPreset($name,$command,$home_cfg_id,$mod_cfg_id)
	{
		$name = mysqli_real_escape_string($this->link,$name);
		$command = mysqli_real_escape_string($this->link,$command);
		$home_cfg_id = mysqli_real_escape_string($this->link,$home_cfg_id);
		$mod_cfg_id = mysqli_real_escape_string($this->link,$mod_cfg_id);
		$query = "INSERT INTO `".$this->table_prefix."rcon_presets` (	`preset_id`, `name`, `command`, `home_cfg_id`, `mod_cfg_id` )
			VALUES ( NULL, '$name', '$command', '$home_cfg_id', '$mod_cfg_id' );";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function delRconPreset($preset_id)
	{
		$preset_id = mysqli_real_escape_string($this->link,$preset_id);
		$query = "DELETE FROM `".$this->table_prefix."rcon_presets`
				  WHERE `preset_id` = '$preset_id'";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function changeRconPreset($name,$command,$preset_id)
	{
		$name = mysqli_real_escape_string($this->link,$name);
		$command = mysqli_real_escape_string($this->link,$command);
		$preset_id = mysqli_real_escape_string($this->link,$preset_id);
		$query = "UPDATE `".$this->table_prefix."rcon_presets` SET `name` = '$name',
																   `command` = '$command'
															 WHERE `preset_id` = $preset_id";

		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function getRconPresets($home_cfg_id,$mod_cfg_id)
	{
		if ( !$this->link ) return;
		$query = sprintf("SELECT * FROM `%srcon_presets` WHERE home_cfg_id=".$home_cfg_id." AND mod_cfg_id=".$mod_cfg_id,
			$this->table_prefix);
		return $this->listQuery($query);
	}
		
	public function getTablePrefix()
	{
		return $this->table_prefix;
	}

	public function incrementalNumByHomeId($home_id,$mod_cfg_id,$remote_server_id)
	{
		$mod_cfg_id = mysqli_real_escape_string($this->link,$mod_cfg_id);
		$remote_server_id = mysqli_real_escape_string($this->link,$remote_server_id);
		$query = "SELECT `home_id` FROM `".$this->table_prefix."server_homes` 
				  NATURAL JOIN `".$this->table_prefix."game_mods`
				  WHERE mod_cfg_id=".$mod_cfg_id." AND remote_server_id=".$remote_server_id;
		$result = $this->listQuery($query);
		
		$position = 0;
		foreach($result as $maching_mod )
		{
			if ( $maching_mod['home_id'] < $home_id )
				$position = $position + 2;
		}

		if ($position <= 9)
			return "00"."$position";
		elseif ($position <= 99)
			return "0"."$position";
		else
			return $position;
	}
	
	public function logger($message){
		$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "Anonymous";
		$ip = $_SERVER['REMOTE_ADDR'];
		$message = mysqli_real_escape_string($this->link,$message);
		$this->query("INSERT INTO OGP_DB_PREFIXlogger (date, user_id, ip, message) VALUE (FROM_UNIXTIME(UNIX_TIMESTAMP(), '%d-%m-%Y %H:%i:%s'), $user_id, '$ip', '$message');");
	}

	public function read_logger(){
		return $this->resultQuery("SELECT * FROM `".$this->table_prefix."logger`;");
	}
	
	public function del_logger_log($log_id){
		return $this->resultQuery("DELETE FROM `".$this->table_prefix."logger` WHERE log_id=$log_id;");
	}
	
	public function empty_logger(){
		return $this->resultQuery("TRUNCATE `".$this->table_prefix."logger`;");
	}
	
	public function getIpIdByIp($ip){
		$query = sprintf("SELECT ip_id FROM `%sremote_server_ips` WHERE ip = '%s';",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip));
		$result = $this->listQuery($query);
		return $result[0]['ip_id'];
	}
	
	public function getIpById($ip_id){
		$query = sprintf("SELECT ip FROM `%sremote_server_ips` WHERE ip_id = '%d';",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip_id));
		$result = $this->listQuery($query);
		return $result[0]['ip'];
	}
	
	public function addPortsRange($ip_id,$home_cfg_id,$start_port,$end_port,$port_increment){
		if ( !$this->link ) return false;
		if ($start_port == "" or $end_port == "" or $port_increment == "" or
			$start_port == "0" or $end_port == "0" or $port_increment == "0" or
			$start_port > $end_port)
			return 1;
		$ranges = $this->getPortsRange($ip_id);
		if($ranges != FALSE)
		{
			$used_range_ports = array();
			foreach($ranges as $range)
			{
				for($port = $range['start_port']; $port >= $range['start_port'] and $port <= $range['end_port']; $port++)
				{
					$used_range_ports[] = $port;
				}
			}
			if(!empty($used_range_ports))
			{
				$range_ports = array();
				for($port = $start_port; $port >= $start_port and $port <= $end_port; $port++)
				{
					$range_ports[] = $port;
				}
				foreach($range_ports as $range_port)
				{
					if(in_array($range_port,$used_range_ports))
						return 2;
				}
			}
		}
		$query = sprintf("INSERT INTO `%sarrange_ports` (`ip_id`,`home_cfg_id`,`start_port`,`end_port`,`port_increment`) VALUES('%d','%d', '%d','%d', '%d')",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip_id),
			mysqli_real_escape_string($this->link,$home_cfg_id),
			mysqli_real_escape_string($this->link,$start_port),
			mysqli_real_escape_string($this->link,$end_port),
			mysqli_real_escape_string($this->link,$port_increment));

		++$this->queries_;
		mysqli_query($this->link,$query);

		if( mysqli_errno($this->link) != 0 )
			return false;
	
		return true;
	}
	
	public function getPortsRange($ip_id,$home_cfg_id = FALSE){
		if ( !$this->link ) return false;
		$and_cfg_id = $home_cfg_id !== FALSE ? "AND home_cfg_id=$home_cfg_id":"";
		$query = sprintf("SELECT * FROM `%sarrange_ports` WHERE ip_id=%d $and_cfg_id;",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$ip_id));

		++$this->queries_;
		
		return $this->listQuery($query);
	}
	
	public function delPortsRange($range_id){
		$range_id = mysqli_real_escape_string($this->link,$range_id);
		return $this->query("DELETE FROM `".$this->table_prefix."arrange_ports` WHERE range_id=$range_id;");
	}
	
	public function editPortsRange($range_id,$ip_id,$start_port,$end_port,$port_increment){
		if ($start_port == "" or $end_port == "" or $port_increment == "" or
			$start_port == "0" or $end_port == "0" or $port_increment == "0" or
			$start_port > $end_port)
			return 1;
		$ranges = $this->getPortsRange($ip_id);
		if($ranges != FALSE)
		{
			$used_range_ports = array();
			foreach($ranges as $range)
			{
				if($range['range_id'] == $range_id)
					continue;
				for($port = $range['start_port']; $port >= $range['start_port'] and $port <= $range['end_port']; $port++)
				{
					$used_range_ports[] = $port;
				}
			}
			if(!empty($used_range_ports))
			{
				$range_ports = array();
				for($port = $start_port; $port >= $start_port and $port <= $end_port; $port++)
				{
					$range_ports[] = $port;
				}
				
				foreach($range_ports as $range_port)
				{
					if(in_array($range_port,$used_range_ports))
						return 2;
				}
			}
		}
		$query = sprintf("UPDATE %sarrange_ports 
								 SET 
								 start_port='%d',
								 end_port='%d',
								 port_increment='%d'
								 WHERE range_id='%d';",
								 $this->table_prefix,
								 mysqli_real_escape_string($this->link,$start_port),
								 mysqli_real_escape_string($this->link,$end_port),
								 mysqli_real_escape_string($this->link,$port_increment),
								 mysqli_real_escape_string($this->link,$range_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
		
	public function getNextAvailablePort($ip_id,$home_cfg_id){
		$ranges = $this->getPortsRange($ip_id,$home_cfg_id);
		$range = $ranges[0];
		if(empty($range))
		{
			$ranges = $this->getPortsRange($ip_id,"0");
			$range = $ranges[0];
		}
		if(empty($range))
			$range = array('start_port' => '27015','end_port' => '39915', 'port_increment' => '100');
			
		$home_used_ports = $this->getIpPorts($ip_id);	
		$used_ports = array();
		if(!empty($home_used_ports))
		{
			foreach($home_used_ports as $home_used_port)
			{
				$used_ports[] = $home_used_port['port'];
			}
		}
		
		for($port = $range['start_port']; $port >= $range['start_port'] and $port <= $range['end_port']; $port+=$range['port_increment'])
		{
			if(!in_array($port,$used_ports))
				return $port;
		}
		return FALSE;
	}
	
	public function changeCustomFields($home_id,$json) {
		$query = sprintf("UPDATE `%sserver_homes` SET `custom_fields` = '%s' WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$json),
			mysqli_real_escape_string($this->link,$home_id));
		++$this->queries_;
		if ( mysqli_query($this->link,$query) === FALSE )
			return FALSE;

		return TRUE;
	}
	
	public function getCustomFields($home_id) {
		if ( !$this->link ) return FALSE;

		$query = sprintf("SELECT `custom_fields` FROM `%sserver_homes` WHERE `home_id` = %d",
			$this->table_prefix,
			mysqli_real_escape_string($this->link,$home_id));

		++$this->queries_;
		$result = mysqli_query($this->link,$query);
		
		if ( mysqli_num_rows($result) != 1 )
			return FALSE;
			
		$result = mysqli_fetch_assoc( $result );

		return $result['custom_fields'];
	}
}

?>