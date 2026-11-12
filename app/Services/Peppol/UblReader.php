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
     * @return array{is_credit_note: bool, supplier_name: string, supplier_vat: ?string, supplier_endpoint: ?string, receiver_endpoint: ?string, supplier_invoice_number: string, invoice_date: string, due_date: ?string, subtotal: float, vat_total: float, total: float, description: ?string, pdf: ?array{name: string, data: string}, order_reference: ?string, sales_order_id: ?string, buyer_reference: ?string, despatch_references: list<string>, lines: list<array{seller_id: ?string, manufacturer_id: ?string, name: ?string, quantity: float, unit: ?string, price: ?float}>}
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
            'despatch_references' => array_values(array_filter(array_map(fn ($node): string => trim($node->textContent), iterator_to_array($xpath->query('/*/cac:DespatchDocumentReference/cbc:ID'))))),
            'lines' => $this->lines($xpath),
        ];
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
            ];
        }

        return $lines;
    }
}
