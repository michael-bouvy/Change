<?php
namespace Rbs\Admin\Http\Actions;

use Change\Http\Event;
use Change\Http\Rest\OAuth\OAuth;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Rbs\Admin\Http\Actions\GetHome
 */
class GetHome
{
	/**
	 * Use Required Event Params:
	 * @param Event $event
	 * @throws \RuntimeException
	 */
	public function execute($event)
	{
		$result = new \Rbs\Admin\Http\Result\Home();
		$templateFileName = implode(DIRECTORY_SEPARATOR, array( __DIR__ , 'Assets', 'home.twig'));
		$attributes = array('baseURL' => $event->getUrlManager()->getByPathInfo('/')->normalize()->toString());
		$attributes['LCID'] = $event->getApplicationServices()->getI18nManager()->getLCID();
		$attributes['lang'] = substr($attributes['LCID'], 0, 2);

		/* @var $manager \Rbs\Admin\Manager */
		$manager = $event->getParam('manager');
		$attributes['resources'] = $manager->getResources();

		$OAuth = new OAuth();
		$OAuth->setApplicationServices($event->getApplicationServices());
		$consumer = $OAuth->getConsumerByApplication('Rbs_Admin');
		$attributes['OAuth']['Consumer'] = $consumer ? array_merge($consumer->toArray(), array('realm' => 'Rbs_Admin')) : array();
		if (!isset($attributes['resources']['menu']))
		{
			$attributes['resources']['menu'] = array("sections" => array(), "entries" => array());
		}
		$renderer = function () use ($templateFileName, $manager, $attributes)
		{
			$resourceDirectoryPath = $manager->getApplication()->inDevelopmentMode() ? $manager->getResourceDirectoryPath(): null;
			$resourceBaseUrl = $manager->getResourceBaseUrl();

			$scripts = $manager->prepareScriptAssets($resourceDirectoryPath, $resourceBaseUrl);
			$attributes = ['scripts' => $scripts] + $attributes;

			$styles = $manager->prepareCssAssets($resourceDirectoryPath, $resourceBaseUrl);
			$attributes = ['styles' => $styles] + $attributes;

			return $manager->renderTemplateFile($templateFileName, $attributes);
		};
		$result->setRenderer($renderer);
		$event->setResult($result);
	}
}