<?php namespace JFusion\Plugins\universal;

/**
 * @package JFusion_universal
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

/**
 * JFusion Authentication Class for universal
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * @package JFusion_universal
 */
class Auth extends Plugin_Auth
{
    /**
     * @param Userinfo $userinfo
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo)
    {
		$user_auth = $this->params->get('user_auth');

		$user_auth = rtrim(trim($user_auth),';');
    	ob_start();
		$testcrypt = eval('return '. $user_auth . ';');
		$error = ob_get_contents();
		ob_end_clean();
		if ($testcrypt===false && strlen($error)) {
			die($error);
		}
        return $testcrypt;
    }
}
