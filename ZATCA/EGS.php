<?php

namespace ZATCA;

use DOMDocument;
use Exception;

class EGS
{
    private $egs_info;
    private API $api;
    public bool $production = false;

    public function __construct(array $egs_info)
    {
        $this->egs_info = $egs_info;
        $this->api = new API();
    }

    public function generateNewKeysAndCSR(string $solution_name)
    {
        $private_key = $this->generateSecp256k1KeyPair();

        return [$private_key, $this->generateCSR($solution_name, $private_key)];
    }

    private function generateSecp256k1KeyPair()
    {
        $result = shell_exec('openssl ecparam -name secp256k1 -genkey');

        $result = explode('-----BEGIN EC PRIVATE KEY-----', $result);

        if (!isset($result[1])) throw new Exception('Error no private key found in OpenSSL output.');

        $result = trim($result[1]);

        $private_key = "-----BEGIN EC PRIVATE KEY-----\n$result";
        return trim($private_key);
    }

    private function generateCSR(string $solution_name, $private_key)
    {
        if (!$private_key) throw new Exception('EGS has no private key');

        if (!is_dir(ROOT_PATH . '/tmp/')) {
            mkdir(ROOT_PATH . '/tmp/', 0775);
        }

        $private_key_file_name = ROOT_PATH . '/tmp/' . self::uuid() . '.pem';
        $csr_config_file_name = ROOT_PATH . '/tmp/' . self::uuid() . '.cnf';

        $private_key_file = fopen($private_key_file_name, 'w');
        $csr_config_file = fopen($csr_config_file_name, 'w');

        require ROOT_PATH . '/ZATCA/templates/csr_template.php';
        fwrite($private_key_file, $private_key);
        fwrite($csr_config_file, $this->defaultCSRConfig($solution_name));

        $result = shell_exec("openssl req -new -sha256 -key $private_key_file_name -config $csr_config_file_name");
        $result = explode('-----BEGIN CERTIFICATE REQUEST-----', $result);
        $result = $result[1];

        $csr = "-----BEGIN CERTIFICATE REQUEST-----$result";

        unlink($private_key_file_name);
        unlink($csr_config_file_name);

        return $csr;
    }

    private function defaultCSRConfig(string $solution_name)
    {
        $config = [
            'egs_model' => $this->egs_info['model'],
            'egs_serial_number' => $this->egs_info['uuid'],
            'solution_name' => $solution_name,
            'vat_number' => $this->egs_info['VAT_number'],
            'branch_location' => $this->egs_info['location']['building'] . ' ' . $this->egs_info['location']['street'] . ', ' . $this->egs_info['location']['city'],
            'branch_industry' => $this->egs_info['branch_industry'],
            'branch_name' => $this->egs_info['branch_name'],
            'taxpayer_name' => $this->egs_info['VAT_name'],
            'taxpayer_provided_id' => $this->egs_info['custom_id'],
            'production' => $this->production
        ];

        $template_csr = require ROOT_PATH . '/ZATCA/templates/csr_template.php';

        $template_csr = str_replace('SET_PRIVATE_KEY_PASS', ($config['private_key_pass'] ?? 'SET_PRIVATE_KEY_PASS'), $template_csr);
        $template_csr = str_replace('SET_PRODUCTION_VALUE', ($config['production'] ? 'ZATCA-Code-Signing' : 'TSTZATCA-Code-Signing'), $template_csr);
        $template_csr = str_replace('SET_EGS_SERIAL_NUMBER', "1-{$config['solution_name']}|2-{$config['egs_model']}|3-{$config['egs_serial_number']}", $template_csr);
        $template_csr = str_replace('SET_VAT_REGISTRATION_NUMBER', $config['vat_number'], $template_csr);
        $template_csr = str_replace('SET_BRANCH_LOCATION', $config['branch_location'], $template_csr);
        $template_csr = str_replace('SET_BRANCH_INDUSTRY', $config['branch_industry'], $template_csr);
        $template_csr = str_replace('SET_COMMON_NAME', $config['taxpayer_provided_id'], $template_csr);
        $template_csr = str_replace('SET_BRANCH_NAME', $config['branch_name'], $template_csr);
        $template_csr = str_replace('SET_TAXPAYER_NAME', $config['taxpayer_name'], $template_csr);

        return $template_csr;
    }

