<?php
App::import('Lib', 'AssetCompress.AssetConfig');
/**
 * AssetCompress Helper.
 *
 * Handle inclusion assets using the AssetCompress features for concatenating and
 * compressing asset files.
 *
 * You add files to be compressed using `script` and `css`.  All files added to a key name
 * will be processed and joined before being served.  When in debug = 2, no files are cached.
 *
 * If debug = 0, the processed file will be cached to disk.  You can also use the routes
 * and config file to create static 'built' files. These built files must have unique names, or
 * as they are made they will overwrite each other.  You can clear built files
 * with the shell provided in the plugin.
 *
 * @package asset_compress.helpers
 */
class AssetCompressHelper extends AppHelper {

	public $helpers = array('Html');

	protected $_Config;

/**
 * Options for the helper
 *
 * - `autoIncludePath` - Path inside of webroot/js that contains autoloaded view js.
 * - `jsCompressUrl` - Url to use for getting compressed js files.
 * - `cssCompressUrl` - Url to use for getting compressed css files.
 *
 * @var array
 */
	public $options = array(
		'autoIncludePath' => 'views',
		'buildUrl' => array(
			'plugin' => 'asset_compress',
			'controller' => 'assets',
			'action' => 'get'
		),
	);

/**
 * A list of build files added during the helper runtime.
 *
 * @var array
 */
	protected $_runtime = array(
		'js' => array(),
		'css' => array()
	);

/**
 * Disable autoInclusion of view js files.
 *
 * @var string
 */
	public $autoInclude = true;

/**
 * Constructor - finds and parses the ini file the plugin uses.
 *
 * @return void
 */
	public function __construct($options = array()) {
		$this->_Config = AssetConfig::buildFromIniFile();
	}

/**
 * Modify the runtime configuration of the helper.
 * Used as a get/set for the ini file values.
 * 
 * @param string $name The dot separated config value to change ie. Css.searchPaths
 * @param mixed $value The value to set the config to.
 * @return mixed Either the value being read or null.  Null also is returned when reading things that don't exist.
 */
	public function config($config = null) {
		if ($config === null) {
			return $this->_Config;
		}
		return $this->_Config;
	}

/**
 * Set options, merge with existing options.
 *
 * @return void
 */
	public function options($options) {
		$this->options = Set::merge($this->options, $options);
	}

/**
 * AfterRender callback.
 *
 * Adds automatic view js files if enabled.
 * Adds css/js files that have been added to the concatenation lists.
 *
 * Auto file inclusion adopted from Graham Weldon's helper
 * http://bakery.cakephp.org/articles/view/automatic-javascript-includer-helper
 *
 * @return void
 */
	public function afterRender() {
		$this->_includeViewJs();
	}

/**
 * Includes the auto view js files if enabled.
 *
 * @return void
 */
	protected function _includeViewJs() {
		if (!$this->autoInclude) {
			return;
		}
		$files = array(
			$this->params['controller'] . '.js',
			$this->params['controller'] . DS . $this->params['action'] . '.js'
		);

		foreach ($files as $file) {
			$includeFile = JS . $this->options['autoIncludePath'] . DS . $file;
			if (file_exists($includeFile)) {
				$this->Html->script($this->options['autoIncludePath'] . '/' . $file, array('inline' => false));
			}
		}
	}

/**
 * Used to include runtime defined build files.  To include build files defined in your
 * ini file use script() or css().
 *
 * Calling this method will clear the asset caches.
 *
 * @return string Empty string or string containing asset link tags.
 */
	public function includeAssets($raw = null) {
		if ($raw !== null) {
			$css = $this->includeCss(array('raw' => true));
			$js = $this->includeJs(array('raw' => true));
		} else {
			$css = $this->includeCss();
			$js = $this->includeJs();
		}
		return $css . "\n" . $js;
	}

/**
 * Include the CSS files that were defined at runtime with
 * the helper.
 *
 * ### Usage
 *
 * #### Include one destination file:
 * `$assetCompress->includeCss('default');`
 *
 * #### Include multiple files:
 * `$assetCompress->includeCss('default', 'reset', 'themed');`
 *
 * #### Include all the files:
 * `$assetCompress->includeCss();`
 *
 * @param string $name Name of the destination file to include.  You can pass any number of strings in to
 *    include multiple files.  Leave null to include all files.
 * @return string A string containing the link tags
 */
	public function includeCss() {
		$args = func_get_args();
		return $this->_genericInclude($args, 'css');
	}

/**
 * Include the Javascript files that were defined at runtime with
 * the helper.
 *
 * ### Usage
 *
 * #### Include one runtime destination file:
 * `$assetCompress->includeJs('default');`
 *
 * #### Include multiple runtime files:
 * `$assetCompress->includeJs('default', 'reset', 'themed');`
 *
 * #### Include all the runtime files:
 * `$assetCompress->includeJs();`
 *
 * @param string $name Name of the destination file to include.  You can pass any number of strings in to
 *    include multiple files.  Leave null to include all files.
 * @return string A string containing the script tags.
 */
	public function includeJs() {
		$args = func_get_args();
		return $this->_genericInclude($args, 'js');
	}

/**
 * The generic version of includeCss and includeJs
 *
 * @param array $files Array of destination/build files to include
 * @param string $ext The extension builds must have.
 * @return string A string containing asset tags.
 */
	protected function _genericInclude($files, $ext) {
		$numArgs = count($files) - 1;
		$options = array();
		if (isset($files[$numArgs]) && is_array($files[$numArgs])) {
			$options = array_pop($files);
			$numArgs -= 1;
		}
		if ($numArgs <= 0) {
			$files = array_keys($this->_runtime[$ext]);
		}
		foreach ($files as &$file) {
			$file = $this->_addExt($file, '.' . $ext);
		}
		$output = array();
		foreach ($files as $build) {
			if (empty($this->_runtime[$ext][$build])) {
				continue;
			}
			if ($ext == 'js') {
				$output[] = $this->script($build, $options);
			} elseif ($ext == 'css') {
				$output[] = $this->css($build, $options);
			}
			unset($this->_runtime[$ext][$build]);
		}
		return implode("\n", $output);
	}

/**
 * Adds an extension if the file doesn't already end with it.
 *
 * @param string $file Filename
 * @param string $ext Extension with .
 * @return string
 */
	protected function _addExt($file, $ext) {
		if (substr($file, strlen($ext) * -1) !== $ext) {
			$file .= $ext;
		}
		return $file;
	}

/**
 * Create a CSS file. Will generate script tags
 * for either the dynamic build controller, or the generated file if it exists.
 *
 * To create build files without configuration use addCss()
 *
 * Options:
 *
 * - All options supported by HtmlHelper::css() are supported.
 * - `raw` - Set to true to get one link element for each file in the build.
 *
 * @param string $file A build target to include.
 * @param array $options An array of options for the stylesheet tag.
 * @return A stylesheet tag
 */
	public function css($file, $options = array()) {
		$file = $this->_addExt($file, '.css');
		$buildFiles = $this->_Config->files($file);
		if (!$buildFiles) {
			throw new RuntimeException('Cannot create a stylesheet tag for a build that does not exist.');
		}
		if (!empty($options['raw'])) {
			$output = '';
			unset($options['raw']);
			foreach ($buildFiles as $part) {
				$output .= $this->Html->css($part, null, $options);
			}
			return $output;
		}
		if ($this->useDynamicBuild($file)) {
			$route = $this->_getRoute($file);
		} else {
			$route = $this->_locateBuild($file);
		}
		$baseUrl = $this->_Config->get('css.baseUrl');
		if ($baseUrl) {
			$route = $baseUrl . $route;
		}
		return $this->Html->css($route, null, $options);
	}

/**
 * Create a script tag for a script asset. Will generate script tags
 * for either the dynamic build controller, or the generated file if it exists.
 *
 * To create build files without configuration use addScript()
 *
 * Options:
 *
 * - All options supported by HtmlHelper::css() are supported.
 * - `raw` - Set to true to get one script element for each file in the build.
 *
 * @param string $file A build target to include.
 * @param array $options An array of options for the script tag.
 * @return A script tag
 */
	public function script($file, $options = array()) {
		$file = $this->_addExt($file, '.js');
		$buildFiles = $this->_Config->files($file);
		if (!$buildFiles) {
			throw new RuntimeException('Cannot create a script tag for a build that does not exist.');
		}
		if (!empty($options['raw'])) {
			$output = '';
			unset($options['raw']);
			foreach ($buildFiles as $part) {
				$output .= $this->Html->script($part, $options);
			}
			return $output;
		}

		if ($this->useDynamicBuild($file)) {
			$route = $this->_getRoute($file);
		} else {
			$route = $this->_locateBuild($file);
		}
		$baseUrl = $this->_Config->get('js.baseUrl');
		if ($baseUrl) {
			$route = $baseUrl . $route;
		}
		return $this->Html->script($route, $options);
	}

/**
 * Check if caching is on. If caching is off, then dynamic builds
 * (pointing at the controller) will be generated.
 *
 * If caching is on for this extension, the helper will try to locate build
 * files using the cachePath. If no cache file exists a dynamic build will be done.
 */
	public function useDynamicBuild($file) {
		if (!$this->_Config->cachingOn($file)) {
			return true;
		}
		if ($this->_locateBuild($file)) {
			return false;
		}
		return true;
	}

/**
 * Locates a build file and returns the url path to it.
 *
 * @param string $build Filename of the build to locate.
 * @return string The url path to the built asset.
 */
	protected function _locateBuild($build) {
		$ext = $this->_Config->getExt($build);
		$path = $this->_Config->cachePath($ext);
		if (!$path) {
			return false;
		}
		if (file_exists($path . $build)) {
			return str_replace(WWW_ROOT, $this->base, $path . $build);
		}
		$name = substr($build, 0, strlen($build) - (strlen($ext) + 1));
		$pattern = $path . $name . '.v[0-9]*.' . $ext;
		$matching = glob($pattern);
		if (empty($matching)) {
			return false;
		}
		return $matching[0];
	}

/**
 * Get the dynamic build path for an asset.
 */
	protected function _getRoute($file) {
		$url = $this->options['buildUrl'];
	
		//escape out of prefixes.
		$prefixes = Router::prefixes();
		foreach ($prefixes as $prefix) {
			if (!array_key_exists($prefix, $url)) {
				$url[$prefix] = false;
			}
		}
		$params = array(
			$file,
			'base' => false
		);
		$ext = $this->_Config->getExt($file);
		if (isset($this->_runtime[$ext][$file])) {
			if (strpos($file, ':hash') === 0) {
				$buildFiles = $this->_Config->files($file);
				$params[0] = md5(implode('_', $buildFiles)) . '.' . $ext;
			}
			$components = $this->_Config->files($file);
			$params['?'] = array('file' => $components);
		}

		$url = Router::url(array_merge($url, $params));
		return $url;
	}

/**
 * Add a script file to a build target, this lets you define build
 * targets without configuring them in the ini file.
 *
 * @param mixed $files Either a string or an array of files to append into the build target.
 * @param string $target The name of the build target, defaults to a hash of the filenames
 * @return void
 */
	public function addScript($files, $target = ':hash-default.js') {
		$target = $this->_addExt($target, '.js');
		$this->_runtime['js'][$target] = true;
		$defined = $this->_Config->files($target);
		$this->_Config->files($target, array_merge($defined, (array)$files));
	}

/**
 * Add a stylesheet file to a build target, this lets you define build
 * targets without configuring them in the ini file.
 *
 * @param mixed $files Either a string or an array of files to append into the build target.
 * @param string $target The name of the build target, defaults to a hash of the filenames
 * @return void
 */
	public function addCss($files, $target = ':hash-default.css') {
		$target = $this->_addExt($target, '.css');
		$this->_runtime['css'][$target] = true;
		$defined = $this->_Config->files($target);
		$this->_Config->files($target, array_merge($defined, (array)$files));
	}
}
