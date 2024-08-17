<?php

// 2.2.2 Profile specification of the Cryptographic Stamp identifiers. & CSR field contents / RDNs
return <<<TEXT
# ------------------------------------------------------------------
# Default section for "req" command options
# ------------------------------------------------------------------
[req]

# Password for reading in existing private key file
# input_password = SET_PRIVATE_KEY_PASS

# Prompt for DN field values and CSR attributes in ASCII
prompt = no
utf8 = no

# Section pointer for DN field options
distinguished_name = my_req_dn_prompt

# Extensions
req_extensions = v3_req

[ v3_req ]
#basicConstraints=CA:FALSE
#keyUsage = digitalSignature, keyEncipherment
# Production or Testing Template (TSTZATCA-Code-Signing - ZATCA-Code-Signing)
1.3.6.1.4.1.311.20.2 = ASN1:UTF8String:SET_PRODUCTION_VALUE
subjectAltName=dirName:dir_sect

[ dir_sect ]
# EGS Serial number (1-SolutionName|2-ModelOrVersion|3-serialNumber)
SN = SET_EGS_SERIAL_NUMBER
# VAT Registration number of TaxPayer (Organization identifier [15 digits begins with 3 and ends with 3])
UID = SET_VAT_REGISTRATION_NUMBER
# Invoice type (TSCZ)(1 = supported, 0 not supported) (Tax, Simplified, future use, future use)
title = 0100
# Location (branch address or website)
registeredAddress = SET_BRANCH_LOCATION
# Industry (industry sector name)
businessCategory = SET_BRANCH_INDUSTRY

# ------------------------------------------------------------------
# Section for prompting DN field values to create "subject"
# ------------------------------------------------------------------
[my_req_dn_prompt]
# Common name (EGS TaxPayer PROVIDED ID [FREE TEXT])
commonName = SET_COMMON_NAME

# Organization Unit (Branch name)
organizationalUnitName = SET_BRANCH_NAME

# Organization name (Tax payer name)
organizationName = SET_TAXPAYER_NAME

# ISO2 country code is required with US as default
countryName = SA
TEXT;
