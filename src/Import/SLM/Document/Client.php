<?php

namespace App\Import\SLM\Document;

use EMS\CommonBundle\Elasticsearch\Document;

class Client extends Document
{
    public function __construct(string $id, string $name, ?int $sequence)
    {
        parent::__construct([
            '_id' => self::createID($id),
            '_type' => 'client',
            '_source' => array_filter([
                '_contenttype' => 'client',
                'client_id'    => $id,
                'name'         => $name,
                'sequence'     => $sequence,
            ])
        ]);
    }

    public static function createID(string $id)
    {
        return sha1('client'.$id);
    }
}