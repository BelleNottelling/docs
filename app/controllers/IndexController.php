<?php

namespace app\controllers;

use app\utils\Text;
use app\utils\Translator;
use Exception;
use Flight;
use flight\Engine;

class IndexController {

	/**
	 * @var string
	 */
	protected const CONTENT_DIR = __DIR__ . '/../../content/';

	/**
	 * @var string
	 */
	protected string $language = 'en';

	/**
	 * Translator class
	 *
	 * @var Translator
	 */
	protected Translator $Translator;

	/**
	 * @var Engine
	 */
	protected Engine $app;

	public function __construct(Engine $app) {
		$this->app = $app;
		$this->language = Translator::getLanguageFromRequest();
		$this->Translator = new Translator($this->language);
	}

	protected function renderPage(string $latte_file, array $params = []) {
		$request = $this->app->request();

		$uri = $request->url;
		if(strpos($uri, '?') !== false) {
			$uri = substr($uri, 0, strpos($uri, '?'));
		}
		// Here we can set variables that will be available on any page
		$params['url'] = $request->getScheme() . '://' . $request->getHeader('Host') . $uri;
		$this->app->latte()->render($latte_file, $params);
	}

	protected function compileSinglePage(string $section) {
		$app = $this->app;
		$markdown_html = $app->cache()->refreshIfExpired($section.'_html_'.$this->language, function() use ($app, $section)  {
			return $app->parsedown()->text($this->Translator->getMarkdownLanguageFile($section . '.md'));
		}, 86400); // 1 day
		$this->renderPage('single_page.latte', [
			'page_title' => $section,
			'markdown' => $markdown_html,
		]);
	}

	protected function compileScrollspyPage(string $section, string $sub_section) {
		$app = $this->app;
		$section_file_path = str_replace('_', '-', $section);
		$sub_section_underscored = str_replace('-', '_', $sub_section);
		$heading_data = $app->cache()->retrieve($sub_section_underscored.'_heading_data_'.$this->language);
		$markdown_html = $app->cache()->refreshIfExpired($sub_section_underscored.'_html_'.$this->language, function() use ($app, $section_file_path, $sub_section_underscored, &$heading_data)  {
			$parsed_text = $app->parsedown()->text($this->Translator->getMarkdownLanguageFile('/'.$section_file_path.'/' . $sub_section_underscored . '.md'));

			$heading_data = [];
			$parsed_text = Text::generateAndConvertHeaderListFromHtml($parsed_text, $heading_data, 'h2');
			$app->cache()->store($sub_section_underscored.'_heading_data_'.$this->language, $heading_data, 86400); // 1 day

			return $parsed_text;
		}, 86400); // 1 day

		// pull the title out of the first h1 tag
		$page_title = '';
		preg_match('/\<h1\>(.*)\<\/h1\>/i', $markdown_html, $matches);
		if (isset($matches[1])) {
			$page_title = $matches[1];
		}

		$Translator = new Translator($this->language);

		$this->renderPage('single_page_scrollspy.latte', [
			'custom_page_title' => ($page_title ? $page_title.' - ' : '').$Translator->translate($section),
			'markdown' => $markdown_html,
			'heading_data' => $heading_data,
		]);
	}

	public function licenseGet() {
		$this->compileSinglePage('license');
	}

	public function aboutGet() {
		$this->compileSinglePage('about');
	}

	public function examplesGet() {
		$this->compileSinglePage('examples');
	}

	public function installGet() {
		$this->compileSinglePage('install');
	}

	public function learnGet() {
		$this->compileSinglePage('learn');
	}

