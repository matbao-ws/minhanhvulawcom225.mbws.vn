<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $cachePath = storage_path('app/htmlpurifier');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0750, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.DefinitionID', 'matbao-post-table');
        $config->set('HTML.DefinitionRev', 2);
        $config->set('HTML.AllowedElements', array_fill_keys([
            'p', 'br', 'hr', 'b', 'strong', 'i', 'em', 'u', 's', 'small', 'sub', 'sup', 'ul', 'ol', 'li',
            'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'table', 'colgroup', 'col',
            'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'pre', 'code', 'span', 'div',
        ], true));
        $config->set('HTML.AllowedAttributes', array_fill_keys([
            'a.href', 'a.title', 'a.rel', 'a.target', 'img.src', 'img.alt', 'img.title', 'img.width', 'img.height',
            '*.class', 'table.cellpadding', 'table.cellspacing', 'col.width', 'th.colspan', 'th.rowspan', 'th.scope',
            'td.colspan', 'td.rowspan', 'p.data-table-id', 'table.data-table-id', 'table.data-full',
            'colgroup.data-table-id', 'col.data-table-id', 'col.data-col-id', 'col.data-full', 'thead.data-table-id',
            'tbody.data-table-id', 'tfoot.data-table-id', 'tr.data-table-id', 'tr.data-row-id', 'th.data-table-id',
            'th.data-row-id', 'th.data-col-id', 'td.data-table-id', 'td.data-row-id', 'td.data-col-id',
        ], true));
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);

        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            foreach (['p', 'table', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'] as $element) {
                $definition->addAttribute($element, 'data-table-id', 'Text');
            }

            foreach (['tr', 'th', 'td'] as $element) {
                $definition->addAttribute($element, 'data-row-id', 'Text');
            }

            foreach (['col', 'th', 'td'] as $element) {
                $definition->addAttribute($element, 'data-col-id', 'Text');
            }

            $definition->addAttribute('table', 'data-full', 'Text');
            $definition->addAttribute('col', 'data-full', 'Text');
        }

        $this->purifier = new HTMLPurifier($config);
    }

    public function clean(?string $html): string
    {
        return MediaUrl::relativizeHtmlSources($this->purifier->purify((string) $html));
    }
}
