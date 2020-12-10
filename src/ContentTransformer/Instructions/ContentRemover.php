<?php

namespace App\ContentTransformer\Instructions;

use EMS\CoreBundle\ContentTransformer\ContentTransformContext;
use EMS\CoreBundle\ContentTransformer\HtmlStylesRemover;

class ContentRemover extends HtmlStylesRemover
{
    public function transform(ContentTransformContext $contentTransformContext): string
    {
        $this->doc = $this->initDocument($contentTransformContext->getData());
        $this->xpath = new \DOMXPath($this->doc);

        $this->removeContent();
        $this->removeEmptyNodes();
        $this->removeHtmlStyles();

        return $this->outputDocument();
    }

    private function removeContent(): void
    {
        while ($node = $this->xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'removable-style-deleted')]")->item(0)) {
            $node->parentNode->removeChild($node);
        }
    }

    private function removeEmptyNodes(): void
    {
        while ($node = $this->xpath->query('//*[not(*) and not(@*) and not(text()[normalize-space()])]')->item(0)) {
            $node->parentNode->removeChild($node);
        }
    }
}
