<?php

declare(strict_types=1);

namespace Dynamite\Bundle\DependencyInjection;

use Aws\DynamoDb\Marshaler;
use Dynamite\ItemManager;
use Dynamite\ItemManagerRegistry;
use Dynamite\ItemSerializer;
use Dynamite\Mapping\ItemMappingReader;
use Dynamite\PrimaryKey\Filter\LowercaseFilter;
use Dynamite\PrimaryKey\Filter\Md5Filter;
use Dynamite\PrimaryKey\Filter\UppercaseFilter;
use Dynamite\PrimaryKey\Filter\UppercaseFirstFilter;
use Dynamite\PrimaryKey\KeyFormatResolver;
use Dynamite\TableSchema;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author pizzaminded <mikolajczajkowsky@gmail.com>
 * @license MIT
 */
class DynamiteExtension extends Extension
{
    private const MAPPING_READER_ID = 'dynamite.item_mapping_reader';

    private const LOGGER_NAME = 'dynamite.%s';

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $filters = [
            'md5' => Md5Filter::class,
            'upper' => UppercaseFilter::class,
            'lower' => LowercaseFilter::class,
            'uccase' => UppercaseFirstFilter::class
        ];

        $configObject = new Configuration();
        $config = $this->processConfiguration($configObject, $configs);

        $registryDefinition = new Definition(ItemManagerRegistry::class);

        $annotationReaderRef = new Reference($config['annotation_reader_id']);

        $itemMappingReaderDef = new Definition(ItemMappingReader::class);
        $itemMappingReaderDef->setArgument('$reader', $annotationReaderRef);
        $container->setDefinition(self::MAPPING_READER_ID, $itemMappingReaderDef);

        $itemSerializerDef = new Definition(ItemSerializer::class);
        $container->setDefinition(ItemSerializer::class, $itemSerializerDef);

        $marshalerDef = new Definition(Marshaler::class);
        $container->setDefinition(Marshaler::class, $marshalerDef);

        $formatResolverDef = new Definition(KeyFormatResolver::class);
        $container->setDefinition(KeyFormatResolver::class, $formatResolverDef);

        foreach ($filters as $name => $filterFqcn) {
            $filterDef = new Definition($filterFqcn);
            $container->setDefinition($filterFqcn, $registryDefinition);
            $formatResolverDef->addMethodCall('addFilter', [$name, $filterDef]);
        }

        foreach ($config['tables'] as $instanceName => $instanceConfiguration) {
            /**
             * @TODO: some prettier way to do itemmanager specifig logger?
             */
            $instanceLogger = new Definition(Logger::class);
            $instanceLogger->setArgument('$name', sprintf(self::LOGGER_NAME, $instanceName));
            $instanceLogger->setArgument('$handlers', [new Reference('monolog.handler.main')]);
            $instanceLogger->setPublic(false);
            $container->setDefinition(sprintf('dynamite.logger.%s', $instanceName), $instanceLogger);

            $tableConfigurationId = sprintf('dynamite.table_configuration.%s', $instanceName);
            $tableConfigurationDefinition = new Definition(TableSchema::class);
            $tableConfigurationDefinition->setArgument('$tableName', $instanceConfiguration['table_name']);
            $tableConfigurationDefinition->setArgument('$partitionKeyName', $instanceConfiguration['partition_key_name']);
            $tableConfigurationDefinition->setArgument('$sortKeyName', $instanceConfiguration['sort_key_name']);
            $tableConfigurationDefinition->setArgument('$indexes', $instanceConfiguration['indexes']);
            $tableConfigurationDefinition->setArgument('$objectTypeAttrName', $instanceConfiguration['object_type_attr']);
            $tableConfigurationDefinition->setPublic(false);
            $container->setDefinition($tableConfigurationId, $tableConfigurationDefinition);

            $instanceDefinition = new Definition(ItemManager::class);
            $instanceDefinition->setBindings([
                '$client' => new Reference($instanceConfiguration['connection']),
                '$tableSchema' => new Reference($tableConfigurationId),
                '$managedObjects' => $instanceConfiguration['managed_items'],
                '$mappingReader' => new Reference(self::MAPPING_READER_ID),
                '$itemSerializer' => new Reference(ItemSerializer::class),
                '$keyFormatResolver' => new Reference(KeyFormatResolver::class),
                '$marshaler' => new Reference(Marshaler::class),
                '$logger' => new Reference(sprintf('dynamite.logger.%s', $instanceName))
            ]);

            $instanceDefinition->setPublic(true);
            $registryDefinition->addMethodCall('addManagedTable', [$instanceDefinition]);
        }

        $container->setDefinition('dynamite.registry', $registryDefinition);
    }
}
