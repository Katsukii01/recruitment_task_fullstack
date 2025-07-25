<?php

namespace Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExchangeRatesControllerTest extends WebTestCase
{
    public function testCurrentRatesEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/current');
        $this->assertResponseIsSuccessful();
        $response = $client->getResponse();
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('mid', $item);
            $this->assertArrayHasKey('buy', $item);
            $this->assertArrayHasKey('sell', $item);
        }
    }

    public function testHistoryRatesEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/history?currency=EUR&date=2023-12-01');
        $this->assertResponseIsSuccessful();
        $response = $client->getResponse();
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertArrayHasKey('date', $item);
            $this->assertArrayHasKey('mid', $item);
            $this->assertArrayHasKey('buy', $item);
            $this->assertArrayHasKey('sell', $item);
        }
    }

    public function testHistoryRatesEndpointWithInvalidCurrency(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/history?currency=XXX&date=2023-12-01');
        $this->assertResponseStatusCodeSame(400);
        $response = $client->getResponse();
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
} 