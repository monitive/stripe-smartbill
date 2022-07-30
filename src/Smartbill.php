<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp\Client;

class Smartbill
{
    private const REQUEST_TIMEOUT = 10.0;

    private const VAT_RATES = [
        'exempt' => ['percentage' => 0, 'name' => 'SFDD'], // scutit de TVA (afara UE)
        'reverse' => ['percentage' => 0, 'name' => 'Taxare inversa'], // taxare inversa (UE)
        'RO' => ['percentage' => 19, 'name' => 'Normala'],
        'GB' => ['percentage' => 0, 'name' => 'SFDD'], // recent iesita din UE
        'DE' => ['percentage' => 19, 'name' => 'Germania'],
        'DK' => ['percentage' => 25, 'name' => 'DK VAT'],
        'NL' => ['percentage' => 21, 'name' => 'NL VAT'],
        'FR' => ['percentage' => 20, 'name' => 'FR VAT'],
        'GR' => ['percentage' => 24, 'name' => 'Grecia'],
    ];

    private array $settings;
    private string $api_token;

    private Client $client;

    public function __construct(array $settings)
    {
        $this->api_token = base64_encode($settings['SMARTBILL_API_KEY']);
        $this->settings = $settings;
        $this->client = new Client([
            'base_uri' => 'https://ws.smartbill.ro',
            'timeout'  => self::REQUEST_TIMEOUT,
        ]);
    }

    public function createInvoice(array $stripe_invoice, array $stripe_charge): array
    {
        // Smartbill issue date will be the Stripe payment date, not Stripe invoice date
        $issueDate = DateTime::createFromFormat('U', (string) $stripe_charge['created'])->format('Y-m-d');

        $smartbill_invoice = [
            'companyVatCode' => (string) $this->settings['SMARTBILL_COMPANY_CUI'],
            'client' => [
                'address' => $stripe_invoice['customer_address']['line1']
                    . ' ' . $stripe_invoice['customer_address']['line2'],
                'city' => $stripe_invoice['customer_address']['city'],
                'county' => $stripe_invoice['customer_address']['state'],
                'code' => $stripe_invoice['customer'],
                'email' => $stripe_invoice['customer_email'],
                'country' => $stripe_invoice['customer_address']['country'],
                'isTaxPayer' => false,
                'saveToDb' => false,
                'name' => (string) ucwords(trim($stripe_invoice['customer_name']))
            ],
            'issueDate' => $issueDate,
            'dueDate' => $issueDate,
            'products' => [],
            // 'precision' => 2,
            'language' => 'RO',
            // 'useEstimateDetails' => false,
            'currency' => strtoupper($stripe_invoice['currency']),
            // 'isDraft' => false,
            'seriesName' => (string) $this->settings['SMARTBILL_SERIES'],
            'mentions' => $this->settings['SMARTBILL_MENTIUNI'] ?? '',
            'aviz' => sprintf('STRIPE Invoice %s', (string) $stripe_invoice['number']),
        ];

        if (
            !empty($stripe_invoice['customer_tax_ids']['0']['type'])
            && $stripe_invoice['customer_tax_ids']['0']['type'] === 'eu_vat'
            && !empty($stripe_invoice['customer_tax_ids']['0']['value'])
        ) {
            $smartbill_invoice['client']['vatCode'] = $stripe_invoice['customer_tax_ids']['0']['value'];
        }

        if (!empty($this->settings['SMARTBILL_INTOCMIT_NUME']) && !empty($this->settings['SMARTBILL_INTOCMIT_CNP'])) {
            $smartbill_invoice['issuerName'] = (string) $this->settings['SMARTBILL_INTOCMIT_NUME'];
            $smartbill_invoice['issuerCnp'] = (string) $this->settings['SMARTBILL_INTOCMIT_CNP'];
        }

        if (!empty($this->settings['SMARTBILL_DELEGAT_NUME']) && !empty($this->settings['SMARTBILL_DELEGAT_CI'])) {
            $smartbill_invoice['delegateName'] = (string) $this->settings['SMARTBILL_DELEGAT_NUME'];
            $smartbill_invoice['delegateIdentityCard'] = (string) $this->settings['SMARTBILL_DELEGAT_CI'];
        }

        if ($stripe_invoice['customer_tax_exempt'] === 'none') {
            $customer_country = $stripe_invoice['customer_address']['country'];
            if (!isset(self::VAT_RATES[$customer_country])) {
                throw new \Exception(
                    'Country ' . $customer_country . ' not defined in Smarbill::VAT_RATES rates, please define.'
                );
            }
            $taxName = self::VAT_RATES[$customer_country]['name'];
            $taxPercentage = self::VAT_RATES[$customer_country]['percentage'];
        }

        if ($stripe_invoice['customer_tax_exempt'] === 'exempt') {
            $taxName = self::VAT_RATES['exempt']['name'];
            $taxPercentage = self::VAT_RATES['exempt']['percentage'];
            $smartbill_invoice['mentions'] .=
                "\n\nServicii neimpozabile in Romania conform articolului 133 alineatul 2, litera G din Codul Fiscal.";
        }

        if ($stripe_invoice['customer_tax_exempt'] === 'reverse') {
            $taxName = self::VAT_RATES['reverse']['name'];
            $taxPercentage = self::VAT_RATES['reverse']['percentage'];
            $smartbill_invoice['mentions'] .= "\n\nTaxarea inversă, conform prevederilor art. 331 din Codul Fiscal.";
        }

        foreach ($stripe_invoice['lines']['data'] as $line) {
            $product = [
                'quantity' => $line['quantity'],
                'isService' => true,
                'measuringUnitName' => 'buc',
                'saveToDb' => false,
                'isDiscount' => false,
                'code' => $line['price']['id'] ?? '',
                'price' => $line['unit_amount_excluding_tax'] / 100,
                'isTaxIncluded' => false,
                'taxName' => $taxName,
                'taxPercentage' => $taxPercentage,
                'currency' => strtoupper($line['currency']),
                'name' => $line['description'],
            ];

            $smartbill_invoice['products'][] = $product;
        }

        // Add payment information
        $smartbill_invoice['payment'] = [
            'value' => $stripe_charge['amount'] / 100,
            // 'paymentSeries' => 'CCC', // not needed
            'type' => 'Card',
            'isCash' => false
        ];

        return $this->sendPostJsonRequest('SBORO/api/invoice', $smartbill_invoice);
    }

    public function getPayment(string $smartbill_invoice_number): array
    {
        return $this->sendGetRequest('SBORO/api/invoice/paymentstatus', [
            'cif' => $this->settings['SMARTBILL_COMPANY_CUI'],
            'seriesname' => $this->settings['SMARTBILL_SERIES'],
            'number' => $smartbill_invoice_number
        ]);
    }

    private function sendGetRequest(string $path, array $parameters = []): array
    {
        $response = $this->client->request('GET', $path, [
            'query' => $parameters,
            'headers' => $this->buildHeaders(),
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body;
    }

    private function sendPostJsonRequest(string $path, array $json_body = []): array
    {
        $response = $this->client->request('POST', $path, [
            'headers' => $this->buildHeaders(),
            'json' => $json_body
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function buildHeaders(): array
    {
        return [
            'Authorization' => sprintf('Basic %s', $this->api_token),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }
}
