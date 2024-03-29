# Security at MyBB

## Running MyBB Securely: Recommendations
In addition to following universal security advice for running web applications (like software updates, HTTPS enforcement, and the use of U2F/2FA), we recommend reviewing server and MyBB configuration and setting up additional protection mechanisms as described in the Security Guide:

[**MyBB Documentation &rsaquo; MyBB Security Guide &rarr;**](https://docs.mybb.com/1.8/administration/security/protection/)

## Mitigating & Recovering From Security Incidents
Responding to security incidents includes both general and MyBB-specific actions that administrators should take. Consult the following guide for information on system health review, active mitigation, and resetting passwords & tokens:

[**MyBB Documentation &rsaquo; Security Incident Response & Recovery &rarr;**](https://docs.mybb.com/1.8/administration/security/recovery/)

## Reporting Vulnerabilities

If you have discovered a potential vulnerability or security risk, we encourage you to responsibly disclose it to us via the [**Private Inquiries**](https://community.mybb.com/forum-135.html) forum. You can also reach us at security@mybb.com for security concerns, however we recommend using Private Inquiries for better communication.

Open [**mybb.com/security**](https://mybb.com/get-involved/security/#composing-a-good-report) for complete instructions on how to report vulnerabilities to maximize efficiency, help MyBB secure users as soon as possible, and receive official recognition. You will also learn which areas we focus on, how to limit harm to other people, and how the disclosure process will look like.

---

## Known Security Issues

### Executive Summary
MyBB 1.x is known to be affected by documented vulnerabilities related to possible XSS attacks from members with access to the Admin Control Panel (ACP) and possible anticipated vulnerabilities that may be caused by incorrect variable type handling, error-prone implementation of the MyCode parser, improper user input filtering, improper HTML filtering (where it is partially sanitized), insufficient session control mechanisms, weak cryptographic primitive algorithms (e.g. during password hashing), insufficient user and group permission control, common usage of [`eval()`](https://secure.php.net/eval) statements and possible usage of outdated libraries and 3rd-party software.

Documented unaddressed, and anticipated vulnerabilities can lead to damage and/or loss of data and servers and can pose a threat to privacy, safety and/or security of forum administrators and their end users.

**We, therefore, do not recommend storing sensitive information using MyBB** until aforementioned issues are resolved.

### Technical Details of Known Issues
- ##### [ACP XSS issues (#3126)](https://github.com/mybb/mybb/issues/3126)
  MyBB series 1.x up to 1.8.x has XSS security issues affecting the Admin Control Panel (ACP).

- ##### `eval()`-based template system ([#3689](https://github.com/mybb/mybb/issues/3689))
  MyBB series 1.x up to 1.8.x use `eval()` statements to combine HTML Templates and variables with dynamic content. While Templates that may contain function calls are rejected on creation and modification in the Admin CP, no checks are otherwise performed, and write access to the forum's database — where the Templates are retrieved from — may enable an adversary to execute PHP code.

  Additionally, the access scope of variables accessible in Templates is not limited, which may lead to disclosure of sensitive information through maliciously crafted or improperly constructed Themes.
