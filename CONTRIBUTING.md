# Contributing to MyBB

## Reporting Issues

You should check [**1.8 Bugs and Issues**](https://community.mybb.com/forum-157.html) forum and the issue tracker on GitHub to make sure the issue hasn't already been reported. These issues posted in the forum will be pushed to GitHub when a member of the MyBB Team is able to reproduce (confirm) that the issue exists.

## Suggesting Features

Post feature suggestions for MyBB in the [**Suggestions and Feedback**](https://community.mybb.com/forum-199.html) forum. Please also make sure that your feature hasn't already been suggested.

## Development

### Branches _(1.9.x and up)_

- `master`

  Contains latest stable release for latest stable branch, or `master/*` for given older branch (e.g. _master/1.9_ for 1.9).

  -  `hotfix/*` (e.g. _hotfix/1.10_) Quick releases for critical issues. When ready, the hotfix branch is merged back to _master/*_ and _develop/*_ (or _release/*_, if one exists).


- `develop/*` Contains current development version with changes that were applied after the last release.

    - `release/*` (e.g. _release/3.2.1_) Release preparation - version numbers and other metadata.

      Immediately preceding the actual release, security patches are merged in and the release branch is merged back into _develop/*_ and _master/*_.

    - `feature/*` (e.g. *feature/custom-avatars*) New features being worked on. When completed and tested, the feature branch is merged back to _develop/*_.

#### Legacy branches
The `feature` branch currently contains code for MyBB 1.8.

### Issues

#### Labels

Triaged issues usually have multiple [**Labels**](https://github.com/mybb/mybb/labels) assigned:
- Branch (`b:*`), the x.**Y** release branches they are related to,
- Priority (`p:*`), how important they are (1 per Issue),
- Status (`s*`), what stage the development is currently in (1 per Issue),
- Type (`t:*`), what kind of problem or operation they are related to (1 per Issue).

The `3rdparty` label is assigned to third-party solutions and `easy-pick` can be used to find easy problems for new contributors.

Additional `i:` (Involving) labels for modules, libraries or types can be assigned.

Labels are usually used for Issues only.

#### Milestones

Issues can have specific [**Milestones**](https://github.com/mybb/mybb/milestones) assigned, meaning that they are expected to be addressed in given versions.

#### Assignments

Issues with personal assignments are expected to be handled by assigned developers. Similarly, the review process of Pull Requests can be directed by developers assigned to them.

### Pull Requests

Before doing any coding and certainly before sending a Pull Request (PR) you must make sure that another developer hasn't already assigned the issue to themselves or sent a fix. There is no point in two people spending time on the same issue.

Pull Requests should only be sent for [confirmed Issues](https://github.com/mybb/mybb/issues?q=is%3Aissue+is%3Aopen+label%3As%3Aconfirmed). Only one issue should be fixed per Pull Request. This allows us to merge fixes successfully - if you send a fix for two issues and one doesn't work we are unable to merge the Pull Request, leaving you with more work.

If your Pull Request is for a new feature, that feature must be already confirmed to be added in the respective suggestions forum. You may only include one feature per Pull Request for the same reasons stated above.

All changes made in Pull Requests must follow the [Development Standards](https://docs.mybb.com/1.8/development/standards/).
If you need any help with sending your code contributions the [GitHub Help site](https://help.github.com) is a good place to start.

For MyBB 1.8, the base branch for Pull Requests is `feature`. For more recent versions, choose the appropriate `develop/*` base branch.

1. **Fork the repository** under your account.

   Make sure the base branch is up to date.

2. **Create a `feature/*`** branch (or `fix-*` for MyBB 1.8) (where * is the Issue number or short title, e.g. _feature/123_ or _feature/custom-avatars_).

3. **Commit changes** into to your branch.

   Use imperative mood in titles (e.g. _Update Acme library_, _Fix Anvil implementation_),

4. **Create a Pull Request** to the main repository's base branch from your head branch.

   Use imperative mood in the title and reference the Issue ID in the content (e.g. _#123_). If possible, use the `Fix #ID Issue title` format (e.g. _Fix #123 Wrong error message_).
