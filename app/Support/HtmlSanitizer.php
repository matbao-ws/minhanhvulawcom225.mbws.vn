<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;
use HTMLPurifier_HTMLDefinition;

class HtmlSanitizer
{
    private const ELEMENTS = [
        'p', 'br', 'hr', 'b', 'strong', 'i', 'em', 'u', 's', 'small', 'sub', 'sup', 'ul', 'ol', 'li',
        'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'table', 'colgroup', 'col',
        'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'pre', 'code', 'span', 'div',
    ];

    private const ATTRIBUTES = [
        'a.href', 'a.title', 'a.rel', 'a.target', 'img.src', 'img.alt', 'img.title', 'img.width', 'img.height',
        '*.class', 'table.cellpadding', 'table.cellspacing', 'col.width', 'th.colspan', 'th.rowspan', 'th.scope',
        'td.colspan', 'td.rowspan',
        // Tables saved by the former admin editor (Quill) keep their markup.
        'p.data-table-id', 'table.data-table-id', 'table.data-full',
        'colgroup.data-table-id', 'col.data-table-id', 'col.data-col-id', 'col.data-full', 'thead.data-table-id',
        'tbody.data-table-id', 'tfoot.data-table-id', 'tr.data-table-id', 'tr.data-row-id', 'th.data-table-id',
        'th.data-row-id', 'th.data-col-id', 'td.data-table-id', 'td.data-row-id', 'td.data-col-id',
    ];

    /**
     * Inline styles the admin editor (Jodit) writes: alignment, image size and float, table
     * layout, colours and fonts. Anything else in a style attribute is dropped.
     */
    private const RICH_TEXT_CSS = [
        'text-align', 'vertical-align', 'float', 'clear',
        'width', 'height', 'max-width', 'min-width',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'color', 'background-color', 'font-family', 'font-size', 'font-weight', 'font-style',
        'text-decoration', 'line-height', 'list-style-type',
        'border', 'border-width', 'border-style', 'border-color', 'border-collapse',
    ];

    private ?HTMLPurifier $purifier = null;

    private ?HTMLPurifier $richTextPurifier = null;

    private ?HTMLPurifier $siteBlockPurifier = null;

    /**
     * Markup without inline styles: storefront site blocks (whose look is design tokens and
     * classes, never style attributes), imports and AI text that is not editor content.
     */
    public function clean(?string $html): string
    {
        $this->purifier ??= new HTMLPurifier($this->config('matbao-post-table', 2));

        return MediaUrl::relativizeHtmlSources($this->purifier->purify((string) $html));
    }

    /**
     * A storefront region edited in place: clean() plus one inline style, the text colour the
     * editor's colour button sets (`<span style="color: …">`). Every other style is still dropped,
     * so a region keeps the theme's fonts, sizes and spacing.
     */
    public function cleanSiteBlock(?string $html): string
    {
        $this->siteBlockPurifier ??= new HTMLPurifier($this->config('matbao-site-block', 1, textColour: true));

        return MediaUrl::relativizeHtmlSources($this->siteBlockPurifier->purify((string) $html));
    }

    /**
     * Content written in the admin rich text editor (posts, products, catalogue and blog
     * descriptions): also keeps a whitelist of inline styles, figure captions, YouTube/Vimeo
     * embeds and links that open in a new tab or are nofollow.
     */
    public function cleanRichText(?string $html): string
    {
        $this->richTextPurifier ??= new HTMLPurifier($this->config('matbao-rich-editor', 1, richText: true));

        return MediaUrl::relativizeHtmlSources($this->richTextPurifier->purify((string) $html));
    }

    private function config(string $definitionId, int $revision, bool $richText = false, bool $textColour = false): HTMLPurifier_Config
    {
        $cachePath = storage_path('app/htmlpurifier');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0750, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.DefinitionID', $definitionId);
        $config->set('HTML.DefinitionRev', $revision);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);

        if (! $richText) {
            $config->set('HTML.AllowedElements', array_fill_keys(self::ELEMENTS, true));
            $config->set('HTML.AllowedAttributes', array_fill_keys([...self::ATTRIBUTES, ...($textColour ? ['span.style'] : [])], true));
            if ($textColour) {
                $config->set('CSS.AllowedProperties', ['color' => true]);
            }
            if ($definition = $config->maybeGetRawHTMLDefinition()) {
                $this->addTableAttributes($definition);
            }

            return $config;
        }

        $config->set('HTML.AllowedElements', array_fill_keys([...self::ELEMENTS, 'figure', 'figcaption', 'iframe', 'caption'], true));
        $config->set('HTML.AllowedAttributes', array_fill_keys([
            ...self::ATTRIBUTES,
            '*.style', 'table.border', 'iframe.src', 'iframe.width', 'iframe.height', 'iframe.title', 'iframe.allowfullscreen',
        ], true));
        $config->set('CSS.AllowedProperties', array_fill_keys(self::RICH_TEXT_CSS, true));
        // "Open in new tab" and "nofollow" from the link dialog.
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer', 'sponsored', 'ugc']);
        // Embedded video: YouTube and Vimeo players only.
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $definition->addElement('figure', 'Block', 'Flow', 'Common');
            $definition->addElement('figcaption', 'Block', 'Flow', 'Common');
            $definition->addAttribute('iframe', 'allowfullscreen', 'Bool');
            $this->addTableAttributes($definition);
        }

        return $config;
    }

    private function addTableAttributes(HTMLPurifier_HTMLDefinition $definition): void
    {
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
}
