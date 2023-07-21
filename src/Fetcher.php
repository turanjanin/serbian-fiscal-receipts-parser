<?php

namespace Turanjanin\FiscalReceipts;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use QueryPath\QueryPath;
use Turanjanin\FiscalReceipts\Data\Receipt;

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
        if (!str_starts_with($url, 'https://suf.purs.gov.rs/v/?vl=')) {
            throw new \RuntimeException('Invalid URL provided.');
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->client->sendRequest($request);
        $html = $response->getBody()->getContents();

        $receiptContent = $this->extractReceiptContent($html);

        return $this->parser->parse($receiptContent);
    }

    public function fetchFromHtml(string $html): Receipt
    {
        $receiptContent = $this->extractReceiptContent($html);

        return $this->parser->parse($receiptContent);
    }

    public function extractReceiptContent(string $html): string
    {
        $document = QueryPath::withHTML5($html);
        $receiptContent = $document->find('pre')->first()->innerHTML() ?? '';

        if (empty($receiptContent)) {
            throw new \RuntimeException('Receipt data not found.');
        }

        return $receiptContent;
    }
}
