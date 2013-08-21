<?php
namespace Rbs\Generic\Presentation\Twig;

use Change\Documents\DocumentServices;
use Change\Http\Web\UrlManager;
use Change\Presentation\PresentationServices;

/**
 * @name \Rbs\Generic\Presentation\Twig\Extension
 */
class Extension  implements \Twig_ExtensionInterface
{

	/**
	 * @var DocumentServices
	 */
	protected $documentServices;

	/**
	 * @var PresentationServices
	 */
	protected $presentationServices;

	/**
	 * @var UrlManager
	 */
	protected $urlManager;

	/**
	 * @param PresentationServices $presentationServices
	 * @param DocumentServices $documentServices
	 * @param UrlManager $urlManager
	 */
	function __construct(PresentationServices $presentationServices, DocumentServices $documentServices, UrlManager $urlManager)
	{
		$this->presentationServices = $presentationServices;
		$this->urlManager = $urlManager;
		$this->documentServices = $documentServices;
	}

	/**
	 * Returns the name of the extension.
	 * @return string The extension name
	 */
	public function getName()
	{
		return 'Rbs_Generic';
	}

	/**
	 * Initializes the runtime environment.
	 * This is where you can load some file that contains filter functions for instance.
	 * @param \Twig_Environment $environment The current Twig_Environment instance
	 */
	public function initRuntime(\Twig_Environment $environment)
	{

	}

	/**
	 * Returns the token parser instances to add to the existing list.
	 * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
	 */
	public function getTokenParsers()
	{
		return array();
	}

	/**
	 * Returns the node visitor instances to add to the existing list.
	 * @return array An array of Twig_NodeVisitorInterface instances
	 */
	public function getNodeVisitors()
	{
		return array();
	}

	/**
	 * Returns a list of filters to add to the existing list.
	 * @return array An array of filters
	 */
	public function getFilters()
	{
		return array();
	}

	/**
	 * Returns a list of tests to add to the existing list.
	 * @return array An array of tests
	 */
	public function getTests()
	{
		return array();
	}

	/**
	 * Returns a list of functions to add to the existing list.
	 * @return array An array of functions
	 */
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('imageURL', array($this, 'imageURL')),
			new \Twig_SimpleFunction('documentURL', array($this, 'documentURL')),
			new \Twig_SimpleFunction('currentURL', array($this, 'currentURL')),
		);
	}

	/**
	 * Returns a list of operators to add to the existing list.
	 * @return array An array of operators
	 */
	public function getOperators()
	{
		return array();
	}

	/**
	 * Returns a list of global variables to add to the existing list.
	 * @return array An array of global variables
	 */
	public function getGlobals()
	{
		return array();
	}

	/**
	 * @param PresentationServices $presentationServices
	 * @return $this
	 */
	public function setPresentationServices(PresentationServices $presentationServices)
	{
		$this->presentationServices = $presentationServices;
		return $this;
	}

	/**
	 * @return PresentationServices
	 */
	public function getPresentationServices()
	{
		return $this->presentationServices;
	}

	/**
	 * @param UrlManager $urlManager
	 * @return $this
	 */
	public function setUrlManager(UrlManager $urlManager)
	{
		$this->urlManager = $urlManager;
		return $this;
	}

	/**
	 * @param \Change\Documents\DocumentServices $documentServices
	 * @return $this
	 */
	public function setDocumentServices($documentServices)
	{
		$this->documentServices = $documentServices;
		return $this;
	}

	/**
	 * @return \Change\Documents\DocumentServices
	 */
	public function getDocumentServices()
	{
		return $this->documentServices;
	}

	/**
	 * @return \Change\Application\ApplicationServices
	 */
	public function getApplicationServices()
	{
		return $this->documentServices->getApplicationServices();
	}

	/**
	 * @return UrlManager
	 */
	public function getUrlManager()
	{
		return $this->urlManager;
	}

	/**
	 * @param \Rbs\Media\Documents\Image|integer $image
	 * @param integer $maxWidth
	 * @param integer $maxHeight
	 * @return string
	 */
	public function imageURL($image, $maxWidth = 0, $maxHeight = 0)
	{
		if (is_numeric($image))
		{
			$image = $this->getDocumentServices()->getDocumentManager()->getDocumentInstance($image);
		}
		if (is_array($maxWidth))
		{
			$size = array_values($maxWidth);
			$maxWidth = isset($size[0]) ? $size[0] : 0;
			$maxHeight = isset($size[1]) ? $size[1] : $maxHeight;
		}
		if (is_string($image) && strpos($image, 'change://') === 0)
		{
			$storageManager = $this->getApplicationServices()->getStorageManager();
			$storageURI = new \Zend\Uri\Uri($image);
			$storageURI->setQuery(array('max-width' => intval($maxWidth), 'max-height' => intval($maxHeight)));
			$url = $storageURI->normalize()->toString();
			return $storageManager->getPublicURL($url);
		}
		elseif ($image instanceof \Rbs\Media\Documents\Image)
		{
			return $image->getPublicURL(intval($maxWidth), intval($maxHeight));
		}
		else
		return '';
	}

	/**
	 * @param \Change\Documents\AbstractDocument|integer $document
	 * @param \Change\Presentation\Interfaces\Section|null $section
	 * @param array $query
	 * @param string|null $LCID
	 * @return string
	 */
	public function documentURL($document, $section, $query = array(), $LCID = null)
	{
		if (is_numeric($document) || $document instanceof \Change\Documents\AbstractDocument)
		{
			if ($section === null || $section instanceof \Change\Presentation\Interfaces\Website)
			{
				$this->getUrlManager()->getCanonicalByDocument($document, $section, $query, $LCID)->normalize()->toString();
			}
			elseif ($section instanceof \Change\Presentation\Interfaces\Section)
			{
				$this->getUrlManager()->getByDocument($document, $section, $query, $LCID)->normalize()->toString();
			}
		}
		return '';
	}

	/**
	 * @param array $query
	 * @param string|null $fragment
	 * @return string
	 */
	public function currentURL($query = array(), $fragment = null)
	{
		$uri = $this->getUrlManager()->getSelf();
		if (is_array($query) && count($query))
		{
			$uri->setQuery(array_merge($uri->getQueryAsArray(), $query));
		}
		if ($fragment)
		{
			$uri->setFragment($fragment);
		}
		return $uri->normalize()->toString();
	}
}