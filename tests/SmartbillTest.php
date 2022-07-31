<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmartbillTest extends TestCase
{
    private ?Smartbill $smartbill = null;

    public function setUp(): void
    {
        parent::setUp();

        $settings = [
            'SMARTBILL_API_KEY' => 'X',
            'SMARTBILL_SERIES' => 'SMURF',
            'SMARTBILL_COMPANY_CUI' => 'RO11111111',
            'SMARTBILL_INTOCMIT_NUME' => 'Ion Popescu',
            'SMARTBILL_INTOCMIT_CNP' => '1000000000000',
            'SMARTBILL_DELEGAT_NUME' => 'Ion Popescu',
            'SMARTBILL_DELEGAT_CI' => 'XX 123456',
            'SMARTBILL_MENTIUNI' => 'Factura incasata cu card bancar prin Stripe.',
        ];

        $vat_rates = [
            'exempt' => ['percentage' => 0, 'name' => 'SFDD'],
            'reverse' => ['percentage' => 0, 'name' => 'Taxare inversa'],
            'RO' => ['percentage' => 19, 'name' => 'Normala'],
            'DE' => ['percentage' => 15, 'name' => 'TVA DE'],
        ];

        $this->smartbill = new Smartbill($settings, $vat_rates);
    }

    public function testItSendsCreateSmartbillInvoiceRequest()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'series' => 'SMURF',
                'number' => '1234',
            ])),
        ]);
        $this->smartbill->setClient(
            new Client(['handler' => HandlerStack::create($mock)])
        );

        $stripe_invoice = $this->makeStripeInvoice();
        $stripe_charge = $this->makeStripeCharge();

        // execute
        $response = $this->smartbill->createInvoice($stripe_invoice, $stripe_charge);

        $this->assertSame('SMURF', $response['series']);
        $this->assertSame('1234', $response['number']);
    }

    public function testItCreatesARomanianVatInvoice()
    {
        $stripe_invoice = $this->makeStripeInvoice();
        $stripe_charge = $this->makeStripeCharge();

        // execute
        $invoice = $this->smartbill->buildInvoice($stripe_invoice, $stripe_charge);

        $this->assertSame('RO11111111', $invoice['companyVatCode']);
        $this->assertSame(
            [
                'address' => 'Str. Principala Nr. 30',
                'city' => 'Timisoara',
                'county' => '',
                'code' => 'cus_J',
                'email' => 'jhonny@mnemonic.com',
                'country' => 'RO',
                'isTaxPayer' => false,
                'saveToDb' => false,
                'name' => 'Johnny Mnemonic',
            ],
            $invoice['client']
        );
        $this->assertSame('2022-07-29', $invoice['issueDate']);
        $this->assertSame('2022-07-29', $invoice['dueDate']);
        $this->assertSame('RO', $invoice['language']);
        $this->assertSame('EUR', $invoice['currency']);
        $this->assertSame('SMURF', $invoice['seriesName']);
        $this->assertSame('Factura incasata cu card bancar prin Stripe.', $invoice['mentions']);
        $this->assertSame('STRIPE Invoice XX-123456', $invoice['aviz']);
        $this->assertSame('Ion Popescu', $invoice['issuerName']);
        $this->assertSame('1000000000000', $invoice['issuerCnp']);
        $this->assertSame('Ion Popescu', $invoice['delegateName']);
        $this->assertSame('XX 123456', $invoice['delegateIdentityCard']);

        $this->assertCount(2, $invoice['products']);
        $this->assertSame(2, $invoice['products'][0]['quantity']);
        // First product
        $this->assertSame(true, $invoice['products'][0]['isService']);
        $this->assertSame('buc', $invoice['products'][0]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isDiscount']);
        $this->assertSame('', $invoice['products'][0]['code']);
        $this->assertSame(3.5, $invoice['products'][0]['price']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isTaxIncluded']);
        $this->assertSame('Normala', $invoice['products'][0]['taxName']);
        $this->assertSame(19, $invoice['products'][0]['taxPercentage']);
        $this->assertSame('EUR', $invoice['products'][0]['currency']);
        $this->assertSame('Basic service', $invoice['products'][0]['name']);
        // Second product
        $this->assertSame(true, $invoice['products'][1]['isService']);
        $this->assertSame('buc', $invoice['products'][1]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isDiscount']);
        $this->assertSame('', $invoice['products'][1]['code']);
        $this->assertSame(6, $invoice['products'][1]['price']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isTaxIncluded']);
        $this->assertSame('Normala', $invoice['products'][1]['taxName']);
        $this->assertSame(19, $invoice['products'][1]['taxPercentage']);
        $this->assertSame('EUR', $invoice['products'][1]['currency']);
        $this->assertSame('Elite service', $invoice['products'][1]['name']);

        $this->assertIsArray($invoice['payment']);
        $this->assertNotEmpty($invoice['payment']);
        $this->assertEquals(23, $invoice['payment']['value']);
        $this->assertEquals(false, $invoice['payment']['isCash']);
        $this->assertEquals('Card', $invoice['payment']['type']);
    }

    public function testItCreatesAnEUReverseChargeInvoiceWithoutVAT()
    {
        $stripe_invoice = $this->makeStripeInvoice();
        $stripe_invoice['customer_address']['country'] = 'DE';
        $stripe_invoice['customer_tax_exempt'] = 'reverse';
        $stripe_invoice['customer_tax_ids'] = [
            [
                'type' => 'eu_vat',
                'value' => 'DE987654321', // DE VAT number
            ]
        ];
        $stripe_charge = $this->makeStripeCharge();

        // execute
        $invoice = $this->smartbill->buildInvoice($stripe_invoice, $stripe_charge);

        $this->assertSame('RO11111111', $invoice['companyVatCode']);
        $this->assertSame('DE', $invoice['client']['country']);
        $this->assertSame('2022-07-29', $invoice['issueDate']);
        $this->assertSame('2022-07-29', $invoice['dueDate']);
        $this->assertSame('RO', $invoice['language']);
        $this->assertSame('EUR', $invoice['currency']);
        $this->assertSame('SMURF', $invoice['seriesName']);
        $this->assertStringContainsString(
            "Taxarea inversă, conform prevederilor art. 331 din Codul Fiscal",
            $invoice['mentions']
        );
        $this->assertSame('STRIPE Invoice XX-123456', $invoice['aviz']);
        $this->assertSame('Ion Popescu', $invoice['issuerName']);
        $this->assertSame('1000000000000', $invoice['issuerCnp']);
        $this->assertSame('Ion Popescu', $invoice['delegateName']);
        $this->assertSame('XX 123456', $invoice['delegateIdentityCard']);

        $this->assertCount(2, $invoice['products']);
        $this->assertSame(2, $invoice['products'][0]['quantity']);
        // First product
        $this->assertSame(true, $invoice['products'][0]['isService']);
        $this->assertSame('buc', $invoice['products'][0]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isDiscount']);
        $this->assertSame('', $invoice['products'][0]['code']);
        $this->assertSame(3.5, $invoice['products'][0]['price']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isTaxIncluded']);
        $this->assertSame('Taxare inversa', $invoice['products'][0]['taxName']);
        $this->assertSame(0, $invoice['products'][0]['taxPercentage']);
        $this->assertSame('EUR', $invoice['products'][0]['currency']);
        $this->assertSame('Basic service', $invoice['products'][0]['name']);
        // Second product
        $this->assertSame(true, $invoice['products'][1]['isService']);
        $this->assertSame('buc', $invoice['products'][1]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isDiscount']);
        $this->assertSame('', $invoice['products'][1]['code']);
        $this->assertSame(6, $invoice['products'][1]['price']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isTaxIncluded']);
        $this->assertSame('Taxare inversa', $invoice['products'][1]['taxName']);
        $this->assertSame(0, $invoice['products'][1]['taxPercentage']);
        $this->assertSame('EUR', $invoice['products'][1]['currency']);
        $this->assertSame('Elite service', $invoice['products'][1]['name']);

        $this->assertIsArray($invoice['payment']);
        $this->assertNotEmpty($invoice['payment']);
        $this->assertEquals(23, $invoice['payment']['value']);
        $this->assertEquals(false, $invoice['payment']['isCash']);
        $this->assertEquals('Card', $invoice['payment']['type']);
    }

    public function testItCreatesAnEUInvoiceWithAddedVatIfClientVatCodeIsMissing()
    {
        $stripe_invoice = $this->makeStripeInvoice();
        $stripe_invoice['customer_address']['country'] = 'DE';
        $stripe_invoice['customer_tax_exempt'] = 'none';
        $stripe_charge = $this->makeStripeCharge();

        // execute
        $invoice = $this->smartbill->buildInvoice($stripe_invoice, $stripe_charge);

        $this->assertSame('RO11111111', $invoice['companyVatCode']);
        $this->assertSame('DE', $invoice['client']['country']);
        $this->assertSame('2022-07-29', $invoice['issueDate']);
        $this->assertSame('2022-07-29', $invoice['dueDate']);
        $this->assertSame('RO', $invoice['language']);
        $this->assertSame('EUR', $invoice['currency']);
        $this->assertSame('SMURF', $invoice['seriesName']);
        $this->assertSame(
            "Factura incasata cu card bancar prin Stripe.",
            $invoice['mentions']
        );
        $this->assertSame('STRIPE Invoice XX-123456', $invoice['aviz']);
        $this->assertSame('Ion Popescu', $invoice['issuerName']);
        $this->assertSame('1000000000000', $invoice['issuerCnp']);
        $this->assertSame('Ion Popescu', $invoice['delegateName']);
        $this->assertSame('XX 123456', $invoice['delegateIdentityCard']);

        $this->assertCount(2, $invoice['products']);
        $this->assertSame(2, $invoice['products'][0]['quantity']);
        // First product
        $this->assertSame(true, $invoice['products'][0]['isService']);
        $this->assertSame('buc', $invoice['products'][0]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isDiscount']);
        $this->assertSame('', $invoice['products'][0]['code']);
        $this->assertSame(3.5, $invoice['products'][0]['price']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isTaxIncluded']);
        $this->assertSame('TVA DE', $invoice['products'][0]['taxName']); // TVA Germany
        $this->assertSame(15, $invoice['products'][0]['taxPercentage']); // 15% (test VAT)
        $this->assertSame('EUR', $invoice['products'][0]['currency']);
        $this->assertSame('Basic service', $invoice['products'][0]['name']);
        // Second product
        $this->assertSame(true, $invoice['products'][1]['isService']);
        $this->assertSame('buc', $invoice['products'][1]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isDiscount']);
        $this->assertSame('', $invoice['products'][1]['code']);
        $this->assertSame(6, $invoice['products'][1]['price']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isTaxIncluded']);
        $this->assertSame('TVA DE', $invoice['products'][1]['taxName']);
        $this->assertSame(15, $invoice['products'][1]['taxPercentage']);
        $this->assertSame('EUR', $invoice['products'][1]['currency']);
        $this->assertSame('Elite service', $invoice['products'][1]['name']);

        $this->assertIsArray($invoice['payment']);
        $this->assertNotEmpty($invoice['payment']);
        $this->assertEquals(23, $invoice['payment']['value']);
        $this->assertEquals(false, $invoice['payment']['isCash']);
        $this->assertEquals('Card', $invoice['payment']['type']);
    }

    public function testItCreatesAnInvoiceWithoutVATWhenChargingOutsideEU()
    {
        $stripe_invoice = $this->makeStripeInvoice();
        $stripe_invoice['customer_address']['country'] = 'US';
        $stripe_invoice['customer_tax_exempt'] = 'exempt';
        $stripe_charge = $this->makeStripeCharge();

        // execute
        $invoice = $this->smartbill->buildInvoice($stripe_invoice, $stripe_charge);

        $this->assertSame('RO11111111', $invoice['companyVatCode']);
        $this->assertSame('US', $invoice['client']['country']);
        $this->assertSame('2022-07-29', $invoice['issueDate']);
        $this->assertSame('2022-07-29', $invoice['dueDate']);
        $this->assertSame('RO', $invoice['language']);
        $this->assertSame('EUR', $invoice['currency']);
        $this->assertSame('SMURF', $invoice['seriesName']);
        $this->assertStringContainsString(
            "Servicii neimpozabile in Romania conform articolului 133 alineatul 2, litera G din Codul Fiscal.",
            $invoice['mentions']
        );
        $this->assertSame('STRIPE Invoice XX-123456', $invoice['aviz']);
        $this->assertSame('Ion Popescu', $invoice['issuerName']);
        $this->assertSame('1000000000000', $invoice['issuerCnp']);
        $this->assertSame('Ion Popescu', $invoice['delegateName']);
        $this->assertSame('XX 123456', $invoice['delegateIdentityCard']);

        $this->assertCount(2, $invoice['products']);
        $this->assertSame(2, $invoice['products'][0]['quantity']);
        // First product
        $this->assertSame(true, $invoice['products'][0]['isService']);
        $this->assertSame('buc', $invoice['products'][0]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isDiscount']);
        $this->assertSame('', $invoice['products'][0]['code']);
        $this->assertSame(3.5, $invoice['products'][0]['price']);
        $this->assertSame(false, $invoice['products'][0]['saveToDb']);
        $this->assertSame(false, $invoice['products'][0]['isTaxIncluded']);
        $this->assertSame('SFDD', $invoice['products'][0]['taxName']); // TVA Germany
        $this->assertSame(0, $invoice['products'][0]['taxPercentage']); // 15% (test VAT)
        $this->assertSame('EUR', $invoice['products'][0]['currency']);
        $this->assertSame('Basic service', $invoice['products'][0]['name']);
        // Second product
        $this->assertSame(true, $invoice['products'][1]['isService']);
        $this->assertSame('buc', $invoice['products'][1]['measuringUnitName']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isDiscount']);
        $this->assertSame('', $invoice['products'][1]['code']);
        $this->assertSame(6, $invoice['products'][1]['price']);
        $this->assertSame(false, $invoice['products'][1]['saveToDb']);
        $this->assertSame(false, $invoice['products'][1]['isTaxIncluded']);
        $this->assertSame('SFDD', $invoice['products'][1]['taxName']);
        $this->assertSame(0, $invoice['products'][1]['taxPercentage']);
        $this->assertSame('EUR', $invoice['products'][1]['currency']);
        $this->assertSame('Elite service', $invoice['products'][1]['name']);

        $this->assertIsArray($invoice['payment']);
        $this->assertNotEmpty($invoice['payment']);
        $this->assertEquals(23, $invoice['payment']['value']);
        $this->assertEquals(false, $invoice['payment']['isCash']);
        $this->assertEquals('Card', $invoice['payment']['type']);
    }

    public function testItThrowsAnErrorIfVatRatesAreNotDefined()
    {

        $stripe_invoice = $this->makeStripeInvoice();
        $stripe_invoice['customer_address']['country'] = 'US';
        $stripe_invoice['customer_tax_exempt'] = 'exempt';
        $stripe_charge = $this->makeStripeCharge();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'VAT rates are not defined, please ensure vat_rates.json contains VAT rates.'
        );

        // execute
        $smartbill = new Smartbill([
            'SMARTBILL_API_KEY' => 'X',
            'SMARTBILL_COMPANY_CUI' => 'RO98786',
        ], []);
        $smartbill->buildInvoice($stripe_invoice, $stripe_charge);
    }

    private function makeStripeInvoice(): array
    {
        return [
            'id' => 'in_1LH',
            'object' => 'invoice',
            'customer' => 'cus_J',
            'customer_name' => 'Johnny Mnemonic',
            'customer_email' => 'jhonny@mnemonic.com',
            'customer_address' => [
                'city' => 'Timisoara',
                'country' => 'RO',
                'line1' => 'Str. Principala Nr. 30',
                'line2' => '',
                'postal_code' => '',
                'state' => '',
            ],
            'currency' => 'eur',
            'number' => 'XX-123456',
            'created' => 1659100000,
            'customer_tax_ids' => [],
            'customer_tax_exempt' => 'none',
            'lines' => [
                'data' => [
                    [
                        'quantity' => 2,
                        'unit_amount_excluding_tax' => 350,
                        'currency' => 'eur',
                        'description' => 'Basic service',
                    ],
                    [
                        'quantity' => 1,
                        'unit_amount_excluding_tax' => 600,
                        'currency' => 'eur',
                        'description' => 'Elite service',
                    ]
                ]
            ]
        ];
    }

    private function makeStripeCharge(): array
    {
        return [
            'id' => 'ch_3',
            'customer' => 'cus_J',
            'created' => 1659100000,
            'metadata' => [],
            'amount' => 2300,
            'status' => 'succeeded'
        ];
    }

    public function testItRetrievesThePaymentStatusForAnInvoice()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'invoiceTotalAmount' => 150,
                'paidAmount' => 500,
            ])),
        ]);
        $this->smartbill->setClient(
            new Client(['handler' => HandlerStack::create($mock)])
        );

        // execute
        $response = $this->smartbill->getPayment('123456');

        $this->assertSame(150, $response['invoiceTotalAmount']);
        $this->assertSame(500, $response['paidAmount']);
    }
}
