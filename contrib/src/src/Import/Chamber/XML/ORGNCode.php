<?php

namespace App\Import\Chamber\XML;

class ORGNCode
{
    /** @var string */
    private $code;
    /** @var int */
    private $level;
    /** @var string */
    private $root;
    /** @var string */
    private $legislature;
    /** @var string */
    private $category;
    /** @var ?string */
    private $group;
    /** @var string */
    private $last;

    private $translations = [];

    const LEVELS = [
        0 => 'root',
        1 => 'subRoot',
        2 => 'legislature',
        3 => 'category',
        4 => 'group',
        5 => 'element'
    ];

    const CATEGORIES = [
        4 => [ // level 4
            1 => 'conference_of_presidents',
            2 => 'bureau',
            3 => 'college_of_quaestors',
            4 => 'plenary',
            5 => 'commissions',
            6 => 'political_groups',
        ],
        6 => [ // level 6
            4 => 'plenary',
            5 => 'commission',
            6 => 'political_group',
        ]
    ];

    public function __construct(string $code, string $pathFr, string $pathNl)
    {
        $this->code = $code;
        $explode = \explode('.', $code);
        $this->level = \count($explode);

        $explodePathFr = array_values(array_filter(\explode('/', $pathFr)));
        $explodePathNl = array_values(array_filter(\explode('/', $pathNl)));

        foreach ($explode as $i => $value) {
            if (isset(self::LEVELS[$i])) {
                $this->{self::LEVELS[$i]} = $value;

                $this->translations[self::LEVELS[$i]] = array_filter([
                    'fr' => $explodePathFr[$i] ?? null,
                    'nl' => $explodePathNl[$i] ?? null
                ]);
            }
        }

        $this->last = \array_pop($explode);
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function isValid(): bool
    {
        return $this->legislature !== null;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLegislature(): ?int
    {
        return $this->legislature ? (int) $this->legislature : null;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getLast(): string
    {
        return $this->last;
    }

    public function getType(): ?string
    {
        if ($this->level === 6 && $this->getTranslation('category', 'fr') === 'Groupes Politiques') {
            return 'political_group';
        }

        return self::CATEGORIES[$this->level][(int) $this->getCategory()] ?? null;
    }

    public function getTranslation(string $level, string $locale): ?string
    {
        return $this->translations[$level][$locale] ?? null;
    }
}