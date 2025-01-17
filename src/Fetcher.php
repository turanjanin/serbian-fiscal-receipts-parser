<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Masterminds\HTML5;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use QueryPath\QueryPath;
use Turanjanin\FiscalReceipts\Data\Receipt;
use Turanjanin\FiscalReceipts\Exceptions\InvalidUrlException;
use Turanjanin\FiscalReceipts\Exceptions\ParsingException;
use Turanjanin\FiscalReceipts\Exceptions\SufException;

class Fetcher
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private Parser $parser;

    public function __construct(
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?Parser $parser = null,
    )
    {
        $this->client = $client ?: Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?: Psr17FactoryDiscovery::findRequestFactory();
        $this->parser = $parser ?: new Parser;
    }

    public function fetchFromUrl(string $url): Receipt
    {
        $receiptContent = $this->fetchReceiptContent($url);

        return $this->parser->parseJournal($receiptContent);
    }

    public function fetchFromApi(string $url): Receipt
    {
        $this->assertUrlValidity($url);

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json');
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new SufException('There was an error while fetching receipt details. SUF returned status code ' . $response->getStatusCode());
        }

        $json = $response->getBody()->getContents();
        $data = json_decode($json, true);

        return $this->parser->parseApiResponse($data);
    }

    public function fetchReceiptContent(string $url): string
    {
        $this->assertUrlValidity($url);

        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new SufException('There was an error while fetching receipt content. SUF returned status code ' . $response->getStatusCode());
        }

        $html = $response->getBody()->getContents();

        return $this->extractReceiptContent($html);
    }

    public function fetchFromHtml(string $html): Receipt
    {
        $receiptContent = $this->extractReceiptContent($html);

        return $this->parser->parseJournal($receiptContent);
    }

    public function extractReceiptContent(string $html): string
    {
        $html = trim($html);

        if (empty($html)) {
            throw new ParsingException('Invalid HTML provided.');
        }

        $source = (new HTML5)->loadHTML($html);
        $document = QueryPath::withHTML5($source);
        $receiptContent = $document->find('pre')->first()->innerHTML() ?? '';

        if (empty($receiptContent)) {
            throw new ParsingException('Receipt data not found.');
        }

        return html_entity_decode($receiptContent);
    }

    private function assertUrlValidity(string $url): void
    {
        $domain = parse_url($url, PHP_URL_HOST) ?? '';
        if ($domain !== 'suf.purs.gov.rs') {
            throw new InvalidUrlException('Only URLs from the suf.purs.gov.rs domain are supported.');
        }
    }
}
