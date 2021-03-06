<?php
namespace Change\Commands;

use Change\Commands\Events\Event;

/**
 * @name \Change\Commands\EnablePlugin
 */
class EnablePlugin
{
	/**
	 * @param Event $event
	 * @throws \Exception
	 */
	public function execute(Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		$type = $event->getParam('type');
		$vendor = $event->getParam('vendor');
		$shortName = $event->getParam('name');

		$response = $event->getCommandResponse();

		$pluginManager = $applicationServices->getPluginManager();

		$plugin = $pluginManager->getPlugin($type, $vendor, $shortName);
		if ($plugin && !$plugin->getActivated())
		{
			if ($plugin->getConfigured())
			{
				$plugin->setActivated(true);
				$tm = $applicationServices->getTransactionManager();
				try
				{
					$tm->begin();
					$pluginManager->update($plugin);
					$tm->commit();
				}
				catch(\Exception $e)
				{
					$response->addErrorMessage("Error disabling plugin");
					$applicationServices->getLogging()->exception($e);
					throw $e;
				}
				$pluginManager->compile();
				$response->addInfoMessage('Done.');
			}
			else
			{
				$response->addErrorMessage('Only configured plugins can be enabled');
			}
		}
		else
		{
			$response->addInfoMessage('Nothing to do');
		}
	}
}