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
		$menu_target_actions = array(
			'procMenuAdminInsert' => true,
			'procMenuAdminUpdate' => true,
			'procMenuAdminDelete' => true,
			'procMenuAdminInsertItem' => true,
			'procMenuAdminUpdateItem' => true,
			'procMenuAdminDeleteItem' => true,
		);
		
		$document_target_actions = array(
			'/^proc\w+(?:Insert|Update|Delete|Vote)Document$/' => true,
		);
		
		// Update sitemap.xml if the menu has changed
		if (isset($menu_target_actions[$trigger_obj->act]))
		{
			getAdminController('sitemaplite')->writeSitemapXml();
			return;
		}
		
		// Update sitemap.xml if documents have changed and the interval has passed
		foreach ($document_target_actions as $regexp => $true)
		{
			if (preg_match($regexp, $trigger_obj->act))
			{
				$config = $this->getConfig();
				if ($config->document_count && $config->document_source_modules)
				{
					switch ($config->document_interval)
					{
						case 'always': $timediff = 3; break;
						case 'hourly': $timediff = 3600; break;
						case 'daily': $timediff = 86400; break;
						case 'weekly': $timediff = 86400 * 7; break;
						case 'monthly': $timediff = 86400 * 30; break;
						case 'manual': $timediff = -1; break;
						default: $timediff = 86400; break;
					}
					
					$xml_path = $this->getSitemapXmlPath($config->sitemap_file_path);
					if ($timediff > 0 && filemtime($xml_path) < time() - $timediff)
					{
						@touch($xml_path);
						getAdminController('sitemaplite')->writeSitemapXml($config);
					}
				}
			}
		}
	}
}
