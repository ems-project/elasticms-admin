<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\MergeInterface;
use App\Import\Chamber\Model;
use EMS\CommonBundle\Elasticsearch\DocumentInterface;

class ACTRMember extends Model implements MergeInterface
{
    use XML;

    private $legislature;
    private $data;

    private $parties = [];

    const WRONG_NAMES = [
        'Guido Swennen' => 'Guy Swennen',
        'Karine Jiroflée' => 'Karin Jiroflée',
        'Hélène Clément' => 'Hélène Couplet-Clément',
        'Jean Marie Dedecker' => 'Jean-Marie Dedecker',
    ];

    public function __construct(Import $import, array $data, int $legislature)
    {
        $this->import = $import;
        $this->legislature = $legislature;
        $this->process($data);

        $fullName = $this->data['first_name'] . ' ' . $this->data['last_name'];
        $actor = $this->searchACTRByFullName($fullName, $legislature);

        if (null === $actor) {
            throw new \LogicException(sprintf('Actor %s not found for legislature %d', $fullName, $legislature));
        }

        $this->source = $actor['_source'];

        $this->source['is_member'] = true;
        $this->source['id_ksegna'] = $this->data['id_ksegna'];

        $this->mergeCV($this->source['cv']??[]);

        $partySeed = ORGNParty::getSeed($this->data['party'], $this->data['party']);

        $partyId = Model::createEmsId(Model::TYPE_ORGN, $partySeed);
        $partyNL = $this->import->getPartyName($partyId, 'nl');
        $partyFR = $this->import->getPartyName($partyId, 'fr');

        if ($partyNL && $partyFR) {
            $this->source['party'] = $partyId;
            $this->source['party_nl'] = $partyNL;
            $this->source['party_fr'] = $partyFR;
            $this->source['search_parties'] = \array_values(\array_unique(\array_merge(
                [$partyId],
                $this->source['search_parties'] ?? []
            )));
        } else {
            $this->source['party_nl'] = $this->data['party'];
            $this->source['party_fr'] = $this->data['party'];
        }

        parent::__construct($import, Model::TYPE_ACTR, $this->source['id_actr']);
    }

    private function mergeCV(array $currentCV)
    {

        $filtered = [];
        foreach ($currentCV as $cv) {
            $filtered[intval($cv['legislature'])] = $cv;
        }

        $filtered[intval($this->legislature)] = $this->getCV();

        $this->source['cv'] = array_values($filtered);
    }

    public function merge(DocumentInterface $current)
    {
        $currentCV = $current->getSource()['cv'] ?? [];

        $this->mergeCV($currentCV);


        $this->source['search_parties'] = \array_values(\array_unique(\array_merge(
            $current->getSource()['search_parties'] ?? [],
            $this->source['search_parties'] ?? []
        )));
    }

    protected function getRootElements(): array
    {
        return [
            'NALEGIS', 'NAFNAAM', 'NAVNAAM', 'CVF', 'CVN',
            'PUBEMAIL', 'EMAIL', 'WEBSITE',
            'KSEGNA', 'PERSON_ID',
            'NAFONAM', 'NAAKTIE', 'NATYPEP', 'NAGROUP', 'GROUPE',
            'PARENT_REF_ID', 'ROLE_REFERENCE_TYPO_ID', 'TYPO_ID',
            'MAJNAAM', 'LANGUAGE_CODE', 'EMAIL', 'WEBSITE'
        ];
    }

    protected function getCallbacks(): array
    {
        $stringCallback = function (string $value) { return trim($value); };

        return [
            'KSEGNA' => ['data', 'id_ksegna', $stringCallback], //Don't cast to int, prefix with letter O, example O1057
            'PERSON_ID' => ['data', 'id_actr', $stringCallback],
            'NAFNAAM' => ['data', 'last_name', $stringCallback],
            'NAGROUP' => ['data', 'party', $stringCallback],
            'EMAIL' => ['data', 'email', $stringCallback],
            'WEBSITE' => ['data', 'website', $stringCallback],
            'CVF' => ['data', 'description_fr', $stringCallback],
            'CVN' => ['data', 'description_nl', $stringCallback],
        ];
    }

    protected function parseNavnaam(string $value)
    {
        if (\strpos($value, ',') > 0) {
            $explode = \explode(',', $value);
            $value = \array_shift($explode);
        }

        $this->data['first_name'] = $value;
    }

    private function getCV(): array
    {
        return [
            'legislature' => $this->legislature,
            'party' => $this->data['party'],
            'email' => $this->data['email'] ?? null,
            'website' => $this->data['website'] ?? null,
            'description_fr' => $this->data['description_fr'],
            'description_nl' => $this->data['description_nl'],
        ];
    }

    private function searchACTRByFullName(string $fullName, int $legislature): ?array
    {
        $search = $this->import->search([
            'query' => [
                'bool' => [
                    'must' => [
                        ['term' => ['full_name.keyword' => ['value' => $fullName]]],
                        ['term' => ['legislature' => ['value' =>$legislature]]]
                    ]
                ]
            ]
        ]);

        if ($search['hits']['total'] === 1) {
            return $search['hits']['hits'][0];
        }

        if (array_key_exists($fullName, self::WRONG_NAMES)) {
            return $this->searchACTRByFullName(self::WRONG_NAMES[$fullName], $legislature);
        }

        return null;
    }
}