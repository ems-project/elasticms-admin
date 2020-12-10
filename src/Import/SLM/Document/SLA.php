<?php

namespace App\Import\SLM\Document;

use EMS\CommonBundle\Elasticsearch\Document;

class SLA extends Document
{
    public function __construct(array $row)
    {
        $client = sprintf('client:%s', Client::createID($row['client_id']));
        $csm = sprintf('csm:%s', CSM::createID($row['csm_acronym']));
        $title = sprintf('%s - %s - %s', $row['id'], $row['client_name'], $row['service_name']);

        parent::__construct([
            '_id' => self::createID($row['id']),
            '_type' => 'sla',
            '_source' => [
                '_contenttype' => 'sla',
                'client'       => $client,
                'csm'          => $csm,
                'has_kpi'      => false,
                'service_name' => $row['service_name'],
                'sla_id'       => (int) $row['id'],
                'status'       => $row['sla_status'],
                'title'        => $title,
            ]
        ]);
    }

    public static function createID(int $id)
    {
        return sha1('sla'.$id);
    }
}