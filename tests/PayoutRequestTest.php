<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\ResponseInterface;

class PayoutRequestTest extends \PHPUnit_Framework_TestCase
{
    const URL = 'https://gate.wetestcode.com/orders/sites/37/payout';
    const SALT = '1234567890';
    const SIGNATURE_HEADER = 'X-Signature';

    function testPayoutRequest()
    {
        $body = json_encode($this->getRequestData());

        $client = new Client();

        try {
            $response = $client->post(
                self::URL,
                [
                    // подпись запроса передается в заголовке
                    'headers' => [self::SIGNATURE_HEADER => $this->getSignature($body)],
                    'body' => $body,
                ]
            );
        } catch (RequestException $e) {
            $this->assertTrue($e->hasResponse(), 'Empty response should be treated as invalid');
            $response = $e->getResponse();
        }

        $responseJson = $this->handleResponse($response);

        $this->assertNotNull($responseJson);
    }

    private function getRequestData()
    {
        return
            [
                'order' => [
                    'external_id' => 'test_order_1',
                    'amount' => 100, // в минимальных единицах (копейки, центы,...)
                    'currency' => 'USD',
                    'comment' => 'test order',
                ],
                'payment_instruments' => [
                    [
                        'card' => [
                            'number' => '4000000000000002',
                            'holder' => 'Ivanov Ivan',
                            'expiry_month' => '11',
                            'expiry_year' => '2018'
                        ]
                    ]
                ],
                'customer' => [
                    'phone' => '79001001010',
                    'birthdate' => '1950-01-01',
                    'address' => [
                        'address' => 'Red Square, 1',
                        'city' => 'Moscow',
                        'index' => '123456'
                    ],
                    'name' => [
                        'first' => 'Ivan',
                        'last' => 'Ivanov',
                        'middle' => 'Ivanovich'
                    ],
                    'document' => [
                        'type' => 1,
                        'id' => '4500111111',
                        'issue_date' => '2007-03-01',
                        'issued_by' => 'MVD'
                    ]
                ]
            ];
    }

    private function getSignature($body)
    {
        return base64_encode(sha1($body . self::SALT));
    }

    private function handleResponse(ResponseInterface $response)
    {
        switch ($response->getStatusCode()) {
            // В зависимости от полученного кода вам необходимо предпринять действие, указанное в документации
            case 200:
                // запрос принят к исполнению
            case 400:
                // ошибка в передаваемых параметрах
            case 500:
                // ошибка на стороне Paymantix
            case 503:
                // повторите запрос поздее
            default: //например 409
                // свяжитесь со службой поддержки Paymantix
        }

        $this->verifySignature($response);
        return json_decode((string)$response->getBody(), true);
    }

    /**
     * Ответы подписываются аналогично запросам.
     * Это применимо и к формированию выплаты, и к callback
     * @param ResponseInterface $response
     */
    private function verifySignature(ResponseInterface $response)
    {
        $this->assertEquals(
            $this->getSignature((string)$response->getBody()),
            $response->getHeader(self::SIGNATURE_HEADER),
            'Incorrect signature'
        );
    }
}
