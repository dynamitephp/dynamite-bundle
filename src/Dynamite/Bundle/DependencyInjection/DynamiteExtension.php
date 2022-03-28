<?php

declare(strict_types=1);

namespace Dynamite\Bundle\DependencyInjection;

use Dynamite\ItemManager;
use Dynamite\ItemManagerRegistry;
use Dynamite\Mapping\ItemMappingReader;
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
        $configObject = new Configuration();
        $config = $this->processConfiguration($configObject, $configs);

        $registryDefinition = new Definition(ItemManagerRegistry::class);

        $annotationReaderRef = new Reference($config['annotation_reader_id']);
        $itemMappingReaderDef = new Definition(ItemMappingReader::class);
        $itemMappingReaderDef->setArgument('$reader', $annotationReaderRef);

        $container->setDefinition(self::MAPPING_READER_ID, $itemMappingReaderDef);

        foreach ($config['tables'] as $instanceName => $instanceConfiguration) {
            /**
             * @TODO: some prettier way to do itemmanager specifig logger?
             */
            $instanceLogger = new Definition(Logger::class);
            $instanceLogger->setArgument('$name', sprintf(self::LOGGER_NAME, $instanceName));
            $instanceLogger->setArgument('$handlers', [new Reference('monolog.handler.main')]);
            $instanceLogger->setPrivate(true);
            $container->setDefinition(sprintf('dynamite.logger.%s', $instanceName), $instanceLogger);

            $tableConfigurationId = sprintf('dynamite.table_configuration.%s', $instanceName);
            $tableConfigurationDefinition = new Definition(TableSchema::class);
            $tableConfigurationDefinition->setArgument('$tableName', $instanceConfiguration['table_name']);
            $tableConfigurationDefinition->setArgument('$partitionKeyName', $instanceConfiguration['partition_key_name']);
            $tableConfigurationDefinition->setArgument('$sortKeyName', $instanceConfiguration['sort_key_name']);
            $tableConfigurationDefinition->setArgument('$indexes', $instanceConfiguration['indexes']);
            $tableConfigurationDefinition->setPrivate(true);
            $container->setDefinition($tableConfigurationId, $tableConfigurationDefinition);

            $instanceDefinition = new Definition(ItemManager::class);
            $instanceDefinition->setArgument('$client', new Reference($instanceConfiguration['connection']));
            $instanceDefinition->setArgument('$logger', new Reference(sprintf('dynamite.logger.%s', $instanceName)));
            $instanceDefinition->setArgument('$mappingReader', new Reference(self::MAPPING_READER_ID));
            $instanceDefinition->setArgument('$managedObjects', $instanceConfiguration['managed_items']);
            $instanceDefinition->setPublic(true);
            $instanceDefinition->setArgument('$tableSchema', new Reference($tableConfigurationId));

            $registryDefinition->addMethodCall('addManagedTable', [$instanceName, $instanceDefinition]);
        }

        $container->setDefinition('dynamite.registry', $registryDefinition);
    }
}
