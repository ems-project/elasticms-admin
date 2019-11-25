<?php

namespace App\Import\Trade4u;

class CPV
{
    /** @var string */
    private $division;
    /** @var string */
    private $group;
    /** @var string */
    private $class;
    /** @var string */
    private $category;

    /** @var string division|group|class|category */
    private $type;

    /** @var array */
    private $body;

    const REGEX = "/^(?'category'(?'class'(?'group'(?'division'\d{2})\d{1})\d{1})\d{1})(?'id'\d{3})-\d{1}$/";

    const CHILD_TYPES = [
        'division' => 'group',
        'group' => 'class',
        'class' => 'category'
    ];

    public function __construct(array $body)
    {
        \preg_match(self::REGEX, $body['code'], $code);

        $this->division = $code['division'];
        $this->group = $code['group'];
        $this->class = $code['class'];
        $this->category = $code['category'];
        $this->body = $body;

        $this->setType();
    }

    public function addChild(CPV $cpv): void
    {
        $this->body['children'][] = 'cpv:'.$cpv->getId();
    }

    public function getId(): string
    {
        return sha1('cpv'.$this->body['code']);
    }

    public function getBody(): array
    {
        return  array_merge($this->body, ['cpv_type' => $this->type]);
    }

    public function getDivision(): string
    {
        return $this->division;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    private function getType(): string
    {
        return $this->type;
    }

    private function setType(): void
    {
        if ($this->category === $this->division . '000') {
            $this->type = 'division';
        } elseif ($this->category === $this->group . '00') {
            $this->type = 'group';
        } elseif ($this->category === $this->class . '0') {
            $this->type = 'class';
        } else {
            $this->type = 'category';
        }
    }

    /**
     * @param CPV[] $items
     */
    public function setChildren(array $items)
    {
        $childType = self::CHILD_TYPES[$this->type] ?? false;

        if ($childType) {
            $this->addChildByType($items, $childType);
        }
    }

    /**
     * @param CPV[] $items
     */
    private function addChildByType(array $items, string $childType)
    {
        $get = 'get'.\ucfirst($this->type);

        foreach ($items as $item) {
            if ($childType === $item->getType() && $this->$get() === $item->$get()) {
                $this->addChild($item);
            }
        }
    }
}