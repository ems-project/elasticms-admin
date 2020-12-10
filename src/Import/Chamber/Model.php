<?php

namespace App\Import\Chamber;

use App\Import\Chamber\XML\SearchTypes;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\DocumentInterface;

abstract class Model implements DocumentInterface
{
    /** @var string */
    private $id;
    /** @var string */
    private $type;
    /** @var Import */
    protected $import;
    /** @var array */
    protected $source = [];
    /** @var SearchTypes */
    protected $searchTypes;

    public const TYPES = [
        self::TYPE_ACTR => 'ACTR',
        self::TYPE_CCRA => 'CCRA',
        self::TYPE_CCRI => 'CCRI',
        self::TYPE_FLWB => 'FLWB',
        self::TYPE_INQO => 'INQO',
        self::TYPE_MTNG => 'MTNG',
        self::TYPE_ORGN => 'ORGN',
        self::TYPE_PCRA => 'PCRA',
        self::TYPE_PCRI => 'PCRI',
        self::TYPE_QRVA => 'QRVA',
    ];

    public const TYPE_ACTR        = 'actr';
    public const TYPE_ACTR_ROLE   = 'actr_role';
    public const TYPE_ACTR_MEMBER = 'actr_member';
    public const TYPE_FLWB        = 'flwb';
    public const TYPE_GENESIS     = 'genesis';
    public const TYPE_CCRA        = 'ccra';
    public const TYPE_CCRI        = 'ccri';
    public const TYPE_INQO        = 'inqo';
    public const TYPE_MTNG        = 'mtng';
    public const TYPE_ORGN        = 'orgn';
    public const TYPE_PCRA        = 'pcra';
    public const TYPE_PCRI        = 'pcri';
    public const TYPE_QRVA        = 'qrva';

    public const COLLECTION_ACTR_MEMBERS = 'actr_members';

    public function __construct(Import $import, string $type, string $id)
    {
        $this->import = $import;
        $this->type = $type;
        $this->id = self::createId($type, $id);
        $this->source['show_nl'] = true;
        $this->source['show_fr'] = true;
        $this->source['show_de'] = false;
        $this->source['show_en'] = false;
        $this->source['last_import'] = date('c');
    }

    public function isValid(): bool
    {
        return true;
    }

    public static function createId(string $type, string $id): string
    {
        return sha1($type.$id);
    }

    public static function createEmsId(string $type, string $id): string
    {
        return $type . ':' . self::createId($type, $id);
    }

    public static function createDate(string $value, ?string $format = 'Ymd'): ?string
    {
        $date = \DateTime::createFromFormat($format, $value);

        return $date ? $date->format('Y-m-d') : null;
    }

    public static function isAssoc(array $arr)
    {
        if (array() === $arr) return false;

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getEmsId(): string
    {
        return EMSLink::fromText($this->getType() . ':' . $this->getId());
    }



    public static function arrayFilterFunction($val) {
        if (is_array($val)) {
            return !empty($val);
        } else {
            return ($val !== null && $val !== '');
        }
    }


    public function getSource(): array
    {
        $source = $this->source;
        ksort($source);

        return \array_filter($source, [Model::class, 'arrayFilterFunction']);
    }
}
