<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\MergeInterface;
use App\Import\Chamber\Model;
use EMS\CommonBundle\Elasticsearch\DocumentInterface;

class ORGNParty extends Model implements MergeInterface
{
    const INDEPENDENT = 'OnafhankelijkIndépendant';

    public function __construct(Import $import, DocumentInterface $orgn)
    {
        $orgnSource = $orgn->getSource();

        $partySeed = self::getSeed($orgnSource['title_nl'], $orgnSource['title_fr']);
        if (self::INDEPENDENT === $partySeed) {
            $orgnSource['title_fr'] = 'Indépendant';
            $orgnSource['title_nl'] = 'Onafhankelijk';
        }

        $this->source = [
            'id_orgn' => 'p'.$orgnSource['id_orgn'],
            'type_orgn' => 'party',
            'title_fr' => $orgnSource['title_fr'],
            'title_nl' => $orgnSource['title_nl'],
            'description_fr' => $orgnSource['description_fr'],
            'description_nl' => $orgnSource['description_nl'],
            'legislature' => [$orgnSource['legislature']],
            'orgn_ids' => [
                sprintf('%s:%s', $orgn->getType(), $orgn->getId())
            ],
            'orgn_business_ids' => [ $orgnSource['id_orgn'] ]
        ];

        parent::__construct($import, Model::TYPE_ORGN, $partySeed);
    }


    public static function getSeed(string $titleNl, string $titleFr){
        if (in_array(\trim($titleNl), [
                'ONAFH',
                'INDEP',
                'ONAFH-INDEP',
                'INDEP-ONAFH',
            ]) || in_array(\trim($titleFr), [
                'ONAFH',
                'INDEP',
                'ONAFH-INDEP',
                'INDEP-ONAFH',
            ])) {
            return self::INDEPENDENT;
        }
        return $titleNl.$titleFr;
    }

    public function merge(DocumentInterface $current)
    {
        $currentSource = $current->getSource();

        $legislatures = \array_unique(\array_merge($currentSource['legislature'], $this->source['legislature']));
        sort($legislatures);

        $this->source['orgn_ids'] = \array_unique(\array_merge($currentSource['orgn_ids'], $this->source['orgn_ids']));
        $this->source['orgn_business_ids'] = \array_unique(\array_merge($currentSource['orgn_business_ids'], $this->source['orgn_business_ids']));
        $this->source['legislature'] =$legislatures;
    }
}