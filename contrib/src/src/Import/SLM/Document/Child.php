<?php

namespace App\Import\SLM\Document;

class Child
{
    /** @var string */
    private $id;
    /** @var string */
    private $type;
    /** @var string */
    private $label;

    private function __construct(string $type, string $label)
    {
        $this->id = sha1($type.$label);
        $this->type = $type;
        $this->label = $label;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getEMSId(): string
    {
        return sprintf('%s:%s', $this->type, $this->id);
    }

    public static function create(string $type, ?string $label = null): ?Child
    {
        if (null == $label) {
            return null;
        }

        return new Child($type, $label);
    }
}