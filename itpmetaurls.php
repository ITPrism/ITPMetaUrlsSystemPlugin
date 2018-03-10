<?php
/**
 * @package      ITPMeta
 * @subpackage   Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2017 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

use Joomla\String\StringHelper;
use Itpmeta\Url\UrlHelper;

// no direct access
defined('_JEXEC') or die;

jimport('Prism.init');
jimport('Itpmeta.init');

/**
 * ITPMeta plugin
 *
 * @package        ITPMeta
 * @subpackage     Plugins
 */
class plgSystemItpmetaUrls extends JPlugin
{
    /**
     * @var JApplicationSite
     */
    protected $app;

    protected $uri;

    /**
     * Get clean URI.
     *
     * @throws \Exception
     *
     * @return Itpmeta\Url\Uri
     */
    protected function getUri()
    {
        $container       = Prism\Container::getContainer();
        $containerHelper = new Itpmeta\Container\Helper();

        $keys = array(
            'uri' => UrlHelper::getCleanUri()
        );

        // Load tags for current address
        $itpUri    = $containerHelper->fetchUri($container, $keys);
        
        if (!$itpUri->getId()) {
            $itpUri->setUri($keys['uri']);
        }

        return $itpUri;
    }

    private function isRestricted()
    {
        if ($this->app->isAdmin()) {
            return true;
        }

        $document = JFactory::getDocument();
        /** @var $document JDocumentHTML */

        $type = $document->getType();
        if (strcmp('html', $type) !== 0) {
            return true;
        }

        // It works only for GET request
        $method = $this->app->input->getMethod();
        if (strcmp('GET', $method) !== 0) {
            return true;
        }

        // Check component enabled
        if (!JComponentHelper::isEnabled('com_itpmeta')) {
            return true;
        }

        return false;
    }

    /**
     * This method adds a new URI.
     *
     * @throws \Exception
     * @return void
     */
    public function onAfterDispatch()
    {
        // Check for restrictions
        if ($this->isRestricted()) {
            return;
        }

        $itpUri = $this->getUri();

        // If URI exists, return.
        if ($itpUri->getId()) {
            return;
        }

        $newLinkState    = (int)$this->params->get('links_new_state', 0);
        $autoUpdateState = (int)$this->params->get('links_autoupdate_state', 1);
        $collectingType  = (int)$this->params->get('links_auto_collect_type', 1);

        // Prepare menu items ids
        $menuItemId   = 0;
        $parentMenuId = 0;
        $isPrimary    = 0;

        // Add a new URI.

        // Strict mode
        if ($collectingType === Itpmeta\Constants::COLLECTION_TYPE_STRICT) {
            // Get path
            $uri  = clone UrlHelper::getUri();
            $path = $uri->toString(array('path'));
            $path = StringHelper::substr($path, 1);

            // Get query
            $query = $uri->toString(array('query'));

            // Get URL params
            $router    = JApplicationSite::getRouter();
            $uriParams = $router->parse($uri);

            $sef   = $this->app->get('sef');

            // Get menu item ID`
            $itemId = Joomla\Utilities\ArrayHelper::getValue($uriParams, 'Itemid');
            if (!$itemId) {
                return;
            }

            // Get menu item
            $menu     = $this->app->getMenu();
            $menuItem = $menu->getItem($itemId);
            if (!$menuItem) {
                return;
            }

            // Check for public access level.
            // If the link have no public access leave,
            // we will not add it to the database.
            if ((int)$menuItem->access !== 1) {
                return;
            }

            // Check for additional parameters in the query
            // If there are vars different from standard,
            // the URI won't be saved
            if ($sef and $query !== '') {
                // Remove first symbol which is '?'
                $query       = StringHelper::substr($query, 1);
                $allowedVars = array('view', 'layout');
                $queryVars   = array();

                // Parse the query string
                parse_str($query, $queryVars);
                $queryVars = array_keys($queryVars);

                // Diff variables
                $resultVars = array_diff($queryVars, $allowedVars);

                // If there are other vars instead 'view' and 'layout',
                // do not add uri and return.
                if (count($resultVars) > 0) {
                    return;
                }
            }

            // Check for valid routing
            // If it is not a menu item, we connect it to its parent menu item.
            if (strcmp($path, $menuItem->route) !== 0) {
                $parentMenuId = (int)$menuItem->id;
            } else {
                $parentMenuId = (int)$menuItem->parent_id;
                $menuItemId   = (int)$menuItem->id;
            }

            if ((int)$menuItem->level === 1) {
                $isPrimary = 1;
            }
        }

        $data = array(
            'published'      => $newLinkState,
            'autoupdate'     => $autoUpdateState,
            'parent_menu_id' => $parentMenuId,
            'menu_id'        => $menuItemId,
            'primary_url'    => $isPrimary
        );

        // Store the URI.
        $itpUri->bind($data);
        $itpUri->store();
    }
}
