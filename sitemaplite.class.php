<?php

/**
 * @file sitemaplite.class.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license GPLv2 or Later <https://www.gnu.org/licenses/gpl-2.0.html>
 * @brief Sitemap Lite Main Class
 */
class SitemapLite extends ModuleObject
{
	/**
	 * Get the configuration of the current module.
	 */
	public function getConfig()
	{
		$config = getModel('module')->getModuleConfig('sitemaplite');
		if (!$config)
		{
			$config = new stdClass;
		}
		
		return $config;
	}
	
	/**
	 * Get the sitemap.xml server-side path.
	 */
	public function getSitemapXmlPath($type = null, $domain = null)
	{
		if (!$type)
		{
			$type = isset($this->getConfig()->sitemap_file_path) ? $this->getConfig()->sitemap_file_path : 'root';
		}
		
		if ($type === 'root')
		{
			return str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '\\/')) . '/sitemap.xml';
		}
		elseif ($type === 'sub')
		{
			return str_replace('\\', '/', rtrim(_XE_PATH_, '\\/')) . '/sitemap.xml';
		}
		elseif ($type === 'files')
		{
			return str_replace('\\', '/', rtrim(_XE_PATH_, '\\/')) . '/files/sitemaplite/sitemap.xml';
		}
		elseif ($type === 'domains')
		{
			$domain = $domain ?: parse_url(Context::getDefaultUrl(), PHP_URL_HOST);
			return str_replace('\\', '/', rtrim(_XE_PATH_, '\\/')) . '/files/sitemaplite/' . $domain . '/sitemap.xml';
		}
	}
	
	/**
	 * Get the sitemap.xml file URL.
	 */
	public function getSitemapXmlUrl($type = null, $domain = null)
	{
		if (!$type)
		{
			$type = isset($this->getConfig()->sitemap_file_path) ? $this->getConfig()->sitemap_file_path : 'root';
		}
		
		if ($type === 'root')
		{
			$dui = parse_url(Context::getDefaultUrl());
			return $dui['scheme'] . '://' . $dui['host'] . ($dui['port'] ? (':' . $dui['port']) : '') . '/sitemap.xml';
		}
		elseif ($type === 'sub')
		{
			return rtrim(Context::getDefaultUrl(), '\\/') . '/sitemap.xml';
		}
		elseif ($type === 'files')
		{
			return rtrim(Context::getDefaultUrl(), '\\/') . '/files/sitemaplite/sitemap.xml';
		}
		elseif ($type === 'domains')
		{
			$domain = $domain ?: parse_url(Context::getDefaultUrl(), PHP_URL_HOST);
			return rtrim(Context::getDefaultUrl(), '\\/') . '/files/sitemaplite/' . $domain . '/sitemap.xml';
		}
	}
	
	/**
	 * Check if a file is writable.
	 */
	public function isWritable($filename)
	{
		if (@file_exists($filename) && @is_writable($filename))
		{
			return true;
		}
		elseif (!@file_exists($filename) && @is_writable(dirname($filename)))
		{
			return true;
		}
		elseif (!@file_exists($filename) && !@file_exists(dirname($filename)) && @mkdir(dirname($filename), 0755, true))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Check triggers.
	 */
	public function checkTriggers()
	{
		$oModuleModel = getModel('module');
		if ($oModuleModel->getTrigger('moduleObject.proc', 'sitemaplite', 'model', 'triggerUpdateSitemapXML', 'after'))
		{
			return true;
		}
		return false;
	}
	
	/**
	 * Register triggers.
	 */
	public function registerTriggers()
	{
		if (!$this->checkTriggers())
		{
			$oModuleController = getController('module');
			$oModuleController->insertTrigger('moduleObject.proc', 'sitemaplite', 'model', 'triggerUpdateSitemapXML', 'after');
			return true;
		}
		return false;
	}
	
	public function moduleInstall()
	{
		$this->registerTriggers();
		return class_exists('BaseObject') ? new BaseObject() : new Object();
	}
	
	public function checkUpdate()
	{
		return !$this->checkTriggers();
	}
	
	public function moduleUpdate()
	{
		$this->registerTriggers();
		return class_exists('BaseObject') ? new BaseObject(0, 'success_updated') : new Object(0, 'success_updated');
	}
	
	public function recompileCache()
	{
		getAdminController('sitemaplite')->writeSitemapXml();
	}
}
