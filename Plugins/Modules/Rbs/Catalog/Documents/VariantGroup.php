<?php
namespace Rbs\Catalog\Documents;

use Change\Documents\AbstractModel;
use Change\Documents\Events\Event;
use Rbs\Catalog\Std\AttributeEngine;

/**
 * @name \Rbs\Catalog\Documents\VariantGroup
 */
class VariantGroup extends \Compilation\Rbs\Catalog\Documents\VariantGroup
{
	/**
	 * @param AbstractModel $documentModel
	 */
	public function setDefaultValues(AbstractModel $documentModel)
	{
		parent::setDefaultValues($documentModel);
		$this->setAxesInfo(array());
	}

	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		parent::attachEvents($eventManager);
		$eventManager->attach(Event::EVENT_CREATED, array($this, 'onCreated'));
	}

	protected function onCreate()
	{
		if (!$this->getLabel() && $this->getRootProduct())
		{
			$this->setLabel($this->getRootProduct()->getLabel());
		}

		$this->initAxisInfo();
	}

	protected function onUpdate()
	{
		if ($this->isPropertyModified('axisAttribute'))
		{
			$this->initAxisInfo();
		}

		if ($this->isPropertyModified('productMatrixInfo'))
		{
			$tm = $this->getApplicationServices()->getTransactionManager();
			try
			{
				$tm->begin();
				$this->normalizeProductMatrix();
				$tm->commit();
			}
			catch (\Exception $e)
			{
				throw $tm->rollBack($e);
			}
		}
	}

	protected function initAxisInfo()
	{
		$axesInfo = $this->getAxesInfo();
		if (count($axesInfo) === 0)
		{
			$axesInfo = array();
			$attrEngine = new AttributeEngine($this->getDocumentServices());
			$axisAttributes = $attrEngine->getAxisAttributes($this->getAxisAttribute());
			foreach ($axisAttributes as $axisAttribute)
			{
				$axis = array('id' => $axisAttribute->getId(), 'dv' => $attrEngine->getCollectionValues($axisAttribute));
				if (!is_array($axis['dv']))
				{
					$axis['dv'] = array();
				}
				$axis['cat'] = $axisAttribute->isVisibleFor('categorization');
				$axesInfo[] = $axis;
			}
			$this->setAxesInfo($axesInfo);
		}
	}

	/**
	 * @param Event $event
	 */
	public function onCreated(Event $event)
	{
		/** @var $variantGroup VariantGroup */
		$variantGroup = $event->getDocument();
		$product = $variantGroup->getRootProduct();
		if ($product instanceof Product)
		{
			$product->setCategorizable(true);
			$product->setVariant(false);
			$product->setVariantGroup($variantGroup);
			$product->update();
		}
	}

	protected function normalizeProductMatrix()
	{
		$pmi = $this->getProductMatrixInfo();
		$axesInfo = array_reduce($this->getAxesInfo(), function($r, $i) {
			$r[$i['id']] = $i;
			return $r;
		}, array());

		$added = array();
		$pmiCount = count($pmi);

		//fix removed tree
		$addRemoved = 1;
		$removed = array();
		while ($addRemoved)
		{
			--$addRemoved;
			for ($i = 0; $i < $pmiCount; $i++)
			{
				if (isset($pmi[$i]['removed']))
				{
					if (!isset($removed[$pmi[$i]['id']]))
					{
						$removed[$pmi[$i]['id']] = $pmi[$i]['id'];
						++$addRemoved;
					}
				}
				elseif (isset($removed[$pmi[$i]['parentId']]))
				{
					$pmi[$i]['removed'] = true;
					++$addRemoved;
				}
				elseif (!isset($axesInfo[$pmi[$i]['axisId']]))
				{
					$pmi[$i]['removed'] = true;
					++$addRemoved;
				}
			}
		}

		$parentIds = array_reduce($pmi, function($r, $i) {
			if (!isset($i['removed']) && $i['id'] > 0) {$r[] = $i['id'];} return $r;
		}, array($this->getRootProductId()));

		for ($i = 0; $i < count($pmi); $i++)
		{
			$entry = $pmi[$i];
			if ($entry['id'] < 0)
			{
				/* @var $product Product */
				$axesValues = $this->buildAxesValues($entry, $pmi);

				$product = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Catalog_Product');
				$product->setLabel($this->getLabel() . ' - ' . $this->buildProductLabel($entry, $pmi, $axesInfo, 'label'));
				$product->getCurrentLocalization()->setTitle($product->getLabel());
				$product->setVariantGroup($this);
				$product->setAttribute($this->getAxisAttribute());
				$product->setAttributeValues($axesValues);
				$product->setCategorizable(($axesInfo[$entry['axisId']]['cat'] == true));
				$product->setNewSkuOnCreation($this->getNewSkuOnCreation() && !$entry['variant']);
				$product->create();
				$added[$entry['id']] = $product->getId();
			}
			elseif ($entry['id'] > 0)
			{
				if (isset($entry['removed']) || !in_array($entry['id'], $parentIds))
				{
					$product = $this->getDocumentManager()->getDocumentInstance($entry['id']);
					if ($product instanceof Product)
					{
						$product->delete();
					}
				}
			}
		}

		$productMatrixInfo = array();

		foreach ($pmi as $entry)
		{
			if ($entry['id'] == 0 || !isset($axesInfo[$entry['axisId']]) || isset($entry['removed']))
			{
				continue;
			}

			if ($entry['id'] < 0)
			{
				if (!isset($added[$entry['id']]))
				{
					continue;
				}
				$entry['id'] = $added[$entry['id']];
			}

			if ($entry['parentId'] < 0)
			{
				if (!isset($added[$entry['parentId']]))
				{
					continue;
				}
				$entry['parentId'] = $added[$entry['parentId']];
			}
			else if (!in_array($entry['parentId'], $parentIds))
			{
				continue;
			}
			$productMatrixInfo[] = $entry;
		}

		$this->setProductMatrixInfo($productMatrixInfo);
	}

	/**
	 * @param array $entry
	 * @param array $productMatrixInfo
	 * @param array $axesInfo
	 * @param string $type title|label|value
	 * @return string
	 */
	protected function buildProductLabel($entry, $productMatrixInfo, $axesInfo, $type = 'label')
	{
		$pe = $this->getProductMatrixEntryById($entry['parentId'], $productMatrixInfo);
		if ($pe)
		{
			$label = $this->buildProductLabel($pe, $productMatrixInfo, $axesInfo, $type) . ' - ';
		}
		else
		{
			$label = '';
		}

		$av = $entry['axisValue'];
		if ($type != 'value' && isset($axesInfo[$entry['axisId']]))
		{
			$ai = $axesInfo[$entry['axisId']];
			foreach ($ai['dv'] as $dv)
			{
				if ($dv['value'] == $av)
				{
					$av = $dv[$type];
					break;
				}
			}
		}
		return $label . $av;
	}

	/**
	 * @param integer $id
	 * @param array $productMatrixInfo
	 * @return array|null
	 */
	protected function getProductMatrixEntryById($id, $productMatrixInfo)
	{
		foreach ($productMatrixInfo as $entry)
		{
			if ($entry['id'] == $id)
			{
				return $entry;
			}
		}
		return null;
	}

	/**
	 * @param array $entry
	 * @param array $productMatrixInfo
	 * @return array
	 */
	protected function buildAxesValues($entry, $productMatrixInfo)
	{
		$axesValue = array();
		$axesValue[] = array('id' => $entry['axisId'], 'value' => $entry['axisValue']);
		$parentEntry = $this->getProductMatrixEntryById($entry['parentId'], $productMatrixInfo);
		if ($parentEntry)
		{
			$axesValue = array_merge($this->buildAxesValues($parentEntry, $productMatrixInfo), $axesValue);
		}
		return $axesValue;
	}

	protected function updateRestDocumentResult($documentResult)
	{
		parent::updateRestDocumentResult($documentResult);
		$axesDefinition = $this->buildAxesDefinition();
		$documentResult->setProperty('axesDefinition', array_values($axesDefinition));
	}

	/**
	 * @return array
	 */
	protected function buildAxesDefinition()
	{
		$axesDefinition = array();

		$attrEngine = new AttributeEngine($this->getDocumentServices());
		foreach ($this->getAxesInfo() as $axisInfo)
		{
			$axisAttribute = $this->getDocumentManager()->getDocumentInstance($axisInfo['id']);
			if (!($axisAttribute instanceof Attribute))
			{
				continue;
			}
			$def = $attrEngine->buildAttributeDefinition($axisAttribute);
			if ($def)
			{
				$axesDefinition[$axisInfo['id']] = $def;
			}
		}
		return $axesDefinition;
	}
}