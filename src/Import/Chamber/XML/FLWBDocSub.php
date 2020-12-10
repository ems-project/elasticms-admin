<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Model;
use Psr\Log\LoggerInterface;

class FLWBDocSub
{
    use XML;
    protected $source = [];
    /** @var FLWB */
    private $flwb;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(Model $flwb, LoggerInterface $logger, array $data)
    {
        $this->flwb = $flwb;
        $this->logger = $logger;

        $this->source['show_nl'] = $flwb->getSource()['show_nl'] ?? null;
        $this->source['show_fr'] = $flwb->getSource()['show_fr'] ?? null;
        $this->source['show_de'] = $flwb->getSource()['show_de'] ?? null;
        $this->source['show_en'] = $flwb->getSource()['show_en'] ?? null;
        $this->process($data);
    }

    public function getSource(): array
    {
        $source = $this->source;
        ksort($source);
        return \array_filter($source, [Model::class, 'arrayFilterFunction']);
    }

    public function getDocType(): ?string
    {
        return $this->source['doc_type'] ?? null;
    }

    protected function getRootElements(): array
    {
        return [
            'SUBDOCNR', 'SUBDOC_TYPE',
            'SUBDOC_DATE', 'SUBDOC_DISTRIBUTION_DATE',
            'AUTEURS', 'SUBDOC_COMMENTS',
            'SUB_PDFDOC','SUBDOC_JOINTDOCS',
        ];
    }

    protected function getCallbacks(): array
    {
        $stringCallback = function (string $value) { return $value; };
        $dateCallback = function (string $value) { return Model::createDate($value); };

        return [
            'SUBDOCNR' => ['source', 'number_doc', $stringCallback],
            'SUBDOC_DATE' => ['source', 'date_submission', $dateCallback],
            'SUBDOC_DISTRIBUTION_DATE' => ['source', 'date_distribution', $dateCallback],
        ];
    }

    protected function parseSUB_PDFDOC(array $value): void
    {
        if(isset($value['SUB_PDFDOC_DOCLINK'])){
            $this->source['file'] = $this->flwb->createFileInfo($value['SUB_PDFDOC_DOCLINK']);
        }
    }

    protected function parseSUBDOC_TYPE(array $value): void
    {
        $this->source['doc_type'] = FLWBDoc::makeDocType($value['SUBDOC_TYPE_KODE'], $value['SUBDOC_TYPE_KODE_textF']);
    }

    protected function parseSUBDOC_COMMENTS(array $value): void
    {
        if(isset($value['SUBDOC_COMMENTS_textF']) && isset($value['SUBDOC_COMMENTS_textN']) && !is_array($value['SUBDOC_COMMENTS_textF']) && !is_array($value['SUBDOC_COMMENTS_textN'])){
            $this->source['comments_fr'] = $value['SUBDOC_COMMENTS_textF'];
            $this->source['comments_nl'] = $value['SUBDOC_COMMENTS_textN'];
        }
    }

    protected function parseAUTEURS(array $value): void
    {
        $nested = isset($value['AUTEURS_SLEUTEL']) ? [$value] : $value ;

        foreach ($nested as $item) {
            if(isset($item['AUTEURS_FAMNAAM']) && $item['AUTEURS_FAMNAAM'] == 'NOT FOUND'){
                continue;
            }

            $type = $item['AUTEURS_TYPE'];

            if (!\array_key_exists($type['AUTEURS_TYPE_KODE'], FLWBDoc::TYPES_AUTHORS)) {
                $this->logger->warning('unknown author type [{type}]', ['type' => \json_encode($type)]);
            }

            $this->source['authors'][] = [
                'type' => $type['AUTEURS_TYPE_KODE'],
                'actor' => $this->flwb->getActor(
                    'flwb_author',
                    $item['AUTEURS_SLEUTEL'],
                    ($item['AUTEURS_FORNAAM'] ?? ''),
                    ($item['AUTEURS_FAMNAAM'] ?? ''),
                    ($item['AUTEURS_PARTY'] ?? null)
                ),
            ];
        }
    }
}
