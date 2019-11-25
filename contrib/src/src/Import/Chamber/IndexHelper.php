<?php

namespace App\Import\Chamber;

use EMS\CommonBundle\Elasticsearch\DocumentInterface;
use EMS\CoreBundle\Elasticsearch\Indexer;

class IndexHelper
{
    /** @var Indexer */
    private $indexer;
    /** @var string */
    private $environment;
    /** @var string */
    private $path;
    /** @var string */
    private $timestamp;
    /** @var array */
    private $indexes = [];
    /** @var string */
    private $name;
    /** @var array */
    private $required = [];
    /** @var array */
    private $indexRollbackBlacklist = [];

    const INDEX_POSTFIX_ROLLBACK_BLACKLIST = ['department_qrva', 'department_inqo', 'keyword'];

    public function __construct(Indexer $indexer, string $environment, string $importId)
    {
        $this->indexer = $indexer;
        $this->environment = $environment;

        $this->name = 'webchamber_import_'.$importId;
        $this->timestamp = date("Ymd_His");

        $this->indexRollbackBlacklist = \array_map(
            function ($postfix) {
                return sprintf('%s_%s', $this->name, $postfix);
            },
            self::INDEX_POSTFIX_ROLLBACK_BLACKLIST
        );
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getIndex(DocumentInterface $doc): string
    {
        $removeRegex = null;
        $name = $this->name . '_' . $doc->getType();

        if (\in_array($doc->getType(), [ Model::TYPE_CCRA,  Model::TYPE_CCRI, Model::TYPE_PCRA, Model::TYPE_PCRI])) {
            $removeRegex = sprintf('/%s/', $name);
            $name .= '_'.$this->timestamp;
        }

        if (\in_array($doc->getType(), [Model::TYPE_INQO, Model::TYPE_FLWB, Model::TYPE_QRVA, Model::TYPE_MTNG])) {
            $name .= '_' . $doc->getSource()['legislature'];
            $removeRegex = sprintf('/%s/', $name);
            $name .= '_'.$this->timestamp;
        }

        if (!\array_key_exists($name, $this->indexes)) {
            if (!$this->indexer->exists($name)) {
                $this->indexer->create($name, $this->getSettings(), $this->getMappings());
            }
            //else {
            //    $this->indexer->update($name, $this->getSettings(), $this->getMappings());
            //}

            $this->indexes[$name] = $removeRegex;
        }

        return $name;
    }

    public function switchIndexes(bool $clean)
    {
        $alias = 'webchamber_ma_'.$this->environment;

        foreach ($this->indexes as $name => $removeRegex) {
            $this->indexer->atomicSwitch($alias, $name, $removeRegex, $clean);
        }
    }

    public function rollbackIndexes()
    {
        $deleteIndexes = \array_filter(
            \array_keys($this->indexes),
            function ($index) {
                return !\in_array($index, $this->indexRollbackBlacklist);
            }
        );

        foreach ($deleteIndexes as $name) {
            $this->indexer->delete($name);
        }
    }

    public function getRequired(string $type): array
    {
        if (isset($this->required[$type])) {
            return $this->required[$type];
        }

        $mapping = $this->getMappings();
        $meta = $mapping['doc']['_meta'] ?? [];

        $this->required[$type] = isset($meta['required'][$type]) ? \explode(', ', $meta['required'][$type]) : [];

        return $this->required[$type];
    }

    private function getMappings(): array
    {
        return \json_decode(\file_get_contents(__DIR__.'/index_mapping.json'), true);
    }

    private function getSettings(): array
    {
        return \json_decode(\file_get_contents(__DIR__.'/index_settings.json'), true);
    }
}
