<?php
// Turn on error reporting
error_reporting(E_ALL);

// Display errors on the page
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use ZATCA\EGS;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

const ROOT_PATH = __DIR__;
date_default_timezone_set('UTC');

function debug($var)
{
    if (is_object($var)) {
        print_r($var);
    } elseif (is_string($var)) {
        echo $var;
    } else {
        var_dump($var);
    }
    echo '<br />';
}

function dd($var)
{
    if (is_object($var)) {
        print_r($var);
    } elseif (is_string($var)) {
        echo $var;
    } else {
        var_dump($var);
    }
    echo '<br />';
    die("=== END DEBUGGING ===");

}

$line_item = [
    'id' => '1',
    'name' => 'Sulaman Khan',
    'quantity' => 1,
    'tax_exclusive_price' => 10,
    'VAT_percent' => 0.15,
    'other_taxes' => [
    ],
    'discounts' => [
        //         ['amount' => 2, 'reason' => 'A discount'],
    ],
];

$egs_unit = [
    'uuid' => '6f4d20e0-6bfe-4a80-9389-7dabe6620f12',
    'custom_id' => 'EGS1-886431145',
    'model' => 'IOS',
    'CRN_number' => '454634645645654',
    'VAT_name' => 'Qr',
    'VAT_number' => '301121971500003',
    'location' => [
        'city' => 'Lahore',
        'city_subdivision' => 'Est',
        'street' => 'King Fahahd st',
        'plot_identification' => '0000',
        'building' => '0000',
        'postal_zone' => '31952',
    ],
    'branch_name' => 'My Branch Name',
    'branch_industry' => 'Food',
    'cancelation' => [
        'cancelation_type' => 'INVOICE',
        'canceled_invoice_number' => '',
    ],
];
$invoice = [
    'invoice_counter_number' => 1,
    'invoice_serial_number' => 'EGS1-886431145-1',
    'issue_date' => '2022-08-17',
    'issue_time' => '17:41:08',
    'previous_invoice_hash' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==', // AdditionalDocumentReference/PIH
    'line_items' => [
        $line_item,
    ],
];

$egs = new EGS($egs_unit);

// Production set to false.
$egs->production = false;

// Generate private key & csr
list($private_key, $csr) = $egs->generateNewKeysAndCSR('Qr');

// Make an request to issue  compliance certificate
list($request_id, $binary_security_token, $secret) = $egs->issueComplianceCertificate('123345', $csr);

// Sing invoice xml
list($signed_invoice_string, $invoice_hash, $qr) = $egs->signInvoice($invoice, $egs_unit, $binary_security_token, $private_key);

// Check fatoora is created invoice is correct or not.
$invoiceCompliance = $egs->checkInvoiceCompliance($signed_invoice_string, $invoice_hash, $binary_security_token, $secret);
//echo '<pre>';
print_r(json_decode($invoiceCompliance));
dd("");


// Generate QR Code
$qrCode = QrCode::create($qr)
    ->setEncoding(new Encoding('UTF-8'))
    ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
    ->setSize(300)
    ->setMargin(10)
    ->setForegroundColor(new Color(0, 0, 0))
    ->setBackgroundColor(new Color(255, 255, 255));

// Save QR Code to file
$writer = new PngWriter();

$label = Label::create('Sulaman Khan')
    ->setTextColor(new Color(255, 0, 0));

$result = $writer->write($qrCode);
$result->saveToFile(__DIR__ . '/assets/phase-2.png');

header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
