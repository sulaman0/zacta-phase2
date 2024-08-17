<?php

namespace ZATCA;

use DOMDocument;

class ZATCASimplifiedTaxInvoice
{
    private $ZATCAInvoiceTypes = [
        'INVOICE' => 388,
        'DEBIT_NOTE' => 383,
        'CREDIT_NOTE' => 381,
    ];

    public function __construct()
    {
    }

    public function simplifiedTaxInvoice(array $invoice, array $egs_unit)
    {
        $populated_template = require ROOT_PATH . '/ZATCA/templates/simplified_tax_invoice_template.php';

        $populated_template = str_replace('SET_INVOICE_TYPE', $this->ZATCAInvoiceTypes[$egs_unit['cancelation']['cancelation_type']], trim($populated_template));

        // if canceled (BR-KSA-56) set reference number to canceled invoice
        if (isset($egs_unit['cancelation']['canceled_invoice_number']) && $egs_unit['cancelation']['canceled_invoice_number']) {
            $populated_template = str_replace('SET_BILLING_REFERENCE', $this->defaultBillingReference($egs_unit['cancelation']['canceled_invoice_number']), $populated_template);
        } else {
            $populated_template = str_replace('SET_BILLING_REFERENCE', '', $populated_template);
        }

        $populated_template = str_replace('SET_INVOICE_SERIAL_NUMBER', $invoice['invoice_serial_number'], $populated_template);
        $populated_template = str_replace('SET_TERMINAL_UUID', $egs_unit['uuid'], $populated_template);
        $populated_template = str_replace('SET_ISSUE_DATE', $invoice['issue_date'], $populated_template);
        $populated_template = str_replace('SET_ISSUE_TIME', $invoice['issue_time'], $populated_template);
        $populated_template = str_replace('SET_PREVIOUS_INVOICE_HASH', $invoice['previous_invoice_hash'], $populated_template);
        $populated_template = str_replace('SET_INVOICE_COUNTER_NUMBER', $invoice['invoice_counter_number'], $populated_template);
        $populated_template = str_replace('SET_COMMERCIAL_REGISTRATION_NUMBER', $egs_unit['CRN_number'], $populated_template);

        $populated_template = str_replace('SET_STREET_NAME', $egs_unit['location']['street'], $populated_template);
        $populated_template = str_replace('SET_BUILDING_NUMBER', $egs_unit['location']['building'], $populated_template);
        $populated_template = str_replace('SET_PLOT_IDENTIFICATION', $egs_unit['location']['plot_identification'], $populated_template);
        $populated_template = str_replace('SET_CITY_SUBDIVISION', $egs_unit['location']['city_subdivision'], $populated_template);
        $populated_template = str_replace('SET_CITY', $egs_unit['location']['city'], $populated_template);
        $populated_template = str_replace('SET_POSTAL_NUMBER', $egs_unit['location']['postal_zone'], $populated_template);

        $populated_template = str_replace('SET_VAT_NUMBER', $egs_unit['VAT_number'], $populated_template);
        $populated_template = str_replace('SET_VAT_NAME', $egs_unit['VAT_name'], $populated_template);

        $parseLineItems = $this->parseLineItems($invoice['line_items']);
        $populated_template = str_replace('PARSE_LINE_ITEMS', $parseLineItems, $populated_template);

        $document = new DOMDocument();
        $document->loadXML($populated_template);
        return $document;
    }

    private function defaultBillingReference(string $invoice_number): string
    {
        $populated_template = require ROOT_PATH . '/ZATCA/templates/invoice_billing_reference_template.php';
        return str_replace('SET_INVOICE_NUMBER', $invoice_number, $populated_template);
    }

    public function getInvoiceHash(DOMDocument $invoice_xml): string
    {
        $pure_invoice_string = $this->getPureInvoiceString($invoice_xml);

        $pure_invoice_string = str_replace('<?xml version="1.0" encoding="UTF-8"?>' . "\n", '', $pure_invoice_string);
        $pure_invoice_string = str_replace('<cac:AccountingCustomerParty/>', '<cac:AccountingCustomerParty></cac:AccountingCustomerParty>', $pure_invoice_string);

        $hash = hash('sha256', trim($pure_invoice_string));
        $hash = pack('H*', $hash);

        return base64_encode($hash);
    }

