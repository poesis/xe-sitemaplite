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
		$config = $this->getConfig();
		$vars = Context::getRequestVars();
		
		$menu_srls = $vars->sitemaplite_menu_srls;
		$config->menu_srls = is_array($menu_srls) ? $menu_srls : array();
		
		$file_path = $vars->sitemaplite_file_path;
		$config->sitemap_file_path = ($file_path === 'root') ? 'root' : 'sub';
		
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('sitemaplite', $config);
		
		if ($output->toBool())
		{
			$write_success = $this->writeSitemapXml();
			if ($write_success)
			{
				$this->setMessage('success_registed');
			}
			else
			{
				return new Object(-1, 'msg_sitemaplite_failed_to_write_xml_file');
			}
		}
		else
		{
			return $output;
		}
		
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
		
		// Check XML path
		$xml_path = $this->getSitemapXmlPath($config->sitemap_file_path);
		if (!$this->isWritable($xml_path))
		{
			return false;
		}
		
		// Insert default URL
		$urls = array(rtrim(Context::getDefaultUrl(), '\\/') . '/');
		
		// Insert URL for each item in menu
		$oMenuAdminModel = getAdminModel('menu');
		$dui = parse_url(Context::getDefaultUrl());
		$baseurl = rtrim(Context::getDefaultUrl(), '\\/') . '/';
		$rewrite = Context::isAllowRewrite();
		foreach ($config->menu_srls as $menu_srl)
		{
			$menu_items = $oMenuAdminModel->getMenuItems($menu_srl);
			foreach ($menu_items->data as $item)
			{
				$url = null;
				$item->url = trim($item->url);
				
				if (preg_match('@^(https?:)?//.+@', $item->url))
				{
					if ($this->_isInternalUrl($item->url))
					{
						$url = $item->url;
					}
				}
				elseif (preg_match('@^/.*@', $item->url))
				{
					$url = $dui['scheme'] . '://' . $dui['host'] . ($dui['port'] ? (':' . $dui['port']) : '') . $item->url;
				}
				elseif (preg_match('@(?:^#|\.php\?)@', $item->url))
				{
					$url = $baseurl . $item->url;
				}
				elseif ($item->url)
				{
					if ($rewrite)
					{
						$url = $baseurl . $item->url;
					}
					else
					{
						$url = $baseurl . 'index.php?mid=' . $item->url;
					}
				}
				
				if ($url)
				{
					$urls[] = $url;
				}
			}
		}
		
		// Remove duplicate URLs
		$urls = array_unique($urls);
		
		// Write XML
		$xml = '<' . '?xml version="1.0"?>' . PHP_EOL . '<urlset>' . PHP_EOL;
		foreach ($urls as $url)
		{
			$xml .= "\t" . $this->_writeUrl($url) . PHP_EOL;
		}
		$xml .= '</urlset>' . PHP_EOL;
		FileHandler::writeFile($xml_path, $xml);
		return true;
	}
	
	/**
	 * Write a single URL
	 */
	protected function _writeUrl($url)
	{
		return '<url><loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8', true) . '</loc></url>';
	}
	
	/**
	 * Check whether a URL is internal
	 */
	protected function _isInternalUrl($url)
	{
		static $regexp = null;
		if ($regexp === null)
		{
			$dui = parse_url(Context::getDefaultUrl());
			$regexp = '@^(https?:)?//' . preg_quote($dui['host'], '@') . '(:[0-9]+)?(/.*)?@';
		}
		return preg_match($regexp, $url) ? true : false;
	}
}
