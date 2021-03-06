<?php
namespace Change\Storage;

/**
* @name \Change\Storage\ItemInfo
*/
class ItemInfo extends \SplFileInfo
{
	/**
	 * @var \Change\Storage\Engines\AbstractStorage
	 */
	protected $storageEngine;

	/**
	 * @param string $file_name
	 */
	public function __construct($file_name)
	{
		parent::__construct($file_name);
		$this->setInfoClass(__CLASS__);
	}

	/**
	 * @param null $class_name
	 * @return \SplFileInfo|void
	 */
	public function getFileInfo($class_name = null)
	{
		$result = parent::getFileInfo($class_name);
		if ($result instanceof ItemInfo)
		{
			$result->setStorageEngine($this->getStorageEngine());
		}
		return $result;
	}

	/**
	 * @param null $class_name
	 * @return \SplFileInfo|void
	 */
	public function getPathInfo($class_name = null)
	{
		$result =  parent::getPathInfo($class_name);
		if ($result instanceof ItemInfo)
		{
			$result->setStorageEngine($this->getStorageEngine());
		}
		return $result;
	}

	/**
	 * @param \Change\Storage\Engines\AbstractStorage $storageEngine
	 */
	public function setStorageEngine(\Change\Storage\Engines\AbstractStorage $storageEngine)
	{
		$this->storageEngine = $storageEngine;
	}

	/**
	 * @return \Change\Storage\Engines\AbstractStorage
	 */
	public function getStorageEngine()
	{
		return $this->storageEngine;
	}

	/**
	 * @return string|null
	 */
	public function getMimeType()
	{
		return $this->getStorageEngine()->getMimeType($this->getPathname());
	}

	/**
	 * @return string|null
	 */
	public function getPublicURL()
	{
		return $this->getStorageEngine()->getPublicURL($this->getPathname());
	}
}