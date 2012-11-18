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
class GitCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * This setups the packages repositories with some stuff helpful for working with gerrit
	 *
	 * This command walks through all packages and checks for 2 things:
	 *
	 *
	 * @param string $package
	 * @return void
	 */
	public function projectCommand($package) {
		$package = strtolower($package);
		$packagePaths = $this->getPackagePaths();
		if (isset($packagePaths[$package])) {
			$packagePath = $packagePaths[$package];
			$arguments = array_splice($_SERVER['argv'], 3);

			foreach ($arguments as $key => $argument) {
				if (stristr($argument, ' ')) {
					$arguments[$key] = '"' . $argument . '"';
				}
			}

			chdir($packagePath);
			system('git ' . implode(' ', $arguments));
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
	 * @param boolean $verbose Show verbose output
	 * @return void
	 */
	public function statusCommand($verbose = FALSE) {
		$clean = TRUE;
		exec(sprintf('find %s -name ".git"', FLOW_PATH_ROOT), $gitWorkingCopies, $status);
		foreach ($gitWorkingCopies as $gitWorkingCopy) {
			$output = NULL;
			$cmd = sprintf('cd %s && git status', dirname($gitWorkingCopy));
			exec($cmd, $output, $return);

			$path = str_replace(FLOW_PATH_ROOT, '', dirname($gitWorkingCopy));

			if ($output[1] === 'nothing to commit (working directory clean)') {
				if ($verbose === TRUE) {
					$this->outputLine('%s is clean', array($path), '0;32');
				}
			} else {
				$clean = FALSE;

				if ($output[0] === '# Not currently on any branch.') {
					$this->outputLine('%s is not on a branch and has local changes', array($path), '0;31');
				} elseif ($output[1] === '# Changes not staged for commit:') {
					$this->outputLine('%s has local changes', array($path), '0;31');
				} else {
					$this->outputLine('%s %s', array($path, $output[1]), '0;33');
				}

				foreach ($output as $outputLine) {
					if (preg_match('/^#\t/', $outputLine)) {
						if (strpos($outputLine, ':') === FALSE) {
							$this->outputLine(str_replace("\t", "\tuntracked:  ", $outputLine));
						} else {
							$this->outputLine($outputLine);
						}
					}
				}
			}
		}

		if ($clean === TRUE) {
			$this->outputLine('Working copy is clean', array(), '0;32');
		}
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