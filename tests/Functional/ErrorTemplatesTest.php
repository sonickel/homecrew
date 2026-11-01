<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Renders the error templates directly; 404 is covered end-to-end in ErrorPageTest.
 */
final class ErrorTemplatesTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function errorTemplates(): iterable
    {
        yield '403' => ['@Twig/Exception/error403.html.twig', 'Kein Zugriff'];
        yield '404' => ['@Twig/Exception/error404.html.twig', 'Seite nicht gefunden'];
        yield 'other' => ['@Twig/Exception/error.html.twig', 'Etwas ist schiefgelaufen'];
    }

    #[DataProvider('errorTemplates')]
    public function testErrorTemplateIsGermanAndLeadsHome(string $template, string $heading): void
    {
        $twig = static::getContainer()->get('twig');

        $crawler = new Crawler($twig->render($template, ['status_code' => 500, 'status_text' => 'Error']));

        self::assertSame($heading, $crawler->filter('h1')->text());
        self::assertSame('de', $crawler->filter('html')->attr('lang'));
        self::assertCount(1, $crawler->filter('a.hc-button[href="/"]'));
    }
}
