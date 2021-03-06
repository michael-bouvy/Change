<?php
namespace Rbs\Website\Documents;

use Change\Documents\Events\Event;

/**
 * @name \Rbs\Website\Documents\StaticPage
 */
class StaticPage extends \Compilation\Rbs\Website\Documents\StaticPage
{
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(Event::EVENT_DISPLAY_PAGE, array($this, 'onDocumentDisplayPage'), 10);

		$callback = function (Event $event)
		{
			/* @var $page StaticPage */
			$page = $event->getDocument();
			if ($page->getSection())
			{
				$tm = $event->getApplicationServices()->getTreeManager();
				$parentNode = $tm->getNodeByDocument($page->getSection());
				if ($parentNode)
				{
					$tm->insertNode($parentNode, $page);
				}
			}
		};
		$eventManager->attach(Event::EVENT_CREATED, $callback);

		$callback = function (Event $event)
		{
			/* @var $page StaticPage */
			if (in_array('section', $event->getParam('modifiedPropertyNames', array())))
			{
				$page = $event->getDocument();
				$tm = $event->getApplicationServices()->getTreeManager();
				$tm->deleteDocumentNode($page);
				if ($page->getSection())
				{
					$parentNode = $tm->getNodeByDocument($page->getSection());
					if ($parentNode)
					{
						$tm->insertNode($parentNode, $page);
					}
				}
			}
		};
		$eventManager->attach(Event::EVENT_UPDATED, $callback);
		$eventManager->attach('populatePathRule', array($this, 'onPopulatePathRule'), 5);


		$eventManager->attach('fullTextContent', array($this, 'onDefaultFullTextContent'), 5);
	}

	public function onDefaultFullTextContent(Event $event)
	{
		$document = $event->getDocument();
		if ($document instanceof StaticPage)
		{
			$fullTextContent = array();
			$layout = $document->getContentLayout();
			foreach ($layout->getBlocks() as $block)
			{
				$params = $block->getParameters();
				if (isset($params['content']) && isset($params['contentType']))
				{
					$richText = new \Change\Documents\RichtextProperty($params['content']);
					$richText->setEditor($params['contentType']);
					$fullTextContent[] = $richText->getRawText();
				}
			}
			if (count($fullTextContent))
			{
				$event->setParam('fullTextContent', $fullTextContent);
			}
		}
	}
	/**
	 * @param Event $event
	 */
	public function onPopulatePathRule(Event $event)
	{
		$document = $event->getDocument();
		if ($document instanceof StaticPage)
		{
			/* @var $pathRule \Change\Http\Web\PathRule */
			$pathRule = $event->getParam('pathRule');

			$relativePath = $pathRule->normalizePath($document->getTitle() . '.' . $document->getId() . '.html');
			$section = $document->getSection();
			if ($section instanceof Topic && $section->getPathPart())
			{
				$relativePath = $section->getPathPart() . '/' . $relativePath;
			}
			$pathRule->setRelativePath($relativePath);
		}
	}

	/**
	 * @param Event $event
	 */
	public function onDocumentDisplayPage(Event $event)
	{
		$document = $event->getDocument();
		if ($document instanceof \Change\Presentation\Interfaces\Page)
		{
			$event->setParam('page', $document);
			$event->stopPropagation();
		}
	}

	/**
	 * @return \Change\Presentation\Interfaces\Section[]
	 */
	public function getPublicationSections()
	{
		$section = $this->getSection();
		return $section ? array($section) : array();
	}

	/**
	 * @param \Change\Documents\AbstractDocument $publicationSections
	 * @return $this
	 */
	public function setPublicationSections($publicationSections)
	{
		return $this;
	}

	public function onDefaultUpdateRestResult(Event $event)
	{
		parent::onDefaultUpdateRestResult($event);

		$restResult = $event->getParam('restResult');
		if ($restResult instanceof \Change\Http\Rest\Result\DocumentLink)
		{
			$documentLink = $restResult;
			$extraColumn = $event->getParam('extraColumn');
			if (in_array('functions', $extraColumn))
			{
				$document = $documentLink->getDocument();
				$query = $event->getApplicationServices()->getDocumentManager()->getNewQuery('Rbs_Website_SectionPageFunction');
				$query->andPredicates(
					$query->eq('page', $documentLink->getDocument()),
					$query->eq('section',
						$event->getApplicationServices()->getTreeManager()->getNodeByDocument($document)->getParentId())
				);
				$functions = array();
				$funcDocs = $query->getDocuments();
				foreach ($funcDocs as $func)
				{
					/* @var $func \Rbs\Website\Documents\SectionPageFunction */
					$functions[] = $func->getFunctionCode();
				}
				$documentLink->setProperty('functions', $functions);
			}
		}
	}
}