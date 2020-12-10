<?php

declare(strict_types=1);

namespace App\Command\TechSearch;

final class Url
{
    /** @var string */
    private $loc;
    /** @var string */
    private $lastMod;
    /** @var array */
    private $languages = [];
    /** @var string */
    private $owner;
    /** @var string */
    private $type;
    /** @var array */
    private $facets = [];
    /** @var null|string */
    private $serviceId;
    /** @var null|string */
    private $serviceName;
    /** @var null|string */
    private $serviceKeywords;
    /** @var array */
    private $descriptions = [];

    public function __construct(array $data)
    {
        $this->loc = $data['loc'];
        $this->lastMod = 'last_published_date' === $data['lastmod'] ?
            '2018-12-13T10:39:15+0100' : $data['lastmod'];

        $this->owner = $data['owner'];
        $this->type = $data['type'];

        $this->languages = $this->parseDataPropertyToArray($data, 'language');
        $this->facets = $this->parseDataPropertyToArray($data, 'facet');

        $this->setDescriptions($data);
        $this->setService($data);
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    public function getUrl(): string
    {
        return $this->loc;
    }

    public function toArray(): array
    {
        $data = [
            'url' => $this->loc,
            'lastmod' => $this->lastMod,
            'languages' => $this->languages,
            'owner' => $this->owner,
            'type' => $this->type,
            'facets' => $this->facets,
            'service_id' => $this->serviceId,
            'keywords' => $this->serviceKeywords,
        ];

        foreach ($this->languages as $language) {
            $data['url_'.$language] = $this->loc;
            $data['service_name_'.$language] = $this->serviceName;
        }

        foreach ($this->descriptions as $description) {
            $data = array_merge($data, $description);
        }

        return $data;
    }

    private function parseDataPropertyToArray(array $data, string $property): array
    {
        if (!isset($data[$property])) {
            return [];
        }

        return is_string($data[$property]) ? [$data[$property]] : array_values($data[$property]);
    }

    private function setDescriptions(array $data): void
    {
        $descriptions = $data['description'] ?? [];
        $nested = isset($descriptions['language']) ? [$descriptions] : $descriptions;

        foreach ($nested as $description) {
            $lang = $description['language'] ?? $description['Language'];

            $this->descriptions[] = array_filter([
                ('title_'.$lang) => $description['title'],
                ('body_'.$lang) => $description['body'] ?? null,
            ]);
        }
    }

    private function setService(array $data): void
    {
        if (!isset($data['service'])) {
            return;
        }

        $this->serviceId = $data['service']['id'];
        $this->serviceName = $data['service']['name'];
        $this->serviceKeywords = $data['service']['keywords'] ?? null;
    }
}