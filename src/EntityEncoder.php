<?php

namespace lucadevelop\TelegramEntitiesDecoder;

class EntityEncoder extends EntityDecoder
{
    /**
     * @param string $text       Text which need encoding (only HTML)
     * @return array
     */
    public function encode(string $text): array
    {
        return $this->wrapper(
            $this->prepare($text)
        );
    }

    /**
     * @param string $text
     * @return string
     */
    public function postPrepare(string $text): string
    {
        $processingText = html_entity_decode($text, ENT_HTML5, 'UTF-8');

        return preg_replace('/<[^>]*>/', '', $processingText);
    }

    protected function wrapper(string $html): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-16');

        libxml_use_internal_errors(true);

        $dom->loadHTML('<?xml encoding="UTF-16"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_use_internal_errors(false);

        $body = $dom->getElementsByTagName('body')->item(0);
        $offset = 0;

        return $this->entityDefinition($body, $offset);
    }

    protected function entityDefinition(\DOMElement|\DOMText $node, int &$offset): array
    {
        $entities = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $length = mb_strlen($child->nodeValue);
                $offset += $length;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = $child->tagName;

                switch ($tag) {
                    case 'strong':
                        $entityType = 'bold';
                        break;
                    case 'em':
                        $entityType = 'italic';
                        break;
                    case 's':
                        $entityType = 'strikethrough';
                        break;
                    case 'u':
                        $entityType = 'underline';
                        break;
                    case 'code':
                        $entityType = 'code';
                        break;
                    case 'pre':
                        $entityType = 'pre';
                        break;
                    case 'a':
                        $entityType = 'text_link';
                        break;
                    case 'span':
                        $entityType = $child->getAttribute('class');
                        break;
                    default:
                        $entityType = null;
                }

                if ($entityType !== null) {
                    $length = mb_strlen($child->nodeValue);
                    $entity = [
                        'type' => $entityType,
                        'offset' => $offset,
                        'length' => $length
                    ];

                    if ($entityType === 'text_link') {
                        $entity['url'] = $child->getAttribute('href');
                    }

                    $entities[] = $entity;
                }

                $childEntities = $this->entityDefinition($child, $offset);
                $entities = array_merge($entities, $childEntities);
            }
        }

        return $entities;
    }

    protected function prepare(string $text): string
    {
        $processingText = str_replace(['<p>', '</p>'], '', $text);
        $processingText = html_entity_decode($processingText, ENT_HTML5, 'UTF-8');
        $pos = mb_strpos($processingText, '<div id=');

        if ($pos === false) {
            return $processingText;
        }

        return mb_substr($processingText, 0, $pos);
    }
}
