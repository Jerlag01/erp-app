<?php

namespace App\Services\Peppol;

use App\Enums\VatRate;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use DOMDocument;
use DOMElement;

/**
 * Writes an invoice or credit note as Peppol BIS Billing 3.0 (UBL 2.1, EN 16931), the format
 * required on the Peppol network. The PDF is embedded so the receiver also sees the layout.
 */
class UblWriter
{
    public const CUSTOMIZATION = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    public const PROFILE = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    protected const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    protected const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    protected DOMDocument $doc;

    public function write(Invoice $invoice, ?string $pdf = null): string
    {
        $invoice->loadMissing(['lines', 'client.country', 'poNumbers', 'creditedInvoice']);
        $isCredit = $invoice->isCreditNote();
        $root = $isCredit ? 'CreditNote' : 'Invoice';

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $rootElement = $this->doc->createElementNS("urn:oasis:names:specification:ubl:schema:xsd:{$root}-2", $root);
        $rootElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $rootElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $this->doc->appendChild($rootElement);

        $this->cbc($rootElement, 'CustomizationID', self::CUSTOMIZATION);
        $this->cbc($rootElement, 'ProfileID', self::PROFILE);
        $this->cbc($rootElement, 'ID', (string) $invoice->number);
        $this->cbc($rootElement, 'IssueDate', $invoice->issue_date->toDateString());

        if (! $isCredit && $invoice->due_date) {
            $this->cbc($rootElement, 'DueDate', $invoice->due_date->toDateString());
        }

        $this->cbc($rootElement, $isCredit ? 'CreditNoteTypeCode' : 'InvoiceTypeCode', $isCredit ? '381' : '380');

        if ($invoice->hasReverseCharge()) {
            $this->cbc($rootElement, 'Note', 'Btw verlegd – art. 51 §2 W.BTW / Autoliquidation');
        }

        $this->cbc($rootElement, 'DocumentCurrencyCode', 'EUR');
        // BT-10: the buyer's reference; Peppol requires this or an order reference.
        $this->cbc($rootElement, 'BuyerReference', $invoice->client_reference ?: ($invoice->poNumberList()[0] ?? (string) $invoice->number));

        if ($po = $invoice->poNumberList()) {
            $order = $this->cac($rootElement, 'OrderReference');
            $this->cbc($order, 'ID', implode(', ', $po));
        }

        if ($isCredit && $invoice->creditedInvoice) {
            $reference = $this->cac($this->cac($rootElement, 'BillingReference'), 'InvoiceDocumentReference');
            $this->cbc($reference, 'ID', (string) $invoice->creditedInvoice->number);
            $this->cbc($reference, 'IssueDate', $invoice->creditedInvoice->issue_date->toDateString());
        }

        if ($pdf !== null) {
            $attachment = $this->cac($rootElement, 'AdditionalDocumentReference');
            $this->cbc($attachment, 'ID', (string) $invoice->number);
            $binary = $this->cbc($this->cac($attachment, 'Attachment'), 'EmbeddedDocumentBinaryObject', base64_encode($pdf));
            $binary->setAttribute('mimeCode', 'application/pdf');
            $binary->setAttribute('filename', $invoice->number.'.pdf');
        }

        $this->supplier($rootElement);
        $this->customer($rootElement, $invoice);

        if (! $isCredit) {
            $means = $this->cac($rootElement, 'PaymentMeans');
            $this->cbc($means, 'PaymentMeansCode', '30');
            $this->cbc($means, 'PaymentID', (string) preg_replace('/\D/', '', (string) $invoice->structured_reference));

            if ($iban = settings()->get('finance.iban')) {
                $account = $this->cac($means, 'PayeeFinancialAccount');
                $this->cbc($account, 'ID', $iban);

                if ($bic = settings()->get('finance.bic')) {
                    $this->cbc($this->cac($account, 'FinancialInstitutionBranch'), 'ID', $bic);
                }
            }

            if ($invoice->due_date) {
                $this->cbc($this->cac($rootElement, 'PaymentTerms'), 'Note', __('erp.finance.pdf.pay_before', ['date' => $invoice->due_date->format('d/m/Y')]));
            }
        }

        $breakdown = $invoice->vatBreakdown();
        $taxTotal = $this->cac($rootElement, 'TaxTotal');
        $this->amount($taxTotal, 'TaxAmount', (float) $invoice->vat_total);

        foreach ($breakdown as $group) {
            $subtotal = $this->cac($taxTotal, 'TaxSubtotal');
            $this->amount($subtotal, 'TaxableAmount', $group['base']);
            $this->amount($subtotal, 'TaxAmount', $group['vat']);
            $this->taxCategory($this->cac($subtotal, 'TaxCategory'), $group['rate'], withReason: true);
        }

        $totals = $this->cac($rootElement, 'LegalMonetaryTotal');
        $this->amount($totals, 'LineExtensionAmount', (float) $invoice->subtotal);
        $this->amount($totals, 'TaxExclusiveAmount', (float) $invoice->subtotal);
        $this->amount($totals, 'TaxInclusiveAmount', (float) $invoice->total);
        $this->amount($totals, 'PayableAmount', (float) $invoice->total);

        foreach ($invoice->lines->values() as $index => $line) {
            $this->line($rootElement, $line, $index + 1, $isCredit);
        }

        return (string) $this->doc->saveXML();
    }

