# Contributing to MyBB

To **report a confirmed bug or problem**, create a thread the Community Forums for the appropriate version of MyBB within the [Development category](http://community.mybb.com/forum-161.html), if the problem was not reported already on the Forums or as [Issues](https://github.com/mybb/mybb/issues) on GitHub. If it is, but receives little attention, you can share details on how it impacts your forum to help diagnose the problem.

To **suggest changes or features**, go to the [**Suggestions and Feedback**](https://community.mybb.com/forum-199.html) forum. Please make sure that your feature hasn't already been suggested and is not already planned.

Help prioritize [Issues](https://github.com/mybb/mybb/issues) by adding a "👍" reaction to new features you'd like to see in MyBB or for problems affecting you.

Learn more about contributing to MyBB by providing support to other users, improving the documentation, verifying security controls, or developing custom extensions:

[**MyBB &mdash; Get Involved &rarr;**](https://mybb.com/get-involved/)

### Development
If you have technical or coding experience, you can help by:
 - participating in development discussions,
 - resolving [Issues](https://github.com/mybb/mybb/issues) by submitting code changes,
 - reviewing [Pull Requests](https://github.com/mybb/mybb/pulls),
 - verifying submitted bugs and providing additional details.

Learn more on the MyBB development page:

[**MyBB &mdash; Get Involved: Features & Development &rarr;**](https://mybb.com/get-involved/development/)

If you need any help with sending your code contributions, the [GitHub Help site](https://help.github.com) is a good place to start.

## MyBB on GitHub

### Branches

#### Maintenance/Legacy Branches
- `master` Currently contains legacy code of MyBB 1.6.

- `feature` Currently contains code for MyBB 1.8.

#### Workflow for MyBB 1.9 and up
- `master`

  After the MyBB 1.9 release, `master` will contain code of latest stable release for latest stable branch, or `master/*` for given older branch (e.g. _master/1.9_ for 1.9).

  -  `hotfix/*` (e.g. _hotfix/1.10_) Quick releases for critical issues. When ready, the hotfix branch is merged back to _master/*_ and _develop/*_ (or _release/*_, if one exists).


- `develop/*` Contains current development version with changes that were applied after the last release.

    - `release/*` (e.g. _release/3.2.1_) Release preparation - version numbers and other metadata.

      Immediately preceding the actual release, security patches are merged in and the release branch is merged back into _develop/*_ and _master/*_.

    - `feature/*` (e.g. *feature/custom-avatars*) New features being worked on. When completed and tested, the feature branch is merged back to _develop/*_.

### Issues
Bugs and improvements to MyBB software packages are documented in corresponding _Issues_.

Issue titles should work as short summaries identifying the problem or intent:

Type | Title Style | Title Examples
-|-|-
Bugs and problems | Problem declaration (declarative mood) | - _Moderator notes fail to get saved_ <br>- _Moved thread link name not changing properly_
Improvements and tasks | Instruction (imperative mood) | - _Move Who's Online building into a function_ <br>- _Omit unwanted links / buttons_
Features | Feature name | - _Goodbye Spammer Integration_ <br>- _Global 2FA_

#### Labels
Triaged Issues usually have multiple [**Labels**](https://github.com/mybb/mybb/labels) assigned:
- Branch (`b:*`), the x.**Y** release branches they are related to,
- Priority (`p:*`), how important they are (1 per Issue),
- Status (`s:*`), what stage the development is currently in (1 per Issue),
- Type (`t:*`), what kind of problem or operation they are related to,
- Involving (`i:*`), additional labels for specific modules, libraries, or types,
- `3rdparty`, assigned to external code and other third-party solutions,
- [`easy-pick`](https://github.com/mybb/mybb/labels/easy-pick), used for simple issues for new contributors that don't require extensive MyBB knowledge to resolve.

Labels are generally only used for Issues.

#### Milestones
Issues can have specific [**Milestones**](https://github.com/mybb/mybb/milestones) assigned, meaning that they are expected to be addressed in given versions.

#### Assignments
Issues with personal assignments are expected to be handled by assigned developers. Similarly, the review process of Pull Requests can be directed by developers assigned to them.

### Commits
Follow [The seven rules of a great Git commit message](https://chris.beams.io/posts/git-commit/#seven-rules).

### Pull Requests
Pull Requests (PRs) should only be sent for [confirmed Issues](https://github.com/mybb/mybb/issues?q=is%3Aissue+is%3Aopen+label%3As%3Aconfirmed), and only one issue should be fixed per Pull Request.

All changes made in Pull Requests must follow the development standards:
- for MyBB 1.8, see [MyBB Documentation &rsaquo; Development Standards](https://docs.mybb.com/1.8/development/standards/),
- for MyBB 1.9 and later, follow the [PSR-12 standard](https://www.php-fig.org/psr/psr-12/).

Use imperative mood in the title, summarizing changes the Pull Request introduces, and reference the Issue ID in the message.

Include a [closing keyword](https://help.github.com/articles/closing-issues-using-keywords/) for each resolved Issue at the beginning of the Pull Request's description, e.g.
```
Resolves #123
Resolves #345

Replaces Anvil::push() with Anvil::fall().
```
