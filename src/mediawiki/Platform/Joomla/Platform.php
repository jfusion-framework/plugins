<?php namespace JFusion\Plugins\mediawiki\Platform\Joomla;

use JFusion\Core\Factory;
use JFusion\Core\Framework;
use JFusion\Plugin\Platform\Joomla;
use JFusion\User\Userinfo;

use Joomla\String\String;
use Joomla\Uri\Uri;

use Psr\Log\LogLevel;

use JFusionFunction;
use JRegistry;
use JDate;

use stdClass;
use DateTimeZone;
use Exception;

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MediaWiki
 * @author     JFusion Team
 * @copyright  2008 JFusion.  All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org/
**/

class Platform extends Joomla
{
    /**
     * @param $config
     * @param $view
     * @param JRegistry $pluginParam
     *
     * @return string
     */
    function renderActivityModule($config, $view, $pluginParam) {
	    $output = '';
	    try {
		    $db = Factory::getDatabase($this->getJname());
		    defined('_DATE_FORMAT_LC2') or define('_DATE_FORMAT_LC2','Y M d h:i:s A');

		    // configuration
		    $display_limit_subject = $pluginParam->get('character_limit_subject');
		    $display_limit = $pluginParam->get('character_limit');
		    $result_limit = $pluginParam->get('result_limit', 0);
		    $itemid = $pluginParam->get('itemid');
		    $avatar = $pluginParam->get('avatar');
		    $avatar_height = $pluginParam->get('avatar_height');
		    $avatar_width = $pluginParam->get('avatar_width');
		    $avatar_keep_proportional = $pluginParam->get('avatar_keep_proportional', 1);
		    $avatar_software = $pluginParam->get('avatar_software');
		    $showdate = $pluginParam->get('showdate');
		    $custom_date = $pluginParam->get('custom_date');
		    $result_order = $pluginParam->get('result_order');
		    $showuser = $pluginParam->get('showuser');
		    $display_body = $pluginParam->get('display_body');

		    if ($this->params->get('new_window')) {
			    $new_window = '_blank';
		    } else {
			    $new_window = '_self';
		    }

		    $query = $db->getQuery(true)
			    ->select('p.page_id , p.page_title AS title, SUBSTRING(t.old_text,1,' . $display_limit . ') as text,
					STR_TO_DATE(r.rev_timestamp, "%Y%m%d%H%i%S") AS created,
					p.page_title AS section,
					r.rev_user_text as user,
					r.rev_user as userid')
			    ->from('#__page AS p')
		        ->innerJoin('#__revision AS r ON r.rev_page = p.page_id AND r.rev_id = p.page_latest')
			    ->innerJoin('#__text AS t on t.old_id = r.rev_text_id')
		        ->order('r.rev_timestamp DESC');

		    $db->setQuery($query, 0 , (int)$result_limit);

		    $results = $db->loadObjectList();
		    //reorder the keys for the for loop
		    if(is_array($results)) {
			    if ($result_order) {
				    $results = array_reverse($results);
			    }
			    $output .= '<ul>';
			    foreach($results as $value ) {
				    if (strlen($value->text)) {
					    //get the avatar of the logged in user
					    $o_avatar_height = $o_avatar_width = '';
					    if ($avatar) {
						    $userlookup = new Userinfo($this->getJname());
						    $userlookup->userid = $value->userid;

						    $PluginUser = Factory::getUser('joomla_int');
						    $userlookup = $PluginUser->lookupUser($userlookup);

						    // retrieve avatar
						    if(!empty($avatar_software) && $avatar_software != 'jfusion' && $userlookup) {
							    $o_avatar = Framework::getAltAvatar($avatar_software, $userlookup);
						    }
						    if(empty($o_avatar)) {
							    $o_avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
						    }
						    $maxheight = $avatar_height;
						    $maxwidth = $avatar_width;


						    $size = ($avatar_keep_proportional) ? Framework::getImageSize($o_avatar) : false;
						    //size the avatar to fit inside the dimensions if larger
						    if($size!==false && ($size->width > $maxwidth || $size->height > $maxheight)) {
							    $wscale = $maxwidth/$size->width;
							    $hscale = $maxheight/$size->height;
							    $scale = min($hscale, $wscale);
							    $w = floor($scale*$size->width);
							    $h = floor($scale*$size->height);
						    } elseif($size!==false) {
							    //the avatar is within the limits
							    $w = $size->width;
							    $h = $size->height;
						    } else {
							    //getimagesize failed
							    $w = $maxwidth;
							    $h = $maxheight;
						    }
						    $o_avatar_source = $o_avatar;
						    $o_avatar_width = $w;
						    $o_avatar_height = $h;
					    } else {
						    $o_avatar = '';
					    }
					    if (!empty($o_avatar_source)) {
						    $output .= '<li style="clear:left;">';
						    $output .= '<img style="vertical-align:middle; float:left; margin:3px;" src="' . $o_avatar_source . '" height="' . $o_avatar_height . '" width="' . $o_avatar_width . '" alt="avatar" />';
					    } else {
						    $output .= '<li>';
					    }
					    $url = JFusionFunction::routeURL('index.php?title=' . $value->title, $itemid, $this->getJname());
					    if (String::strlen($value->title) > $display_limit_subject) {
						    //we need to shorten the subject
						    $value->pagename = String::substr($value->title, 0, $display_limit_subject) . '...';
					    }
					    $output .= '<a href="' . $url . '" target="' . $new_window . '">' . $value->title . '</a> - ';
					    if ($showuser) {
						    $output .= $value->user;
					    }
					    //process date info
					    if($showdate) {
						    jimport('joomla.utilities.date');
						    $JDate =  new JDate($value->created);
						    $JDate->setTimezone(new DateTimeZone(JFusionFunction::getJoomlaTimezone()));
						    if (empty($custom_date)) {
							    $output .= ' ' . $JDate->format(_DATE_FORMAT_LC2, true);
						    } else {
							    $output .= ' ' . $JDate->format($custom_date, true);
						    }
					    }
					    if($display_body) {
						    $output .= ' - ' . $value->text;
					    }
					    $output .= '</li>';
				    }
			    }
			    $output .= '</ul>';
		    }
	    } catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $output;
	}

	/**
	 * getSearchQueryColumns
	 *
	 * @return object
	 */
	function getSearchQueryColumns()
	{
		$columns = new stdClass();
		$columns->title = 'p.page_title';
		$columns->text = 't.old_text';
		return $columns;
	}

	/**
	 * @param object $pluginParam
	 * @return string
	 */
	function getSearchQuery(&$pluginParam)
	{
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('p.page_id , p.page_title AS title, t.old_text as text,
					STR_TO_DATE(p.page_touched, "%Y%m%d%H%i%S") AS created,
					p.page_title AS section')
			->from('#__page AS p')
			->innerJoin('#__revision AS r ON r.rev_page = p.page_id AND r.rev_id = p.page_latest')
			->innerJoin('#__text AS t on t.old_id = r.rev_text_id');

		return (string)$query;
	}

	/**
	 * Add on a plugin specific clause;
	 * @TODO permissions
	 *
	 * @param string &$where reference to where clause already generated by search bot; add on plugin specific criteria
	 * @param object &$pluginParam custom plugin parameters in search.xml
	 * @param string $ordering
	 *
	 * @return void
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering)
	{
	}

	/**
	 * @param mixed $post
	 *
	 * @return string
	 */
	function getSearchResultLink($post)
	{
		return 'index.php?title=' . $post->title;
	}

	/**
	 * @param $data
	 */
	function _parseBody(&$data)
	{
		$regex_body		= array();
		$replace_body	= array();

		$uri = new Uri($data->integratedURL);
		$regex_body[]	= '#addButton\("/(.*?)"#mS';
		$replace_body[]	= 'addButton("' . $uri->toString(array('scheme', 'host')) . '/$1"';

		$data->body = preg_replace($regex_body, $replace_body, $data->body);
	}
}