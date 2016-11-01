<?php

/* It's require for CURL download stream */
set_time_limit(0);

/* Default CURL timeout is 300 secs */
define('CREXJUPDATE_CURL_TIMEOUT', 300);

if (!class_exists('CrexJoomlaObject'))
{
	/**
	 * Crex Joomla! Object class
	 *
	 * @package  Object
	 *
	 * @since    1.0.0
	 */
	class CrexJoomlaObject
	{
		/**
		 * @var   array
		 * @since 1.0.0
		 */
		private $localVersion;

		const MANIFEST_DIR = 'administrator/manifests/files/joomla.xml';

		/**
		 * CrexJoomlaObject constructor.
		 *
		 * @param   string  $name  Joomla! sitename
		 */
		public function __construct($name)
		{
			// Require Joomla!
			define('_JEXEC', 1);
			define('JPATH_BASE', './' . $name);
			require_once (JPATH_BASE . '/includes/defines.php');
			require_once (JPATH_BASE . '/includes/framework.php');

			/* Create the Application */
			$app = JFactory::getApplication('site');

			JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_installer/models');

			$this->log('Checking site: ' . $name);
			$this->log('Joomla! path: ' . JPATH_ROOT);

			$this->localVersion = $this->getManifestVersion($name);
			$installerModel = $this->getDatabaseModel();

			$this->log('schemaVersion: ' . $installerModel->getSchemaVersion());
			$this->log('updateVersion: ' . $installerModel->getUpdateVersion());
			$this->log('Local platform: ' . $this->getLocalPlatform());
			$this->log('Local version: ' . $this->getLocalVersion());

		}

		/**
		 * Get Installer Database model
		 *
		 * @return bool|JModelLegacy
		 *
		 * @since 1.0.0
		 */
		public function getDatabaseModel()
		{
			return JModelLegacy::getInstance('Database', 'InstallerModel');
		}

		/**
		 * Get schema version
		 *
		 * @return  string
		 *
		 * @since 1.0.0
		 */
		public function getSchemaVersion()
		{
			$model = $this->getDatabaseModel();

			return $model->getSchemaVersion();
		}

		/**
		 * Get version version
		 *
		 * @return  string
		 *
		 * @since 1.0.0
		 */
		public function getUpdateVersion()
		{
			$model = $this->getDatabaseModel();

			return $model->getUpdateVersion();
		}

		/**
		 * Get version from target
		 *
		 * @param   string  $target  Target
		 *
		 * @return  array   Version information
		 *
		 * @since   1.0.0
		 */
		public function getManifestVersion($target)
		{
			$this->log('Checking manifest file');
			$manifestFile = rtrim($target, '/') . '/' . rtrim(self::MANIFEST_DIR);
			$this->log('Manifest file: ' . $manifestFile);

			if (!file_exists($manifestFile))
			{
				$this->log('Manifest file is not exists');

				return array();
			}

			$this->log('Manifest file is exists');

			/* Get version */
			if (is_readable($manifestFile))
			{
				$xml                       = simplexml_load_file($manifestFile);
				$version['targetPlatform'] = (string) $xml['version'];
				$version['targetVersion']  = (string) $xml->version;

				return $version;
			}
			else
			{
				return array();
			}
		}

		/**
		 * Get local platform
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public function getLocalPlatform()
		{
			return (isset($this->localVersion['targetPlatform'])) ? $this->localVersion['targetPlatform'] : '';
		}

		/**
		 * Get local version
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public function getLocalVersion()
		{
			return (isset($this->localVersion['targetVersion'])) ? $this->localVersion['targetVersion'] : '';
		}

		/**
		 * Execute database fixing
		 *
		 * @return  mixed
		 *
		 * @since   1.0.0
		 */
		public function fixDatabase()
		{
			$this->log('Database checking');
			$model = $this->getDatabaseModel();
			$model->fix();

			return $model->getItems()->getStatus();
		}

		/**
		 * Display log
		 *
		 * @param   string  $str  Message
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function log($str)
		{
			echo '<small>' . $str . '</small>' . '<br />';
		}
	}
}

if (!class_exists('CrexJUpdater'))
{
	/**
	 * Crex Joomla! Updater class
	 *
	 * @package  Updater
	 *
	 * @since    1.0.0
	 */
	class CrexJUpdater
	{
		/**
		 * @var   array  Joomla! location
		 * @since 1.0.0
		 */
		private $_listJTargets = array();

		/**
		 * @var   string  Update URL
		 * @since 1.0.0
		 */
		private $_jUpdateUrl = 'http://update.joomla.org/core/list.xml';

		/**
		 * @var   array  Array of options
		 * @since 1.0.0
		 */
		private $_options = array(
			'downloadDir' => './CrexJUpdate'
		);

		/**
		 * CrexJUpdater constructor.
		 *
		 * @param   array  $targets  Target array
		 * @param   array  $options  Options
		 *
		 * @since   1.0.0
		 */
		public function __construct($targets, $options = array())
		{
			$this->_verifyRequirements();

			/* override options */
			$this->_options = array_merge($this->_options, $options);

			/* list of local Joomla! need to check */
			$this->_listJTargets = $targets;

			/* make init download dir */
			if (!file_exists($this->_options['downloadDir']))
			{
				mkdir($this->_options['downloadDir'], 0777, true);
			}

			(is_writable($this->_options['downloadDir']) && is_readable($this->_options['downloadDir'])) or die('<b>jStorage</b> not readable/writable.<br>');
		}

		/**
		 * Get update version information
		 *
		 * @return  array
		 *
		 * @since  1.0.0
		 */
		public function getUpdates()
		{
			$xml = simplexml_load_file($this->_jUpdateUrl);

			foreach ($xml as $extension)
			{
				$updates[(string) $extension['targetplatformversion']]['version']    = (string) $extension['version'];
				$updates[(string) $extension['targetplatformversion']]['detailsurl'] = (string) $extension['detailsurl'];
			}

			return $updates;
		}

		/**
		 * Get update downloads information
		 *
		 * @param   string  $xmlUrl  XML URL
		 *
		 * @return  array   Download information
		 *
		 * @since  1.0.0
		 */
		public function getUpdateDownloads($xmlUrl)
		{
			$this->log('Get update downloads');
			$xml = simplexml_load_file($xmlUrl);

			foreach ($xml as $download)
			{
				$downloads[(string) $download->targetplatform['version']]['version']     = (string) $download->version;
				$downloads[(string) $download->targetplatform['version']]['downloadurl'] = (string) $download->downloads->downloadurl;
			}

			return $downloads;
		}

		/**
		 * Execute update
		 *
		 * @return  void
		 *
		 * @since  1.0.0
		 */
		public function autoUpdate()
		{
			/* check remote latest version */
			$updateVersion = $this->getUpdates();

			/* loop target sites need to check */
			foreach ($this->_listJTargets as $localJoomla)
			{
				$joomla = new CrexJoomlaObject($localJoomla);

				/* Get local version */
				$localVersion  = $joomla->getLocalVersion();
				$localPlatform = $joomla->getLocalPlatform();

				/* check if targetPlatform exist */
				if (isset($updateVersion[$localPlatform]))
				{
					$latestVersion = $updateVersion[$localPlatform]['version'];
					$downloadsXml  = $updateVersion[$localPlatform]['detailsurl'];

					/* localVersion is older */
					if (version_compare($localVersion, $latestVersion) < 0)
					{
						$this->log('Local version is older than latest version: ' . $latestVersion);

						$downloads   = $this->getUpdateDownloads($downloadsXml);

						if (isset($downloads[$localPlatform]))
						{
							$download = $downloads[$localPlatform];
						}
						else
						{
							$download = end($downloads);
						}

						$downloadUrl = $download['downloadurl'];
						$this->log('Download: ' . $downloadUrl);

						/* parse to get fileName */
						$parts    = explode('/', $downloadUrl);
						$fileName = end($parts);

						/* save into <downloadDir>/<platform>/<version> */
						$saveDir  = $this->_options['downloadDir'] . '/' . $localPlatform . '/' . $download['version'];
						$saveFile = $saveDir . '/' . $fileName;

						/* create saveDir */
						if (!file_exists($saveDir))
						{
							mkdir($saveDir, 0777, true);
						}

						$this->log('Check saved file: ' . $saveFile);
						/* if patch file is not downloaded before. We don't need redownload if already downloaded */
						if (!file_exists($saveFile))
						{
							$this->log('This file is not exists. Going download it: ' . $saveFile);
							$this->_download($downloadUrl, $saveFile);
						}
						else
						{
							$this->log('This file is exists');
						}

						$this->log('Extract to: ' . $saveDir . '/extract');

						/* extract patch file */
						$extractDir = $saveDir . '/extract';

						if (!file_exists($extractDir))
						{
							mkdir($extractDir, 0777, true);
						}

						$this->_unpack($saveFile, $extractDir);

						/* do copy */
						$this->_recurseCopy($extractDir, rtrim($localJoomla, '/'));
						$this->log('Copy to: ' . rtrim($localJoomla, '/'));

						$this->log('Update from <b>' . $joomla->getLocalVersion() . ' to ' . $latestVersion . ' is completed.</b><br>');
					}
					else
					{
						$this->log('Your version <b>' . $joomla->getLocalVersion() . ' is up to date </b>');
					}

					// TODO Database upgrade also


					$rtn = $joomla->fixDatabase();

					if (count($rtn['error']) == 0)
					{
						$this->log('Database is upgraded');
					}

					$this->log('</hr>');
				}
			}
		}



		/**
		 * Download file
		 *
		 * @param string $url  Download URL
		 * @param string $file Write to file
		 */

		/**
		 * Download file
		 *
		 * @param   string  $url   Download URL
		 * @param   string  $file  Download file path
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		private function _download($url, $file)
		{
			// This is the file where we save the    information
			$fp = fopen($file, 'w+');

			// $ch = curl_init(str_replace(" ","%20",$url));//Here is the file we are downloading, replace spaces with %20
			$ch = curl_init(urldecode($url));
			curl_setopt($ch, CURLOPT_TIMEOUT, CREXJUPDATE_CURL_TIMEOUT);

			// Write curl response to file
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

			// Get curl response
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
		}

		/**
		 * Unpack a package file
		 *
		 * @param   string  $filename        Source file path
		 * @param   string  $targetLocation  Directory path to extract
		 *
		 * @return  bool
		 *
		 * @since   1.0.0
		 */
		private function _unpack($filename, $targetLocation)
		{
			$this->log('Unpacking: ' . $targetLocation);

			$zip = new ZipArchive;

			if ($zip->open($filename) === true)
			{
				$zip->extractTo($targetLocation);
				$zip->close();

				return true;
			}
			else
			{
				return false;
			}
		}

		/**
		 * Recurse copy
		 *
		 * @param   string  $src  Source
		 * @param   string  $dst  Target
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		private function _recurseCopy($src, $dst)
		{
			$dir = opendir($src);
			@mkdir($dst);

			while (false !== ($file = readdir($dir)))
			{
				if (($file != '.') && ($file != '..'))
				{
					if (is_dir($src . '/' . $file))
					{
						$this->_recurseCopy($src . '/' . $file, $dst . '/' . $file);
					}
					else
					{
						copy($src . '/' . $file, $dst . '/' . $file);
					}
				}
			}

			closedir($dir);
		}

		/**
		 * Check requirements
		 *
		 * @return  void
		 *
		 * @since  1.0.0
		 */
		private function _verifyRequirements ()
		{
			/**
			 * check if matches required
			 */
			function_exists('simplexml_load_file') or die('<b>simplexml_load_file</b> not found.<br>');
			function_exists('curl_init') or die('<b>Curl</b> not found.<br>');
			class_exists('ZipArchive') or die('<b>ZipArchive</b> notfound.<br>');
		}

		/**
		 * Display log
		 *
		 * @param   string  $str  Message
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function log($str)
		{
			echo '<small>' . $str . '</small>' . '<br />';
		}
	}
}

if (isset($_GET['targetSites']))
{
	if ($_GET['targetSites'] !== "")
	{
		$sites        = explode(';', $_GET['targetSites']);
		$checkedSites = array();

		foreach ($sites as $_site)
		{
			if (is_dir($_site))
			{
				$checkedSites[] = $_site;
			}
		}

		if (count($checkedSites) <= 0) die('<b>targetSites</b> invalid.<br>');

	}
	else
	{
		die('<b>targetSites</b> isn\'t set.<br>');
	}
}
else
{
	die('<b>targetSites</b> isn\'t set.<br>');
}

$jUpdate = new CrexJUpdater($checkedSites);

echo '<pre>';
$jUpdate->autoUpdate();
echo '</pre>';