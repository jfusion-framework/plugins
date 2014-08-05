<?php namespace JFusion\Plugins\mediawiki;

/**
 * @package JFusion_mediawiki
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

/**
 * JFusion Authentication Class for mediawiki 1.1.x
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * @package JFusion_mediawiki
 */
class Auth extends Plugin_Auth
{
    /**
     * @param Userinfo $userinfo
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo)
    {
        return ':A:' . md5($userinfo->password_clear);
    }
}
