<?php

namespace Papper;

/**
 * Main configuration object holding all mapping configuration for a source and destination type
 *
 * @author Vladimir Komissarov <dr0id@dr0id.ru>
 */
class TypeMap
{
	/**
	 * @var string
	 */
	private $destinationType;
	/**
	 * @var string
	 */
	private $sourceType;
	/**
	 * @var ObjectCreatorInterface
	 */
	private $objectCreator;
	/**
	 * @var PropertyMap[]
	 */
	private $propertyMaps = array();
	/**
	 * @var \Closure|null
	 */
	private $beforeMapFunc;
	/**
	 * @var \Closure|null
	 */
	private $afterMapFunc;

	public function __construct($sourceType, $destinationType, ObjectCreatorInterface $objectCreator)
	{
		$this->destinationType = $destinationType;
		$this->sourceType = $sourceType;
		$this->objectCreator = $objectCreator;
	}

	/**
	 * @return string
	 */
	public function getDestinationType()
	{
		return $this->destinationType;
	}

	/**
	 * @return string
	 */
	public function getSourceType()
	{
		return $this->sourceType;
	}

	/**
	 * @return ObjectCreatorInterface
	 */
	public function getObjectCreator()
	{
		return $this->objectCreator;
	}

	/**
	 * @param ObjectCreatorInterface $objectCreator
	 */
	public function setObjectCreator(ObjectCreatorInterface $objectCreator)
	{
		$this->objectCreator = $objectCreator;
	}

	/**
	 * @return PropertyMap[]
	 */
	public function getPropertyMaps()
	{
		return $this->propertyMaps;
	}

	/**
	 * @return PropertyMap[]
	 */
	public function getMappedPropertyMaps()
	{
		return array_filter($this->propertyMaps, function (PropertyMap $propertyMap) {
			return $propertyMap->isMapped() && !$propertyMap->isIgnored();
		});
	}

	/**
	 * @return PropertyMap[]
	 */
	public function getUnmappedPropertyMaps()
	{
		return array_filter($this->propertyMaps, function (PropertyMap $propertyMap) {
			return !$propertyMap->isMapped();
		});
	}

	/**
	 * @param PropertyMap $propertyMap
	 */
	public function addPropertyMap(PropertyMap $propertyMap)
	{
		$this->propertyMaps[$propertyMap->getMemberName()] = $propertyMap;
	}

	/**
	 * @param string $memberName
	 * @return null|PropertyMap
	 */
	public function getPropertyMap($memberName)
	{
		return isset($this->propertyMaps[$memberName])
			? $this->propertyMaps[$memberName]
			: null;
	}

	/**
	 * @return callable|null
	 */
	public function getBeforeMapFunc()
	{
		return $this->beforeMapFunc;
	}

	/**
	 * @param callable $func
	 */
	public function setBeforeMapFunc(\Closure $func)
	{
		$this->beforeMapFunc = $func;
	}

	/**
	 * @return callable|null
	 */
	public function getAfterMapFunc()
	{
		return $this->afterMapFunc;
	}

	/**
	 * @param callable $func
	 */
	public function setAfterMapFunc(\Closure $func)
	{
		$this->afterMapFunc = $func;
	}

	/**
	 * @todo validate source members
	 * @todo validate constructor args
	 */
	public function validate()
	{
		$unmappedProperties = $this->getUnmappedPropertyMaps();

		if (empty($unmappedProperties)) {
			return;
		}

		$memberNames = array_map(function (PropertyMap $propertyMap) {
			return $propertyMap->getMemberName();
		}, $unmappedProperties);

		throw new ValidationException(sprintf(
			"Unmapped members were found. Add a custom mapping expression, ignore, " .
			"add a custom resolver, or modify the source/destination type:\n%s -> %s\nDestination members: %s",
			$this->sourceType, $this->destinationType, implode(", ", $memberNames)
		));
	}

	public function getMapFunc()
	{
		$objectCreator = $this->getObjectCreator();
		$propertyMaps = $this->getMappedPropertyMaps();
		$sourceType = $this->getSourceType();
		$destinationType = $this->getDestinationType();
		$beforeMapFunc = $this->getBeforeMapFunc();
		$afterMapFunc = $this->getAfterMapFunc();

		return function ($source, $destination = null) use ($objectCreator, $propertyMaps, $sourceType, $destinationType, $beforeMapFunc, $afterMapFunc) {

			if ($destination === null) {
				$destination = $objectCreator->create($source);
			}

			if (!$destination instanceof $destinationType) {
				$type = is_object($destination) ? get_class($destination) : gettype($destination);
				$message = sprintf('Constructed object type expected %s, but actual %s', $destinationType, $type);
				throw new ValidationException($message);
			}

			if (!$source instanceof $sourceType) {
				$type = is_object($source) ? get_class($source) : gettype($source);
				$message = sprintf('Source object type expected %s, but actual %s', $destinationType, $type);
				throw new ValidationException($message);
			}

			if ($beforeMapFunc) {
				$beforeMapFunc($source, $destination);
			}

			foreach ($propertyMaps as $propertyMap) {
				$value = $propertyMap->getSourceGetter()->getValue($source);
				if ($propertyMap->hasValueConverter()) {
					$value = $propertyMap->getValueConverter()->convert($value);
				}
				if ($value === null) {
					$value = $propertyMap->getNullSubtitute();
				}
				$propertyMap->getDestinationSetter()->setValue($destination, $value);
			}

			if ($afterMapFunc) {
				$afterMapFunc($source, $destination);
			}

			return $destination;
		};
	}
}
