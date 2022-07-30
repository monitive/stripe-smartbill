<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class StripeTest extends TestCase
{
    public function testItRetrievesStripeChargeIdsForSucceededChargesWithoutSmartbillMetaInvoice()
    {
        $stripe = new Stripe();
        $mock = new MockHandler([
            new Response(200, [], json_encode($this->makeStripeCharges())),
            // new Response(202, ['Content-Length' => 0]),
            // new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);
        $stripe->setClient(
            new Client(['handler' => HandlerStack::create($mock)])
        );

        // execute
        $charges = $stripe->getChargeIdsAfterDateWithoutSmartbillMeta(new DateTime('2022-07-01'));

        // assert
        $this->assertSame([
            1659100000 => 'ch_3',
            1659500000 => 'ch_4',
            1659200000 => 'ch_7',
        ], $charges);
    }

    private function makeStripeCharges(): array
    {
        return [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'ch_3',
                    'customer' => 'cus_J',
                    'created' => 1659100000,
                    'metadata' => [],
                    'status' => 'succeeded'
                ],
                [
                    'id' => 'ch_4',
                    'customer' => 'cus_J',
                    'created' => 1659500000,
                    'metadata' => [],
                    'status' => 'succeeded'
                ],
                [
                    'id' => 'ch_5',
                    'customer' => 'cus_J',
                    'created' => 1659300000,
                    'metadata' => ['smartbill_invoice' => 'inv_1'],
                    'status' => 'succeeded'
                ],
                [
                    'id' => 'ch_6',
                    'customer' => 'cus_J',
                    'created' => 1659400000,
                    'metadata' => [],
                    'status' => 'failed'
                ],
                [
                    'id' => 'ch_7',
                    'customer' => 'cus_J',
                    'created' => 1659200000,
                    'metadata' => [],
                    'status' => 'succeeded'
                ],
            ]
        ];
    }

    public function testItRetrievesASingleCharge()
    {
        $stripe = new Stripe();
        $mock = new MockHandler([
            new Response(200, [], json_encode($this->makeStripeCharge())),
        ]);
        $stripe->setClient(
            new Client(['handler' => HandlerStack::create($mock)])
        );

        // execute
        $charge = $stripe->getChargeById('ch_3');

        // assert
        $this->assertSame('cus_J', $charge['customer']);
        $this->assertSame('ch_3', $charge['id']);
        $this->assertSame('succeeded', $charge['status']);
        $this->assertSame(1659100000, $charge['created']);
    }

    private function makeStripeCharge(): array
    {
        return [
            'id' => 'ch_3',
            'customer' => 'cus_J',
            'created' => 1659100000,
            'metadata' => [],
            'status' => 'succeeded'
        ];
    }

    public function testItRetrievesASingleCustomer()
    {
        $stripe = new Stripe();
        $mock = new MockHandler([
            new Response(200, [], json_encode($this->makeStripeCustomer())),
        ]);
        $stripe->setClient(
            new Client(['handler' => HandlerStack::create($mock)])
        );

        // execute
        $customer = $stripe->getCustomerById('ch_3');

        // assert
        $this->assertSame('cus_J', $customer['id']);
        $this->assertSame('John Doe', $customer['name']);
        $this->assertSame('London', $customer['address']['city']);
    }

    private function makeStripeCustomer(): array
    {
        return [
            'id' => 'cus_J',
            'object' => 'customer',
            'created' => 1659100000,
            'name' => 'John Doe',
            'metadata' => [],
            'address' => [
                'city' => 'London',
                'country' => 'GB',
                'line1' => '43 Burnham Way',
                'line2' => '',
                'postal_code' => '',
                'state' => ''
            ]
        ];
    }

    public function testItRetrievesASingleInvoice()
    {
        $stripe = new Stripe();
        $mock = new MockHandler([
            new Response(200, [], json_encode($this->makeStripeInvoice())),
        ]);
        $stripe->setClient(
            new Client(['handler' => HandlerStack::create($mock)])
        );

        // execute
        $invoice = $stripe->getInvoiceById('in_1LH');

        // assert
        $this->assertSame('in_1LH', $invoice['id']);
        $this->assertSame('cus_J', $invoice['customer']);
        $this->assertSame(1659100000, $invoice['created']);
        $this->assertSame('invoice', $invoice['object']);
    }

    private function makeStripeInvoice(): array
    {
        return [
            'id' => 'in_1LH',
            'object' => 'invoice',
            'created' => 1659100000,
            'customer' => 'cus_J',
            'account_country' => 'RO',
        ];
    }
}
