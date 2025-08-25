TESTING DKIM, S/MIME and XOAUTH2
================================

This document explains how to test DKIM, S/MIME signing and XOAUTH2 authentication for Advanced Mailer.

Prerequisites
-------------
- PHP with `openssl` extension.
- `openssl` installed to generate keys/certificates if needed.
- Access to an SMTP server for full end-to-end tests (or a test relay).

DKIM
----
1. Generate RSA keypair for DKIM:

```bash
openssl genrsa -out dkim.private.pem 2048
openssl rsa -in dkim.private.pem -pubout -out dkim.public.pem
```

2. Publish the public key in DNS as TXT record at: `<selector>._domainkey.<domain>` with value `v=DKIM1; k=rsa; p=<base64(pubkey)>` (remove PEM headers).

3. In your SMTP config (used by Advanced Mailer transport) set:

```
dkim_private_key => file_get_contents('/path/to/dkim.private.pem')
dkim_selector => 'default'
dkim_domain => 'example.com'
dkim_identity => 'sender@example.com'  # optional
```

4. Use the example script `examples/test_dkim_smime_xoauth2.php` to compute DKIM-Signature for a sample message (no mail is sent).


S/MIME
------
1. Prepare certificate and private key (PEM):

```bash
openssl req -new -x509 -days 365 -keyout smime.key -out smime.crt
```

2. Configure transport:

```
smime_cert => '/path/to/smime.crt',
smime_key => '/path/to/smime.key',
smime_key_pass => '' # optional
```

3. Example script will attempt to sign a sample message using `openssl_pkcs7_sign` if configured.


XOAUTH2
-------
1. Obtain an XOAUTH2 access token from your provider (OAuth2 flow out of scope for this doc).
2. Set in transport config:

```
smtp_auth_method => 'xoauth2',
smtp_oauth_token => '<access_token>'
```

3. The example script will show the base64-encoded XOAUTH2 auth string that can be sent to the server.


Example script
--------------
- `examples/test_dkim_smime_xoauth2.php` — inspect and run. It does NOT send email; it demonstrates DKIM header computation, S/MIME signing (if available) and XOAUTH2 auth string.

Notes and limitations
---------------------
- DKIM implementation is basic and uses relaxed header canonicalization with simple body canonicalization. For production consider more complete canonicalization and canonicalization options.
- S/MIME test requires `openssl_pkcs7_sign` and writable temporary directory.
- XOAUTH2 requires that the SMTP server supports XOAUTH2.


