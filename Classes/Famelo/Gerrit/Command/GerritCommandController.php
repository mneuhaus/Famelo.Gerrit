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
	 * @var array
	 */
	protected $colors = array(
		'green' => '0;32',
		'red' => '0;31',
		'yellow' => '0;33'
	);

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

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

	/**
	 * @param string $basePath
	 * @return void
	 */
	public function processPackages($basePath) {
		$paths = scandir($basePath);
		foreach ($paths as $path) {
			if ($path === '.' || $path === '..') {
				continue;
			}

			if ($path === '.git') {
				$this->addGerritRemote($basePath . '/' . $path);
				$this->addChangeIdCommitHook($basePath . '/' . $path);
			} elseif (is_dir($basePath . '/' . $path)) {
				$this->processPackages($basePath . '/' . $path);
			}
		}
	}

	/**
	 * @param string $path
	 * @return void
	 */
	public function addChangeIdCommitHook($path) {
		if (!file_exists($path . '/hooks/commit-msg')) {
			file_put_contents($path . '/hooks/commit-msg', file_get_contents('resource://Famelo.Gerrit/Private/Hooks/commit-msg'));
			$this->outputLine('Added commit-msg hook to add ChangeId to: %s', array(realpath($path)));
		}
		system('chmod +x ' . $path . '/hooks/commit-msg');
	}

	/**
	 * @param string $path
	 * @return void
	 */
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
	 * This command will cherry-pick all reviews specified in gerrit.json
	 *
	 * @return void
	 */
	public function updateCommand() {
		$gerritFile = FLOW_PATH_ROOT . 'gerrit.json';
		$typeDirs = scandir(FLOW_PATH_PACKAGES);
		$packagePaths = array('BuildEssentials' => 'Build/BuildEssentials');
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
				echo $this->colorize('Could not load gerrit.json! Check for Syntax errors', 'red');
				return;
			}
			foreach (get_object_vars($packages) as $package => $patches) {
				if (!isset($packagePaths[$package])) {
					echo $this->colorize('The Package ' . $package . ' is not installed', 'red') . PHP_EOL;
					continue;
				}
				chdir($packagePaths[$package]);
				$patches = get_object_vars($patches);
				$commits = $this->executeShellCommand('git log -n30');
				foreach ($patches as $description => $changeId) {
					$change = $this->fetchChangeInformation($changeId);
					$header = $package . ': ' . $change->subject;
					echo $this->colorize($header, 'green') . PHP_EOL;

					if ($change->status == 'MERGED') {
						echo $this->colorize('This change has been merged!', 'yellow') . PHP_EOL;
					} elseif ($change->status == 'ABANDONED') {
						echo $this->colorize('This change has been abandoned!', 'red') . PHP_EOL;
					} else {
						$command = 'git fetch --quiet git://' . $this->settings['gerrit']['host'] . '/' . $change->project . ' ' . $change->revisions->{$change->current_revision}->fetch->git->ref . '';
						$output = $this->executeShellCommand($command);

						$commit = $this->executeShellCommand('git log --format="%H" -n1 FETCH_HEAD');
						if ($this->isAlreadyPicked($commit, $commits)) {
							echo $this->colorize('Already picked', 'yellow') . PHP_EOL;
						} else {
							echo $output;
							system('git cherry-pick -x --strategy=recursive -X theirs FETCH_HEAD');
						}
					}

					echo PHP_EOL;
				}
				chdir(FLOW_PATH_ROOT);
			}
		}
	}

	/**
	 * @param string $commit
	 * @param string $commits
	 * @return boolean
	 */
	public function isAlreadyPicked($commit, $commits) {
		return stristr($commits, '(cherry picked from commit ' . $commit . ')') !== FALSE;
	}

	/**
	 * @param integer $changeId The numeric change id, not the hash
	 * @return mixed
	 */
	public function fetchChangeInformation($changeId) {
		$output = file_get_contents($this->settings['gerrit']['apiEndpoint'] . 'changes/?format=JSON_COMPACT&q=' . intval($changeId) . '&o=CURRENT_REVISION');

			// Remove first line
		$output = substr($output, strpos($output, "\n") + 1);
			// trim []
		$output = ltrim($output, '[');
		$output = rtrim(rtrim($output), ']');

		$data = json_decode($output);
		return $data;
	}

	/**
	 * @param string $command
	 * @return string
	 */
	public function executeShellCommand($command) {
		$output = '';
		$fp = popen($command, 'r');
		while (($line = fgets($fp)) !== FALSE) {
			$output .= $line;
		}
		pclose($fp);
		return trim($output);
	}

	/**
	 * @param string $text
	 * @param string $color Allowed values: green, red, yellow
	 * @return string
	 */
	public function colorize($text, $color) {
		return sprintf("\033[%sm%s\033[0m", $this->colors[$color], $text);
	}
}

?>