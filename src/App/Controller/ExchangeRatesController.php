<?php

declare(strict_types=1);

namespace App\Controller;

use App\NbpRatesService;
use App\CurrencyCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ExchangeRatesController
{
    private NbpRatesService $nbpRatesService;

    private const SUPPORTED_CURRENCIES = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];

    public function __construct(NbpRatesService $nbpRatesService)
    {
        $this->nbpRatesService = $nbpRatesService;
    }

    /**
     * @Route("/api/rates/current", name="api_rates_current", methods={"GET"})
     */
    public function currentRates(): JsonResponse
    {
        $currencies = self::SUPPORTED_CURRENCIES;
        $midRates = $this->nbpRatesService->fetchAverageRatesCurrentBatch($currencies);
        $result = [];
        foreach ($currencies as $currency) {
            $mid = $midRates[$currency] ?? null;
            $buy = CurrencyCalculator::calculateBuyRate($currency, $mid);
            $sell = CurrencyCalculator::calculateSellRate($currency, $mid);
            $result[] = [
                'currency' => $currency,
                'mid' => $mid,
                'buy' => $buy,
                'sell' => $sell,
            ];
        }
        return new JsonResponse($result);
    }

    /**
     * @Route("/api/rates/history", name="api_rates_history", methods={"GET"})
     */
    public function historyRates(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $currency = strtoupper($request->query->get('currency', 'EUR'));
        $date = $request->query->get('date', date('Y-m-d'));
        if (!in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            return new JsonResponse(['error' => 'Unsupported currency'], 400);
        }
        $history = $this->nbpRatesService->fetchAverageRatesHistory($currency, $date, 14);
        // Dodajemy przeliczone kursy kupna/sprzedaży do każdego dnia
        foreach ($history as &$item) {
            $item['buy'] = CurrencyCalculator::calculateBuyRate($currency, $item['mid']);
            $item['sell'] = CurrencyCalculator::calculateSellRate($currency, $item['mid']);
        }
        unset($item);
        return new JsonResponse($history);
    }
} 