    private function getPureInvoiceString(DOMDocument $invoice_xml)
    {
        $document = new DOMDocument();
        $document->loadXML($invoice_xml->saveXML());

        while ($element = $document->getElementsByTagName('UBLExtensions')->item(0))
            $element->parentNode->removeChild($element);

        while ($element = $document->getElementsByTagName('Signature')->item(0))
            $element->parentNode->removeChild($element);

        while ($element = $document->getElementsByTagName('AdditionalDocumentReference')->item(2)) // qr code tag remove
            $element->parentNode->removeChild($element);

        return $document->saveXML();
    }

    public function getCertificateInfo(string $certificate_string): array
    {
        $cleaned_certificate_string = $this->cleanUpCertificateString($certificate_string);
        $wrapped_certificate_string = "-----BEGIN CERTIFICATE-----\n{$cleaned_certificate_string}\n-----END CERTIFICATE-----";

        $hash = $this->getCertificateHash($cleaned_certificate_string);

        $x509 = openssl_x509_parse($wrapped_certificate_string);

        // Signature, and public key extraction from x509 PEM certificate (asn1 rfc5280)
        // Crypto module does not have those functionalities so i'm the crypto boy now :(
        // https://github.com/nodejs/node/blob/main/ZATCA/crypto/crypto_x509.cc
        // https://linuxctl.com/2017/02/x509-certificate-manual-signature-verification/
        // https://github.com/junkurihara/js-x509-utils/blob/develop/ZATCA/x509.js
        // decode binary x509-formatted object

        $res = openssl_get_publickey($wrapped_certificate_string);
        $cert = openssl_pkey_get_details($res);

        $public_key = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'], '', $cert['key']);

        return [
            $hash,
            'CN=' . implode(', ', array_reverse((array) $x509['issuer'])),
            $x509['serialNumber'],
            base64_decode($public_key),
            $this->getCertificateSignature($wrapped_certificate_string),
        ];
    }

    public function getCertificateSignature(string $cer): string
    {
        $res = openssl_x509_read($cer);
        openssl_x509_export($res, $out, FALSE);

        $out = explode('Signature Algorithm:', $out);
        $out = explode('-----BEGIN CERTIFICATE-----', $out[2]);
        $out = explode("\n", $out[0]);
        $out = $out[1] . $out[2] . $out[3] . $out[4];
        $out = str_replace([':', ' '], '', $out);

        return pack('H*', $out);
    }

    public function extractSignature($certPemString)
    {

        $bin = ($certPemString);

        if (empty($certPemString) || empty($bin)) {
            return false;
        }

        $bin = substr($bin, 4);

        while (strlen($bin) > 1) {
            $seq = ord($bin[0]);
            if ($seq == 0x03 || $seq == 0x30) {
                $len = ord($bin[1]);
                $bytes = 0;

                if ($len & 0x80) {
                    $bytes = ($len & 0x0f);
                    $len = 0;
                    for ($i = 0; $i < $bytes; $i++) {
                        $len = ($len << 8) | ord($bin[$i + 2]);
                    }
                }

                if ($seq == 0x03) {
                    return substr($bin, 3 + $bytes, $len);
                } else {
                    $bin = substr($bin, 2 + $bytes + $len);
                }
            } else {
                return false;
            }
        }
        return false;
    }

    private function getCertificateHash($cleanup_certificate_string): string
    {
        $hash = openssl_digest($cleanup_certificate_string, 'sha256');
        return base64_encode($hash);
    }

    public static function cleanUpCertificateString(string $certificate_string): string
    {
        $certificate_string = str_replace('-----BEGIN CERTIFICATE-----', '', $certificate_string);
        $certificate_string = str_replace('-----END CERTIFICATE-----', '', $certificate_string);

        return trim($certificate_string);
    }

    public function createInvoiceDigitalSignature(string $invoice_hash, string $private_key)
    {
        $invoice_hash_bytes = base64_encode($invoice_hash);
        $cleanedup_private_key_string = $this->cleanUpPrivateKeyString($private_key);
        $wrapped_private_key_string = "-----BEGIN EC PRIVATE KEY-----\n{$cleanedup_private_key_string}\n-----END EC PRIVATE KEY-----";

        base64_encode(openssl_sign($invoice_hash_bytes, $binary_signature, $wrapped_private_key_string, 'sha256'));

        return base64_encode($binary_signature);
    }

