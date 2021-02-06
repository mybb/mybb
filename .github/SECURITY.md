# Security at MyBB

## Running MyBB Securely: Recommendations
In addition to following universal security advice for running web applications (like software updates, HTTPS enforcement, and the use of U2F/2FA), we recommend reviewing server and MyBB configuration and setting up additional protection mechanisms as described in the Security Guide:

[**MyBB Documentation &rsaquo; MyBB Security Guide &rarr;**](https://docs.mybb.com/1.8/administration/security/protection/)

## Mitigating & Recovering From Security Incidents
Responding to security incidents includes both general and MyBB-specific actions that administrators should take. Consult the following guide for information on system health review, active mitigation, and resetting passwords & tokens:

[**MyBB Documentation &rsaquo; Security Incident Response & Recovery &rarr;**](https://docs.mybb.com/1.8/administration/security/recovery/)

## Reporting Vulnerabilities

If you have discovered a potential vulnerability or security risk, we encourage you to responsibly disclose it to us via the [**Private Inquiries**](https://community.mybb.com/forum-135.html) forum. You can also reach us at security@mybb.com for security concerns, however we recommend using Private Inquiries for best feedback.

Open [**mybb.com/security**](https://mybb.com/get-involved/security/#composing-a-good-report) with complete instructions on how to report vulnerabilities to maximize the efficiency, help MyBB secure users as soon as possible and receive recognition. You will also learn what issues we look after, how to limit harm to other people and what the disclosure process will look like.

---

## Known Security Issues

### Executive Summary
MyBB 1.x is known to be affected by documented vulnerabilities related to possible XSS attacks from members with access to the Admin Control Panel (ACP) and possible anticipated vulnerabilities that may be caused by incorrect variable type handling, error-prone implementation on the MyCode parser, improper user input filtering, improper HTML filtering (where it is partially sanitized), insufficient session control mechanisms, weak cryptographic primitives (e.g. password hashing algorithms), insufficient user and group permission control, common usage of [`eval()`](https://secure.php.net/eval) statements and possible usage of outdated libraries and 3rd-party software.

Documented unaddressed, and anticipated vulnerabilities can lead to damage and/or loss of data and servers and can pose a threat to privacy, safety and/or security of forum administrators and their end users.

**We, therefore, do not recommend storing sensitive information using MyBB** until aforementioned issues are resolved.

### Technical Details of Known Issues
- ##### [ACP XSS issues (#3126)](https://github.com/mybb/mybb/issues/3126)
  MyBB series 1.x up to 1.8.x has XSS security issues affecting the Admin Control Panel (ACP).
