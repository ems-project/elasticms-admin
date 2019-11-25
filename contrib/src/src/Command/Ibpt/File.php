<?php

namespace App\Command\Ibpt;

use Symfony\Component\DomCrawler\Crawler;

class File
{
    /** @var string */
    private $localPath;
    /** @var string */
    private $originalUrl;
    /** @var Crawler */
    private $crawler;

    const LANGUAGES = ['Nederlands' => 'nl', 'Français' => 'fr', 'English' => 'en', 'Deutsch' => 'de'];
    const CONSUMERS = ['Verbraucher', 'Consumenten', 'Consommateurs', 'Consumers'];

    public function __construct($website, $file)
    {
        $this->crawler = new Crawler();
        $this->localPath = $website . DIRECTORY_SEPARATOR . $file;
        $content = $this->getFileRawContent($this->localPath);
        $this->crawler->addHtmlContent($content);
    }

    private function getFileRawContent(string $filePath): string
    {
        try {
            if (!\file_exists($filePath)) {
                throw new \Exception('File not found.');
            }

            $file = \fopen($filePath, 'r');

            if (!$file) {
                throw new \Exception('File open failed.');
            }

            $content = \fread($file, \filesize($filePath));
            \fclose($file);

        } catch (\Exception $e) {
            throw new \Error($e->getMessage());
        }

        return $content;
    }

    public function setOriginalUrl($originalUrl)
    {
        $this->originalUrl = $originalUrl;
    }

    public function getOriginalUrl()
    {
        return $this->originalUrl;
    }

    public function getLocalPath(): string
    {
        return $this->localPath;
    }

    public function getTitle(): ?string
    {
        return $this->crawler->filterXPath('//title')->first()->text();
    }

    public function getLanguage(): string
    {
        $language = $this->crawler->filterXPath('//ul[@id="top-languages"]//li[@class="active"]')->first()->text();
        return self::LANGUAGES[\trim($language)] ?? 'en';
    }

    public function getDescription(): ?string
    {
        $desc = $this->crawler->filterXPath('//div[@style="clear:left"]')->first();
        return $desc->count() ? \trim($desc->html()) : '';
    }

    public function getTargetGroup(): string
    {
        $targetGroupRaw = $this->crawler->filterXPath('//ul[@id="top-section"]/li[@class="active"]')->first()->text();
        return \in_array($targetGroupRaw, self::CONSUMERS) ? 'consumers' : 'operators';
    }

    public function getType(): string
    {
        $typeFilter = $this->crawler->filterXPath('//div[@class="right-content"]/div/p')->first();

        if(!$typeFilter->count()){
            return '';
        }

        $typeRaw = $typeFilter->html(); // e.g. <strong>Type</strong><br> Overheidsopdracht
        if(\strpos($typeRaw, 'Type') !== false){
            $typeArray = \explode(' ', $typeRaw); // e.g. ['<strong>Type</strong><br>', ['Overheidsopdracht']
            return \array_pop($typeArray); // e.g. Overheidsopdracht
        }else{
            return '';
        }
    }

    public function getAttachments(): ?array
    {
        $list = $this->crawler->filterXPath('//div[@class="document-languages"]');
        $attachments = [];
        $list->filterXPath('//ul/li/a')->each(function (Crawler $crawler) use (&$attachments) {
            $lang = \strtolower(\trim($crawler->text()));
            $attachments[] = [$lang, $crawler->attr('href')];
        });
        return $attachments;
    }

    public function getOtherLanguages(): ?array
    {
        $otherLanguages = [];
        $this->crawler->filterXPath('//ul[@id="top-languages"]/li/a')->each(function (Crawler $crawler) use (&$otherLanguages) {
            $lang = self::LANGUAGES[\trim($crawler->text())] ?? 'en';
            $otherLanguages[] = [$lang, $crawler->attr('href')];
        });

        return $otherLanguages;
    }

    public function getPublicationDate(): ?string
    {
        $this->crawler->filterXPath('//div[@class="telephone-right-block document-detail"]/p')->each(function (Crawler $crawler) use (&$date) {
            if (\strpos(\strtolower($crawler->html()), 'pub') || \strpos(\strtolower($crawler->html()), 'lichungsdatum')) {
                $date = \substr($crawler->text(), -10);
            }
        });

        return $date;
    }

    public function getReactionDate(): ?string
    {
        $this->crawler->filterXPath('//div[@class="right-content"]/div/p')->each(function (Crawler $crawler) use (&$reactionDate) {
            if (\strpos($crawler->html(), 'React untill') !== false
                || \strpos($crawler->html(), 'Reageren tot') !== false
                || \strpos($crawler->html(), 'Reagieren bis') !== false
                || \strpos($crawler->html(), 'Réagir') !== false
            ) {
                $pieces = \explode(' ', $crawler->html());
                $reactionDate = \array_pop($pieces);
            }
        });
        return $reactionDate;
    }

    public function getClosingDate(): ?string
    {
        $this->crawler->filterXPath('//div[@class="right-content"]/div/p')->each(function (Crawler $crawler) use (&$closingDate) {
            if (\strpos($crawler->html(), 'Date closing') !== false
                || \strpos($crawler->html(), 'Sluitingsdatum') !== false
                || \strpos($crawler->html(), 'Abgabetermin') !== false
                || \strpos($crawler->html(), 'Date de fermeture') !== false
            ) {
                $pieces = \explode(' ', $crawler->html());
                $closingDate = \array_pop($pieces);
            }
        });
        return $closingDate;
    }

    public function getDocumentDate(): ?string
    {
        $list = $this->crawler->filterXPath('//div[@class="right-content"]');
        $list->filterXPath('//p')->each(function (Crawler $crawler) use (&$documentDate) {
            if (\strpos(\strtolower($crawler->html()), 'dat') && !\strpos(\strtolower($crawler->html()), 'pub') && !\strpos(\strtolower($crawler->html()), 'veröffen')) {
                $documentDate = \substr($crawler->text(), -10);
            }
        });

        return $documentDate;
    }

    public function getRelatedDossier(): ?string
    {
        $list = $this->crawler->filterXPath('//div[@class="telephone-right-block document-detail"]');
        $list->filterXPath('//div/ul')->each(function (Crawler $crawler) use (&$related) {
            $related = $crawler->text();
        });

        return Linker::determineDossier(\trim($related));
    }

    public function getLinkedContent(): ?string
    {
        $this->crawler->filterXPath('//ul[@class="entity_detail_related_list"]/li/a/@href')->each(function (Crawler $crawler) use (&$linked) {
            $linked = 'publication:' . \hash('sha1', $crawler->text());
        });
        return $linked;
    }

}
