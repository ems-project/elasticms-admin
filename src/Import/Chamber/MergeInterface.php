<?php

namespace App\Import\Chamber;

use EMS\CommonBundle\Elasticsearch\DocumentInterface;

interface MergeInterface
{
    public function merge(DocumentInterface $current);
}