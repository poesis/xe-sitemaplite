<?php

/**
 * @file sitemaplite.admin.controller.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license GPLv2 or Later <https://www.gnu.org/licenses/gpl-2.0.html>
 * @brief Sitemap Lite Admin Controller
 */
class SitemapLiteAdminController extends SitemapLite
{
	/**
	 * Save admin config
	 */
	public function procSitemapliteAdminInsertConfig()
	{
		// Get current config and request vars
		$config = $this->getConfig();
		$vars = Context::getRequestVars();

		// Load general config
		$file_path = $vars->sitemaplite_file_path;
		$config->sitemap_file_path = in_array($file_path, array('root', 'sub', 'files', 'domains')) ? $file_path : 'root';

		$ping_search_engines = $vars->sitemaplite_ping_search_engines;
		$config->ping_search_engines = is_array($ping_search_engines) ? $ping_search_engines : array();

		// Load menu config
		$menu_srls = $vars->sitemaplite_menu_srls;
		$config->menu_srls = is_array($menu_srls) ? $menu_srls : array();

		$only_public_menus = $vars->sitemaplite_only_public_menus;
		$config->only_public_menus = ($only_public_menus === 'Y') ? true : false;

		$config->additional_urls = array();
		$additional_urls = explode("\n", $vars->sitemaplite_additional_urls);
		foreach ($additional_urls as $additional_url)
		{
			$additional_url = trim($additional_url);
			if ($additional_url)
			{
				$config->additional_urls[] = $additional_url;
			}
		}

		// Load document config
		$config->document_count = intval($vars->sitemaplite_document_count);
		if ($config->document_count < 0)
		{
			$config->document_count = 0;
		}
		if ($config->document_count > 48000)
		{
			$config->document_count = 48000;
		}

		$config->document_source_modules = $vars->sitemaplite_document_source_modules;
		if (!$config->document_source_modules)
		{
			$config->document_source_modules = array();
		}
		$config->document_source_modules = array_unique(array_map('intval', $config->document_source_modules));

		$config->document_order = $vars->sitemaplite_document_order;
		if (!in_array($config->document_order, array('recent', 'view', 'vote')))
		{
			$config->document_order = 'recent';
		}

		$config->document_interval = $vars->sitemaplite_document_interval;
		if (!in_array($config->document_interval, array('always', 'hourly', 'daily', 'weekly', 'monthly', 'manual')))
		{
			$config->document_interval = 'daily';
		}

		// Save new config
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('sitemaplite', $config);

		// Try to write new sitemap.xml file
		if ($output->toBool())
		{
			$write_success = $this->writeSitemapXml($config);
			if ($write_success)
			{
				$this->setMessage('success_registed');
			}
			else
			{
				if (class_exists('BaseObject'))
				{
					return new BaseObject(-1, 'msg_sitemaplite_failed_to_write_xml_file');
				}
				else
				{
					return new Object(-1, 'msg_sitemaplite_failed_to_write_xml_file');
				}
			}
		}
		else
		{
			return $output;
		}

		// Redirect back to config page
		if (Context::get('success_return_url'))
		{
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSitemapliteAdminConfig'));
		}
	}

	/**
	 * Write sitemap.xml
	 */
	public function writeSitemapXml($config = null)
	{
		// Use module config if a different config is not given
		if (!$config)
		{
			$config = $this->getConfig();
		}

		// Initialize domains and URLs
		$domains = array();
		$urls = array('rel:');

		// Get list of domains
		$oModuleModel = getModel('module');
		if ($config->sitemap_file_path === 'domains' && defined('RX_BASEDIR') && method_exists($oModuleModel, 'getAllDomains'))
		{
			$domains = array();
			foreach ($oModuleModel->getAllDomains(100)->data as $domain_info)
			{
				$scheme = $domain_info->security === 'always' ? 'https://' : 'http://';
				$port = $domain_info->security === 'always' ? $domain_info->https_port : $domain_info->http_port;
				$baseurl = $scheme . $domain_info->domain . ($port ? sprintf(':%d', $port) : '') . RX_BASEURL;
				$domains[] = Rhymix\Framework\URL::encodeIdna($baseurl);
			}
		}
		else
		{
			$domains[] = rtrim(Context::getDefaultUrl(), '\\/') . '/';
		}

		// Insert URL for each item in menu
		$oMenuAdminModel = getAdminModel('menu');
		foreach ($config->menu_srls as $menu_srl)
		{
			$menu_items = $oMenuAdminModel->getMenuItems($menu_srl);
			foreach ($menu_items->data as $item)
			{
				if (intval($item->group_srls) !== 0 && $config->only_public_menus !== false)
				{
					continue;
				}

				$url = $this->_formatUrl($item->url);
				if ($url !== false)
				{
					$urls[] = $url;
				}
			}
		}

		// Insert URL for documents
		if ($config->document_count && $config->document_source_modules)
		{
			$this->_addDocumentUrls($urls, $config);
		}

		// Register additional URLs
		if ($config->additional_urls)
		{
			foreach ($config->additional_urls as $url)
			{
				$url = $this->_formatUrl($url);
				if ($url !== false)
				{
					$urls[] = $url;
				}
			}
		}

		// Remove duplicate URLs
		$urls = array_unique($urls);

		// Loop domains
		foreach ($domains as $domain)
		{
			// Examine domain info
			$domain_info = parse_url($domain);
			$absprefix = $domain_info['scheme'] . '://' . $domain_info['host'] . (empty($domain_info['port']) ? '' : (':' . $domain_info['port']));

			// Check XML path
			$xml_path = $this->getSitemapXmlPath($config->sitemap_file_path, $domain_info['host']);
			if (!$this->isWritable($xml_path))
			{
				return false;
			}

			// Write XML
			$xml = '<' . '?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
			$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
			foreach ($urls as $url)
			{
				list($url_type, $url_value) = explode(':', $url, 2);
				switch ($url_type)
				{
					case 'url':
						$url = $url_value;
						break;
					case 'pro':
						$url = $domain_info['scheme'] . ':' . $url_value;
						break;
					case 'abs':
						$url = $absprefix . $url_value;
						break;
					case 'rel':
					default:
						$url = $domain . $url_value;
						break;
				}
				if ($this->_isInternalUrl($url, $absprefix))
				{
					$xml .= "\t" . '<url><loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8', true) . '</loc></url>' . PHP_EOL;
				}
			}
			$xml .= '</urlset>' . PHP_EOL;
			FileHandler::writeFile($xml_path, $xml);

			// Ping search engines
			if ($config->ping_search_engines)
			{
				if ($config->sitemap_file_path === 'root' || $config->sitemap_file_path === 'sub')
				{
					$xml_url = $this->getSitemapXmlUrl($config->sitemap_file_path);
				}
				else
				{
					$xml_url = $domain . 'sitemap.xml';
				}
				$this->_pingSearchEngines($xml_url, $config->ping_search_engines);
			}
		}