    public static function cleanUpPrivateKeyString(string $private_key)
    {
        $private_key = str_replace('-----BEGIN EC PRIVATE KEY-----', '', $private_key);
        $private_key = str_replace('-----END EC PRIVATE KEY-----', '', $private_key);

        return trim($private_key);
    }

    public function generateQR(DOMDocument $invoice_xml, string $digital_signature, $public_key, $signature, string $invoice_hash)
    {
        // Extract required tags
        $seller_name = $invoice_xml->getElementsByTagName('AccountingSupplierParty')[0]
            ->getElementsByTagName('RegistrationName')[0]->textContent;

        $VAT_number = $invoice_xml->getElementsByTagName('CompanyID')[0]->textContent;

        $invoice_total = $invoice_xml->getElementsByTagName('TaxInclusiveAmount')[0]->textContent;

        $VAT_total = 0;
        if ($tax_amount = $invoice_xml->getElementsByTagName('TaxTotal')[0]) {
            $VAT_total = $tax_amount->getElementsByTagName('TaxAmount')[0]->textContent;
        }

        $issue_date = $invoice_xml->getElementsByTagName('IssueDate')[0]->textContent;
        $issue_time = $invoice_xml->getElementsByTagName('IssueTime')[0]->textContent;

        // Detect if simplified invoice or not (not used currently assuming all simplified tax invoice)
        //$invoice_type = $invoice_xml->getElementsByTagName('Invoice/cbc:InvoiceTypeCode')[0]['@_name'];

        $formatted_datetime = date('Y-m-d\TH:i:s', strtotime("{$issue_date} {$issue_time}"));
        $qr_tlv = $this->TLV([
            $seller_name,
            $VAT_number,
            $formatted_datetime,
            $invoice_total,
            $VAT_total,
            $invoice_hash,
            $digital_signature,
            $public_key,
            $signature
        ]);

        return base64_encode($qr_tlv);
    }

    private function TLV(array $tags): string
    {
        $__toHex = function ($value) {
            return pack('H*', sprintf('%02X', $value));
        };

        $__toString = function ($__tag, $__value, $__length) use ($__toHex) {
            $value = (string)$__value;
            return $__toHex($__tag) . $__toHex($__length) . $value;
        };

        foreach ($tags as $i => $tag)
            $__TLVS[] = $__toString($i + 1, $tag, strlen($tag));


        return implode('', $__TLVS) ?? '';
    }

    public function defaultUBLExtensionsSignedPropertiesForSigning(array $signed_properties_props): string
    {
        $populated_template = require ROOT_PATH . '/ZATCA/templates/ubl_signature_signed_properties_for_signing_template.php';

        $populated_template = str_replace('SET_SIGN_TIMESTAMP', $signed_properties_props['sign_timestamp'], $populated_template);
        $populated_template = str_replace('SET_CERTIFICATE_HASH', $signed_properties_props['certificate_hash'], $populated_template);
        $populated_template = str_replace('SET_CERTIFICATE_ISSUER', $signed_properties_props['certificate_issuer'], $populated_template);
        $populated_template = str_replace('SET_CERTIFICATE_SERIAL_NUMBER', $signed_properties_props['certificate_serial_number'], $populated_template);

        return $populated_template;
    }

    public function defaultUBLExtensionsSignedProperties(array $signed_properties_props): string
    {
        $populated_template = require ROOT_PATH . '/ZATCA/templates/ubl_signature_signed_properties_template.php';

        $populated_template = str_replace('SET_SIGN_TIMESTAMP', $signed_properties_props['sign_timestamp'], $populated_template);
        $populated_template = str_replace('SET_CERTIFICATE_HASH', $signed_properties_props['certificate_hash'], $populated_template);
        $populated_template = str_replace('SET_CERTIFICATE_ISSUER', $signed_properties_props['certificate_issuer'], $populated_template);
        $populated_template = str_replace('SET_CERTIFICATE_SERIAL_NUMBER', $signed_properties_props['certificate_serial_number'], $populated_template);

        return $populated_template;
    }

