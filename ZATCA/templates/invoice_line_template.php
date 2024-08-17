<?php

$invoice_line = <<<XML

    <cac:InvoiceLine>
        <cbc:ID>__ID</cbc:ID>
        <cbc:InvoicedQuantity unitCode="PCE">__InvoicedQuantity</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="SAR">__LineExtensionAmount</cbc:LineExtensionAmount>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="SAR">__TaxAmount</cbc:TaxAmount>
            <cbc:RoundingAmount currencyID="SAR">__RoundingAmount</cbc:RoundingAmount>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Name>__Name</cbc:Name>ClassifiedTaxCategory
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="SAR">10</cbc:PriceAmount>AllowanceCharge
        </cac:Price>
    </cac:InvoiceLine>
XML;

$invoice_item = <<<XML

            <cac:ClassifiedTaxCategory>
                <cbc:ID>___S</cbc:ID>
                <cbc:Percent>___Percent</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
XML;

$invoice_price = <<<XML

            <cac:AllowanceCharge>
                <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
                <cbc:AllowanceChargeReason>___AllowanceChargeReason</cbc:AllowanceChargeReason>
                <cbc:Amount currencyID="SAR">___Amount</cbc:Amount>
            </cac:AllowanceCharge>
XML;


return [
    'invoice_line' => $invoice_line,
    'invoice_item' => $invoice_item,
    'invoice_price' => $invoice_price,
];