		return true;
	}

	/**
	 * Format a URL
	 */
	protected function _formatUrl($url)
	{
		// Cache settings
		static $rewrite = null;
		if ($rewrite === null)
		{
			$rewrite = Context::isAllowRewrite();
		}

		// Trim the URL
		$url = trim($url);

		// External URL
		if (preg_match('@^https?://.+@', $url))
		{
			return 'url:' . $url;
		}

		// Protocol-relative URL
		elseif (preg_match('@^//.*@', $url))
		{
			return 'pro:' . $url;
		}

		// Absolute URL
		elseif (preg_match('@^/.*@', $url))
		{
			return 'abs:' . $url;
		}

		// Miscellaneous script URL
		elseif (preg_match('@(?:^#|\.php\?)@', $url))
		{
			return 'rel:' . $url;
		}

		// Regular mid link
		elseif ($url)
		{
			if ($rewrite)
			{
				return 'rel:' . $url;
			}
			else
			{
				return 'rel:' . 'index.php?mid=' . $url;
			}
		}

		// Not found
		return false;
	}

	/**
	 * Check whether a URL is internal
	 */
	protected function _isInternalUrl($url, $domain)
	{
		return strncmp($url, $domain, strlen($domain)) === 0;
	}

	/**
	 * Check whether a URL is allowed (block admin and member module URLs)
	 */
	protected function _isAllowedUrl($url)
	{
		if (preg_match('@\b(?:admin|module=admin|act=(?:disp|proc)(?:member|socialxe)\w+)\b@i', $url))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Add document URLs
	 */
	protected function _addDocumentUrls(&$urls, $config)
	{
		// Get settings
		$rewrite = Context::isAllowRewrite();

		// Determine sort index
		switch ($config->document_order)
		{
			case 'view': $sort_index = 'readed_count'; break;
			case 'vote': $sort_index = 'voted_count'; break;
			case 'recent': default: $sort_index = 'regdate'; break;
		}

		// Get documents
		$args = new stdClass;
		$args->module_srl = $config->document_source_modules;
		$args->list_count = $config->document_count;
		$args->sort_index = $sort_index;
		$args->status = 'PUBLIC';
		$output = executeQuery('sitemaplite.getDocumentList', $args);
		$output->data = $output->data ? (is_array($output->data) ? $output->data : array($output->data)) : null;
		$midmap = array();

		// If documents are found...
		if ($documents = $output->data)
		{
			// Get conversion map (module_srl -> mid)
			$args = new stdClass;
			$args->module_srl = $config->document_source_modules;
			$output = executeQuery('sitemaplite.getModuleList', $args);
			$output->data = $output->data ? (is_array($output->data) ? $output->data : array($output->data)) : null;
			foreach ($output->data as $module)
			{
				$midmap[intval($module->module_srl)] = $module->mid;
			}

			// Add each document to the URL list
			foreach ($documents as $document)
			{
				if (isset($midmap[$document->module_srl]))
				{
					if ($rewrite)
					{
						$urls[] = 'rel:' . $midmap[$document->module_srl] . '/' . $document->document_srl;
					}
					else
					{
						$urls[] = 'rel:' . 'index.php?mid=' . $midmap[$document->module_srl] . '&document_srl=' . $document->document_srl;
					}
				}
				else
				{
					if ($rewrite)
					{
						$urls[] = 'rel:' . $document->document_srl;
					}
					else
					{
						$urls[] = 'rel:' . 'index.php?document_srl=' . $document->document_srl;
					}
				}
			}
		}
	}

	/**
	 * Ping search engines
	 */
	protected function _pingSearchEngines($url, $search_engines = array())
	{
		$pings = array(
			'google' => 'http://www.google.com/webmasters/sitemaps/ping?sitemap=%s',
			'bing' => 'http://www.bing.com/ping?sitemap=%s',
		);

		$config = array('ssl_verify_host' => false);
		if (extension_loaded('curl'))
		{
			$config['adapter'] = 'curl';
		}

		if ($search_engines)
		{
			foreach ($search_engines as $search_engine)
			{
				if (isset($pings[$search_engine]))
				{
					$ping_url = sprintf($pings[$search_engine], urlencode($url));
					FileHandler::getRemoteResource($ping_url, null, 3, 'GET', null, array(), array(), array(), $config);
				}
			}
		}
	}
}
