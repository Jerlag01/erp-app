<?php

namespace App\Services\Peppol;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

/**
 * Reads an incoming UBL invoice or credit note (Peppol BIS 3.0) into the fields of an expense.
 * External entities are never loaded (XXE-safe).
 */
class UblReader
{
    /**
     * @return array{is_credit_note: bool, supplier_name: string, supplier_vat: ?string, supplier_endpoint: ?string, receiver_endpoint: ?string, supplier_invoice_number: string, invoice_date: string, due_date: ?string, subtotal: float, vat_total: float, total: float, description: ?string, pdf: ?array{name: string, data: string}, order_reference: ?string, sales_order_id: ?string, buyer_reference: ?string, despatch_references: list<string>, sales_order_ids: list<string>, lines: list<array{seller_id: ?string, manufacturer_id: ?string, name: ?string, quantity: float, unit: ?string, price: ?float, line_total: float, vat_category: ?string, vat_percent: ?float}>, payment_id: ?string, currency: string, note: ?string, billing_reference: ?string, customer: array{name: ?string, vat: ?string, endpoint: ?string, street: ?string, house_number: ?string, postal_code: ?string, city: ?string, country: ?string, email: ?string}}
     */
    public function read(string $xml): array
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new InvalidArgumentException('UBL documents may not contain a DOCTYPE.');
        }

        $doc = new DOMDocument;

        if (! @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('Not a valid XML document.');
        }

        $root = $doc->documentElement?->localName;

        if (! in_array($root, ['Invoice', 'CreditNote'], true)) {
            throw new InvalidArgumentException('Not a UBL invoice or credit note.');
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $value = fn (string $path): ?string => ($node = $xpath->query($path)->item(0)) ? trim($node->textContent) : null;

        $supplier = '/*/cac:AccountingSupplierParty/cac:Party';
        $endpoint = fn (string $party): ?string => ($node = $xpath->query("{$party}/cbc:EndpointID")->item(0))
            ? trim($node->attributes?->getNamedItem('schemeID')?->nodeValue.':'.$node->textContent, ':')
            : null;

        $pdf = null;

        foreach ($xpath->query('/*/cac:AdditionalDocumentReference/cac:Attachment/cbc:EmbeddedDocumentBinaryObject') as $node) {
            if (($node->attributes?->getNamedItem('mimeCode')?->nodeValue) === 'application/pdf') {
                $data = base64_decode(preg_replace('/\s+/', '', $node->textContent), true);

                if ($data !== false && str_starts_with($data, '%PDF')) {
                    $pdf = ['name' => basename($node->attributes?->getNamedItem('filename')?->nodeValue ?: 'invoice.pdf'), 'data' => $data];
                    break;
                }
            }
        }

        $total = (float) ($value('/*/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount') ?? $value('/*/cac:LegalMonetaryTotal/cbc:PayableAmount') ?? 0);
        $vat = (float) ($value('/*/cac:TaxTotal/cbc:TaxAmount') ?? 0);

        return [
            'is_credit_note' => $root === 'CreditNote',
            'supplier_name' => $value("{$supplier}/cac:PartyLegalEntity/cbc:RegistrationName") ?? $value("{$supplier}/cac:PartyName/cbc:Name") ?? '?',
            'supplier_vat' => $value("{$supplier}/cac:PartyTaxScheme/cbc:CompanyID"),
            'supplier_endpoint' => $endpoint($supplier),
            'receiver_endpoint' => $endpoint('/*/cac:AccountingCustomerParty/cac:Party'),
            'supplier_invoice_number' => (string) $value('/*/cbc:ID'),
            'invoice_date' => (string) $value('/*/cbc:IssueDate'),
            'due_date' => $value('/*/cbc:DueDate') ?? $value('/*/cac:PaymentMeans/cbc:PaymentDueDate'),
            'subtotal' => (float) ($value('/*/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount') ?? round($total - $vat, 2)),
            'vat_total' => $vat,
            'total' => $total,
            'description' => $value('/*/cac:InvoiceLine/cac:Item/cbc:Name') ?? $value('/*/cac:CreditNoteLine/cac:Item/cbc:Name'),
            'pdf' => $pdf,
            'order_reference' => $value('/*/cac:OrderReference/cbc:ID'),
            'sales_order_id' => $value('/*/cac:OrderReference/cbc:SalesOrderID'),
            'buyer_reference' => $value('/*/cbc:BuyerReference'),
            'despatch_references' => $this->distinct($xpath, '/*/cac:DespatchDocumentReference/cbc:ID | //cac:DespatchLineReference/cac:DocumentReference/cbc:ID'),
            'sales_order_ids' => $this->distinct($xpath, '/*/cac:OrderReference/cbc:SalesOrderID | //cac:OrderLineReference/cac:OrderReference/cbc:SalesOrderID'),
            'lines' => $this->lines($xpath),
            'payment_id' => $value('/*/cac:PaymentMeans/cbc:PaymentID'),
            'currency' => $value('/*/cbc:DocumentCurrencyCode') ?? 'EUR',
            'note' => $value('/*/cbc:Note'),
            'billing_reference' => $value('/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'),
            'customer' => $this->party($xpath, '/*/cac:AccountingCustomerParty/cac:Party', $endpoint),
        ];
    }

    /**
     * A party's name, VAT number, address and e-mail address.
     *
     * @param  callable(string): ?string  $endpoint
     * @return array{name: ?string, vat: ?string, endpoint: ?string, street: ?string, house_number: ?string, postal_code: ?string, city: ?string, country: ?string, email: ?string}
     */
    protected function party(DOMXPath $xpath, string $party, callable $endpoint): array
    {
        $value = fn (string $path): ?string => ($node = $xpath->query("{$party}/{$path}")->item(0)) && trim($node->textContent) !== '' ? trim($node->textContent) : null;

        return [
            'name' => $value('cac:PartyLegalEntity/cbc:RegistrationName') ?? $value('cac:PartyName/cbc:Name'),
            'vat' => $value('cac:PartyTaxScheme/cbc:CompanyID') ?? $value('cac:PartyLegalEntity/cbc:CompanyID'),
            'endpoint' => $endpoint($party),
            'street' => $value('cac:PostalAddress/cbc:StreetName'),
            'house_number' => $value('cac:PostalAddress/cbc:BuildingNumber'),
            'postal_code' => $value('cac:PostalAddress/cbc:PostalZone'),
            'city' => $value('cac:PostalAddress/cbc:CityName'),
            'country' => $value('cac:PostalAddress/cac:Country/cbc:IdentificationCode'),
            'email' => $value('cac:Contact/cbc:ElectronicMail'),
        ];
    }

    /**
     * The different non-empty values at these paths, in document order.
     *
     * @return list<string>
     */
    protected function distinct(DOMXPath $xpath, string $paths): array
    {
        $values = array_map(fn ($node): string => trim($node->textContent), iterator_to_array($xpath->query($paths)));

        return array_values(array_unique(array_filter($values, fn (string $value): bool => $value !== '')));
    }

    /**
     * The invoice (or credit note) lines: the seller's and manufacturer's item numbers, the name, the
     * quantity with its unit and the net price per unit.
     *
     * @return list<array{seller_id: ?string, manufacturer_id: ?string, name: ?string, quantity: float, unit: ?string, price: ?float}>
     */
    protected function lines(DOMXPath $xpath): array
    {
        $lines = [];

        foreach ($xpath->query('/*/cac:InvoiceLine | /*/cac:CreditNoteLine') as $line) {
            $value = fn (string $path): ?string => ($node = $xpath->query($path, $line)->item(0)) && trim($node->textContent) !== '' ? trim($node->textContent) : null;
            $quantity = $xpath->query('cbc:InvoicedQuantity | cbc:CreditedQuantity', $line)->item(0);
            $price = $value('cac:Price/cbc:PriceAmount');
            $base = (float) ($value('cac:Price/cbc:BaseQuantity') ?? 1) ?: 1;

            $lines[] = [
                'seller_id' => $value('cac:Item/cac:SellersItemIdentification/cbc:ID'),
                'manufacturer_id' => $value('cac:Item/cac:ManufacturersItemIdentification/cbc:ID'),
                'name' => $value('cac:Item/cbc:Name') ?? $value('cac:Item/cbc:Description'),
                'quantity' => (float) ($quantity?->textContent ?? 0),
                'unit' => $quantity?->attributes?->getNamedItem('unitCode')?->nodeValue,
                'price' => $price === null ? null : round((float) $price / $base, 4),
                'line_total' => (float) ($value('cbc:LineExtensionAmount') ?? 0),
                'vat_category' => $value('cac:Item/cac:ClassifiedTaxCategory/cbc:ID'),
                'vat_percent' => ($percent = $value('cac:Item/cac:ClassifiedTaxCategory/cbc:Percent')) === null ? null : (float) $percent,
            ];
        }

        return $lines;
    }
}
