<?php

namespace App\Import\Chamber\XML;

use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

trait XML
{
    protected function clean($value, $key): bool
    {
        return null === $value;
    }

    protected function getRootElements(): array
    {
        return [];
    }

    protected function getCallbacks(): array
    {
        return [];
    }

    protected function getCallbacksHTML(): array
    {
        return [];
    }

    protected function process(array $data, \DOMDocument $dom = null): void
    {
        $rootElements = $this->getRootElements();
        $callbacks = $this->getCallbacks();
        $callbacksHTML = $this->getCallbacksHTML();

        $clean = $this->recursive($data, [$this, 'clean'], true);

        foreach ($clean as $element => $value) {
            if (!in_array($element, $rootElements)) {
                continue;
            }

            if (isset($callbacks[$element])) {
                list($array, $property, $callback) = array_values($callbacks[$element]);
                $this->$array[$property] = call_user_func($callback, $value);
                continue;
            }

            if (isset($callbacksHTML[$element])) {
                list($array, $property) = array_values($callbacksHTML[$element]);
                $this->$array[$property] = $this->getRawHTML($dom, $element);
                continue;
            }

            $element = ucfirst(str_replace([':', '@'], '', $element));
            if (method_exists($this, 'parse' . $element)) {
                $this->{'parse' . $element}($value);
                continue;
            }

            if ($dom && method_exists($this, 'parseHtml' . $element)) {
                $this->{'parseHtml' . $element}($this->getRawHTML($dom, $element));
            }
        }
    }

    protected function xmlToArray(SplFileInfo $file): array
    {
        try {
            $encoder = new XmlEncoder();

            return $encoder->decode($file->getContents(), XmlEncoder::FORMAT);
        } catch (NotEncodableValueException $e) {
            throw new \LogicException('File is empty');
        }
    }

    protected function xmlToDom(SplFileInfo $file): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($file->getContents());

        return $dom;
    }

    private function getRawHTML(\DOMDocument $dom, $tag): ?string
    {
        $domElements = $dom->getElementsByTagName($tag);
        $doc = new \DOMDocument('1.0', 'UTF-8');

        foreach ($domElements as $element) {
            $cloned = array_map(function($value){
                /** @var $value \DOMNode */
                return $value->cloneNode(TRUE);
            }, iterator_to_array($element->childNodes));

            array_map(function($clone) use ($doc){
                $doc->appendChild($doc->importNode($clone,TRUE));
            }, $cloned);
        }

        $raw = null;
        foreach ($doc->childNodes as $x) {
            $raw .= $doc->saveHTML($x);
        }

        return trim($raw);
    }

    private function recursive($array, $callback, $remove = false): array
    {
        foreach ($array as $key => &$value) { // mind the reference
            if (is_array($value)) {
                $value = $this->recursive($value, $callback, $remove);
                if ($remove && $callback($value, $key)) {
                    unset($array[$key]);
                }
            } else if ($callback($value, $key)) {
                unset($array[$key]);
            }
        }
        unset($value); // kill the reference
        return $array;
    }
}