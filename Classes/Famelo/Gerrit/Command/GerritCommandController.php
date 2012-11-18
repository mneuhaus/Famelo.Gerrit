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
class GerritCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * This setups the packages repositories with some stuff helpful for working with gerrit
	 *
	 * This command walks through all packages and checks for 2 things:
	 *
	 * 1. is there a commit-msg hook to add the Change ID
	 * 2. is there a remote target to push to gerrit
	 *
	 * if either of those misses it adds those.
	 *
	 * @return void
	 */
	public function setupCommand() {
		$this->processPackages(FLOW_PATH_PACKAGES);
	}

	public function processPackages($basePath) {
		$paths = scandir($basePath);
		foreach ($paths as $path) {
			if ($path == '.' || $path == '..') {
				continue;
			}

			if ($path == '.git') {
				$this->addGerritRemote($basePath . '/' . $path);
				$this->addChangeIdCommitHook($basePath . '/' . $path);
			} elseif (is_dir($basePath . '/' . $path)) {
				$this->processPackages($basePath . '/' . $path);
			}
		}
	}

	public function addChangeIdCommitHook($path) {
		if (!file_exists($path . '/hooks/commit-msg')) {
			file_put_contents($path . '/hooks/commit-msg', file_get_contents('resource://Famelo.Gerrit/Private/Hooks/commit-msg'));
			$this->outputLine('Added commit-msg hook to add ChangeId to: %s', array(realpath($path)));
		}
		system('chmod +x ' . $path . '/hooks/commit-msg');
	}

	public function addGerritRemote($path) {
		$configTemplate = '
[remote "gerrit"]
	fetch = +refs/heads/*:refs/remotes/origin/*
	url = git://git.typo3.org/FLOW3/Packages/{package}.git
	push = HEAD:refs/for/master
';
		$config = file_get_contents($path . '/config');
		preg_match('/url = git:\/\/git.typo3.org\/FLOW3\/Packages\/(.+).git/', $config, $matches);
		if (count($matches) > 0 && !stristr($config, '[remote "gerrit"]')) {
			$config .= str_replace('{package}', $matches[1], $configTemplate);
			file_put_contents($path . '/config', $config);
			$this->outputLine('Added gerrit remote to repository: %s', array(realpath($path)));
		}
	}


	/**
	 * This command checks for a gerrit.json in the current dir and fetches patches from gerrit
	 *
	 * This command will cherrypick all reviews specified in gerrit.json
	 *
	 * @return void
	 */
	public function updateCommand() {
		$gerritFile = FLOW_PATH_ROOT . 'gerrit.json';
		$typeDirs = scandir(FLOW_PATH_PACKAGES);
		$packagePaths = array();
		foreach ($typeDirs as $typeDir) {
			if (is_dir(FLOW_PATH_PACKAGES . $typeDir) && substr($typeDir, 0, 1) !== '.') {
				$typeDir = FLOW_PATH_PACKAGES . $typeDir . '/';
				$packageDirs = scandir($typeDir);
				foreach ($packageDirs as $packageDir) {
					if (is_dir($typeDir . $packageDir) && substr($packageDir, 0, 1) !== '.') {
						$packagePaths[$packageDir] = $typeDir . $packageDir;
					}
				}
			}
		}
		if (file_exists($gerritFile)) {
			$packages = json_decode(file_get_contents($gerritFile));
			if (!is_object($packages)) {
				echo $this->colorize('Could not load gerrit.json! Check for Syntax erros', 'red');
				return;
			}
			foreach (get_object_vars($packages) as $package => $patches) {
				if (isset($packagePaths[$package])) {
					echo $this->colorize('Could not load gerrit.json! Check for Syntax erros', 'red');
					continue;
				}
				chdir($packagePaths[$package]);
				$patches = get_object_vars($patches);
				$commits = explode("\n", $this->executeShellCommand('git log --format="%H" -n50'));
				foreach ($patches as $description => $changeId) {
					$change = $this->fetchChangeInformation($changeId);
					$header = 'Fetching: ' . $change->subject;
					echo $this->colorize($header, 'green') . chr(10);

					$command = 'git fetch --quiet git://git.typo3.org/' . $change->project . ' ' . $change->currentPatchSet->ref . '';
					$output = $this->executeShellCommand($command);

					$commit = $this->executeShellCommand('git log --format="%H" -n1 FETCH_HEAD^');
					if (in_array($commit, $commits)) {
						echo $this->colorize('Already picked', 'yellow') . chr(10);
					} else {
						echo $output;
						system('git cherry-pick FETCH_HEAD');
					}

					echo chr(10);
				}
				chdir(FLOW_PATH_ROOT);
			}
		}
	}

	public function fetchChangeInformation($changeId) {
		$command = 'ssh review.typo3.org -- "gerrit query --current-patch-set --format JSON change:' . $changeId . '"';

		$output = $this->executeShellCommand($command);

		$parts = explode('{"type":"stats"', $output);
		$output = $parts[0];

		$data = json_decode($output);
		return $data;
	}

	public function executeShellCommand($command) {
		$output = '';
		$fp = popen($command, 'r');
		while (($line = fgets($fp)) !== FALSE) {
			$output .= $line;
		}
		pclose($fp);
		return trim($output);
	}

	public function colorize($text, $color) {
		$colors = array(
			'green' => '0;32',
			'red' => '0;31',
			'yellow' => '0;33'
		);
		return sprintf("\033[%sm%s\033[0m", $colors[$color], $text);
	}
}

?>