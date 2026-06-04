<?php

namespace Rhymix\Modules\SitemapLite\Controllers;

use Rhymix\Modules\SitemapLite\Models\Sitemap as SitemapModel;

class EventHandlers extends Base
{
	/**
	 * Trigger called when sitemap.xml may need to be updated
	 *
	 * @param object $trigger_obj
	 * @return void
	 */
	public function triggerUpdateSitemapXML(object $trigger_obj)
	{
		$config = $this->getConfig();

		$menu_target_actions = array(
			'procMenuAdminInsert' => true,
			'procMenuAdminUpdate' => true,
			'procMenuAdminDelete' => true,
			'procMenuAdminInsertItem' => true,
			'procMenuAdminUpdateItem' => true,
			'procMenuAdminDeleteItem' => true,
			'procDocumentInsertCategory' => true,
			'procDocumentDeleteCategory' => true,
		);

		$document_target_actions = array(
			'/^proc\w+(?:Insert|Update|Delete|Vote)Document$/' => true,
		);

		// Update sitemap.xml if the menu has changed
		if (isset($menu_target_actions[$trigger_obj->act]))
		{
			SitemapModel::updateSitemap($config);
			return;
		}

		// Update sitemap.xml if documents have changed and the interval has passed
		foreach ($document_target_actions as $regexp => $true)
		{
			if (preg_match($regexp, $trigger_obj->act))
			{
				$do_reresh = false;
				if (!empty($config->document_count) && !empty($config->document_source_modules))
				{
					$do_reresh = true;
				}
				foreach ($config->domains ?? [] as $domain)
				{
					if (!empty($domain->document_count) && !empty($domain->document_source_modules))
					{
						$do_reresh = true;
						break;
					}
				}

				if ($do_reresh)
				{
					switch ($config->refresh_interval ?? $config->document_interval)
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
						SitemapModel::updateSitemap($config);
					}
				}

				break;
			}
		}
	}
}
