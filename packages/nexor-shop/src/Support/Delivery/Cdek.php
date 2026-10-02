<?php

namespace Nexor\Shop\Support\Delivery;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Клиент API СДЭК (v2).
 *
 * Отвечает только за разговор со службой: токен, адреса методов, разбор
 * ошибок. Что именно спрашивать — решают `Delivery\Rates` и панель.
 *
 * Токен живёт час и кэшируется: СДЭК ограничивает частоту авторизаций, а
 * оформление заказа обращается к калькулятору на каждое изменение адреса.
 */
class Cdek
{
    public const URL = 'https://api.cdek.ru/v2';

    /**
     * Тестовый контур: заказы никуда не едут.
     *
     * Ключи к нему свои, боевые не подойдут. Их выдаёт СДЭК — те, что когда-то
     * публиковались в документации, уже отозваны.
     */
    public const TEST_URL = 'https://api.edu.cdek.ru/v2';

    public function __construct(
        protected string $account,
        protected string $secret,
        protected bool $test = false,
        protected ?int $methodId = null,
    ) {}

    public function url(): string
    {
        return $this->test ? self::TEST_URL : self::URL;
    }

    /**
     * Проверка ключей: если токен выдали, разговаривать можно.
     *
     * @throws DeliveryException
     */
    public function ping(): void
    {
        $this->token(fresh: true);
    }

    /**
     * Подсказки по городам: то, что покупатель набирает в поле «Город».
     *
     * @return array<int, array{code: int, city: string, region: string, full: string}>
     *
     * @throws DeliveryException
     */
    public function cities(string $query, int $limit = 10): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $rows = $this->request('get', 'location/suggest/cities', [
            'name' => $query,
            'country_code' => 'RU',
        ]);

