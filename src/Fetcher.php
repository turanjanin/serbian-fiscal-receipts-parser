<?php

namespace Turanjanin\FiscalReceipts;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use QueryPath\QueryPath;
use Turanjanin\FiscalReceipts\Data\Receipt;
use Turanjanin\FiscalReceipts\Exceptions\InvalidUrlException;
use Turanjanin\FiscalReceipts\Exceptions\ParsingException;

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

        return $this->parser->parse($receiptContent);
    }

    public function fetchReceiptContent(string $url): string
    {
        if (!str_starts_with($url, 'https://suf.purs.gov.rs/v/?vl=')) {
            throw new InvalidUrlException('Only URLs from suf.purs.gov.rs domain are supported.');
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->client->sendRequest($request);
        $html = $response->getBody()->getContents();

        return $this->extractReceiptContent($html);
    }

    public function fetchFromHtml(string $html): Receipt
    {
        $receiptContent = $this->extractReceiptContent($html);

        return $this->parser->parse($receiptContent);
    }

    public function extractReceiptContent(string $html): string
    {
        $html = trim($html);

        if (empty($html)) {
            throw new ParsingException('Invalid HTML provided.');
        }

        $document = QueryPath::withHTML5($html);
        $receiptContent = $document->find('pre')->first()->innerHTML() ?? '';

        if (empty($receiptContent)) {
            throw new ParsingException('Receipt data not found.');
        }

        return $receiptContent;
    }
}