    public function defaultUBLExtensions(string $invoice_hash, string $signed_properties_hash, string $digital_signature, string $cleanUpCertificateString, string $ubl_signature_signed_properties_xml_string): string
    {
        $cleanUpCertificateString = $this->cleanUpCertificateString($cleanUpCertificateString);

        $populated_template = require ROOT_PATH . '/ZATCA/templates/ubl_signature.php';
        $populated_template = str_replace('SET_INVOICE_HASH', $invoice_hash, $populated_template);
        $populated_template = str_replace('SET_SIGNED_PROPERTIES_HASH', $signed_properties_hash, $populated_template);
        $populated_template = str_replace('SET_DIGITAL_SIGNATURE', $digital_signature, $populated_template);
        $populated_template = str_replace('SET_CERTIFICATE', $cleanUpCertificateString, $populated_template);
        $populated_template = str_replace('SET_SIGNED_PROPERTIES_XML', $ubl_signature_signed_properties_xml_string, $populated_template);

        return $populated_template;
    }

    /**
     * This hurts to do :'(. I hope that it's only temporary and ZATCA decides to just minify the XML before doing any hashing on it.
     * there is no logical reason why the validation expects an incorrectly indented XML.
     * Anyway, this is a function that fucks up the indentation in order to match validator hashing.
     */
    public function signedPropertiesIndentationFix(string $signed_invoice_string): string
    {
        $fixer = $signed_invoice_string;
        $signed_props_lines = explode('<ds:Object>', $fixer)[1];
        $signed_props_lines = explode('</ds:Object>', $signed_props_lines)[0];
        $signed_props_lines = explode("\n", $signed_props_lines);

        $fixed_lines = [];

        // Stripping first 4 spaces

        $fixed_lines[] = array_slice($signed_props_lines, 4);

        $signed_props_lines = array_slice($signed_props_lines, sizeof($signed_props_lines) - 1);
        $fixed_lines = array_slice($fixed_lines, sizeof($fixed_lines) - 1);

        $fixed_lines = implode("\n", $fixed_lines[0]);
        $signed_props_lines = implode("\n", $signed_props_lines);

        $fixer = str_replace($fixed_lines, $signed_props_lines, $fixer);

        return $fixer;
    }