	public function learnSectionsGet(string $section_name) {
		$this->compileScrollspyPage('learn', $section_name);
		return;
		$app = $this->app;
		$section_name_for_file = str_replace('-', '', $section_name);
		$heading_data = $app->cache()->retrieve($section_name_for_file.'_heading_data_'.$this->language);
		$markdown_html = $app->cache()->refreshIfExpired($section_name_for_file.'_html_'.$this->language, function() use ($app, $section_name_for_file, &$heading_data)  {
			$parsed_text = $app->parsedown()->text(file_get_contents(self::CONTENT_DIR . $this->language . '/learn/' . $section_name_for_file . '.md'));

			$heading_data = [];
			$parsed_text = Text::generateAndConvertHeaderListFromHtml($parsed_text, $heading_data, 'h2');
			$app->cache()->store($section_name_for_file.'_heading_data_'.$this->language, $heading_data, 86400); // 1 day

			return $parsed_text;
		}, 86400); // 1 day

		// pull the title out of the first h1 tag
		$page_title = '';
		preg_match('/\<h1\>(.*)\<\/h1\>/i', $markdown_html, $matches);
		if (isset($matches[1])) {
			$page_title = $matches[1];
		}

		$Translator = new Translator($this->language);

		$this->renderPage('single_page_scrollspy.latte', [
			'custom_page_title' => $page_title.' - '.$Translator->translate('learn'),
			'markdown' => $markdown_html,
			'heading_data' => $heading_data,
		]);
	}

	public function awesomePluginsGet() {
		$this->compileScrollspyPage('awesome_plugins', 'index');
		return;
		$app = $this->app;
		$heading_data = $app->cache()->retrieve('plugins_heading_data_'.$this->language);
		$markdown_html = $app->cache()->refreshIfExpired('plugins_html_'.$this->language, function() use ($app, &$heading_data)  {
			$parsed_text = $app->parsedown()->text(file_get_contents(self::CONTENT_DIR . $this->language . '/awesome-plugins/index.md'));
			$heading_data = [];
			$parsed_text = Text::generateAndConvertHeaderListFromHtml($parsed_text, $heading_data, 'h2');
			$app->cache()->store('plugins_heading_data_'.$this->language, $heading_data, 86400); // 1 day
			return $parsed_text;
		}, 86400); // 1 day

		$this->renderPage('single_page_scrollspy.latte', [
			'page_title' => 'awesome_plugins',
			'markdown' => $markdown_html,
			'heading_data' => $heading_data,
		]);
	}

	public function pluginGet(string $plugin_name) {
		$this->compileScrollspyPage('awesome_plugins', $plugin_name);
		return;
		$app = $this->app;
		$plugin_name_underscored = str_replace('-', '_', $plugin_name);
		$heading_data = $app->cache()->retrieve($plugin_name_underscored.'_heading_data_'.$this->language);
		$markdown_html = $app->cache()->refreshIfExpired($plugin_name_underscored.'_html_'.$this->language, function() use ($app, $plugin_name_underscored, &$heading_data)  {
			$parsed_text = $app->parsedown()->text(file_get_contents(self::CONTENT_DIR . $this->language . '/awesome-plugins/' . $plugin_name_underscored . '.md'));

			$heading_data = [];
			$parsed_text = Text::generateAndConvertHeaderListFromHtml($parsed_text, $heading_data, 'h2');
			$app->cache()->store($plugin_name_underscored.'_heading_data_'.$this->language, $heading_data, 86400); // 1 day

			return $parsed_text;
		}, 86400); // 1 day

		// pull the title out of the first h1 tag
		$plugin_title = '';
		preg_match('/\<h1\>(.*)\<\/h1\>/i', $markdown_html, $matches);
		if (isset($matches[1])) {
			$plugin_title = $matches[1];
		}

		$Translator = new Translator($this->language);

		$this->renderPage('single_page_scrollspy.latte', [
			'custom_page_title' => $plugin_title.' - '.$Translator->translate('awesome_plugins'),
			'markdown' => $markdown_html,
			'heading_data' => $heading_data,
		]);
	}

	public function updateStuffPost() {
		$secret = $this->app->get('config')['github_webhook_secret'];
		$request = $this->app->request();
		$signature_header = $request->getVar('HTTP_X_HUB_SIGNATURE');
		$signature_parts = explode('=', $signature_header);
		
        if (count($signature_parts) != 2) {
            throw new Exception('signature has invalid format');
        }
        $known_signature = hash_hmac('sha1', $request->getBody(), $secret);

        if (! hash_equals($known_signature, $signature_parts[1])) {
            throw new Exception('Could not verify request signature ' . $signature_parts[1]);
        }

		// it was successful. Do the stuff
		exec('cd /var/www/flightphp-docs/ && git pull && /usr/bin/php82 /usr/local/bin/composer install --no-progress -o --no-dev && rm -rf app/cache/*');
	}
}