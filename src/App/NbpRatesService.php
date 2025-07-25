<?php

declare(strict_types=1);

namespace App;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * NbpRatesService
 * ----------------
 * Serwis odpowiada za:
 * - Pobieranie kursów walut z API NBP.
 * - Przechowywanie i odczytywanie kursów z Airtable (cache).
 * - Optymalizację liczby zapytań do zewnętrznych API.
 */
class NbpRatesService
{
    private const NBP_API_URL = 'https://api.nbp.pl/api/exchangerates/rates/A/';
    private const AIRTABLE_API_URL = 'https://api.airtable.com/v0/';
    private HttpClientInterface $httpClient;
    private string $airtableBaseId = "apphLU2VTXhwlwDfs";
    private string $airtableTable = "NBP_Rates";
    private string $airtableApiKey = "patTc0AJeFvVRAzj3.a93acbfd90068fb9389c73a1b2b0989332cfea13f8771d87ab38155b34fb1cd4";

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Zwraca kurs średni dla podanej waluty i daty.
     * Najpierw sprawdza w Airtable, jeśli brak - pobiera z NBP i od razu zwraca użytkownikowi.
     * Zapisuje kurs do Airtable i usuwa stare rekordy (dla dzisiejszego kursu) już po zwróceniu odpowiedzi.
     */
    public function fetchAverageRate(string $currency, ?string $date = null): ?float
    {
        $currency = strtoupper($currency);
        $date = $date ?? date('Y-m-d');

        // 1. Pobierz z Airtable (jeśli jest)
        $rate = $this->fetchRateFromAirtable($currency, $date);
        if ($rate !== null) {
            return $rate;
        }

        $mid = null;
        $today = date('Y-m-d');
        if ($date === 'latest' || $date === $today) {
            // 2. Pobierz z NBP (jeśli nie ma w Airtable)
            $mid = $this->fetchAverageRateFromNbp($currency);
            if ($mid !== null) {
                // Od razu zwróć użytkownikowi, a zapis do Airtable i usuwanie starych rekordów wykonaj "w tle"
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                $this->saveRateToAirtable($currency, $today, $mid); // Zapisz do Airtable
                $this->deleteOldRatesFromAirtable($currency, $today, 70); // Usuń stare rekordy
            }
            return $mid;
        }

        // 3. Kurs historyczny (nie usuwa starych rekordów)
        $mid = $this->fetchAverageRateFromNbp($currency, $date);
        if ($mid !== null) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $this->saveRateToAirtable($currency, $date, $mid);
        }
        return $mid;
    }

    /**
     * Zwraca historię kursów dla danej waluty z wybranego zakresu dat.
     * Najpierw pobiera kursy z Airtable, a brakujące pobiera z NBP i zapisuje do Airtable.
     */
    public function fetchAverageRatesHistory(string $currency, string $date, int $days = 70): array
    {
        $currency = strtoupper($currency);
        $endDate = $date;
        $startDate = (new \DateTimeImmutable($endDate))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        // Pobierz z Airtable
        $airtableRates = $this->fetchRatesHistoryFromAirtable($currency, $startDate, $endDate);
        $datesNeeded = $this->getDateRange($startDate, $endDate);
        $ratesByDate = [];
        foreach ($airtableRates as $rate) {
            $ratesByDate[$rate['date']] = $rate['mid'];
        }
        $missingDates = array_diff($datesNeeded, array_keys($ratesByDate));
        // Pobierz brakujące z NBP jednym zapytaniem (jeśli są)
        if (!empty($missingDates)) {
            $missingDatesSorted = array_values($missingDates);
            sort($missingDatesSorted);
            $firstMissing = $missingDatesSorted[0];
            $lastMissing = $missingDatesSorted[count($missingDatesSorted) - 1];
            $nbpRates = $this->fetchAverageRatesHistoryFromNbp($currency, $firstMissing, $lastMissing);
            $toAirtable = [];
            foreach ($nbpRates as $rate) {
                $ratesByDate[$rate['date']] = $rate['mid'];
                $toAirtable[] = $rate;
            }
            if (!empty($toAirtable)) {
                $this->saveRatesBatchToAirtable($currency, $toAirtable);
            }
        }
        // Zwróć posortowaną tablicę kursów
        $result = [];
        foreach ($datesNeeded as $d) {
            if (isset($ratesByDate[$d])) {
                $result[] = [
                    'date' => $d,
                    'mid' => $ratesByDate[$d],
                ];
            }
        }
        return $result;
    }

    /**
     * Zwraca kursy średnie dla wielu walut na dzisiaj.
     * Pobiera batchowo kursy z Airtable dla wszystkich walut.
     * Jeśli brakuje kursu dla którejś waluty, pobiera go z NBP i zapisuje do Airtable.
     */
    public function fetchAverageRatesCurrentBatch(array $currencies): array
    {
        $today = date('Y-m-d');
        // 1. Pobierz batch z Airtable dla wszystkich walut na dziś
        $airtableRates = $this->fetchRatesTodayFromAirtableBatch($currencies, $today);
        $ratesByCurrency = [];
        foreach ($airtableRates as $rate) {
            $ratesByCurrency[$rate['currency']] = $rate['mid'];
        }
        $result = [];
        foreach ($currencies as $currency) {
            if (isset($ratesByCurrency[$currency])) {
                $result[$currency] = $ratesByCurrency[$currency];
            } else {
                // Brak w Airtable – pobierz z NBP i zapisz
                $mid = $this->fetchAverageRateFromNbp($currency);
                if ($mid !== null) {
                    $this->saveRateToAirtable($currency, $today, $mid);
                    $this->deleteOldRatesFromAirtable($currency, $today, 70);
                    $result[$currency] = $mid;
                } else {
                    $result[$currency] = null;
                }
            }
        }
        return $result;
    }

    // --- POMOCNICZE METODY DO AIRTABLE ---

    private function fetchRateFromAirtable(string $currency, string $date): ?float
    {
        $url = self::AIRTABLE_API_URL . $this->airtableBaseId . "/" . $this->airtableTable;
        $filter = sprintf("AND({Currency} = '%s', {Date} = '%s')", $currency, $date);
        $params = http_build_query([
            'filterByFormula' => $filter,
            'maxRecords' => 1,
        ]);
        $response = $this->httpClient->request('GET', "$url?$params", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->airtableApiKey,
            ],
        ]);
        $data = $response->toArray(false);
        if (!empty($data['records'][0]['fields']['Mid'])) {
            return (float)$data['records'][0]['fields']['Mid'];
        }
        return null;
    }

    private function fetchRatesHistoryFromAirtable(string $currency, string $startDate, string $endDate): array
    {
        $url = self::AIRTABLE_API_URL . $this->airtableBaseId . "/" . $this->airtableTable;
        $filter = sprintf("AND({Currency} = '%s', IS_AFTER({Date}, '%s'), IS_BEFORE({Date}, '%s'))", $currency, (new \DateTimeImmutable($startDate))->modify('-1 day')->format('Y-m-d'), (new \DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d'));
        $params = http_build_query([
            'filterByFormula' => $filter,
            'maxRecords' => 100,
            'sort[0][field]' => 'Date',
            'sort[0][direction]' => 'asc',
        ]);
        $response = $this->httpClient->request('GET', "$url?$params", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->airtableApiKey,
            ],
        ]);
        $data = $response->toArray(false);
        $result = [];
        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            if (!empty($fields['Date']) && isset($fields['Mid'])) {
                $result[] = [
                    'date' => $fields['Date'],
                    'mid' => (float)$fields['Mid'],
                ];
            }
        }
        return $result;
    }

    private function fetchRatesHistoryFromAirtableBatch(array $currencies, string $startDate, string $endDate): array
    {
        $url = self::AIRTABLE_API_URL . $this->airtableBaseId . "/" . $this->airtableTable;
        $currencyFilter = implode(',', array_map(fn($c) => "'{$c}'", $currencies));
        $filter = "AND(FIND({Currency}, '" . implode(",", $currencies) . "'), IS_AFTER({Date}, '$startDate'), IS_BEFORE({Date}, '$endDate'))";
        // Możesz też użyć OR({Currency}='USD', {Currency}='EUR', ...)
        // lub po prostu pobrać wszystkie i przefiltrować w PHP jeśli walut jest mało
        $params = http_build_query([
            'filterByFormula' => $filter,
            'maxRecords' => 100,
            'sort[0][field]' => 'Date',
            'sort[0][direction]' => 'asc',
        ]);
        $response = $this->httpClient->request('GET', "$url?$params", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->airtableApiKey,
            ],
        ]);
        $data = $response->toArray(false);
        $result = [];
        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            if (!empty($fields['Date']) && isset($fields['Mid']) && isset($fields['Currency'])) {
                $result[] = [
                    'date' => $fields['Date'],
                    'mid' => (float)$fields['Mid'],
                    'currency' => $fields['Currency'],
                ];
            }
        }
        return $result;
    }

    /**
     * Zapisuje kurs do Airtable.
     */
    private function saveRateToAirtable(string $currency, string $date, float $mid): void
    {
        $url = self::AIRTABLE_API_URL . $this->airtableBaseId . "/" . $this->airtableTable;
        $fields = [
            'Currency' => $currency,
            'Date' => $date,
            'Mid' => $mid,
        ];
        $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->airtableApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => ['fields' => $fields],
        ]);
    }

    /**
     * Usuwa z Airtable kursy starsze niż podany zakres dni.
     */
    private function deleteOldRatesFromAirtable(string $currency, string $latestDate, int $days): void
    {
        $cutoff = (new \DateTimeImmutable($latestDate))->modify('-' . $days . ' days')->format('Y-m-d');
        $url = self::AIRTABLE_API_URL . $this->airtableBaseId . "/" . $this->airtableTable;
        $filter = sprintf("AND({Currency} = '%s', IS_BEFORE({Date}, '%s'))", $currency, $cutoff);
        $params = http_build_query([
            'filterByFormula' => $filter,
            'maxRecords' => 100,
        ]);
        $response = $this->httpClient->request('GET', "$url?$params", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->airtableApiKey,
            ],
        ]);
        $data = $response->toArray(false);
        foreach ($data['records'] ?? [] as $record) {
            if (!empty($record['id'])) {
                $this->httpClient->request('DELETE', $url . '/' . $record['id'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->airtableApiKey,
                    ],
                ]);
            }
        }
    }

    private function fetchAverageRateFromNbp(string $currency, ?string $date = null): ?float
    {
        $url = self::NBP_API_URL . strtoupper($currency);
        if ($date) {
            $url .= '/' . $date;
        }
        $url .= '?format=json';
        try {
            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();
            if (isset($data['rates'][0]['mid'])) {
                return (float)$data['rates'][0]['mid'];
            }
        } catch (\Throwable $e) {
            // Można logować błąd
        }
        return null;
    }

    private function getDateRange(string $startDate, string $endDate): array
    {
        $range = [];
        $current = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        while ($current <= $end) {
            $range[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }
        return $range;
    }

    // --- POMOCNICZE METODY DO AIRTABLE I NBP ---

    private function fetchAverageRatesHistoryFromNbp(string $currency, string $startDate, string $endDate): array
    {
        $url = self::NBP_API_URL . strtoupper($currency) . '/' . $startDate . '/' . $endDate . '/?format=json';
        try {
            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();
            if (isset($data['rates'])) {
                return array_map(fn($rate) => [
                    'date' => $rate['effectiveDate'],
                    'mid' => (float)$rate['mid'],
                ], $data['rates']);
            }
        } catch (\Throwable $e) {
            // Można logować błąd
        }
        return [];
    }

    private function saveRatesBatchToAirtable(string $currency, array $rates): void
    {
        $url = self::AIRTABLE_API_URL . $this->airtableBaseId . "/" . $this->airtableTable;
        $records = [];
        foreach ($rates as $rate) {
            $records[] = [
                'fields' => [
                    'Currency' => $currency,
                    'Date' => $rate['date'],
                    'Mid' => $rate['mid'],
                ]
            ];
        }
        // Airtable API allows up to 10 records per batch request
        $chunks = array_chunk($records, 10);
        foreach ($chunks as $chunk) {
            $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->airtableApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['records' => $chunk],
            ]);
        }
    }

    /**
     * Pobiera batchowo kursy z Airtable dla wielu walut na konkretną datę.
     */
    private function fetchRatesTodayFromAirtableBatch(array $currencies, string $date): array
    {
        $url = self::AIRTABLE_API_URL . $this->airtableBaseId . "/" . $this->airtableTable;
        // Budujemy formułę OR({Currency}='USD', {Currency}='EUR', ...) AND {Date} = '2024-06-01'
        $orParts = array_map(fn($c) => sprintf("{Currency} = '%s'", $c), $currencies);
        $filter = sprintf("AND(OR(%s), {Date} = '%s')", implode(",", $orParts), $date);
        $params = http_build_query([
            'filterByFormula' => $filter,
            'maxRecords' => count($currencies),
        ]);
        $response = $this->httpClient->request('GET', "$url?$params", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->airtableApiKey,
            ],
        ]);
        $data = $response->toArray(false);
        $result = [];
        foreach ($data['records'] ?? [] as $record) {
            $fields = $record['fields'];
            if (!empty($fields['Date']) && isset($fields['Mid']) && isset($fields['Currency'])) {
                $result[] = [
                    'date' => $fields['Date'],
                    'mid' => (float)$fields['Mid'],
                    'currency' => $fields['Currency'],
                ];
            }
        }
        return $result;
    }
}
