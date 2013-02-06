<?php
namespace Famelo\Gerrit\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Famelo.Gerrit".         *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * update command controller for the TYPO3.Gerrit package
 *
 * @Flow\Scope("singleton")
 */
class PatchCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * Create a new Patch unstaged git changes in a specific Package
	 *
	 * @param string $package
	 * @param string $patchName
	 * @return void
	 */
	public function createCommand($package, $patchName) {
		$package = strtolower($package);
		$packagePaths = $this->getPackagePaths();

		if (!is_dir($patchFile = FLOW_PATH_ROOT . '/Patches/')) {
			mkdir($patchFile = FLOW_PATH_ROOT . '/Patches/');
		}

		if (isset($packagePaths[$package])) {
			$patchDir = $patchFile = FLOW_PATH_ROOT . '/Patches/' . $package . '/';
			if (!is_dir($patchDir)) {
				mkdir($patchDir);
			}

			$packagePath = $packagePaths[$package];

			chdir($packagePath);
			$patchFile = $patchDir . $patchName . '.diff';

			system('git diff > ' . $patchFile);

			chdir(FLOW_PATH_ROOT);
		} else {
			$this->outputLine('Unknown Package!', array(), 'red');
		}
	}

	/**
	 * Apply a specific patch from Patches/package.key/package-name.diff
	 *
	 *
	 * @param string $package
	 * @param string $patchName
	 * @return void
	 */
	public function applyCommand($package, $patchName) {
		$package = strtolower($package);
		$packagePaths = $this->getPackagePaths();
		if (isset($packagePaths[$package])) {
			$patchDir = $patchFile = FLOW_PATH_ROOT . '/Patches/' . $package . '/';
			$packagePath = $packagePaths[$package];
			chdir($packagePath);
			$patchFile = $patchDir . $patchName . '.diff';

			system('patch -p1 < ' . $patchFile);

			chdir(FLOW_PATH_ROOT);
		} else {
			$this->outputLine('Unknown Package!', array(), 'red');
		}
	}

	public function getPackagePaths() {
		$typeDirs = scandir(FLOW_PATH_PACKAGES);
		$packagePaths = array();
		foreach ($typeDirs as $typeDir) {
			if (is_dir(FLOW_PATH_PACKAGES . $typeDir) && substr($typeDir, 0, 1) !== '.') {
				$typeDir = FLOW_PATH_PACKAGES . $typeDir . '/';
				$packageDirs = scandir($typeDir);
				foreach ($packageDirs as $packageDir) {
					if (is_dir($typeDir . $packageDir) && substr($packageDir, 0, 1) !== '.') {
						$packagePaths[$packageDir] = $typeDir . $packageDir;
						$packagePaths[strtolower($packageDir)] = $typeDir . $packageDir;
					}
				}
			}
		}
		return $packagePaths;
	}

	/**
	 * @param string $text
	 * @param array $arguments
	 * @param string $color
	 * @return void
	 */
	public function outputLine($text = '', array $arguments = array(), $color = NULL) {
		$colors = array(
			'green' => '0;32',
			'red' => '0;31',
			'yellow' => '0;33'
		);

		if (isset($colors[$color])) {
			$color = $colors[$color];
		}

		if ($color !== NULL) {
			$text = sprintf("\033[%sm%s\033[0m", $color, $text);
		}

		parent::outputLine($text, $arguments);
	}
}

?>