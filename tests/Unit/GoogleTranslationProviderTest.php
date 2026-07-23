<?php

namespace Tests\Unit;

use App\Services\Translation\GoogleTranslationProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleTranslationProviderTest extends TestCase
{
    public function test_it_translates_multiple_texts_without_an_api_key(): void
    {
        config()->set('multilingual.translation.google.endpoint', 'https://translate.googleapis.com/translate_a/single');

        Http::fakeSequence()
            ->push([[['Hello ', 'Xin chào '], ['world', 'thế giới']], null, 'vi'])
            ->push([[['Goodbye', 'Tạm biệt']], null, 'vi']);

        $provider = app(GoogleTranslationProvider::class);

        $this->assertTrue($provider->configured());
        $this->assertSame('google-unofficial', $provider->name());
        $this->assertSame(
            ['Hello world', 'Goodbye'],
            $provider->translate(['Xin chào thế giới', 'Tạm biệt'], 'vi', 'en'),
        );

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'POST'
                && $query['client'] === 'gtx'
                && $query['sl'] === 'vi'
                && $query['tl'] === 'en'
                && filled($request['q']);
        });
    }

    public function test_it_rejects_an_invalid_google_response(): void
    {
        Http::fake([
            '*' => Http::response(['unexpected' => true]),
        ]);

        $this->expectExceptionMessage('translation_provider_invalid_response');

        app(GoogleTranslationProvider::class)->translate(['Xin chào'], 'vi', 'en');
    }
}
