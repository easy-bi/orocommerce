<?php

namespace Oro\Bundle\CMSBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionFieldConfig;
use Oro\Bundle\ApiBundle\Processor\GetConfig\ConfigContext;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\ApiBundle\Util\EntityFieldFilteringHelper;
use Oro\Bundle\CMSBundle\Provider\WYSIWYGFieldsProvider;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * The base processor to configure WYSIWYG fields.
 */
abstract class ConfigureWYSIWYGFields implements ProcessorInterface
{
    public const FIELD_VALUE      = 'value';
    public const FIELD_STYLE      = 'style';
    public const FIELD_PROPERTIES = 'properties';

    private const WYSIWYG_FIELDS          = 'wysiwyg_fields';
    private const RENDERED_WYSIWYG_FIELDS = 'rendered_wysiwyg_fields';

    /** @var WYSIWYGFieldsProvider */
    private $wysiwygFieldsProvider;

    /** @var EntityFieldFilteringHelper */
    private $entityFieldFilteringHelper;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /**
     * @param WYSIWYGFieldsProvider      $wysiwygFieldsProvider
     * @param EntityFieldFilteringHelper $entityFieldFilteringHelper
     * @param DoctrineHelper             $doctrineHelper
     */
    public function __construct(
        WYSIWYGFieldsProvider $wysiwygFieldsProvider,
        EntityFieldFilteringHelper $entityFieldFilteringHelper,
        DoctrineHelper $doctrineHelper
    ) {
        $this->wysiwygFieldsProvider = $wysiwygFieldsProvider;
        $this->entityFieldFilteringHelper = $entityFieldFilteringHelper;
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * Gets the list of names of WYSIWYG fields added by this processor.
     *
     * @param EntityDefinitionConfig $definition
     *
     * @return string[]|null [field name, ...]
     */
    public static function getWysiwygFields(EntityDefinitionConfig $definition): ?array
    {
        return $definition->get(self::WYSIWYG_FIELDS);
    }

    /**
     * Gets the list of names of "rendered" WYSIWYG fields added by this processor.
     * The "rendered" WYSIWYG fields is read-only nested objects with "value" and "style" properties,
     * both properties a processed by the specified TWIG template and contain ready to use HTML ans CSS.
     *
     * @param EntityDefinitionConfig $definition
     *
     * @return array|null [field path => ["value" property name, "style" property name], ...]
     */
    public static function getRenderedWysiwygFields(EntityDefinitionConfig $definition): ?array
    {
        return $definition->get(self::RENDERED_WYSIWYG_FIELDS);
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $fieldName
     *
     * @return bool
     */
    protected static function isWysiwygFieldProcessed(
        EntityDefinitionConfig $definition,
        string $fieldName
    ): bool {
        $wysiwygFields = $definition->get(self::WYSIWYG_FIELDS);

        return $wysiwygFields && \in_array($fieldName, $wysiwygFields, true);
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $fieldName
     *
     * @return bool
     */
    protected static function isRenderedWysiwygFieldProcessed(
        EntityDefinitionConfig $definition,
        string $fieldName
    ): bool {
        $renderedWysiwygFields = $definition->get(self::RENDERED_WYSIWYG_FIELDS);

        return $renderedWysiwygFields && isset($renderedWysiwygFields[$fieldName]);
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $fieldName
     */
    protected static function registerWysiwygField(
        EntityDefinitionConfig $definition,
        string $fieldName
    ): void {
        $wysiwygFields = $definition->get(self::WYSIWYG_FIELDS) ?? [];
        if (!\in_array($fieldName, $wysiwygFields, true)) {
            $wysiwygFields[] = $fieldName;
            $definition->set(self::WYSIWYG_FIELDS, $wysiwygFields);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $fieldName
     * @param string                 $valueFieldName
     * @param string                 $styleFieldName
     */
    protected static function registerRenderedWysiwygField(
        EntityDefinitionConfig $definition,
        string $fieldName,
        string $valueFieldName,
        string $styleFieldName
    ): void {
        $renderedWysiwygFields = $definition->get(self::RENDERED_WYSIWYG_FIELDS) ?? [];
        $renderedWysiwygFields[$fieldName] = [$valueFieldName, $styleFieldName];
        $definition->set(self::RENDERED_WYSIWYG_FIELDS, $renderedWysiwygFields);
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        $entityClass = $context->getClassName();
        if (!$this->doctrineHelper->isManageableEntityClass($entityClass)) {
            return;
        }

        $wysiwygFieldNames = $this->wysiwygFieldsProvider->getWysiwygFields($entityClass);
        $enabledWysiwygFieldNames = [];
        if ($wysiwygFieldNames) {
            $enabledWysiwygFieldNames = $this->entityFieldFilteringHelper->filterEntityFields(
                $entityClass,
                $wysiwygFieldNames,
                $context->getExplicitlyConfiguredFieldNames(),
                $context->getRequestedExclusionPolicy()
            );
        }
        if (!$wysiwygFieldNames) {
            return;
        }

        $definition = $context->getResult();
        foreach ($wysiwygFieldNames as $fieldName) {
            $this->configureWysiwygField(
                $context,
                $definition,
                $entityClass,
                $fieldName,
                !\in_array($fieldName, $enabledWysiwygFieldNames, true)
            );
        }
    }

    /**
     * @param ConfigContext          $context
     * @param EntityDefinitionConfig $definition
     * @param string                 $fieldName
     * @param bool                   $excluded
     */
    abstract protected function configureWysiwygField(
        ConfigContext $context,
        EntityDefinitionConfig $definition,
        string $entityClass,
        string $fieldName,
        bool $excluded
    ): void;

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $wysiwygFieldName
     * @param bool                   $excluded
     * @param string|null            $sourceWysiwygFieldName
     *
     * @return EntityDefinitionFieldConfig
     */
    protected function createWysiwygField(
        EntityDefinitionConfig $definition,
        string $wysiwygFieldName,
        bool $excluded,
        string $sourceWysiwygFieldName = null
    ): EntityDefinitionFieldConfig {
        if ($sourceWysiwygFieldName && $sourceWysiwygFieldName !== $wysiwygFieldName) {
            $sourceWysiwygFieldName = $definition->findFieldNameByPropertyPath($sourceWysiwygFieldName);
            if (null !== $sourceWysiwygFieldName) {
                $definition->addField($wysiwygFieldName, $definition->getField($sourceWysiwygFieldName));
                $definition->removeField($sourceWysiwygFieldName);
            }
        }
        $wysiwygField = $definition->getField($wysiwygFieldName);
        if (null === $wysiwygField) {
            $wysiwygField = $definition->addField($wysiwygFieldName);
        }
        $wysiwygField->setDataType(DataType::NESTED_OBJECT);
        $wysiwygField->setPropertyPath(ConfigUtil::IGNORE_PROPERTY_PATH);
        $wysiwygField->setFormOption('inherit_data', true);
        if ($excluded && !$wysiwygField->hasExcluded()) {
            $wysiwygField->setExcluded();
        }
        $wysiwygField->getOrCreateTargetEntity()->setExcludeAll();

        return $wysiwygField;
    }

    /**
     * @param EntityDefinitionFieldConfig $wysiwygField
     * @param string                      $fieldName
     */
    protected function addNestedValueField(EntityDefinitionFieldConfig $wysiwygField, string $fieldName): void
    {
        $this->addNestedField($wysiwygField, self::FIELD_VALUE, $fieldName, DataType::STRING);
    }

    /**
     * @param EntityDefinitionFieldConfig $wysiwygField
     * @param string                      $entityClass
     * @param string                      $fieldName
     */
    protected function addNestedStyleField(
        EntityDefinitionFieldConfig $wysiwygField,
        string $entityClass,
        string $fieldName
    ): void {
        $this->addNestedField(
            $wysiwygField,
            self::FIELD_STYLE,
            $this->getWysiwygStyleFieldName($entityClass, $fieldName),
            DataType::STRING
        );
    }

    /**
     * @param EntityDefinitionFieldConfig $wysiwygField
     * @param string                      $entityClass
     * @param string                      $fieldName
     */
    protected function addNestedPropertiesField(
        EntityDefinitionFieldConfig $wysiwygField,
        string $entityClass,
        string $fieldName
    ): void {
        $this->addNestedField(
            $wysiwygField,
            self::FIELD_PROPERTIES,
            $this->getWysiwygPropertiesFieldName($entityClass, $fieldName),
            DataType::OBJECT
        );
    }

    /**
     * @param EntityDefinitionFieldConfig $wysiwygField
     * @param string                      $fieldName
     * @param string                      $propertyPath
     * @param string                      $dataType
     */
    protected function addNestedField(
        EntityDefinitionFieldConfig $wysiwygField,
        string $fieldName,
        string $propertyPath,
        string $dataType
    ): void {
        $nestedField = $wysiwygField->getTargetEntity()->getOrAddField($fieldName);
        $nestedField->setPropertyPath($propertyPath);
        $nestedField->setDataType($dataType);
        if (!$nestedField->isExcluded() && ConfigUtil::IGNORE_PROPERTY_PATH !== $propertyPath) {
            $wysiwygField->addDependsOn($propertyPath);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $entityClass
     * @param string                 $fieldName
     */
    protected function configureSourceWysiwygFields(
        EntityDefinitionConfig $definition,
        string $entityClass,
        string $fieldName
    ): void {
        $valueField = $definition->getOrAddField('_' . $fieldName);
        if (!$valueField->hasPropertyPath()) {
            $valueField->setPropertyPath($fieldName);
        }
        $this->excludeField($definition, $entityClass, $fieldName);
        $this->excludeField($definition, $entityClass, $this->getWysiwygStyleFieldName($entityClass, $fieldName));
        $this->excludeField($definition, $entityClass, $this->getWysiwygPropertiesFieldName($entityClass, $fieldName));
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $entityClass
     * @param string                 $propertyPath
     */
    protected function excludeField(
        EntityDefinitionConfig $definition,
        string $entityClass,
        string $propertyPath
    ): void {
        $fieldName = $definition->findFieldNameByPropertyPath($propertyPath);
        if (!$fieldName) {
            $fieldName = $propertyPath;
            $definition->addField($fieldName);
        }
        /** @var EntityDefinitionFieldConfig $field */
        $field = $definition->getField($fieldName);
        if (!$field->hasExcluded()) {
            $field->setExcluded();
        }
        if (!$field->hasPropertyPath() && $fieldName !== $propertyPath) {
            $field->setPropertyPath($propertyPath);
        }
        if ($this->wysiwygFieldsProvider->isSerializedWysiwygField($entityClass, $propertyPath)) {
            $field->addDependsOn('serialized_data');
        }
    }

    /**
     * @param string $entityClass
     * @param string $fieldName
     *
     * @return string
     */
    protected function getWysiwygStyleFieldName(string $entityClass, string $fieldName): string
    {
        return $this->wysiwygFieldsProvider->getWysiwygStyleField($entityClass, $fieldName);
    }

    /**
     * @param string $entityClass
     * @param string $fieldName
     *
     * @return string
     */
    protected function getWysiwygPropertiesFieldName(string $entityClass, string $fieldName): string
    {
        return $this->wysiwygFieldsProvider->getWysiwygPropertiesField($entityClass, $fieldName);
    }
}
