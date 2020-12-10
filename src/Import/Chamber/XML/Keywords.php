<?php

namespace App\Import\Chamber\XML;

class Keywords
{
    private $keywords = [];

    const TYPE_KEYWORDS  = 'keywords';
    const TYPE_FREE      = 'keywords_free';
    const TYPE_CANDIDATE = 'keywords_candidate';
    const TYPE_IMPORTANT = 'keywords_main';

    private const FLWB = [
        'EDISCRIPTOR'   => self::TYPE_KEYWORDS,
        'EDESCRIPTOR'   => self::TYPE_KEYWORDS,
        'EKANDIDAAT'    => self::TYPE_CANDIDATE,
        'FREE'          => self::TYPE_FREE,
        'IMPORTANT'     => self::TYPE_IMPORTANT,
    ];

    public function add(string $type, array $data = []): void
    {
        if (!$data) {
            return;
        }

        $countFR = isset($data['fr']) ? \count($data['fr']) : 0;
        $countNL = isset($data['nl']) ? \count($data['nl']) : 0;

        if ($countFR !== $countNL) {
            return;
        }

        $i = 0;
        while ($i < $countFR) {
            $this->keywords[$type][] = Child::createKeyword([
                'title_fr' => $data['fr'][$i],
                'title_nl' => $data['nl'][$i],
                'title_de' => $data['de'][$i] ?? null,
                'show_fr' => true,
                'show_nl' => true,
                'show_de' => isset($data['de'][$i]) ? true : false,
                'show_en' => false,
            ]);
            $i++;
        }
    }

    public function addFLWB(string $type, array $data): void
    {
        if (!\array_key_exists($type, self::FLWB)) {
            throw new \Exception(sprintf('invalid keyword type : %s', $type));
        }

        $this->keywords[self::FLWB[$type]][] = Child::createKeyword([
            'code' => (int) $data[$type.'_kode'],
            'title_fr' => $data[$type . '_textF'],
            'title_nl' => $data[$type . '_textN'],
            'title_de' => $data[$type . '_textD'] ?? null,
            'show_fr' => true,
            'show_nl' => true,
            'show_de' => isset($data[$type . '_textD']) ? true : false,
            'show_en' => false,
        ]);
    }

    public function getSource(): array
    {
        $data = [];

        foreach ($this->keywords as $type => $keywords) {
            $data[$type] = array_map(function (Child $keyword) {
                return $keyword->getEmsId();
            }, $keywords);
        }

        return array_filter($data);
    }

    public function isEmpty(): bool
    {
        return null == $this->keywords;
    }

    public function all(): array
    {
        return \array_unique(array_merge(
            $this->keywords[self::TYPE_KEYWORDS] ?? [],
            $this->keywords[self::TYPE_CANDIDATE] ?? [],
            $this->keywords[self::TYPE_FREE] ?? [],
            $this->keywords[self::TYPE_IMPORTANT] ?? []
        ));
    }

    public function getKeywordsText($locale): array
    {
        return array_map(function (Child $keyword) use($locale) { return $keyword->getSource()['title_'.$locale]; }, $this->all());
    }

    public function getEmsIds(): array
    {
        return array_map(function (Child $keyword) { return $keyword->getEmsId(); }, $this->all());
    }

    public function deduplicateMainKeywords(): void
    {
        if (!isset($this->keywords[self::TYPE_IMPORTANT])) {
            return;
        }

        foreach ($this->keywords[self::TYPE_IMPORTANT] as $keyword) {
            $this->removeKeyword(self::TYPE_KEYWORDS, $keyword);
            $this->removeKeyword(self::TYPE_FREE, $keyword);
            $this->removeKeyword(self::TYPE_CANDIDATE, $keyword);
        }
    }

    private function removeKeyword(string $type, Child $keyword): void
    {
       if (isset($this->keywords[$type])) {
           $this->keywords[$type] = \array_values(\array_filter($this->keywords[$type], function (Child $child) use ($keyword) {
                return $child->getEmsId() !== $keyword->getEmsId();
            }));
        }
    }
}