    private function parseLineItems(array $line_items)
    {
        // BT-110
        $total_taxes = 0;
        $total_subtotal = 0;

        $invoice_line_items = [];

        array_map(function ($line_item) use (&$total_taxes, &$total_subtotal, &$invoice_line_items) {

            list($line_item_xml, $line_item_totals) = $this->constructLineItem($line_item);

            $total_taxes += $line_item_totals['taxes_total'];
            $total_subtotal += (float)$line_item_totals['subtotal'];

            $invoice_line_items[] = $line_item_xml;
        }, $line_items);

//        if(props.cancelation) {
//            // Invoice canceled. Tunred into credit/debit note. Must have PaymentMeans
//            // BR-KSA-17
//            $this->invoice_xml.set('Invoice/cac:PaymentMeans', false, {
//                'cbc:PaymentMeansCode': props.cancelation.payment_method,
//                'cbc:InstructionNote': props.cancelation.reason ?? 'No note Specified'
//            });
//        }

        /*
         * <cac:TaxTotal>
         *      </cac:TaxSubtotal> ...
         * set invoice lines
         */
        $tax_total_template = require ROOT_PATH . '/ZATCA/templates/tax_total_template.php';

        $item_lines = $this->constructTaxTotal($line_items);

        $lines = '';
        foreach ($item_lines[0]['cac:TaxSubtotal'] as $line) {

            $l = $tax_total_template['tax_sub_total'];
            $l = str_replace('46.00', $line['cbc:TaxableAmount']['#text'], $l);
            $l = str_replace('_6.89', $line['cbc:TaxAmount']['#text'], $l);
            $l = str_replace('__S', $line['cac:TaxCategory']['cbc:ID']['#text'], $l);
            $l = str_replace('15.00', $line['cac:TaxCategory']['cbc:Percent'], $l);

            $lines .= $l;
        }

        $tax_total_template['tax_total'] = str_replace('__158.67', $item_lines[0]['cbc:TaxAmount']['#text'], $tax_total_template['tax_total']);
        $tax_total_template['tax_total'] = str_replace('___tax_amount', $item_lines[1]['cbc:TaxAmount']['#text'], $tax_total_template['tax_total']);
        $tax_total_template = str_replace('__TaxSubtotal', $lines, $tax_total_template['tax_total']);

        /*
         * <cac:LegalMonetaryTotal>
         * $legal_monetary_total_template tags set
         */
        $legal_monetary_total_template = require ROOT_PATH . '/ZATCA/templates/legal_monetary_total_template.php';

        $constructLegalMonetaryTotal = $this->constructLegalMonetaryTotal($total_subtotal, $total_taxes);

        $legal_monetary_total_template = str_replace('_LineExtensionAmount', $constructLegalMonetaryTotal['cbc:LineExtensionAmount']['#text'], $legal_monetary_total_template);
        $legal_monetary_total_template = str_replace('_TaxExclusiveAmount', $constructLegalMonetaryTotal['cbc:TaxExclusiveAmount']['#text'], $legal_monetary_total_template);
        $legal_monetary_total_template = str_replace('_TaxInclusiveAmount', $constructLegalMonetaryTotal['cbc:TaxInclusiveAmount']['#text'], $legal_monetary_total_template);
        $legal_monetary_total_template = str_replace('_AllowanceTotalAmount', $constructLegalMonetaryTotal['cbc:AllowanceTotalAmount']['#text'], $legal_monetary_total_template);
        $legal_monetary_total_template = str_replace('_PrepaidAmount', $constructLegalMonetaryTotal['cbc:PrepaidAmount']['#text'], $legal_monetary_total_template);
        $legal_monetary_total_template = str_replace('_PayableAmount', $constructLegalMonetaryTotal['cbc:PayableAmount']['#text'], $legal_monetary_total_template);

        /*
         * <cac:InvoiceLine> ...
         * set invoice lines
         */
        $invoice_line_template = require_once ROOT_PATH . '/ZATCA/templates/invoice_line_template.php';

        $invoice_line = '';
        foreach ($invoice_line_items as $item) {

            $invoice_line_template_copy = $invoice_line_template['invoice_line'];

            $invoice_line_template_copy = str_replace('__ID', $item['cbc:ID'], $invoice_line_template_copy);
            $invoice_line_template_copy = str_replace('__InvoicedQuantity', $item['cbc:InvoicedQuantity']['#text'], $invoice_line_template_copy);
            $invoice_line_template_copy = str_replace('__LineExtensionAmount', $item['cbc:LineExtensionAmount']['#text'], $invoice_line_template_copy);
            $invoice_line_template_copy = str_replace('__TaxAmount', $item['cac:TaxTotal']['cbc:TaxAmount']['#text'], $invoice_line_template_copy);
            $invoice_line_template_copy = str_replace('__RoundingAmount', $item['cac:TaxTotal']['cbc:RoundingAmount']['#text'], $invoice_line_template_copy);

            $invoice_line_template_copy = str_replace('__Name', $item['cac:Item']['cbc:Name'], $invoice_line_template_copy);

            /*
             *
             */
            $iit = '';
            foreach ($item['cac:Item']['cac:ClassifiedTaxCategory'] as $ClassifiedTaxCategory) {
                $invoice_item_template = $invoice_line_template['invoice_item'];
                $invoice_item_template = str_replace('___S', $ClassifiedTaxCategory['cbc:ID'], $invoice_item_template);
                $invoice_item_template = str_replace('___Percent', $ClassifiedTaxCategory['cbc:Percent'], $invoice_item_template);

                $iit .= $invoice_item_template;
            }
            $invoice_line_template_copy = str_replace('ClassifiedTaxCategory', $iit, $invoice_line_template_copy);

            /*
             *
             */
            $ipt = '';
            foreach ($item['cac:Price']['cac:AllowanceCharge'] as $AllowanceCharge) {
                $invoice_price_template = $invoice_line_template['invoice_price'];
                $invoice_price_template = str_replace('___AllowanceChargeReason', $AllowanceCharge['cbc:AllowanceChargeReason'], $invoice_price_template);
                $invoice_price_template = str_replace('___Amount', $AllowanceCharge['cbc:Amount']['#text'], $invoice_price_template);

                $ipt .= $invoice_price_template;
            }
            $invoice_line_template_copy = str_replace('AllowanceCharge', $ipt, $invoice_line_template_copy);

            $invoice_line .= $invoice_line_template_copy;
        }

        return $tax_total_template . $legal_monetary_total_template . $invoice_line;
    }