        return collect($rows)
            ->filter(fn ($row) => isset($row['code']))
            ->take($limit)
            ->map(fn (array $row) => [
                'code' => (int) $row['code'],
                'city' => (string) ($row['city'] ?? ''),
                'region' => (string) ($row['region'] ?? ''),
                'full' => (string) ($row['full_name'] ?? $row['city'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Расчёт по конкретному тарифу.
     *
     * @param  array<string, mixed>  $payload
     * @return array{price: float, period_min: int|null, period_max: int|null}
     *
     * @throws DeliveryException
     */
    public function calculate(array $payload): array
    {
        $response = $this->request('post', 'calculator/tariff', $payload);

        if (isset($response['errors'][0]['message'])) {
            throw new DeliveryException((string) $response['errors'][0]['message']);
        }

        if (! isset($response['total_sum'])) {
            throw new DeliveryException('СДЭК не рассчитал доставку по этому тарифу.');
        }

        return [
            'price' => (float) $response['total_sum'],
            'period_min' => isset($response['period_min']) ? (int) $response['period_min'] : null,
            'period_max' => isset($response['period_max']) ? (int) $response['period_max'] : null,
        ];
    }

    /**
     * Регистрирует заказ и возвращает номер заявки.
     *
     * Трек-номера в этом ответе ещё нет: СДЭК присваивает его через несколько
     * секунд, поэтому за ним ходят отдельно — `shipment()`.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DeliveryException
     */
    public function createOrder(array $payload): string
    {
        $response = $this->request('post', 'orders', $payload);

        $uuid = (string) ($response['entity']['uuid'] ?? '');

        if ($uuid === '') {
            throw new DeliveryException(self::requestError($response) ?? 'СДЭК не принял заказ.');
        }

        return $uuid;
    }

    /**
     * Состояние заявки: трек-номер, статус посылки, состояние обработки и ошибки.
     *
     * Состояние заявки (`ACCEPTED` — принята и обрабатывается, `SUCCESSFUL` —
     * обработана, `INVALID` — отклонена) объясняет, почему номера ещё нет.
     *
     * @return array{track: string|null, status: string|null, code: string|null, state: string|null, error: string|null}
     *
     * @throws DeliveryException
     */
    public function shipment(string $uuid): array
    {
        $response = $this->request('get', 'orders/'.$uuid);

        $statuses = $response['entity']['statuses'] ?? [];
        $requests = $response['requests'] ?? [];

        return [
            'track' => filled($response['entity']['cdek_number'] ?? null)
                ? (string) $response['entity']['cdek_number']
                : null,
            // Статусы приходят по возрастанию даты — последний и есть текущий.
            'status' => filled(end($statuses)['name'] ?? null) ? (string) end($statuses)['name'] : null,
            // Код того же статуса (DELIVERED, INVALID…): по нему видно, что следить дальше незачем.
            'code' => filled(end($statuses)['code'] ?? null) ? (string) end($statuses)['code'] : null,
            'state' => filled(end($requests)['state'] ?? null) ? (string) end($requests)['state'] : null,
            'error' => self::requestError($response),
        ];
    }

    /**
     * Текст отказа СДЭК по заявке, если он есть.
     *
     * @param  array<mixed>  $response
     */
    protected static function requestError(array $response): ?string
    {
        foreach ($response['requests'] ?? [] as $request) {
            foreach ($request['errors'] ?? [] as $error) {
                if (filled($error['message'] ?? null)) {
                    return (string) $error['message'];
                }
            }
        }

        return isset($response['errors'][0]['message']) ? (string) $response['errors'][0]['message'] : null;
    }

    /**
     * Пункты выдачи города.
     *
     * @return array<int, array{code: string, name: string, address: string, full: string, latitude: float|null, longitude: float|null, work_time: string, type: string}>
     *
     * @throws DeliveryException
     */
    public function deliveryPoints(int $cityCode, int $limit = 300): array
    {
        $rows = $this->request('get', 'deliverypoints', [
            'city_code' => $cityCode,
            'country_code' => 'RU',
        ]);

        return collect($rows)
            ->take($limit)
            ->map(fn (array $row) => [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? $row['code'] ?? ''),
                'address' => (string) ($row['location']['address'] ?? ''),
                'full' => (string) ($row['location']['address_full'] ?? $row['location']['address'] ?? ''),
                'latitude' => isset($row['location']['latitude']) ? (float) $row['location']['latitude'] : null,
                'longitude' => isset($row['location']['longitude']) ? (float) $row['location']['longitude'] : null,
                'work_time' => (string) ($row['work_time'] ?? ''),
                'type' => (string) ($row['type'] ?? 'PVZ'),
            ])
            ->filter(fn (array $point) => $point['code'] !== '')
            ->values()
            ->all();
    }

    /**
     * Токен доступа. Живёт час, поэтому кэшируется почти на час.
     *
     * @throws DeliveryException
     */
    protected function token(bool $fresh = false): string
    {
        $key = 'nexor-shop:cdek:token:'.($this->methodId ?? 0).':'.md5($this->url().$this->account.$this->secret);

        if ($fresh) {
            Cache::forget($key);
        }

        $token = Cache::get($key);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        try {
            $response = $this->send(fn () => Http::asForm()->post($this->url().'/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->account,
                'client_secret' => $this->secret,
            ]));
        } catch (DeliveryException $exception) {
            // Отказ на авторизации — это всегда ключи, а не «что-то пошло не так».
            throw str_contains($exception->getMessage(), 'invalid_client') || str_contains($exception->getMessage(), '401')
                ? new DeliveryException('СДЭК не принял ключи: проверьте аккаунт и секретный пароль'
                    .($this->test ? ' тестового контура.' : '.'))
                : $exception;
        }

        $token = (string) ($response['access_token'] ?? '');

        if ($token === '') {
            throw new DeliveryException('СДЭК не выдал токен — проверьте аккаунт и секретный пароль.');
        }

        // Токен действует час; забираем запас, чтобы не поймать протухший.
        Cache::put($key, $token, now()->addMinutes(50));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     *
     * @throws DeliveryException
     */
    protected function request(string $method, string $path, array $payload = []): array
    {
        $token = $this->token();

        $response = $this->send(fn () => $method === 'get'
            ? Http::withToken($token)->acceptJson()->get($this->url().'/'.$path, $payload)
            : Http::withToken($token)->acceptJson()->post($this->url().'/'.$path, $payload));

        return $response;
    }

    /**
     * Запрос с разбором ответа: сетевые сбои и ошибки СДЭК — в исключение.
     *
     * @param  callable(): Response  $send
     * @return array<mixed>
     *
     * @throws DeliveryException
     */
    protected function send(callable $send): array
    {
        try {
            $response = $send();
        } catch (Throwable $exception) {
            Log::warning('СДЭК недоступен: '.$exception->getMessage());

            throw new DeliveryException('Служба доставки сейчас недоступна.');
        }

        $body = $response->json();

        if ($response->failed()) {
            $message = $body['errors'][0]['message']
                ?? $body['error_description']
                ?? $body['message']
                ?? $body['error']
                ?? 'ответ '.$response->status();

            Log::warning('СДЭК отказал: '.json_encode($body, JSON_UNESCAPED_UNICODE));

            throw new DeliveryException('СДЭК: '.$message);
        }

        return is_array($body) ? $body : [];
    }
}
