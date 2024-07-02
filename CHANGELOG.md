# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Changing post's language via bulk edit (#66).

### Changed

- Use transients in LangInterface to optimize fetching language and translations (#55, #60).

### Fixed

- Broken translation linking on a new post in Gutenberg (#56).
- Language metabox being enqueued for non-translatable post types (#57).
- SQL bug in LangInterface::get_terms_for_source (#68).

## [0.3.1] - 2024-02-22

## [0.3.0] - 2024-01-30

## [0.2.3] - 2024-01-16

## [0.2.2] - 2024-01-16

## [0.2.1] - 2024-01-11

## [0.2.0] - 2024-01-09

## [0.1.1] - 2023-10-26

## [0.0.13] - 2023-09-14

## [0.1.0] - 2023-08-29

## [0.0.12] - 2023-03-27

## [0.0.11] - 2023-03-11

## [0.0.10] - 2023-03-08

## [0.0.9] - 2023-03-06



## [0.0.8] - 2023-03-03

### Fixed

- Bugs with the translations of meta fields.
- Accessing site_id property of WP_Site objects.
- Network activated Unbabble not working.

## [0.0.7] - 2023-02-22

### Changed

- Remove vcs repository for `diqa/formatter` and use our fork `26b/diqa-formatter` directly.
- Update `26b/diqa-formatter` version.

## [0.0.6] - 2023-02-17

### Added

- Dependency to `wp-cli/wp-cli` to WordPress CLI commands.
- Dependency to `diqa/formatter` for better visual output in CLI commands.
- Abstract `Command` class for CLI commands.
- `Post` CLI command class for post related commands:
  - `wp ubb post info <post_id>` for information on a post's language, source ID and translations.
  - `wp ubb post set <post_id> <language>` for setting a post's language.
  - `wp ubb post link <post_id> <target_id> [--force]` for linking a post to another as a translation.
  - `wp ubb post unlink <post_id>` for unlinking a post from its translations.
- `Term` CLI command class for term related commands:
  - `wp ubb term info <term_id>` for information on a term's language, source ID and translations.
  - `wp ubb term set <term_id> <language>` for setting a term's language.
  - `wp ubb term link <term_id> <target_id> [--force]` for linking a term to another as a translation.
  - `wp ubb term unlink <term_id>` for unlinking a term from its translations.
- `Options` CLI command class for options/settings related commands:
  - `wp ubb options get` for infomation on options/settings of Unbabble.
- Check if term slug already exists when creating a term when language filter is being applied to terms.

### Changed

- Updated frontend dependencies.

## [0.0.5] - 2023-02-16

### Changed

- Updated dependencies.
- Used Unbabble's shorthand `ubb` for commands instead of `unbabble`.
- Simplified SQL to get a post's language.
- Register components in the `init` WordPress action.
- Delayed language matching for language directory until `wp_loaded`.

### Fixed

- Minimum PHP version in composer changed to 8.0 due to UUID lib dependency.

## [0.0.4] - 2023-01-09

### Added

- Filter `ubb_new_source_id` to bypass UUID generation for translation source IDs.

### Changed

- Switch use of `PHP_SELF` to `REQUEST_URI` completely.
- Simplify condition for setting language in `$_GET` when current route matches language directory.

## [0.0.3] - 2023-01-06

### Added

- Multilingual homepage.
- Multilingual menus.
- WPML to Unbabble migrator and a CLI command `wp unbabble migrate-wpml` for it.
- `LangInterface::translate_current_url()` method to translate current url.
- `LangInterface::is_taxonomy_translatable()` method to check if a taxonomy is translatable.
- Routing type resolver to handle either query_var or directory routing depending on the settings.
- `Options` method to fetch language information via the WordPress language packs.
- Removing language query var from queries in otherwise empty queries. Needed for homepages in non-default languages.

### Changed

- Change the main plugin file `wp-plugin.php` to `unbabble.php`.

### Fixed

- Handle urls correctly when switching blogs.

## [0.0.2] - 2022-12-19

### Removed

- Unnecessary dependencies.

## [0.0.1] - 2023-12-19

First Release!

[unreleased]: https://github.com/26b/unbabble/compare/0.3.1...HEAD
[0.3.1]: https://github.com/26b/unbabble/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/26b/unbabble/compare/0.2.3...0.3.0
[0.2.3]: https://github.com/26b/unbabble/compare/0.2.2...0.2.3
[0.2.2]: https://github.com/26b/unbabble/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/26b/unbabble/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/26b/unbabble/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/26b/unbabble/compare/0.1.0...0.1.1
[0.0.13]: https://github.com/26b/unbabble/compare/0.0.12...0.0.13
[0.1.0]: https://github.com/26b/unbabble/compare/0.0.12...0.1.0
[0.0.12]: https://github.com/26b/unbabble/compare/0.0.11...0.0.12
[0.0.11]: https://github.com/26b/unbabble/compare/0.0.10...0.0.11
[0.0.10]: https://github.com/26b/unbabble/compare/0.0.9...0.0.10
[0.0.9]: https://github.com/26b/unbabble/compare/0.0.8...0.0.9
[0.0.8]: https://github.com/26b/unbabble/compare/0.0.7...0.0.8
[0.0.7]: https://github.com/26b/unbabble/compare/0.0.6...0.0.7
[0.0.6]: https://github.com/26b/unbabble/compare/0.0.5...0.0.6
[0.0.5]: https://github.com/26b/unbabble/compare/0.0.4...0.0.5
[0.0.4]: https://github.com/26b/unbabble/compare/0.0.3...0.0.4
[0.0.3]: https://github.com/26b/unbabble/compare/0.0.2...0.0.3
[0.0.2]: https://github.com/26b/unbabble/compare/0.0.1...0.0.2
[0.0.1]: https://github.com/26b/unbabble/releases/tag/0.0.1
