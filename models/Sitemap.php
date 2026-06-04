<?php

namespace Rhymix\Modules\SitemapLite\Models;

use Rhymix\Modules\SitemapLite\Controllers\Base as BaseController;
use Rhymix\Framework\HTTP;
use Rhymix\Framework\Queue;
use Rhymix\Framework\Storage;
use Rhymix\Framework\URL;
use DocumentModel;
use MenuAdminModel;
use ModuleModel;

class Sitemap
{
	/**
	 * Update wrapper #1
	 *
	 * @param ?object $config
	 * @return void
	 */
	public static function updateSitemap(?object $config = null)
	{
		if (($config->use_async ?? 'N') === 'Y' && config('queue.enabled'))
		{
			Queue::addTask(self::class . '::updateSitemapStatic', new \stdClass());
		}
		else
		{
			self::writeSitemapXml($config);
		}
	}

	/**
	 * Update wrapper #2
	 *
	 * @return void
	 */
	public static function updateSitemapStatic()
	{
		echo "Update triggered for Sitemap.xml\n";
		self::writeSitemapXml();
	}

	/**
	 * Write sitemap.xml
	 *
	 * @param ?object $config
	 * @return bool
	 */
	public static function writeSitemapXml(?object $config = null)
	{
		// Use module config if a different config is not given
		if (!$config)
		{
			$config = BaseController::getConfig();
		}

		// Get list of domains
		$domains = array();
		foreach (ModuleModel::getAllDomains(100)->data as $domain)
		{
			$scheme = $domain->security === 'always' ? 'https://' : 'http://';
			$port = $domain->security === 'always' ? $domain->https_port : $domain->http_port;
			$baseurl = $scheme . $domain->domain . ($port ? sprintf(':%d', $port) : '') .  parse_url(config('url.default'), \PHP_URL_PATH);
			$domain->sitemaplite_prefix = URL::encodeIdna($baseurl);

			if ($config->sitemap_file_path === 'domains' || $domain->is_default_domain === 'Y')
			{
				$domains[] = $domain;
			}
		}

		// Loop domains
		foreach ($domains as $domain)
		{
			$domain_config = $config->domains[$domain->domain_srl] ?? $config;
			$urls = array('rel:');

			// Insert URL for each item in menu
			$oMenuAdminModel = MenuAdminModel::getInstance();
			foreach ($domain_config->menu_srls as $menu_srl)
			{
				$categories_added = [];
				$menu_items = $oMenuAdminModel->getMenuItems($menu_srl);
				foreach ($menu_items->data as $item)
				{
					if (intval($item->group_srls) !== 0 && $config->only_public_menus !== false)
					{
						continue;
					}
					if (empty($item->url) || preg_match('/^#/', $item->url))
					{
						continue;
					}

					$url = self::_formatUrl($item->url);
					if ($url !== false)
					{
						$urls[] = $url;
					}

					if (preg_match('/^\w+$/', $item->url))
					{
						$module_info = ModuleModel::getModuleInfoByMid($item->url);
						if ($module_info && $module_info->module_srl)
						{
							$categories_added[$module_info->module_srl] = true;
							$categories = DocumentModel::getCategoryList($module_info->module_srl);
							if ($categories)
							{
								foreach ($categories as $category)
								{
									$category_url = getNotEncodedUrl(['mid' => $item->url, 'category' => $category->category_srl]);
									if (preg_match('!^https?://.+!', $category_url))
									{
										$category_url = parse_url($category_url, \PHP_URL_PATH);
									}
									$urls[] = 'abs:' . $category_url;
								}
							}
						}
					}
				}
			}

			// Insert URL for documents
			if ($domain_config->document_count && $domain_config->document_source_modules)
			{
				// Get conversion map (module_srl -> mid)
				$midmap = [];
				$output = executeQueryArray('sitemaplite.getModuleList', ['module_srl' => $domain_config->document_source_modules]);
				foreach ($output->data as $module)
				{
					$midmap[intval($module->module_srl)] = $module->mid;
				}

				// Add category URLs for document source modules
				foreach ($domain_config->document_source_modules as $module_srl)
				{
					if (!isset($categories_added[$module_srl]))
					{
						$categories_added[$module_srl] = true;
						$categories = DocumentModel::getCategoryList($module_srl);
						foreach ($categories ?: [] as $category)
						{
							$category_url = getNotEncodedUrl(['mid' => $midmap[$module_srl], 'category' => $category->category_srl]);
							if (preg_match('!^https?://.+!', $category_url))
							{
								$category_url = parse_url($category_url, \PHP_URL_PATH);
							}
							$urls[] = 'abs:' . $category_url;
						}
					}
				}

				// Add documents
				$domain_config->midmap = $midmap;
				self::_addDocumentUrls($urls, $domain_config);
			}

			// Register additional URLs
			if ($domain_config->additional_urls)
			{
				foreach ($domain_config->additional_urls as $url)
				{
					$url = self::_formatUrl($url);
					if ($url !== false)
					{
						$urls[] = $url;
					}
				}
			}

			// Remove duplicate URLs
			$urls = array_unique($urls);

			// Examine domain info
			$domain_info = parse_url($domain->sitemaplite_prefix);
			$absprefix = $domain_info['scheme'] . '://' . $domain_info['host'] . (empty($domain_info['port']) ? '' : (':' . $domain_info['port']));

			// Check XML path
			$xml_path = BaseController::getSitemapXmlPath($config->sitemap_file_path, $domain_info['host']);
			if (!BaseController::isWritable($xml_path))
			{
				return false;
			}

			// Write XML
			$xml = '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' . PHP_EOL;
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
						$url = $domain->sitemaplite_prefix . $url_value;
						break;
				}
				if (self::_isInternalUrl($url, $absprefix))
				{
					$xml .= '<url><loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8', true) . '</loc></url>' . PHP_EOL;
				}
			}
			$xml .= '</urlset>' . PHP_EOL;
			Storage::write($xml_path, $xml);

			// Ping search engines
			if ($config->ping_search_engines)
			{
				if ($config->sitemap_file_path === 'root' || $config->sitemap_file_path === 'sub')
				{
					$xml_url = BaseController::getSitemapXmlUrl($config->sitemap_file_path);
				}
				else
				{
					$xml_url = $domain->sitemaplite_prefix . 'sitemap.xml';
				}
				self::_pingSearchEngines($xml_url, $config->ping_search_engines);
			}
		}

		return true;
	}

	/**
	 * Format a URL
	 *
	 * @param string $url
	 * @return string|false
	 */
	protected static function _formatUrl(string $url)
	{
		// Cache settings
		static $rewrite = null;
		if ($rewrite === null)
		{
			$rewrite = config('url.rewrite');
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
	 *
	 * @param string $url
	 * @param string $domain
	 * @return bool
	 */
	protected static function _isInternalUrl(string $url, string $domain): bool
	{
		return strncmp($url, $domain, strlen($domain)) === 0;
	}

	/**
	 * Check whether a URL is allowed (block admin and member module URLs)
	 *
	 * @param string $url
	 * @return bool
	 */
	protected static function _isAllowedUrl(string $url): bool
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
	 *
	 * @param array &$urls
	 * @param object $config
	 * @return void
	 */
	protected static function _addDocumentUrls(array &$urls, object $config)
	{
		// Get settings
		$rewrite = config('url.rewrite');

		// Determine sort index
		switch ($config->document_order)
		{
			case 'view': $sort_index = 'readed_count'; break;
			case 'vote': $sort_index = 'voted_count'; break;
			case 'recent': default: $sort_index = 'regdate'; break;
		}

		// Get documents
		$args = new \stdClass;
		$args->module_srl = $config->document_source_modules ?? [];
		$args->list_count = $config->document_count ?? 100;
		$args->sort_index = $sort_index;
		$args->status = 'PUBLIC';
		$output = executeQueryArray('sitemaplite.getDocumentList', $args);

		// Extract mid map from config
		$midmap = $config->midmap ?? [];

		// If documents are found...
		if ($documents = $output->data)
		{
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
	 *
	 * @param string $url
	 * @param array $search_engines
	 * @return void
	 */
	protected static function _pingSearchEngines(string $url, array $search_engines = []): void
	{
		// These APIs don't work anymore, so the following code is only kept for future reference.
		/*
		$pings = [
			'google' => 'http://www.google.com/webmasters/sitemaps/ping?sitemap=%s',
			'bing' => 'http://www.bing.com/ping?sitemap=%s',
		];

		foreach ($search_engines as $search_engine)
		{
			if (isset($pings[$search_engine]))
			{
				$ping_url = sprintf($pings[$search_engine], urlencode($url));
				$request = HTTP::request($ping_url, 'GET', null, [], [], ['timeout' => 10]);
			}
		}
		*/
	}
}
