<?php
namespace Change\Http\Rest\Result;

/**
 * @name \Change\Http\Rest\Result\Link
 */
class Link
{
	/**
	 * @var \Change\Http\UrlManager
	 */
	protected $urlManager;

	/**
	 * @var string
	 */
	protected $rel;

	/**
	 * @var string
	 */
	protected $pathInfo;

	/**
	 * @var string|array
	 */
	protected $query;

	/**
	 * @var string
	 */
	protected $method;

	/**
	 * @param \Change\Http\UrlManager $urlManager
	 * @param null $pathInfo
	 * @param string $rel
	 */
	public function __construct(\Change\Http\UrlManager $urlManager, $pathInfo = null, $rel = 'self')
	{
		$this->urlManager = $urlManager;
		$this->rel = $rel;
		$this->pathInfo = $pathInfo;
	}

	/**
	 * @param \Change\Http\UrlManager $urlManager
	 */
	public function setUrlManager(\Change\Http\UrlManager $urlManager)
	{
		$this->urlManager = $urlManager;
	}

	/**
	 * @return \Change\Http\UrlManager
	 */
	public function getUrlManager()
	{
		return $this->urlManager;
	}

	/**
	 * @param string $pathInfo
	 */
	public function setPathInfo($pathInfo)
	{
		$this->pathInfo = $pathInfo;
	}

	/**
	 * @return string
	 */
	public function getPathInfo()
	{
		return $this->pathInfo;
	}

	/**
	 * @param array|string $query
	 */
	public function setQuery($query)
	{
		$this->query = $query;
	}

	/**
	 * @return array|string
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * @param string $rel
	 */
	public function setRel($rel)
	{
		$this->rel = $rel;
	}

	/**
	 * @return string
	 */
	public function getRel()
	{
		return $this->rel;
	}

	/**
	 * @param string $method
	 */
	public function setMethod($method)
	{
		$this->method = $method;
	}

	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}

	/**
	 * @return string
	 */
	public function href()
	{
		return $this->urlManager->getByPathInfo($this->getPathInfo())->setQuery($this->getQuery())->normalize()->toString();
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		$array = array('rel' => $this->getRel(), 'href' => $this->href());
		if ($this->getMethod())
		{
			$array['method'] = $this->getMethod();
		}
		return $array;
	}
}