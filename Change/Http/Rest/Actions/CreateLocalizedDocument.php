<?php
namespace Change\Http\Rest\Actions;

use Change\Documents\Interfaces\Editable;
use Change\Documents\Interfaces\Localizable;
use Change\Http\Rest\PropertyConverter;
use Change\Http\Rest\Result\DocumentLink;
use Change\Http\Rest\Result\DocumentResult;
use Change\Http\Rest\Result\ErrorResult;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Change\Http\Rest\Actions\CreateLocalizedDocument
 */
class CreateLocalizedDocument
{
	/**
	 * Use Required Event Params: documentId, modelName
	 * @param \Change\Http\Event $event
	 * @throws \RuntimeException
	 * @throws \Exception
	 */
	public function execute($event)
	{
		$modelName = $event->getParam('modelName');
		$model = ($modelName) ? $event->getDocumentServices()->getModelManager()->getModelByName($modelName) : null;
		if (!$model || !$model->isLocalized())
		{
			throw new \RuntimeException('Invalid Parameter: modelName', 71000);
		}

		$documentId = intval($event->getParam('documentId'));
		if ($documentId <= 0)
		{
			throw new \RuntimeException('Invalid Parameter: documentId', 71000);
		}
		$documentManager = $event->getDocumentServices()->getDocumentManager();

		$document = $documentManager->getDocumentInstance($documentId, $model);
		if ($document === null)
		{
			$document = $documentManager->getDocumentInstance($documentId);
			if ($document !== null)
			{
				throw new \RuntimeException('Invalid Parameter: documentId', 71000);
			}

			$document = $documentManager->getNewDocumentInstanceByModel($model);
			$document->initialize($documentId);
		}

		if (!($document instanceof Localizable))
		{
			throw new \RuntimeException('Invalid Parameter: documentId', 71000);
		}

		$LCID = $event->getParam('LCID');

		$properties = $event->getRequest()->getPost()->toArray();
		if (isset($properties['LCID']))
		{
			if ($LCID === null)
			{
				$LCID = $properties['LCID'];
			}
			elseif ($LCID !== $properties['LCID'])
			{
				$LCID = null;
			}
		}

		if (!$LCID || !$event->getApplicationServices()->getI18nManager()->isSupportedLCID($LCID))
		{
			$supported = $event->getApplicationServices()->getI18nManager()->getSupportedLCIDs();
			$errorResult = new ErrorResult('INVALID-LCID', 'Invalid LCID property value', HttpResponse::STATUS_CODE_409);
			$errorResult->addDataValue('value', $LCID);
			$errorResult->addDataValue('supported-LCID', $supported);
			$event->setResult($errorResult);
			return;
		}

		/* @var $document \Change\Documents\AbstractDocument|Localizable */
		$transactionManager = $event->getApplicationServices()->getTransactionManager();
		try
		{
			$transactionManager->begin();

			$documentManager->pushLCID($LCID);
			if ($document->getCurrentLocalization()->isNew())
			{
				if ($document instanceof Editable)
				{
					if (!isset($properties['authorId']) || intval($properties['authorId']) === 0)
					{
						$user = $event->getAuthenticationManager()->getCurrentUser();
						$properties['authorId'] = $user->getId();
						$properties['authorName'] = $user->getName();
					}
				}
				$event->setParam('LCID', $LCID);
				$this->create($event, $document, $properties);
			}
			else
			{
				/* @var $document Localizable */
				$definedLCIDArray = $document->getLCIDArray();
				$supported = array_values(array_diff($event->getApplicationServices()->getI18nManager()->getSupportedLCIDs(),
					$definedLCIDArray));
				$errorResult = new ErrorResult('INVALID-LCID', 'Invalid LCID property value', HttpResponse::STATUS_CODE_409);
				$errorResult->addDataValue('value', $LCID);
				$errorResult->addDataValue('supported-LCID', $supported);
				$event->setResult($errorResult);
			}
			$documentManager->popLCID();

			$transactionManager->commit();
		}
		catch (\Exception $e)
		{
			throw $transactionManager->rollBack($e);
		}
	}

	/**
	 * @param \Change\Http\Event $event
	 * @param \Change\Documents\AbstractDocument $document
	 * @param array $properties
	 * @throws \Exception
	 * @return DocumentResult
	 */
	protected function create($event, $document, $properties)
	{
		$urlManager = $event->getUrlManager();
		foreach ($document->getDocumentModel()->getProperties() as $name => $property)
		{
			/* @var $property \Change\Documents\Property */
			if (array_key_exists($name, $properties))
			{
				try
				{
					$c = new PropertyConverter($document, $property, $urlManager);
					$c->setPropertyValue($properties[$name]);
				}
				catch (\Exception $e)
				{
					$errorResult = new ErrorResult('INVALID-VALUE-TYPE', 'Invalid property value type', HttpResponse::STATUS_CODE_409);
					$errorResult->setData(array('name' => $name, 'value' => $properties[$name], 'type' => $property->getType()));
					$errorResult->addDataValue('document-type', $property->getDocumentType());
					$event->setResult($errorResult);
					return;
				}
			}
		}

		/* @var $document \Change\Documents\AbstractDocument|Localizable */
		try
		{
			$document->save();
			$event->setParam('LCID', $document->getLCID());

			$getLocalizedDocument = new GetLocalizedDocument();
			$getLocalizedDocument->execute($event);

			$result = $event->getResult();
			if ($result instanceof DocumentResult)
			{
				$result->setHttpStatusCode(HttpResponse::STATUS_CODE_201);
			}
		}
		catch (\Change\Documents\PropertiesValidationException $e)
		{
			$errors = $e->getPropertiesErrors();
			$errorResult = new ErrorResult('VALIDATION-ERROR', 'Document properties validation error', HttpResponse::STATUS_CODE_409);
			if (count($errors) > 0)
			{
				$i18nManager = $event->getApplicationServices()->getI18nManager();
				$pe = array();
				foreach ($errors as $propertyName => $errorsMsg)
				{
					foreach ($errorsMsg as $errorMsg)
					{
						$pe[$propertyName][] = $i18nManager->trans($errorMsg);
					}
				}
				$errorResult->addDataValue('properties-errors', $pe);
			}
			$event->setResult($errorResult);
			return;
		}
	}
}