    private function constructLineItem($line_item): array
    {
        [
            $cacAllowanceCharges,
            $cacClassifiedTaxCategories, $cacTaxTotal,
            $line_item_total_tax_exclusive,
            $line_item_total_taxes,
            $line_item_total_discounts
        ] = $this->constructLineItemTotals($line_item);

        return [
            /*'line_item_xml' => */ [
                'cbc:ID' => $line_item['id'],
                'cbc:InvoicedQuantity' => [
                    '@_unitCode' => 'PCE',
                    '#text' => $line_item['quantity']
                ],
                // BR-DEC-23
                'cbc:LineExtensionAmount' => [
                    '@_currencyID' => 'SAR',
                    '#text' => number_format($line_item_total_tax_exclusive, 2, '.', '')
                ],
                'cac:TaxTotal' => $cacTaxTotal,
                'cac:Item' => [
                    'cbc:Name' => $line_item['name'],
                    'cac:ClassifiedTaxCategory' => $cacClassifiedTaxCategories
                ],
                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '@_currencyID' => 'SAR',
                        '#text' => $line_item['tax_exclusive_price']
                    ],
                    'cac:AllowanceCharge' => $cacAllowanceCharges
                ]
            ],
            /*'line_item_totals' => */ [
                'taxes_total' => $line_item_total_taxes,
                'discounts_total' => $line_item_total_discounts,
                'subtotal' => $line_item_total_tax_exclusive
            ]
        ];
    }

    private function constructLineItemTotals($line_item): array
    {
        $line_item_total_discounts = 0;
        $line_item_total_taxes = 0;

        $cacAllowanceCharges = [];

        // VAT
        // BR-KSA-DEC-02
        $VAT = [
            'cbc:ID' => $line_item['VAT_percent'] ? 'S' : 'O',
            // BT-120, KSA-121
            'cbc:Percent' => number_format($line_item['VAT_percent'] ? ($line_item['VAT_percent'] * 100) : 0, 2, '.', ''),
            'cac:TaxScheme' => [
                'cbc:ID' => 'VAT'
            ],
        ];
        $cacClassifiedTaxCategories[] = $VAT;

        // Calc total discounts
        array_map(function ($discount) use (&$line_item_total_discounts, &$cacAllowanceCharges) {
            $line_item_total_discounts += $discount['amount'];
            $cacAllowanceCharges[] = [
                'cbc:ChargeIndicator' => 'false',
                'cbc:AllowanceChargeReason' => $discount['reason'],
                'cbc:Amount' => [
                    '@_currencyID' => 'SAR',
                    // BR-DEC-01
                    '#text' => number_format($discount['amount'], 2, '.', '')
                ]
            ];
        }, $line_item['discounts'] ?? []);


        // Calc item subtotal
        $line_item_subtotal = ($line_item['tax_exclusive_price'] * $line_item['quantity']) - $line_item_total_discounts;

        // Calc total taxes
        // BR-KSA-DEC-02
        $line_item_total_taxes = $line_item_total_taxes + ($line_item_subtotal * $line_item['VAT_percent']);

        array_map(function ($tax) use (&$line_item_total_taxes, $line_item_subtotal, &$cacClassifiedTaxCategories) {
            $line_item_total_taxes = $line_item_total_taxes + (floatval($tax['percent_amount']) * $line_item_subtotal);

            $cacClassifiedTaxCategories[] = [
                'cbc:ID' => 'S',
                'cbc:Percent' => number_format($tax['percent_amount'] * 100, 2, '.', ''),
                'cac:TaxScheme' => [
                    'cbc:ID' => 'VAT'
                ]
            ];

        }, $line_item['other_taxes'] ?? [])[0] ?? [0, 0];

        // BR-KSA-DEC-03, BR-KSA-51
        $cacTaxTotal = [
            'cbc:TaxAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => number_format($line_item_total_taxes, 2, '.', '')
            ],
            'cbc:RoundingAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => number_format($line_item_subtotal + $line_item_total_taxes, 2, '.', '')
            ]
        ];


        return [
            $cacAllowanceCharges,
            $cacClassifiedTaxCategories, $cacTaxTotal,
            $line_item_subtotal,
            $line_item_total_taxes,
            $line_item_total_discounts
        ];
    }

    private function constructLegalMonetaryTotal(float $total_subtotal, float $total_taxes)
    {
        return [
            // BR-DEC-09
            'cbc:LineExtensionAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => number_format($total_subtotal, 2, '.', '')
            ],
            // BR-DEC-12
            'cbc:TaxExclusiveAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => number_format($total_subtotal, 2, '.', '')
            ],
            // BR-DEC-14, BT-112
            'cbc:TaxInclusiveAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => number_format($total_subtotal + $total_taxes, 2, '.', '')
            ],
            'cbc:AllowanceTotalAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => 0
            ],
            'cbc:PrepaidAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => 0
            ],
            // BR-DEC-18, BT-112
            'cbc:PayableAmount' => [
                '@_currencyID' => 'SAR',
                '#text' => number_format($total_subtotal + $total_taxes, 2, '.', '')
            ]
        ];
    }

    private function constructTaxTotal(array $line_items)
    {
        $cacTaxSubtotal = [];
        // BR-DEC-13, MESSAGE : [BR-DEC-13]-The allowed maximum number of decimals for the Invoice total VAT amount (BT-110) is 2.
        $addTaxSubtotal = function ($taxable_amount, $tax_amount, $tax_percent) use (&$cacTaxSubtotal) {
            $cacTaxSubtotal[] = [
                // BR-DEC-19
                'cbc:TaxableAmount' => [
                    '@_currencyID' => 'SAR',
                    '#text' => number_format((float)($taxable_amount), 2, '.', '')
                ],
                'cbc:TaxAmount' => [
                    '@_currencyID' => 'SAR',
                    '#text' => number_format((float)($tax_amount), 2, '.', '')
                ],
                'cac:TaxCategory' => [
                    'cbc:ID' => [
                        '@_schemeAgencyID' => 6,
                        '@_schemeID' => 'UN/ECE 5305',
                        '#text' => $tax_percent ? 'S' : 'O'
                    ],
                    'cbc:Percent' => number_format((float)$tax_percent * 100.00, 2, '.', ''),
                    // BR-O-10
                    'cbc:TaxExemptionReason' => $tax_percent ? '' : 'Not subject to VAT',
                    'cac:TaxScheme' => [
                        'cbc:ID' => [
                            '@_schemeAgencyID' => 6,
                            '@_schemeID' => 'UN/ECE 5153',
                            '#text' => 'VAT'
                        ]
                    ],
                ]
            ];
        };

        $taxes_total = 0;
        array_map(function ($line_item) use (&$addTaxSubtotal, &$taxes_total) {
            $total_line_item_discount = array_reduce($line_item['discounts'], function ($p, $c) {
                return $p + $c['amount'];
            }, 0);
            $taxable_amount = ($line_item['tax_exclusive_price'] * $line_item['quantity']) - ($total_line_item_discount ?? 0);

            $tax_amount = ((float)$line_item['VAT_percent']) * ((float)$taxable_amount);
            $addTaxSubtotal($taxable_amount, $tax_amount, $line_item['VAT_percent']);
            $taxes_total += $tax_amount;
            array_map(function ($tax) use (&$taxable_amount, &$addTaxSubtotal, &$taxes_total) {
                $tax_amount = $tax['percent_amount'] * $taxable_amount;
                $addTaxSubtotal($taxable_amount, $tax_amount, $tax['percent_amount']);
                $taxes_total += $tax_amount;
            }, $line_item['other_taxes']);
        }, $line_items);

        // BT-110
        $taxes_total = number_format($taxes_total, 2, '.', '');

        // BR-DEC-13, MESSAGE : [BR-DEC-13]-The allowed maximum number of decimals for the Invoice total VAT amount (BT-110) is 2.
        return [
            [
                // Total tax amount for the full invoice
                'cbc:TaxAmount' => [
                    '@_currencyID' => 'SAR',
                    '#text' => $taxes_total
                ],
                'cac:TaxSubtotal' => $cacTaxSubtotal,
            ],
            [
                // KSA Rule for VAT tax
                'cbc:TaxAmount' => [
                    '@_currencyID' => 'SAR',
                    '#text' => $taxes_total
                ]
            ]
        ];
    }
}
