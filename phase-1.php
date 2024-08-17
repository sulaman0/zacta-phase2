<?php
require 'vendor/autoload.php';

use ZATCA\Tags\Seller;
use ZATCA\GenerateQrCode;
use ZATCA\Tags\TaxNumber;
use Endroid\QrCode\QrCode;
use ZATCA\Tags\InvoiceDate;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use ZATCA\Tags\InvoiceTaxAmount;
use ZATCA\Tags\InvoiceTotalAmount;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

// Data for QR Code
// $dataString = "Seller Name,VAT Number,2024-01-29 10:00:00,Invoice Total,VAT Total";
$generatedString = GenerateQrCode::fromArray([
    new Seller('Qr'), // seller name        
    new TaxNumber('323457892389823'), // seller tax number
    new InvoiceDate('2021-07-12T14:25:09Z'), // invoice date as Zulu ISO8601 @see https://en.wikipedia.org/wiki/ISO_8601
    new InvoiceTotalAmount('100.00'), // invoice total amount
    new InvoiceTaxAmount('15.00') // invoice tax amount
])->toBase64();

// Generate QR Code
$qrCode = QrCode::create($generatedString)
    ->setEncoding(new Encoding('UTF-8'))
    ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
    ->setSize(300)
    ->setMargin(10)
    ->setForegroundColor(new Color(0, 0, 0))
    ->setBackgroundColor(new Color(255, 255, 255));

// Save QR Code to file
$writer = new PngWriter();
$logo = Logo::create(__DIR__ . '/assets/logo.png')
    ->setResizeToWidth(50)
    ->setPunchoutBackground(true);

$label = Label::create('Qr Phase-1')
    ->setTextColor(new Color(255, 0, 0));

$result = $writer->write($qrCode, $logo, $label);
$result->saveToFile(__DIR__ . '/assets/phase-1.png');

header('Content-Type: ' . $result->getMimeType());

echo $result->getString();
?>

