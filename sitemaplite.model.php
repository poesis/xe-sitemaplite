<?php

/**
 * @file sitemaplite.model.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license GPLv2 or Later <https://www.gnu.org/licenses/gpl-2.0.html>
 * @brief Sitemap Lite Model
 */
class SitemapLiteModel extends SitemapLite
{
	/**
	 * Update sitemap.xml after editing menu
	 */
	public function triggerUpdateSitemapXML($trigger_obj)
	{
		$target_actions = array(
			'procMenuAdminInsert' => true,
			'procMenuAdminUpdate' => true,
			'procMenuAdminDelete' => true,
			'procMenuAdminInsertItem' => true,
			'procMenuAdminUpdateItem' => true,
			'procMenuAdminDeleteItem' => true,
		);
		
		if (isset($target_actions[$trigger_obj->act]))
		{
			getAdminController('sitemaplite')->writeSitemapXml();
		}
	}
}
