<?php
namespace Rbs\Price\Documents;

use Change\Documents\Events\Event;
use Change\Http\Rest\Result\DocumentLink;
use Change\Http\Rest\Result\DocumentResult;

/**
 * @name \Rbs\Price\Documents\Price
 */
class Price extends \Compilation\Rbs\Price\Documents\Price implements \Rbs\Price\PriceInterface
{
	/**
	 * @return string
	 */
	public function getLabel()
	{
		if ($this->getBillingArea() &&  $this->getBillingArea()->getCurrencyCode())
		{
			$nf = new \NumberFormatter($this->getDocumentManager()->getLCID(), \NumberFormatter::CURRENCY);
			return $nf->formatCurrency($this->getBoValue(), $this->getBillingArea()->getCurrencyCode());
		}
		return $this->getBoValue();
	}

	/**
	 * @param string $label
	 * @return $this
	 */
	public function setLabel($label)
	{
		// The label is dynamically generated.
		return $this;
	}

	/**
	 * @return float|null
	 */
	public function getValue()
	{
		return $this->getDefaultValue();
	}

	/**
	 * @return boolean
	 */
	public function isDiscount()
	{
		return $this->getBasePrice() != null  && $this->getBasePrice()->activated();
	}

	/**
	 * @return float|null
	 */
	public function getBasePriceValue()
	{
		return ($this->isDiscount()) ? $this->getBasePrice()->getValue() : null;
	}

	/**
	 * @param \Rbs\Commerce\CommerceServices $commerceServices
	 * @return boolean
	 */
	public function applyBoValues(\Rbs\Commerce\CommerceServices $commerceServices)
	{
		if ($this->getBoValue() !== null)
		{
			$this->updateValuesFromBo($this->getBoValue(), $commerceServices);
			return true;
		}
		return false;
	}

	/**
	 * @param \Rbs\Commerce\CommerceServices $commerceServices
	 * @return null|\Rbs\Price\Tax\TaxManager
	 */
	protected function getBoTaxManager(\Rbs\Commerce\CommerceServices $commerceServices)
	{
		$ba = $this->getBillingArea();
		$taxCategories = $this->getTaxCategories();
		if (is_array($taxCategories))
		{
			$taxCodes = array_keys($taxCategories);
			$zone = null;
			foreach ($ba->getTaxes() as $tax)
			{
				if (in_array($tax->getCode(), $taxCodes))
				{
					$zone = $tax->getDefaultZone();
					break;
				}
			}

			if ($zone)
			{
				$commerceServices->getContext()->setBillingArea($ba)->setZone($zone);
				return $commerceServices->getTaxManager();
			}
		}
		return null;
	}

	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(Event::EVENT_CREATE, array($this, 'onDefaultCreate'), 10);
		$eventManager->attach(Event::EVENT_UPDATE, array($this, 'onDefaultUpdate'), 10);
	}

	public function onDefaultCreate(Event $event)
	{
		if ($this->getBoValue() !== null)
		{
			$commerceServices = $event->getServices('commerceServices');
			if ($commerceServices instanceof \Rbs\Commerce\CommerceServices)
			{
				$this->updateValuesFromBo($this->getBoValue(), $commerceServices);
			}
			else
			{
				throw new \RuntimeException('Commerce services not set', 999999);
			}
		}
		if ($this->getStartActivation() === null)
		{
			$this->setStartActivation(new \DateTime());
		}
	}

	public function onDefaultUpdate(Event $event)
	{
		if ($this->isPropertyModified('boValue'))
		{
			$commerceServices = $event->getServices('commerceServices');
			if ($commerceServices instanceof \Rbs\Commerce\CommerceServices)
			{
				$this->updateValuesFromBo($this->getBoValue(), $commerceServices);
			}
			else
			{
				throw new \RuntimeException('Commerce services not set', 999999);
			}

		}

		if ($this->getStartActivation() === null)
		{
			$this->setStartActivation(new \DateTime());
		}

		// Check if property taxCategories is modified and price has associated discount prices
		if ($this->isPropertyModified('taxCategories') && $this->countPricesBasedOn() > 0)
		{
			$arguments = ['basePriceId' => $this->getId()];
			$job = $event->getApplicationServices()->getJobManager()->createNewJob('Rbs_Price_UpdateTax', $arguments);

			// Save meta on price
			$this->setMeta('Job_UpdateTax', $job->getId());
			$this->saveMetas();
		}

	}

	/**
	 * @param float $boValue
	 * @param \Rbs\Commerce\CommerceServices $commerceServices
	 */
	protected function updateValuesFromBo($boValue, $commerceServices)
	{
		$ba = $this->getBillingArea();
		if ($ba->getBoEditWithTax() && ($taxManager = $this->getBoTaxManager($commerceServices)) !== null)
		{
			$valueCallback = function ($valueWithTax, $taxCategories) use ($taxManager) {
				$taxApplications = $taxManager->getTaxByValueWithTax($valueWithTax, $taxCategories);
				foreach ($taxApplications as $taxApplication)
				{
					/* @var $taxApplication \Rbs\Price\Tax\TaxApplication */
					$valueWithTax -= $taxApplication->getValue();
				}
				return $valueWithTax;
			};

			$this->setBoEditWithTax(true);
			$taxCategories = $this->getTaxCategories();
			$boValue =  $valueCallback($boValue,  $taxCategories);
		}
		else
		{
			$this->setBoEditWithTax(false);
		}

		$this->setDefaultValue($boValue);
	}

	public function onDefaultUpdateRestResult(Event $event)
	{
		parent::onDefaultUpdateRestResult($event);

		/** @var $restResult DocumentLink|DocumentResult */
		$restResult = $event->getParam('restResult');
		if ($restResult instanceof DocumentLink || $restResult instanceof DocumentResult)
		{
			/* @var $price \Rbs\Price\Documents\Price */
			$price = $event->getDocument();
			$nf = new \NumberFormatter($event->getApplicationServices()->getI18nManager()->getLCID(), \NumberFormatter::CURRENCY);

			$restResult->setProperty('formattedBoValue', $nf->formatCurrency($price->getBoValue(), $price->getBillingArea()->getCurrencyCode()));
			$basePrice = $price->getBasePrice();
			if ($basePrice)
			{
				$restResult->setProperty('formattedBoBaseValue', $nf->formatCurrency($basePrice->getBoValue(), $basePrice->getBillingArea()->getCurrencyCode()));
			}
			else
			{
				$restResult->setProperty('formattedBoBaseValue', null);
			}

			if ($this->getMeta('Job_UpdateTax') !== null)
			{
				$restResult->setProperty('hasJobToUpdateTax', true);
			}

			if ($restResult instanceof \Change\Http\Rest\Result\DocumentLink)
			{
				$extraColumn = $event->getParam('extraColumn');
				if (in_array('basePrice', $extraColumn))
				{
					if ($price->getBasePrice() !== null)
					{
						$restResult->setProperty('basePrice', ['id' => $price->getBasePrice()->getId(), 'model' => $price->getDocumentModelName()]);
					}
				}
			}
		}
	}

	/**
	 * Return the number of discount prices based on current price
	 * @return int
	 */
	public function countPricesBasedOn()
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Price_Price');
		$query->andPredicates($query->eq('basePrice', $this));
		return $query->getCountDocuments();
	}

	/**
	 * Return the lists of discount prices based on current price
	 * @return \Change\Documents\DocumentCollection
	 */
	public function getPricesBasedOn()
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Price_Price');
		$query->andPredicates($query->eq('basePrice', $this));
		return $query->getDocuments();
	}

}