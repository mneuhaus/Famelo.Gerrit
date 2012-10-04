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
	 * An example command
	 *
	 * The comment of this command method is also used for TYPO3 Flow's help screens. The first line should give a very short
	 * summary about what the command does. Then, after an empty line, you should explain in more detail what the command
	 * does. You might also give some usage example.
	 *
	 * It is important to document the parameters with param tags, because that information will also appear in the help
	 * screen.
	 *
	 * @param string $requiredArgument This argument is required
	 * @param string $optionalArgument This argument is optional
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
		if (!file_exists($path . "/hooks/commit-msg")) {
			file_put_contents($path . "/hooks/commit-msg", file_get_contents('resource://TYPO3.Gerrit/Private/Hooks/commit-msg'));
			$this->outputLine('Added commit-msg hook to add ChangeId to: %s', array(realpath($path)));
		}
	}

	public function addGerritRemote($path) {
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