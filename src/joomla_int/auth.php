<?php namespace JFusion\Plugins\joomla_int;

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaInt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Core\Factory;
use JFusion\Core\Framework;
use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

use JUserHelper;

/**
 * JFusion Authentication class for the internal Joomla database
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_int
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Auth extends Plugin_Auth
{
	/**
	 * Generates an encrypted password based on the userinfo passed to this function
	 *
	 * @param Userinfo $userinfo userdata object containing the userdata
	 *
	 * @return string Returns generated password
	 */
	public function generateEncryptedPassword(Userinfo $userinfo)
	{
		jimport('joomla.user.helper');
		if (jimport('phpass.passwordhash')) {
			$testcrypt = JUserHelper::hashPassword($userinfo->password_clear);
		} else {
			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'md5-hex');
		}
		return $testcrypt;
	}

	/**
	 * used by framework to ensure a password test
	 *
	 * @param Userinfo $userinfo userdata object containing the userdata
	 *
	 * @return boolean
	 */
	function checkPassword(Userinfo $userinfo) {
		$rehash = false;
		$match = false;

		// If we are using phpass
		if (strpos($userinfo->password, '$P$') === 0) {
			// Use PHPass's portable hashes with a cost of 10.
			$phpass = new \PasswordHash(10, true);

			$match = $phpass->CheckPassword($userinfo->password_clear, $userinfo->password);
		} elseif ($userinfo->password[0] == '$') {
			// JCrypt::hasStrongPasswordSupport() includes a fallback for us in the worst case
			\JCrypt::hasStrongPasswordSupport();
			$match = password_verify($userinfo->password_clear, $userinfo->password);

			// Uncomment this line if we actually move to bcrypt.
			// $rehash = password_needs_rehash($hash, PASSWORD_DEFAULT);
			$rehash = true;
		} elseif (substr($userinfo->password, 0, 8) == '{SHA256}') {
			// Check the password
			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'sha256', true);

			$match = $this->comparePassword($userinfo->password, $testcrypt);

			$rehash = true;
		} else {
			$rehash = true;

			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'md5-hex', false);

			$match = $this->comparePassword($userinfo->password, $testcrypt);
		}

		// If we have a match and rehash = true, rehash the password with the current algorithm.
		if ($match && $rehash) {
			$user = Factory::getUser($this->getJname());
			$old = $user->getUser($userinfo);
			if ($old) {
				$user->updatePassword($userinfo, $old);
			}
		}
		return $match;
	}

	/**
	 * Hashes a password using the current encryption.
	 *
	 * @param   Userinfo  $userinfo  The plaintext password to encrypt.
	 *
	 * @return  string  The encrypted password.
	 *
	 * @since   3.2.1
	 */
	public function hashPassword(Userinfo $userinfo)
	{
		jimport('joomla.user.helper');
		if (jimport('phpass.passwordhash')) {
			$password = JUserHelper::hashPassword($userinfo->password_clear);
		} else {
			$salt = Framework::genRandomPassword(32);
			$password = JUserHelper::getCryptedPassword($userinfo->password_clear, $salt, 'md5-hex') . ':' . $salt;
		}
		return $password;
	}
}
