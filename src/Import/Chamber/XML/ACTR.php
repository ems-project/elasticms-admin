<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Elasticsearch\ACTRIndexedMember;
use App\Import\Chamber\Import;
use App\Import\Chamber\IndexHelper;
use App\Import\Chamber\Model;
use EMS\CoreBundle\Elasticsearch\ParentDocument;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Example http://data.dekamer.be/v0/actr/1
 */
class ACTR extends Model implements ParentDocument
{
    use XML;
    /** @var ACTRRole[] */
    private $roles = [];
    private $parties = [];
    private $allParties = [];
    private $partiesLogs = [];
    /** @var IndexHelper  */
    private $indexHelper;

    public function __construct(SplFileInfo $file, Import $import, IndexHelper $indexHelper)
    {
        $this->import = $import;
        $this->indexHelper = $indexHelper;
        $this->searchTypes = new SearchTypes();

        $data = $this->xmlToArray($file);
        $processData = $data['items']['actr:person'] ?? $data;
        $this->process($processData);

        $this->source['full_name'] = trim(vsprintf('%s %s', [
            $this->source['first_name'] ?? '',
            $this->source['last_name'] ?? ''
        ]));

        $this->source['title_nl'] = \trim($this->source['last_name'] ?? '') . ' ' . \trim($this->source['first_name'] ?? '');
        $this->source['title_fr'] = $this->source['title_nl'];

        if (\preg_match('/^n\d$/i', $this->source['last_name'] ?? '')) {
            $this->source['party'] = $this->source['first_name']; //Seat not yet allocated in commission
        }

        parent::__construct($import, Model::TYPE_ACTR, $this->source['id_actr']);

        if ($this->isValid()) {
            $this->setSearch($import);
        }

        if (!$import->hasKeepCv()) {
            return;
        }

        $member =  new ACTRIndexedMember($import, $indexHelper->getIndex($this), $this->source['id_actr']);
        if ($member->isValid()) {
            $this->source = \array_merge($this->source, $member->getSource());
        }
    }

    public function isValid(): bool
    {
        return $this->roles != null;
    }

    public function getChildren(): array
    {
        return $this->roles;
    }

    protected function clean($value, $key): bool
    {
        if (is_string($value) && trim($value) == '') {
            return true;
        }

        return false;
    }

    protected function getRootElements(): array
    {
        return [
            '@id',
            'actr:link',
            'actr:name',
            'actr:fName',
            'actr:initOtherForeName',
            'actr:otherForeName',
            'actr:languageCode',
            'actr:roles'
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (string $value) { return (int) $value; };
        $stringCallback = function (string $value) { return trim($value); };
        $languages = ['N' => 'NL', 'F' => 'FR', 'D' => 'DE', 'A' => 'DE'];
        $languageCallback = function (string $value) use ($languages) { return $languages[$value]; };

        return [
            '@id' => ['source', 'id_actr', $intCallback],
            'actr:name' => ['source', 'last_name', $stringCallback],
            'actr:fName' => ['source', 'first_name', $stringCallback],
            'actr:initOtherForeName' => ['source', 'initials', $stringCallback],
            'actr:otherForeName' => ['source', 'middle_names', $stringCallback],
            'actr:languageCode' => ['source', 'language', $languageCallback],
        ];
    }

    protected function parseActrroles(array $value): void
    {
        $nested = isset($value['actr:role']['@id']) ? [$value['actr:role']] : $value['actr:role'];
        $emsLinkActr = self::createEmsId(Model::TYPE_ACTR, $this->source['id_actr']);

        $notAssigned = isset($this->source['last_name']) && $this->source['last_name'] === 'N' && isset($this->source['first_name']) && $this->source['first_name'] === 'N';

        $legislatures = [];

        foreach ($nested as $roleData) {
            $role = new ACTRRole($this->import, $roleData, $emsLinkActr);

            if ($role->isValid()) {
                $this->roles[] = $role;
                $legislatures[] = $role->getLegislature();

                if ($categories = $role->getSearchCategories($notAssigned)) {
                    $this->searchTypes->addTypes($categories, $role->getLegislature());
                }

                if ($role->getOrgnType() === 'political_group') {
                    $partyId = $this->import->getParty($role->getOrgn());
                    if ($partyId === null) {
                        continue;
                    }

                    $this->parties[$role->getLegislature()] = $partyId;
                    $this->allParties[] = $partyId;
                    $this->partiesLogs[] = [
                        'date_start' => $role->getSource()['date_start'] ?? null,
                        'date_end' => $role->getSource()['date_end'] ?? null,
                        'party' => $partyId,
                        'legislature' => $role->getLegislature(),
                        'party_nl' => $this->import->getPartyName($partyId, 'nl'),
                        'party_fr' => $this->import->getPartyName($partyId, 'fr'),
                    ];

                }
            }
        }

        if (!$this->searchTypes->hasTypes()) {
            $this->searchTypes->addTypes([SearchCategories::CAT_ACTR_OTHER]);
        }

        $legislatures = array_unique(array_filter($legislatures));
        sort($legislatures);
        $this->source['legislature'] = \array_values($legislatures);
    }

    private function setSearch(Import $import)
    {
        $dates = $import->getLegislatureDates($this->source['legislature']);

        $parties = \array_filter($this->parties);

        if ($parties != null) {
            krsort($parties);
            $current = array_shift($parties);

            $this->source['party'] = $current;
            $this->source['party_nl'] = $this->import->getPartyName($current, 'nl');
            $this->source['party_fr'] = $this->import->getPartyName($current, 'fr');
            $this->source['search_parties'] = \array_values(\array_unique(\array_filter($this->allParties)));
        }

        if (!empty($this->partiesLogs)) {
            $this->source['party_logs'] = $this->partiesLogs;
        }

        $this->source['search_type'] = 'actr';
        $this->source['search_types'] = $this->searchTypes->getTypes();
        $this->source['search_id'] = $this->source['id_actr'];
        $this->source['search_dates'] = $dates;
        $this->source['search_date_sort'] = $dates[0] ?? null;

        $actrId = self::createEmsId(Model::TYPE_ACTR, $this->source['id_actr']);
        $this->source['search_actors'] = [$actrId];
        $this->source['search_actors_types'] = [['actr' => $actrId]];

        if (isset($this->source['last_name']) && in_array($this->source['last_name'], ['N', 'N1', 'N2', 'N3', 'N4'])) {
            $this->source['show_fr'] = false;
            $this->source['show_nl'] = false;
        }
    }
}
