<?php
namespace TYPO3\Gerrit\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Gerrit".          *
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
	 * This updates the packages repositories with some stuff helpful for working with gerrit
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
	public function updateCommand() {
		$this->processPackages(FLOW_PATH_PACKAGES);
	}

	public function processPackages($basePath) {
		$paths = scandir($basePath);
		foreach($paths as $path){
			if ($path == '.' || $path == '..') continue;

			if ($path == ".git"){
				$this->addGerritRemote($basePath . '/' . $path);
				$this->addChangeIdCommitHook($basePath . '/' . $path);
			} else if (is_dir($basePath . '/' . $path)) {
				$this->processPackages($basePath . "/" . $path);
			}
		}
	}

	public function addChangeIdCommitHook($path) {
		system("chmod +x " . $path . "/hooks/commit-msg");
		if (!file_exists($path . "/hooks/commit-msg")) {
			file_put_contents($path . "/hooks/commit-msg", file_get_contents('resource://TYPO3.Gerrit/Private/Hooks/commit-msg'));
			$this->outputLine('Added commit-msg hook to add ChangeId to: %s', array(realpath($path)));
		}
	}

	public function addGerritRemote($path) {
		$configTemplate = '
[remote "gerrit"]
	fetch = +refs/heads/*:refs/remotes/origin/*
	url = git://git.typo3.org/FLOW3/Packages/{package}.git
	push = HEAD:refs/for/master
';
		$config = file_get_contents($path . '/config');
		preg_match("/url = git:\/\/git.typo3.org\/FLOW3\/Packages\/(.+).git/", $config, $matches);
		if (count($matches) > 0 && !stristr($config, '[remote "gerrit"]')){
			$config .= str_replace('{package}', $matches[1], $configTemplate);
			file_put_contents($path . '/config', $config);
			$this->outputLine('Added gerrit remote to repository: %s', array(realpath($path)));
		}
	}
}

?>