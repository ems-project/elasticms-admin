<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use EMS\CoreBundle\Service\AssetExtractorService;

class Report extends Model
{
    use XML;
    /** @var string */
    private $dateFR;
    /** @var string */
    private $dateNL;
    /** @var \DateTime */
    private $date;

    public function __construct(Import $import, string $type, string $searchCategory, int $legislature, array $data, AssetExtractorService $extractorService = null)
    {
        $this->process($data);

        $this->source['legislature'] = $legislature;
        $prefix = self::getPrefix($type, $legislature);

        $this->source['file'] = [
            'path' => sprintf('%s/pdf/%d/', $type, $legislature),
            'filename' => self::getFilename($type, $legislature, $this->source['number_report']),
        ];


        $this->source['all_fr'] =  $this->source['all_nl'] = $this->extractData($import, $extractorService);

        $this->source['type_report'] = $type;
        $this->source['date'] = $this->date->format('Y-m-d H:i:s');
        $this->source['date_day'] = $this->date->format('Y-m-d');
        $this->source['date_hour'] = $this->date->format('H:i:s');
        $this->source['date_week'] = $this->date->format('W');

        if (isset($this->source['comid']) && 'U' != \strtoupper(\substr($this->source['comid'], 0,1))) {
            $docName = substr($this->source['comid'], 1);
            $this->source['commission'] = $import->getCommission($docName, $legislature);
        }

        $this->source['title_fr'] = sprintf('%s - %s', $this->getTitleFr($type), $this->dateFR);
        $this->source['title_nl'] = sprintf('%s - %s', $this->getTitleNl($type), $this->dateNL);

        $this->source['search_id'] = $prefix.$this->source['number_report'];
        $this->source['search_type'] = 'report';
        $this->source['search_types'] = SearchTypes::single($searchCategory, $this->source['legislature']);
        $this->source['search_dates'] = [$this->date->format('Y-m-d')];
        $this->source['search_date_sort'] = $this->date->format('Y-m-d');

        parent::__construct($import, $type, $legislature.$this->source['number_report']);
    }

    protected function extractData(Import $import, AssetExtractorService $extractorService = null) : string
    {
        if ($extractorService === null) {
            return '';
        }

        $file = $import->getRootDir() . '/' . $this->source['file']['path'] . $this->source['file']['filename'];

        $file = str_replace(['ccri', 'ccra', 'pcri', 'pcra'], ['CCRI', 'CCRA', 'PCRI', 'PCRA'], $file);

        if (!file_exists($file)) {
            return '';
        }

        try {
            $content = $extractorService->extractData(sha1_file($file), $file);
            return $content['content'] ?? '';
        }
        catch (Exception $e) {
            $import->getLogger()->critical($e->getMessage());
        }
        return '';
    }

    protected function getRootElements(): array
    {
        return [
            'COMID',
            'COMMENTF', 'COMMENTN',
            'COMTITFR', 'COMTITNL',
            'DATEF', 'DATEN', 'DOCNAME', 'HOURF', 'HOURN',
            'KEY', 'MHREDAT'
        ];
    }

    protected function getCallbacks(): array
    {
        $stringCallback = function (string $value) { return (string) $value; };

        return [
            'KEY' => ['source', 'number_report', $stringCallback],
            'COMID' => ['source', 'comid', $stringCallback],
            'COMMENTF' => ['source', 'comment_fr', $stringCallback],
            'COMMENTN' => ['source', 'comment_nl', $stringCallback],
            'HOURF' => ['source', 'hour_fr', $stringCallback],
            'HOURN' => ['source', 'hour_nl', $stringCallback],
            'COMTITFR' => ['source', 'commission_fr', $stringCallback],
            'COMTITNL' => ['source', 'commission_nl', $stringCallback]
        ];
    }

    protected function parseDATEF(string $value)
    {
        $this->dateFR = $value;
    }

    protected function parseDATEN(string $value)
    {
        $this->dateNL = $value;

        $months = [
            'januari' => 1, 'februari' => 2, 'maart' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
            'juli' => 7, 'augustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'december' => 12,
            'jan' => 1, 'ma' => 3, 'feb' => 2, 'dec' => 12
        ];

        $value = preg_replace('/\s+/', '_', $value); //double whitespaces
        $explode = \explode('_', $value);

        if (!\array_key_exists($explode[2], $months)) {
            throw new \RuntimeException(sprintf('invalid dutch date in month %s (%s)', $value, $explode[2]));
        }

        $dateString = sprintf('%s/%s/%s', $explode[1], $months[$explode[2]], $explode[3]);

        $this->date = \DateTime::createFromFormat('d/m/Y', $dateString);
    }

    /** Leg < 50 */
    protected function parseDOCNAME(string $value)
    {
        $this->source['number_report'] = substr($value, 5);
    }

    /** Leg < 50 */
    protected function parseMHREDAT(string $value)
    {
        $this->setDate($value);
        $this->dateNL = $value;
        $this->dateFR = $value;
    }

    private function setDate(string $value)
    {
        $this->date = \DateTime::createFromFormat('Y-m-d', $value);
    }

    private function getTitleNl(string $type): string
    {
        switch ($type) {
            case Model::TYPE_CCRA:
                return 'Beknopt verslag - '.$this->source['commission_nl'];
            case Model::TYPE_CCRI:
                return 'Integraal verslag - '.$this->source['commission_nl'];
            case Model::TYPE_PCRA:
                return 'Beknopt verslag - Plenumvergadering';
            case Model::TYPE_PCRI:
                return 'Integraal verslag - Plenumvergadering';
            default:
                throw new \Exception('invalid type');
        }
    }

    private function getTitleFr(string $type): string
    {
        switch ($type) {
            case Model::TYPE_CCRA:
                return 'Compte rendu analytique - '.$this->source['commission_fr'];
            case Model::TYPE_CCRI:
                return 'Compte rendu intégral - '.$this->source['commission_fr'];
            case Model::TYPE_PCRA:
                return 'Compte rendu analytique - Séance plénière';
            case Model::TYPE_PCRI:
                return 'Compte rendu intégral - Séance plénière';
            default:
                throw new \Exception('invalid type');
        }
    }

    public static function getPrefix(string $type, int $legislature)
    {
        switch ($type) {
            case Model::TYPE_CCRA:
                return 'ac';
            case Model::TYPE_CCRI:
                return ($legislature < 50 ? 'KC' : 'ic');
            case Model::TYPE_PCRA:
                return 'ap';
            case Model::TYPE_PCRI:
                return ($legislature < 50 ? 'KP' : 'ip');
            default:
                throw new \Exception('invalid type');
        }
    }

    public static function getFilename(string $type, int $legislature, string $number)
    {
        $prefix = self::getPrefix($type, $legislature);

        if ($legislature < 50) {
            $number = \sprintf('%04d', $number);
            $filename = $legislature . $prefix . $number;
        } else {
            if (strlen($number) === 4 && '0' === substr($number, 0, 1)) {
                $number = substr($number, 1);
            }

            $filename = $prefix . $number;
        }

        return sprintf('%s.pdf', $filename);
    }
}
