# Contributing to block_oksigeniatembiapo

Thanks for taking the time. Open an issue before a large PR so we can agree on the approach.

- PHP follows the Moodle Coding Style: <https://moodledev.io/general/development/policies/codingstyle>.
- License header: GPL v3 or later, copyright `Oksigenia SL <dev@oksigenia.cc>`.
- Code, comments and commit messages in **English**. Lang files carry the localised strings.
- No commit message carries the signature of an AI tool. Turn on the hook once in your clone:
  `git config core.hooksPath .githooks`; CI checks the new commits too.
- The code must run on every supported Moodle branch (4.5 to 6.0) and on PHP 8.1.
- A change to the contract between plugins (the fields of a door) is agreed first: every plugin that takes part
  depends on it.
