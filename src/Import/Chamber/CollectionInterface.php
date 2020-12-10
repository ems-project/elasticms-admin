<?php

namespace App\Import\Chamber;

interface CollectionInterface
{
    /** @return Model[] */
    public function getCollection(): array;
}