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
        $config->set('HTML.Allowed', 'p[class],br,hr,b,strong,i,em,u,s,small,sub,sup,ul,ol,li,blockquote,h1,h2,h3,h4,h5,h6,a[href|title|rel|target],img[src|alt|title|width|height|class],table[class],thead,tbody,tfoot,tr,th[colspan|rowspan|scope],td[colspan|rowspan],pre,code,span[class],div[class]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);

        $this->purifier = new HTMLPurifier($config);
    }

    public function clean(?string $html): string
    {
        return MediaUrl::relativizeHtmlSources($this->purifier->purify((string) $html));
    }
}
