<?php

/**
 * @file sitemaplite.admin.view.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license GPLv2 or Later <https://www.gnu.org/licenses/gpl-2.0.html>
 * @brief Sitemap Lite Admin View
 */
class SitemapLiteAdminView extends SitemapLite
{
	/**
	 * Display admin config page
	 */
	public function dispSitemapliteAdminConfig()
	{
		// Get module config.
		$config = $this->getConfig();

		// Automatically select the index menu if running this module for the first time.
		$index_menu_srl = $this->_getIndexMenuSrl();
		if (!isset($config->menu_srls) || !is_array($config->menu_srls))
		{
			$config->menu_srls = array($index_menu_srl);
		}

		// Automatically select the sitemap file path.
		if (!isset($config->sitemap_file_path))
		{
			$config->sitemap_file_path = 'root';
		}

		// Initialize the search engine list.
		if (!isset($config->ping_search_engines))
		{
			$config->ping_search_engines = array();
		}

		// Initialize the document source module list.
		if (!isset($config->document_source_modules))
		{
			$config->document_source_modules = array();
		}

		// Initialize the additional URL list.
		if (!isset($config->additional_urls))
		{
			$config->additional_urls = array();
		}

		Context::set('sitemaplite_config', $config);
		Context::set('sitemaplite_url_root', $this->getSitemapXmlUrl('root'));
		Context::set('sitemaplite_path_root', $this->getSitemapXmlPath('root'));
		Context::set('sitemaplite_path_root_writable', $this->isWritable($this->getSitemapXmlPath('root')));
		Context::set('sitemaplite_url_sub', $this->getSitemapXmlUrl('sub'));
		Context::set('sitemaplite_path_sub', $this->getSitemapXmlPath('sub'));
		Context::set('sitemaplite_path_sub_writable', $this->isWritable($this->getSitemapXmlPath('sub')));
		Context::set('sitemaplite_url_files', $this->getSitemapXmlUrl('files'));
		Context::set('sitemaplite_path_files', $this->getSitemapXmlPath('files'));
		Context::set('sitemaplite_path_files_writable', $this->isWritable($this->getSitemapXmlPath('files')));
		Context::set('sitemaplite_url_domains', $this->getSitemapXmlUrl('domains'));
		Context::set('sitemaplite_path_domains', $this->getSitemapXmlPath('domains'));
		Context::set('sitemaplite_path_domains_writable', $this->isWritable($this->getSitemapXmlPath('domains')));
		Context::set('sitemaplite_index_menu_srl', $index_menu_srl);
		Context::set('sitemaplite_module_list', $this->_getModuleList());
		Context::set('sitemaplite_menus', getAdminModel('menu')->getMenus());

		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile('config');
	}

	/**
	 * Get menu_srl of index module
	 */
	protected function _getIndexMenuSrl()
	{
		$start_module = getModel('module')->getSiteInfo(0);
		$output = executeQuery('menu.getMenuItemByUrl', (object)array(
			'url' => $start_module->mid,
			'site_srl' => 0,
		));
		if (!$output->toBool())
		{
			return false;
		}
		else
		{
			return $output->data->menu_srl;
		}
	}

	/**
	 * Get the list of modules to extract documents from
	 */
	protected function _getModuleList()
	{
		$args = new stdClass;
		$args->module = array('board', 'bodex', 'beluxe');
		$output = executeQueryArray('sitemaplite.getModuleList', $args);
		if ($output->data)
		{
			$result = array();
			foreach ($output->data as $module)
			{
				$result[intval($module->module_srl)] = $module->browser_title;
			}
			return $result;
		}
		else
		{
			return array();
		}
	}
}
