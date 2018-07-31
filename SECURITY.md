# Security at MyBB

## Running MyBB Securely: Recommendations

- maintain the server software up to date (through dedicated and experienced administrators or managed hosting),
- always keep MyBB and extensions up to date (running updates no later than 7 days after release is recommended) &mdash; [**subscribe to official channels**](https://mybb.com/download/verifying/#latest-version-information) to immediately get notified of new versions of MyBB,
- follow recommendations on secure filesystem setup, HTTPS support, Two-Factor Authentication (2FA) available at [here](https://docs.mybb.com/1.8/administration/security/), also including the [**main security guide**](https://docs.mybb.com/1.8/administration/security/protection/).

## Known Security Issues

### Executive Summary
MyBB 1.x is known to be affected by documented vulnerabilities related to possible XSS attacks from members with access to the Admin Control Panel (ACP) and possible anticipated vulnerabilities that may be caused by incorrect variable type handling, error-prone implementation on the MyCode parser, improper user input filtering, improper HTML filtering (where it is partially sanitized), insufficient session control mechanisms, weak cryptographic primitives (e.g. password hashing algorithms), insufficient user and group permission control, common usage of [`eval()`](https://secure.php.net/eval) statements and possible usage of outdated libraries and 3rd-party software.

Documented unaddressed, and anticipated vulnerabilities can lead to damage and/or loss of data and servers and can pose a threat to privacy, safety and/or security of forum administrators and their end users.

**We, therefore, do not recommend storing sensitive information using MyBB** until aforementioned issues are resolved.

### Technical Details of Known Issues
- ##### [ACP XSS issues (#3126)](https://github.com/mybb/mybb/issues/3126)
  MyBB series 1.x up to 1.8.x has XSS security issues affecting the Admin Control Panel (ACP).

## Reporting Vulnerabilities

We recognize reporters that follow responsible disclosure by including their names and affiliations in Release Notes and Release Blog Posts.

If you have discovered a potential vulnerability or security risk, we encourage you to responsibly disclose it to us via the [**Private Inquiries**](https://community.mybb.com/forum-135.html) forum.

Do not use the form if you notice your browser displays HTTPS warnings, or anything otherwise suspicious happens. You can optionally encrypt your message using GPG keys of at least 3 _Lead_ staffers, available at https://mybb.com/about/team/.

You can also reach us at security@mybb.com for security concerns, however we recommend using Private Inquiries for best feedback.

Open [**mybb.com/security**](https://mybb.com/get-involved/security/#composing-a-good-report) with complete instructions on how to report vulnerabilities to maximize the efficiency, help MyBB secure users as soon as possible and receive recognition. You will also learn what issues we look after, how to limit harm to other people and what the disclosure process will look like.
