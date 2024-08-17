<div align="center">
  <h1>
  ZATCA Phases
  <br/>
  </h1>
  <p>
    An implementation of Saudi Arabia ZATCA's E-Invoicing requirements, processes, and standards in PHP. <br/>
  </p>
  Read the <a href="/docs">documentation PDFs</a> or <a href="https://zatca.gov.sa/en/E-Invoicing/SystemsDevelopers/Pages/TechnicalRequirementsSpec.aspx">Systems Developers</a> for more details.
  <br/>
  <br/>
  <p>

[![GitHub license](https://badgen.net/github/license/wes4m/zatca-xml-js?v=0.1.0)](https://github.com/wes4m/zatca-xml-js/blob/main/LICENSE)
<a href="https://github.com/sulaman0">
<img src="https://img.shields.io/badge/maintainer-SulamanKhan-blue"/>
</a>
</div>

# Dependencies

If you plan on using the built in `EGS` module to generate keys, and CSR. The `EGS` module in the package is dependent
on <a href="https://www.openssl.org">OpenSSL</a> being installed in the system it's running on. It's being used to
generate an `ECDSA` key pair using the `secp256k1` curve. also to generate and sign a CSR.

All other parts of the package will work fine without `OpenSSL`. (meaning it supports react-native and other frameworks)

**Run this project on linux OS, openSSL conversation very from OS to OS basis.**

# Supports

All tha main futures required to on-board a new EGS. Create, sign, and report a simplified tax invoice are currently
supported.

- EGS (E-Invoice Generation System).
    - Creation/on-boarding (Compliance and Production x.509 CSIDs).
    - Cryptographic stamps generation.
- Simplified Tax Invoice.
    - Creation.
    - Signing.
    - Compliance checking.

# Installation

1. Download the package from github
2. Run `composer install` to install dependencies.
3. Run a local server to view the examples using `php -S localhost:8000`
4. Open http://localhost:8000/phase-1.php in your browser.
5. Open http://localhost:8000/ in your browser.
6. Download the ZATCA QR Reader App from Google Play
   Store. <a href="https://play.google.com/store/apps/details?id=com.posbankbh.einvoiceqrreader&pcampaignid=web_share">*
   *Zatca QR ReaderApp**</a>
7. Scan the QR code generated in the example, Once you scanned in base with QR code you will see base64 code, Hurry
   you've integrated Phase 2

# Usage

View full example at <a href="/phase-1.php">phase-1.php</a> and <a href="/">phase-2.php</a>.

```php
// New Invoice and EGS Unit
$egs = new \ZATCA\EGS($egsUnit);

$egs->production = false;

// Generate private key & csr
list($privateKey, $csr) = $egs->generateNewKeysAndCSR('QR');

// Make an request to issue  compliance certificate
list($requestId, $binarySecurityToken, $secret) = $egs->issueComplianceCertificate('123345', $csr);

// Sing invoice xml
list($signedInvoiceString, $invoiceHash, $qr) = $egs->signInvoice($invoice, $egsUnit, $binarySecurityToken, $privateKey);

// Check fatoora is created invoice is correct or not.
$response =$egs->checkInvoiceCompliance($signedInvoiceString, $invoiceHash, $binarySecurityToken, $secret);
//echo '<pre>'; //un-comment if you want to see compliance result
//print_r(json_decode($response)); //un-comment if you want to see compliance result
//dd("");    //un-comment if you want to see compliance result
```

# Results

If checkInvoiceCompliance looks like mention response, so, means your invoice is validated or good to submit to Zacta

```object 
stdClass Object
(
    [validationResults] => stdClass Object
        (
            [infoMessages] => Array
                (
                    [0] => stdClass Object
                        (
                            [type] => INFO
                            [code] => XSD_ZATCA_VALID
                            [category] => XSD validation
                            [message] => Complied with UBL 2.1 standards in line with ZATCA specifications
                            [status] => PASS
                        )

                )

            [warningMessages] => Array
                (
                )

            [errorMessages] => Array
                (
                )

            [status] => PASS
        )

    [reportingStatus] => REPORTED
    [clearanceStatus] => 
    [qrSellertStatus] => 
    [qrBuyertStatus] => 
)
```

# Notice of Non-Affiliation and Disclaimer

`zatca-qr` is influenced by <a href="https://github.com/mudassaralichouhan/zatca-xml-php">`zatca-xml-php`</a> that not
affiliated, associated, authorized, endorsed by, or in any way officially connected with ZATCA (
Zakat, Tax and Customs Authority), or any of its subsidiaries or its affiliates. The official ZATCA website can be found
at https://zatca.gov.sa.

# Contribution

All contributions are appreciated, For more information please
visit <a href="/User_Manual_Developer_Portal_Manual_Version_3.pdf">Complete Guidance</a>

