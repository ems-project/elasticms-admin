<?php

namespace App\Import\SLM\Document;

use EMS\CommonBundle\Elasticsearch\Document;

class CSM extends Document
{
    public function __construct(string $acronym, ?string $fullName, ?string $email)
    {
        parent::__construct([
            '_id' => self::createID($acronym),
            '_type' => 'csm',
            '_source' => array_filter([
                '_contenttype' => 'csm',
                'acronym'      => $acronym,
                'fullname'     => $fullName,
                'email'        => $email
            ])
        ]);
    }

    public static function createID(string $acronym)
    {
        return sha1('csm'.$acronym);
    }
}