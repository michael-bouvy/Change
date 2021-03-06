<?php
namespace Rbs\Review\Blocks;

use Change\Presentation\Blocks\Event;
use Change\Presentation\Blocks\Parameters;
use Change\Presentation\Blocks\Standard\Block;

/**
 * @name \Rbs\Review\Blocks\ReviewDetail
 */
class ReviewDetail extends Block
{
	/**
	 * @api
	 * Set Block Parameters on $event
	 * Required Event method: getBlockLayout, getApplication, getApplicationServices, getServices, getHttpRequest
	 * Optional Event method: getHttpRequest
	 * @param Event $event
	 * @return Parameters
	 */
	protected function parameterize($event)
	{
		$parameters = parent::parameterize($event);
		$parameters->addParameterMeta('reviewId');
		$parameters->addParameterMeta('editionMode', false);

		$parameters->setLayoutParameters($event->getBlockLayout());

		if ($parameters->getParameter('reviewId') === null)
		{
			$target = $event->getParam('document');
			if ($target instanceof \Rbs\Review\Documents\Review)
			{
				$parameters->setParameterValue('reviewId', $target->getId());
			}
		}

		return $parameters;
	}

	/**
	 * Set $attributes and return a twig template file name OR set HtmlCallback on result
	 * Required Event method: getBlockLayout, getApplication, getApplicationServices, getServices, getHttpRequest
	 * @param Event $event
	 * @param \ArrayObject $attributes
	 * @return string|null
	 */
	protected function execute($event, $attributes)
	{
		$parameters = $event->getBlockParameters();
		$review = $event->getApplicationServices()->getDocumentManager()
			->getDocumentInstance($parameters->getParameter('reviewId'));
		if ($review)
		{
			/* @var $review \Rbs\Review\Documents\Review */
			$urlManager = $event->getUrlManager();
			$attributes['review'] = $review->getInfoForTemplate($urlManager);
			$attributes['displayVote'] = true;
			return 'review.twig';
		}
		return null;
	}
}