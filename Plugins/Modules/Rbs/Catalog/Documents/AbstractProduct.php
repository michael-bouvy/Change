<?php
namespace Rbs\Catalog\Documents;

use Change\Http\Rest\Result\DocumentResult;
use Change\Http\Rest\Result\Link;

/**
 * @name \Rbs\Catalog\Documents\AbstractProduct
 */
class AbstractProduct extends \Compilation\Rbs\Catalog\Documents\AbstractProduct
{
	/**
	 * @return \Rbs\Media\Documents\Image|null
	 */
	public function getFirstVisual()
	{
		$visuals = $this->getVisuals();
		return $visuals->count() ? $visuals[0] : null;
	}

	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach('updateRestResult', function(\Change\Documents\Events\Event $event) {
			$result = $event->getParam('restResult');
			if ($result instanceof DocumentResult)
			{
				$selfLinks = $result->getRelLink('self');
				$selfLink = array_shift($selfLinks);
				if ($selfLink instanceof Link)
				{
					$pathParts = explode('/', $selfLink->getPathInfo());
					array_pop($pathParts);
					$link = new Link($event->getParam('urlManager'), implode('/', $pathParts) . '/ProductCategorization/', 'productcategorizations');
					$result->addLink($link);
					$link = new Link($event->getParam('urlManager'), implode('/', $pathParts) . '/Prices/', 'prices');
					$result->addLink($link);
				}
			}
		}, 5);
	}
}