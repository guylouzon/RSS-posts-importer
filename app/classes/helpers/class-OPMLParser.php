<?php
/**
 * OPML Parser
 *
 * A simple OPML parser for PHP 5/8, using the SimpleXMLElement class.
 *
 * Copyright (c) 2008, Ryan McCue
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice, this list of
 *    conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright notice, this list
 *    of conditions and the following disclaimer in the documentation and/or other materials
 *    provided with the distribution.
 *
 *  * Neither the name of Lilina nor the names of its contributors may be used
 *    to endorse or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
 * AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Ryan McCue
 * @package OPML Parser
 * @link http://cubegames.net/code/opml-parser/ OPML Parser Homepage
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * OPML Parser class
 *
 * @package OPML Parser
 */
class OPMLParser {

    public array $data = [];
    public string $raw = '';
    public string $error = '';

    public function __construct(string $raw_data) {
        $this->raw = $raw_data;
        // Create an XML parser
        try {
            $xml_parser = new SimpleXMLElement($this->raw, LIBXML_NOERROR);

            $this->data = $this->loop($xml_parser->body->outline);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return;
        }
    }

    protected function loop($element): array {
        $data = [];

        foreach ($element as $el) {
            $type = (string) ($el['type'] ?? '');
            $hasXmlUrl = isset($el['xmlUrl']);
            if ($type === 'rss' || $hasXmlUrl) {
                $data[] = $this->format($el);
            } elseif ($el->outline) {
                $data[(string) $el['text']] = $this->loop($el->outline);
            }
        }

        return $data;
    }

    /**
     * Return an array from a supplied SimpleXMLElement object
     *
     * @param SimpleXMLElement $element
     * @return array
     */
    protected function format($element): array {
        return [
            'htmlurl' => (string) ($element['htmlUrl'] ?? ''),
            'xmlurl' => (string) ($element['xmlUrl'] ?? ''),
            'text' => (string) ($element['text'] ?? ''),
            'title' => (string) ($element['title'] ?? ''),
            'description' => (string) ($element['description'] ?? ''),
        ];
    }

}
