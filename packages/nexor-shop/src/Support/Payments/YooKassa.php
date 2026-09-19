<?php

namespace Nexor\Shop\Support\Payments;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Клиент Payments API ЮKassa.
 *
 * Отвечает только за разговор с провайдером: заголовки, ключ идемпотентности,
 * разбор ошибок. Что именно отправлять — решает `Payments`.
 */
class YooKassa
{
    public const URL = 'https://api.yookassa.ru/v3';

    /** Адреса, с которых ЮKassa шлёт уведомления. */
    public const NOTIFICATION_NETWORKS = [
        '185.71.76.0/27',
        '185.71.77.0/27',
        '77.75.153.0/25',
        '77.75.156.11/32',
        '77.75.156.35/32',
        '77.75.154.128/25',
        '2a02:5180::/32',
    ];

    public function __construct(
        protected string $shopId,
        protected string $secretKey,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function createPayment(array $body, string $idempotenceKey): array
    {
        return $this->request('post', 'payments', $body, $idempotenceKey);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function payment(string $id): array
    {
        return $this->request('get', 'payments/'.$id);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function createRefund(array $body, string $idempotenceKey): array
    {
        return $this->request('post', 'refunds', $body, $idempotenceKey);
    }

    /**
     * Проверка ключей: запрашиваем список платежей одной строкой.
     *
     * @throws PaymentException
     */
    public function ping(): void
    {
        $this->request('get', 'payments?limit=1');
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    protected function request(string $method, string $path, ?array $body = null, ?string $idempotenceKey = null): array
    {
        $request = Http::timeout(20)
            ->withBasicAuth($this->shopId, $this->secretKey)
            ->acceptJson()
            ->asJson();

        if ($idempotenceKey !== null) {
            // Повтор с тем же ключом не создаёт второй платёж — это защита от
            // двойного списания при обрыве связи и от двойного нажатия кнопки.
            $request = $request->withHeaders(['Idempotence-Key' => $idempotenceKey]);
        }

        try {
            $response = $method === 'get'
                ? $request->get(self::URL.'/'.$path)
                : $request->post(self::URL.'/'.$path, $body ?? []);
        } catch (Throwable $exception) {
            Log::error('ЮKassa недоступна: '.$exception->getMessage());

            throw new PaymentException('Платёжный сервис не отвечает. Попробуйте ещё раз через минуту.');
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw new PaymentException($this->explain($response));
    }

    /**
     * Ответ об ошибке — по-русски и с подсказкой, что делать.
     */
    protected function explain(Response $response): string
    {
        $code = (string) $response->json('code', '');
        $description = (string) $response->json('description', '');

        Log::error('ЮKassa ответила ошибкой', ['status' => $response->status(), 'body' => $response->json()]);

        return match (true) {
            $response->status() === 401 => 'ЮKassa не приняла ключи магазина — проверьте shopId и секретный ключ.',
            $code === 'invalid_credentials' => 'ЮKassa не приняла ключи магазина — проверьте shopId и секретный ключ.',
            $code === 'invalid_request' => 'ЮKassa отклонила запрос: '.($description ?: 'проверьте данные заказа.'),
            $response->status() === 429 => 'ЮKassa просит подождать: слишком много запросов подряд.',
            $description !== '' => 'ЮKassa: '.$description,
            default => 'ЮKassa ответила ошибкой '.$response->status().'.',
        };
    }
}