    protected function supplier(DOMElement $root): void
    {
        $party = $this->cac($this->cac($root, 'AccountingSupplierParty'), 'Party');
        $vat = (string) settings()->get('finance.vat_number');
        $endpoint = settings()->get('finance.peppol_id') ?: (str_starts_with($vat, 'BE') ? '0208:'.substr($vat, 2) : null);

        if ($endpoint) {
            [$scheme, $id] = explode(':', $endpoint, 2);
            $this->cbc($party, 'EndpointID', $id)->setAttribute('schemeID', $scheme);
        }

        $name = settings()->get('finance.company_name') ?: settings()->siteName();
        $this->cbc($this->cac($party, 'PartyName'), 'Name', $name);
        $this->address($party, settings()->get('finance.street').' '.settings()->get('finance.house_number'), settings()->get('finance.city'), settings()->get('finance.postal_code'), settings()->get('finance.country_code') ?: 'BE');

        if ($vat !== '') {
            $tax = $this->cac($party, 'PartyTaxScheme');
            $this->cbc($tax, 'CompanyID', $vat);
            $this->cbc($this->cac($tax, 'TaxScheme'), 'ID', 'VAT');
        }

        $legal = $this->cac($party, 'PartyLegalEntity');
        $this->cbc($legal, 'RegistrationName', $name);

        if (str_starts_with($vat, 'BE')) {
            $this->cbc($legal, 'CompanyID', substr($vat, 2))->setAttribute('schemeID', '0208');
        }

        if ($email = settings()->get('finance.email')) {
            $this->cbc($this->cac($party, 'Contact'), 'ElectronicMail', $email);
        }
    }

    protected function customer(DOMElement $root, Invoice $invoice): void
    {
        $client = $invoice->client;
        $party = $this->cac($this->cac($root, 'AccountingCustomerParty'), 'Party');

        if ($participant = $client->peppolParticipant()) {
            [$scheme, $id] = explode(':', $participant, 2);
            $this->cbc($party, 'EndpointID', $id)->setAttribute('schemeID', $scheme);
        }

        $this->cbc($this->cac($party, 'PartyName'), 'Name', $client->company_name);
        $this->address($party, trim($client->street.' '.$client->house_number.' '.$client->addition), $client->city, $client->postal_code, $client->country?->code ?: 'BE');

        if ($client->vat_number) {
            $tax = $this->cac($party, 'PartyTaxScheme');
            $this->cbc($tax, 'CompanyID', $client->vat_number);
            $this->cbc($this->cac($tax, 'TaxScheme'), 'ID', 'VAT');
        }

        $this->cbc($this->cac($party, 'PartyLegalEntity'), 'RegistrationName', $client->company_name);

        if ($email = $client->invoiceEmail()) {
            $this->cbc($this->cac($party, 'Contact'), 'ElectronicMail', $email);
        }
    }

    protected function address(DOMElement $party, ?string $street, ?string $city, ?string $postalCode, string $country): void
    {
        $address = $this->cac($party, 'PostalAddress');

        if (filled(trim((string) $street))) {
            $this->cbc($address, 'StreetName', trim((string) $street));
        }

        if (filled($city)) {
            $this->cbc($address, 'CityName', $city);
        }

        if (filled($postalCode)) {
            $this->cbc($address, 'PostalZone', $postalCode);
        }

        $this->cbc($this->cac($address, 'Country'), 'IdentificationCode', strtoupper($country));
    }

    protected function line(DOMElement $root, InvoiceLine $line, int $number, bool $isCredit): void
    {
        $element = $this->cac($root, $isCredit ? 'CreditNoteLine' : 'InvoiceLine');
        $this->cbc($element, 'ID', (string) $number);
        $this->cbc($element, $isCredit ? 'CreditedQuantity' : 'InvoicedQuantity', $this->decimal((float) $line->quantity, 3))->setAttribute('unitCode', 'C62');
        $this->amount($element, 'LineExtensionAmount', (float) $line->line_total);

        $item = $this->cac($element, 'Item');
        $this->cbc($item, 'Name', mb_substr($line->description, 0, 200));
        $this->taxCategory($this->cac($item, 'ClassifiedTaxCategory'), $line->vat_rate ?? VatRate::TwentyOne);

        $this->amount($this->cac($element, 'Price'), 'PriceAmount', (float) $line->unit_price);
    }

    protected function taxCategory(DOMElement $category, VatRate $rate, bool $withReason = false): void
    {
        $this->cbc($category, 'ID', $rate->ublCategory());
        $this->cbc($category, 'Percent', $this->decimal($rate->percentage(), 2));

        if ($withReason && $rate === VatRate::ReverseCharge) {
            $this->cbc($category, 'TaxExemptionReasonCode', 'VATEX-EU-AE');
            $this->cbc($category, 'TaxExemptionReason', 'Reverse charge');
        }

        $this->cbc($this->cac($category, 'TaxScheme'), 'ID', 'VAT');
    }

    protected function amount(DOMElement $parent, string $name, float $value): DOMElement
    {
        $element = $this->cbc($parent, $name, $this->decimal($value, 2));
        $element->setAttribute('currencyID', 'EUR');

        return $element;
    }

    protected function decimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    protected function cbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $this->doc->createElementNS(self::CBC, 'cbc:'.$name);
        $element->appendChild($this->doc->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    protected function cac(DOMElement $parent, string $name): DOMElement
    {
        $element = $this->doc->createElementNS(self::CAC, 'cac:'.$name);
        $parent->appendChild($element);

        return $element;
    }
}
