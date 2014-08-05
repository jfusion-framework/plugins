<?php namespace JFusion\Plugins\mediawiki;

/**
 * @package JFusion_mediawiki
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use JFusion\Core\Factory;
use JFusion\Core\Framework;
use JFusion\User\Userinfo;
use JFusion\Plugin\Plugin_User;

use Joomla\Language\Text;

use Psr\Log\LogLevel;

use RuntimeException;
use stdClass;
use Exception;

/**
 * JFusion User Class for mediawiki 1.1.x
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * @package JFusion_mediawiki
 */

/**
 * JFusionUser_mediawiki class
 *
 * @category   JFusion
 * @package    Plugin
 * @subpackage JFusionUser_mediawiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class User extends Plugin_User
{

    /**
     * @param Userinfo $userinfo
     *
	 * @return null|Userinfo
     */
    function getUser(Userinfo $userinfo)
    {
	    $user = null;
	    try {
		    // get the username
		    $userinfo->username = $this->filterUsername($userinfo->username);

		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'user_name', 'user_email', 'user_id');

		    // initialise some objects
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('user_id as userid, user_name as username, user_token, user_real_name as name, user_email as email, user_password as password, NULL as password_salt, NULL as activation, TRUE as is_activated, NULL as reason, user_touched as lastvisit')
			    ->from('#__user')
		        ->where($identifier_type . ' = ' . $db->quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();

		    if ($result) {
			    $query = $db->getQuery(true)
				    ->select('ug_group')
				    ->from('#__user_groups')
				    ->where('ug_user = ' . $db->quote($result->userid));

			    $db->setQuery($query);
			    $grouplist = $db->loadObjectList();
			    $groups = array();
			    foreach($grouplist as $group) {
				    $groups[] = $group->ug_group;
			    }
			    $result->group_id = implode(',', $groups);
			    $result->groups = $groups;

			    $query = $db->getQuery(true)
				    ->select('ipb_user, ipb_expiry')
				    ->from('#__ipblocks')
				    ->where('ipb_user = ' . $db->quote($result->userid));

			    $db->setQuery($query);
			    $block = $db->loadObject();

			    if (isset($block->ipb_user)) {
				    if ($block->ipb_expiry ) {
					    $result->block = true;
				    } else {
					    $result->block = false;
				    }
			    } else {
				    $result->block = false;
			    }

			    $result->activation = null;

			    $user = new Userinfo($this->getJname());
			    $user->bind($result);
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $user;
    }

    /**
     * @param Userinfo $userinfo
     *
     * @return boolean returns true on success and false on error
     */
    function deleteUser(Userinfo $userinfo) {
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->delete('#__user')
		    ->where('user_name = ' .  $db->quote($userinfo->username));

	    $db->setQuery($query);
	    $db->execute();

	    $query = $db->getQuery(true)
		    ->delete('#__user_groups')
		    ->where('ug_user = ' .  $db->quote($userinfo->userid));

	    $db->setQuery($query);
	    $db->execute();
		return true;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options){
        $cookie_path = $this->params->get('cookie_path');
        $cookie_domain = $this->params->get('cookie_domain');
        $cookie_secure = $this->params->get('secure');
        $cookie_httponly = $this->params->get('httponly');
        $cookie_name = $this->helper->getCookieName();
        $expires = -3600;

	    $this->helper->startSession($options);
   		$_SESSION['wsUserID'] = 0;
   		$_SESSION['wsUserName'] = '';
   		$_SESSION['wsToken'] = '';
	    $this->helper->closeSession();

        $status[LogLevel::DEBUG][] = $this->addCookie($cookie_name  . 'UserName', '', $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
        $status[LogLevel::DEBUG][] = $this->addCookie($cookie_name  . 'UserID', '', $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
        $status[LogLevel::DEBUG][] = $this->addCookie($cookie_name  . 'Token', '', $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);

   		$now = time();
        $expiration = 86400;

        $status[LogLevel::DEBUG][] = $this->addCookie('LoggedOut', $now, $expiration, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
		return $status;
     }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     * @return array
     */
    function createSession(Userinfo $userinfo, $options){
        $status = array('error' => array(), 'debug' => array());

		//do not create sessions for blocked users
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = Text::_('FUSION_BLOCKED_USER');
		} else {
            $cookie_path = $this->params->get('cookie_path');
            $cookie_domain = $this->params->get('cookie_domain');
            $cookie_secure = $this->params->get('secure');
            $cookie_httponly = $this->params->get('httponly');
			$expires = $this->params->get('cookie_expires', 3100);
            $cookie_name = $this->helper->getCookieName();
			$this->helper->startSession($options);

			$status[LogLevel::DEBUG][] = $this->addCookie($cookie_name  . 'UserName', $userinfo->username, $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
            $_SESSION['wsUserName'] = $userinfo->username;

			$status[LogLevel::DEBUG][] = $this->addCookie($cookie_name  . 'UserID', $userinfo->userid, $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
            $_SESSION['wsUserID'] = $userinfo->userid;

            $_SESSION[ 'wsToken'] = $userinfo->user_token;
            if (!empty($options['remember'])) {
	            $status[LogLevel::DEBUG][] = $this->addCookie($cookie_name  . 'Token', $userinfo->user_token, $expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly);
            }

			$this->helper->closeSession();
        }
		return $status;
	}


    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username)
    {
	    // as the username also is used as a directory we probably must strip unwanted characters.
	    $bad = array('_');
	    $replacement = array(' ');
	    $username = str_replace($bad, $replacement, $username);
	    $username = ucfirst($username);
        return $username;
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser) {
	    $existinguser->password = ':A:' . md5($userinfo->password_clear);
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__user')
		    ->set('user_password = ' . $db->quote($existinguser->password))
		    ->where('user_id = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updateUsername(Userinfo $userinfo, Userinfo &$existinguser)
    {

    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    //we need to update the email
	    $db = Factory::getDatabase($this->getJname());
	    $query = $db->getQuery(true)
		    ->update('#__user')
		    ->set('user_email = ' . $db->quote($userinfo->email))
		    ->where('user_id = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws \RuntimeException
	 *
	 * @return void
	 */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		if (empty($usergroups)) {
			throw new RuntimeException(Text::_('USERGROUP_MISSING'));
		} else {
			$db = Factory::getDatabase($this->getJname());
			try {
				$query = $db->getQuery(true)
					->delete('#__user_groups')
					->where('ug_user = ' .  $db->quote($existinguser->userid));

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
			}
			foreach($usergroups as $usergroup) {
				//prepare the user variables
				$ug = new stdClass;
				$ug->ug_user = $existinguser->userid;
				$ug->ug_group = $usergroup;

				$db->insertObject('#__user_groups', $ug, 'ug_user' );
			}

			$this->debugger->addDebug(Text::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . implode(' , ', $usergroups));
		}
	}

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function blockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $db = Factory::getDatabase($this->getJname());
	    $ban = new stdClass;
	    $ban->ipb_id = NULL;
	    $ban->ipb_address = NULL;
	    $ban->ipb_user = $existinguser->userid;
	    $ban->ipb_by = $existinguser->userid;
	    $ban->ipb_by_text = $existinguser->username;

	    $ban->ipb_reason = 'You have been banned from this software. Please contact your site admin for more details';
	    $ban->ipb_timestamp = gmdate('YmdHis', time());

	    $ban->ipb_auto = 0;
	    $ban->ipb_anon_only = 0;
	    $ban->ipb_create_account = 1;
	    $ban->ipb_enable_autoblock = 1;
	    $ban->ipb_expiry = 'infinity';
	    $ban->ipb_range_start = NULL;
	    $ban->ipb_range_end = NULL;
	    $ban->ipb_deleted = 0;
	    $ban->ipb_block_email = 0;
	    $ban->ipb_allow_usertalk = 0;

	    //now append the new user data
	    $db->insertObject('#__ipblocks', $ban, 'ipb_id' );

	    $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function unblockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->delete('#__ipblocks')
		    ->where('ipb_user = ' .  $db->quote($userinfo->userid));

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
    }
/*
    function activateUser(\JFusion\User\Userinfo $userinfo, \JFusion\User\Userinfo &$existinguser)
    {
        $db = \JFusion\Core\Factory::getDatabase($this->getJname());
	    $query = $db->getQuery(true)
		    ->update('#__user')
		    ->set('is_activated = 1')
			->set('validation_code = ' . $db->quote(''))
		    ->where('user_id = ' . (int)$existinguser->userid);

        $db->setQuery($query);
		$db->execute():
		$this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
    }

    function inactivateUser(\JFusion\User\Userinfo $userinfo, \JFusion\User\Userinfo &$existinguser)
    {
        $db = \JFusion\Core\Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__user')
		    ->set('is_activated = 0')
			->set('validation_code = ' . $db->quote($userinfo->activation))
		    ->where('user_id = ' . (int)$existinguser->userid);

        $db->setQuery($query);
		$db->execute();
		$this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
    }
*/

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 *
	 * @return Userinfo
	 */
    function createUser(Userinfo $userinfo)
    {
	    //we need to create a new SMF user
	    $db = Factory::getDatabase($this->getJname());

	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
	    } else {
		    //prepare the user variables
		    $user = new stdClass;
		    $user->user_id = NULL;
		    $user->user_name = $this->filterUsername($userinfo->username);
		    $user->user_real_name = $userinfo->name;
		    $user->user_email = $userinfo->email;
		    $user->user_email_token_expires = null;
		    $user->user_email_token = '';

		    if (isset($userinfo->password_clear)) {
			    $user->user_password = ':A:' . md5($userinfo->password_clear);
		    } else {
			    $user->user_password = ':A:' . $userinfo->password;
		    }
		    $user->user_newpass_time = $user->user_newpassword = null;

		    $db->setQuery('SHOW COLUMNS FROM #__user LIKE \'user_options\'');
		    $db->execute();

		    if ($db->getNumRows() ) {
			    $user->user_options = ' ';
		    }

		    $user->user_email_authenticated = $user->user_registration = $user->user_touched = gmdate('YmdHis', time());
		    $user->user_editcount = 0;
		    /*
			if ($userinfo->activation){
				$user->is_activated = 0;
				$user->validation_code = $userinfo->activation;
			} else {
				$user->is_activated = 1;
				$user->validation_code = '';
			}
	*/
		    //now append the new user data
		    $db->insertObject('#__user', $user, 'user_id' );

		    $wgDBprefix = $this->params->get('database_prefix');
		    $wgDBname = $this->params->get('database_name');

		    if ($wgDBprefix) {
			    $wfWikiID = $wgDBname . '-' . $wgDBprefix;
		    } else {
			    $wfWikiID = $wgDBname;
		    }

		    $wgSecretKey = $this->helper->getConfig('wgSecretKey');
		    $wgProxyKey = $this->helper->getConfig('wgProxyKey');

		    if ($wgSecretKey) {
			    $key = $wgSecretKey;
		    } elseif ($wgProxyKey) {
			    $key = $wgProxyKey;
		    } else {
			    $key = microtime();
		    }
		    //update the stats
		    $mToken = md5($key . mt_rand(0, 0x7fffffff) . $wfWikiID . $user->user_id);

		    $query = $db->getQuery(true)
			    ->update('#__user')
			    ->set('is_activated = 0')
			    ->set('user_token = ' . $db->quote($mToken))
			    ->where('user_id = ' . $db->quote($user->user_id));

		    $db->setQuery($query);
		    $db->execute();

		    //prepare the user variables
		    foreach($usergroups as $usergroup) {
			    //prepare the user variables
			    $ug = new stdClass;
			    $ug->ug_user = $user->user_id;
			    $ug->ug_group = $usergroup;

			    $db->insertObject('#__user_groups', $ug, 'ug_user' );
		    }
		    //return the good news
		    return $this->getUser($userinfo);
	    }
    }
}
