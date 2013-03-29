<?php
define('FLOW_PATH_ROOT', getcwd() . '/');
define('FLOW_PATH_FLOW', FLOW_PATH_ROOT . '/Packages/Framework/TYPO3.Flow/');

require(FLOW_PATH_ROOT . '/Packages/Framework/TYPO3.Flow/Classes/TYPO3/Flow/Core/Bootstrap.php');
require_once(FLOW_PATH_FLOW . 'Classes/TYPO3/Flow/Core/Booting/Scripts.php');

use TYPO3\Flow\Core\Booting\Scripts;

$context = trim(getenv('FLOW_CONTEXT'), '"\' ') ?: 'Development';
$bootstrap = new \TYPO3\Flow\Core\Bootstrap($context);

Scripts::initializeClassLoader($bootstrap);
Scripts::initializeSignalSlot($bootstrap);
Scripts::initializePackageManagement($bootstrap);
Scripts::initializeConfiguration($bootstrap);

if (!isset($argv[1])) {
	echo 'No Command specified!';
}

$configurationManager = $bootstrap->getEarlyInstance('TYPO3\Flow\Configuration\ConfigurationManager');
$settings = $configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Famelo.Gerrit');
$commandController = new \Famelo\Gerrit\Command\GerritCommandController();
$commandController->injectSettings($settings);

switch ($argv[1]) {
	case 'update':
		$commandController->updateCommand();
		break;

	default:
		echo 'unknown command';
		break;
}

?>