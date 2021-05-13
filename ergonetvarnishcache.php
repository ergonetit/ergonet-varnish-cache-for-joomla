<?php
/**
 * @package    Joomla
 *
 * @author     Ergonet srl <info@ergonet.it>
 * @copyright  A copyright
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.ergonet.it
 */

defined('_JEXEC') or die;

/**
 * Joomla plugin.
 * @author Ergonet Srl
 * @since     1.0.0
 */

class plgSystemergonetvarnishcache extends JPlugin
{
    public function __construct(&$subject, $config = array())
    {
        if (JFactory::getUser()->guest) {
            JResponse::setHeader('X-Logged-In', 'False', true);
        } else {
            JResponse::setHeader('X-Logged-In', 'True', true);
        }
        parent::__construct($subject);
    }

    function onContentAfterSave($context, $article, $isNew)
    {
        if (get_class($article) != "Joomla\CMS\Table\Content") {
           return true;
        }
        
        $rootURL = rtrim(JURI::base(),'/');
        $subpathURL = JURI::base(true);
        if(!empty($subpathURL) && ($subpathURL != '/')) {
            $rootURL = substr($rootURL, 0, -1 * strlen($subpathURL));
        }

        $app    = JApplication::getInstance('site');
        $router = &$app->getRouter();
        $newUrl = JRoute::_(ContentHelperRoute::getArticleRoute($article->id.':'.$article->alias, $article->catid));
        $newUrl = $router->build($newUrl);
        $parsed_url = $newUrl->toString();
        $parsed_url = str_replace(JURI::base(true), '', $parsed_url);
        $parsed_url = $rootURL.$parsed_url;

        $wwwparsed_url = str_replace('://', '://www.', $parsed_url);
        $wwwrootURL = str_replace('://', '://www.', $rootURL);

        $this->execCachePurge($parsed_url);
        $this->execCachePurge($wwwparsed_url);
        $this->execCachePurge($rootURL);
        $this->execCachePurge($wwwrootURL);

        JFactory::getApplication()->enqueueMessage('Aggiornamento della cache Varnish avvenuto correttamente.');

        return true;
    }

    function onUserAfterLogin($options)
    {
        $app    = JApplication::getInstance('site');
        $plg = JPluginHelper::getPlugin('system', 'plgSystemergonetvarnishcache');
        $plg_params = new JRegistry();
        $plg_params->loadString($plg->params);
        $cookie = $plg_params->get('cookie_lifetime', 60);
        $lifetime = $cookie* 24 * 60 * 60;
        $token    = JUserHelper::genRandomPassword(16);
        setcookie("joomla_logged_in", $token, time()+$lifetime);
        return true;
    }

    function onUserAfterLogout($options)
    {
        unset($_COOKIE['joomla_logged_in']);
        setcookie("joomla_logged_in", '', time()-3600);

        return true;
    }

    function execCachePurge( $url )
    {
        $urlFormatted = $this->getUrl($url);
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_USERAGENT,'joomla_purgeCache');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PURGE");
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Host: ' . $urlFormatted['hostname']));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl,CURLOPT_URL, $urlFormatted['url']);
        $response=curl_exec($curl);
        curl_close($curl);
        return true;
    }

    private function getUrl($url)
    {
        $parsedUrl = parse_url($url);
        $hostname = $parsedUrl['host'];
        $address = gethostbyname($hostname);
        $url = $parsedUrl['scheme'] . '://' . $address;
        if($parsedUrl['port']) {
            $url .= ':' . $parsedUrl['port'];
        }
        if($parsedUrl['path']) {
            $url .= $parsedUrl['path'];
        }
        if($parsedUrl['query']) {
            $url .= '?' . $parsedUrl['query'];
        }
        if($parsedUrl['fragment']) {
            $url .= '#' . $parsedUrl['fragment'];
        }
        return array(
            'url' => $url,
            'hostname' => $hostname
        );
    }
}
