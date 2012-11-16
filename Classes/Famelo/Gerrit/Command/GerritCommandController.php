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
			foreach (get_object_vars($packages) as $package => $patches) {
				$patches = get_object_vars($patches);
				foreach ($patches as $description => $changeId) {
					$change = $this->fetchChangeInformation($changeId);
					$header = '# Fetching: ' . $change->subject;
					echo str_pad('', strlen($header), '#') . chr(10);
					echo $header . chr(10);
					echo str_pad('', strlen($header), '#') . chr(10);

					chdir($packagePaths[$package]);
					$command = 'git fetch git://git.typo3.org/' . $change->project . ' ' . $change->currentPatchSet->ref . ' && git cherry-pick FETCH_HEAD';
					system($command);
					chdir(FLOW_PATH_ROOT);
					echo chr(10);
				}
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
		return $output;
	}
}

?>