    public static function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function issueComplianceCertificate(string $otp, $csr): array
    {
        if (!$csr) throw new Exception('EGS needs to generate a CSR first.');

        list($issueCertificate, $checkInvoiceCompliance) = $this->api->compliance();
        $issued_data = $issueCertificate($csr, $otp);

        return [$issued_data->requestID, $issued_data->binarySecurityToken, $issued_data->secret];
    }

    public function signInvoice(array $invoice, array $egs_unit, string $certificate, string $private_key): array
    {

        require ROOT_PATH . '/ZATCA/ZATCASimplifiedTaxInvoice.php';
        $zatca_simplified_tax_invoice = new ZATCASimplifiedTaxInvoice();

        $invoice_xml = $zatca_simplified_tax_invoice->simplifiedTaxInvoice($invoice, $egs_unit);

        $invoice_hash = $zatca_simplified_tax_invoice->getInvoiceHash($invoice_xml);

        list($hash, $issuer, $serialNumber, $public_key, $signature)
            = $zatca_simplified_tax_invoice->getCertificateInfo($certificate);

        $digital_signature = $zatca_simplified_tax_invoice->createInvoiceDigitalSignature($invoice_hash, $private_key);

        $qr = $zatca_simplified_tax_invoice->generateQR(
            $invoice_xml,
            $digital_signature,
            $public_key,
            $signature,
            $invoice_hash
        );

        $issue_date = $invoice['issue_date'];
        $issue_time = $invoice['issue_time'];
        $signed_properties_props = [
//             'sign_timestamp' => date('Y-m-d\TH:i:s\Z'),
            'sign_timestamp' => date('Y-m-d\TH:i:s', strtotime("{$issue_date} {$issue_time}")),
//             'sign_timestamp' => sprintf('%sT%s',$invoice['issue_date'], $invoice['issue_time']),
//             'sign_timestamp' => '2024-01-14T10:26:49',
            'certificate_hash' => $hash, // SignedSignatureProperties/SigningCertificate/CertDigest/<ds:DigestValue>SET_CERTIFICATE_HASH</ds:DigestValue>
            'certificate_issuer' => $issuer,
            'certificate_serial_number' => $serialNumber
        ];

        $ubl_signature_signed_properties_xml_string_for_signing = $zatca_simplified_tax_invoice->defaultUBLExtensionsSignedPropertiesForSigning($signed_properties_props);

        $ubl_signature_signed_properties_xml_string = $zatca_simplified_tax_invoice->defaultUBLExtensionsSignedProperties($signed_properties_props);

        $signed_properties_hash = base64_encode(openssl_digest($ubl_signature_signed_properties_xml_string_for_signing, 'sha256'));

        // UBL Extensions
        $ubl_signature_xml_string = $zatca_simplified_tax_invoice->defaultUBLExtensions(
            $invoice_hash, // <ds:DigestValue>SET_INVOICE_HASH</ds:DigestValue>
            $signed_properties_hash, // SignatureInformation/Signature/SignedInfo/Reference/<ds:DigestValue>SET_SIGNED_PROPERTIES_HASH</ds:DigestValue>
            $digital_signature,
            $certificate,
            $ubl_signature_signed_properties_xml_string
        );

        // Set signing elements
        $unsigned_invoice_str = $invoice_xml->saveXML();

        $unsigned_invoice_str = str_replace('SET_UBL_EXTENSIONS_STRING', $ubl_signature_xml_string, $unsigned_invoice_str);
        $unsigned_invoice_str = str_replace('SET_QR_CODE_DATA', $qr, $unsigned_invoice_str);

        $signed_invoice = new DOMDocument();
        $signed_invoice->loadXML($unsigned_invoice_str);

        $signed_invoice_string = $signed_invoice->saveXML();
        //$signed_invoice_string = $zatca_simplified_tax_invoice->signedPropertiesIndentationFix($signed_invoice_string);

        return [$signed_invoice_string, $invoice_hash, $qr];
    }

    public function checkInvoiceCompliance(string $signed_invoice_string, string $invoice_hash, string $certificate, string $secret): string
    {
        if (!$certificate || !$secret)
            throw new Exception('EGS is missing a certificate/private key/api secret to check the invoice compliance.');

        list($issueCertificate, $checkInvoiceCompliance) = $this->api->compliance($certificate, $secret);
        $issued_data = $checkInvoiceCompliance($signed_invoice_string, $invoice_hash, $this->egs_info['uuid']);

        return json_encode($issued_data);
    }
}