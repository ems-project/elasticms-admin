<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\Model;

class Agenda extends Model
{
    use XML;
    private $data = [];
    /** @var \DateTime */
    private $date;

    public function __construct(array $data, Import $import)
    {
        $this->import = $import;
        $this->process($data);

        $time = $this->data['time'] ?? '00:00:00';
        $this->date = \DateTime::createFromFormat('Y-m-dH:i:s', ($this->data['date']. $time));
        $this->source['date_agenda'] = $this->date->format('Y-m-d H:i:s');
        $this->source['date_day_agenda'] = $this->date->format('Y-m-d');
        $this->source['date_hour_agenda'] = $this->date->format('H:i:s');
        $this->source['week'] = $this->date->format('W');

        $title = $this->date->format('d-m-Y');
        if (isset($this->data['time'])) {
            $title .= ' ' . $this->date->format('H:i');
        }
        $this->source['title_nl'] = $title;
        $this->source['title_fr'] = $title;

        $this->source['id_agenda'] = $this->source['agenda_organ'].'_'.$this->date->format('Ymd').'_'.$this->source['number_meeting'];

        $legislature = $import->getLegislatureByDate($this->date);
        $this->source['legislature'] = $legislature['id'];

        $this->source['search_type'] = 'agenda';
        $this->source['search_types'] = SearchTypes::single(SearchCategories::CAT_MTNG, $this->source['legislature']);
        $this->source['search_dates'] = [$this->date->format('Y-m-d')];
        $this->source['search_date_sort'] = $this->date->format('Y-m-d H:i:s');

        parent::__construct($import, Model::TYPE_MTNG, $this->source['id_agenda']);
    }

    protected function getRootElements(): array
    {
        return [
            '@schemaVersion', '@modification',
            'title', 'id', 'organ', 'date', 'time', 'status',
            'building', 'room', 'meetingNumber', 'agenda'
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (string $value) { return (int) $value; };
        $stringCallback = function (string $value) { return (string) $value; };

        return [
            '@modification' => ['source', 'number_modification', $intCallback],
            'status' => ['source', 'status_mtng', $stringCallback],
            'meetingNumber' => ['source', 'number_meeting', $intCallback],
            'date' => ['data', 'date', $stringCallback],
            'time' => ['data', 'time', $stringCallback],
        ];
    }

    protected function parseORGAN(string $value): void
    {
        $this->source['agenda_organ'] = $value;

        if (\strtolower($value) === 'plen') {
            $this->source['type_agenda'] = 'plen';
            return;
        }
        if (\strtolower(\substr($value, 0, 4)) === 'comm') {
            $this->source['type_agenda'] = 'ci';
            return;
        }

        throw new \RuntimeException(sprintf('invalid organ %s', $value));
    }

    protected function parseTitle(array $data): void
    {
        $normalize = $this->normalize($data);
        $this->source['info_nl'] = $normalize['nl'] ?? null;
        $this->source['info_fr'] = $normalize['fr'] ?? null;
    }

    protected function parseBuilding(array $data): void
    {
        $normalize = $this->normalize($data);
        $this->source['building_nl'] = $normalize['nl'] ?? null;
        $this->source['building_fr'] = $normalize['fr'] ?? null;
    }

    protected function parseRoom(array $data): void
    {
        $normalize = $this->normalize($data);
        $this->source['room_nl'] = $normalize['nl'] ?? null;
        $this->source['room_fr'] = $normalize['fr'] ?? null;
    }

    protected function parseAgenda(array $data): void
    {
        $normalize = $this->normalize($data);

        if (isset($normalize['section']['item'])) {
            $normalize['section'] = [$normalize['section']];
        }

        $sections = $normalize['section'] ?? [];

        if (isset($sections['para'])) {
            $sections = [
                [
                    'item' => $sections,
                    'description_nl' => $sections['description_nl'] ?? '',
                    'description_fr' => $sections['description_fr'] ?? '',
                ],
            ];
        }

        foreach ($sections as &$section) {
            if (isset($section['item']['para'])) {
                $section['item'] = [$section['item']];
            }

            if (isset($section['item'])) {
                foreach ($section['item'] as &$item) {
                    if (self::isAssoc($item['para'])) {
                        $item['para'] = [$item['para']];
                    }
                    foreach ($item['para'] as &$p) {
                        if (isset($p['annexes']['annex']) && self::isAssoc($p['annexes']['annex'])) {
                            $p['annexes']['annex'] = [$p['annexes']['annex']];
                        }
                    }
                }
            }


        }
        $normalize['section'] = $sections;

        $this->source['agenda'] = $normalize;
    }

    private function normalize(array &$data)
    {
        foreach ($data as $key => &$value) {
            if ($key === 'id') {
                unset($data[$key]);
                continue;
            } elseif ($key === 'label') {
                foreach ($value as $label) {
                    if (null != $label['#']) {
                        $data[$label['@lang']] = $label['#'];
                    }
                }
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $value = array_filter($value);
                if (null == $value) {
                    unset($data[$key]);
                } else {
                    if (null == $this->normalize($value)) {
                        unset($data[$key]);
                    }
                }
            }

            if ($key === 'description' || $key === 'link') {
                foreach ($value as $k => $v) {
                    $data[$key.'_'.$k] = $v;
                }
                unset($data[$key]);
            } elseif (substr($key, 0, 1) === '@') {
                $data[substr($key, 1)] = $value;
                unset($data[$key]);
            }
        }

        return $data;
    }
}
