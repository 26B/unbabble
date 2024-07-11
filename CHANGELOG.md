# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Search filter for link translations.

### Changed

- Improved loading in link translations modal.

## [0.4.2] - 2024-07-10

### Added

- Filter for terms without language or unknown languages in the back-office term list for translatable taxonomies.
- Missing doc blocks from version 0.4.0.
- Admin notices for when a term missing or with an unknown language.
- Admin notice explaining the term filter for missing/unknown languages.
- I18n icon to the language switcher HTML.

### Changed

- Hide create and link translations when language is unknown in language metaboxes for terms and posts.
- Stop the archive admin notice for the existence of terms without language or with an unknown language showign when the filter is being applied.
- Removed missing/unknown language filter check in `Posts\LangFilter`. Now done directly via the `ubb_use_post_lang_filter` filter.

### Fixed

- Fixed bad HTML for language metabox in the Menu customization page.
- Setting term language when the term is missing language or has an unknown language in the term edit page.
- Post edit page breaking when language is unknown.
- Showing when post language is unknown in the language metabox in the post edit page.
- Post's missing/unknown language filter not considering posts with unknown language.

## [0.4.1] - 2024-07-05

### Added

- Fetching Unbbable's options value from other blogs (#43).

### Fixed

- Validating user session directly to show hidden languages (#76).

## [0.4.0] - 2024-07-03

### Added

- Changing post's language via bulk edit (#66).
- Filter for posts without language or unknown languages in the back-office post list for translatable post types.
- Filter `ubb_translatable_routes` for routes outside of post/term/archives that the user wants to be available in other languages when using the method `LangInterface::translate_current_url()`.

### Changed

- Use transients in LangInterface to optimize fetching language and translations (#55, #60).
- Improved language interface in post edit for setting language for posts without language or with an unknown language.

### Fixed

- Broken translation linking on a new post in Gutenberg (#56).
- Language metabox being enqueued for non-translatable post types (#57).
- SQL bug in LangInterface::get_terms_for_source (#68).

## [0.3.1] - 2024-02-22

### Fixed

- Missing check for existence of function `wp_get_available_translations`.

## [0.3.0] - 2024-01-30

### Changed

- **Breaking:** Return for `get_posts_for_source` and `get_terms_for_source` changed. Optimized fetching of languages for posts and terms.

### Fixed

- Bug when deleting attachments with no metadata.

## [0.2.3] - 2024-01-16

### Changed

- Changed `Language/Locale`'s `register` method for an `init` method.

### Fixed

- Issue with language switcher.

### Removed

- `init` method call in `Router/RoutingResolver`'s `register` method.

## [0.2.2] - 2024-01-16

### Changed

- Moved `Language/Frontend` to `Language\Locale`.

### Fixed

- Check function `wp_get_current_user` exists before using it in `LangInterface`.
- Parse routing and set locale as early as possible to fix localization issues.

## [0.2.1] - 2024-01-11

### Added

- Pass CLI arguments to `WP_CLI::confirm` in the WPML migrator.

### Removed

- Database verification for UUID generation to improve performance.

## [0.2.0] - 2024-01-09

### Added

- Improved options page with javascript and via API.
- `ubb_proxy_options` filter for general proxy options.
- `Admin\OptionsProxy` class for general proxying of options.
  - Includes a method `is_option_proxiable` to check if an option is proxiable or not.
- YoastSEO's options for proxying.
- Transient to `LangInterface::get_post_translation`.
- Top menu and sub menu pages for Unbabble options.

### Changed

- Improved proxying of options to be changeable by the user.

### Fixed

- Query var bug with `rest_url`.

### Removed

- Unnecessary `post_type_archive_link` from Directory routing class.

## [0.1.1] - 2023-10-26

### Added

- Relevanssi Integration.
- Fallback condition for post lang filter when post type format in WP_Query is not as expected.

### Fixed

- Check for `editpost` action for posts created via ajax.
- Homepage in non-default language directory having language twice.
- Yoast's rewrite-republish post's did not have language or a `ubb_source` meta.

## [0.0.13] - 2023-09-14

### Fixed

- Check `$wp_the_query` is a WP_Query in `LangInterface`.

## [0.1.0] - 2023-08-29

### Added

- Gutenberg (Block Editor) support.
- CLI commands for hidden posts, posts without language or unknown languages, via the `CLI\Hidden\Post` class:
  - `wp ubb post hidden stats` for statistics about the hidden posts.
  - `wp ubb post hidden list` to list the hidden posts.
  - `wp ubb post hidden fix` to fix the hidden posts.
- CLI commands for hidden terms, terms without language or unknown languages, via the `CLI\Hidden\Term` class:
  - `wp ubb term hidden stats` for statistics about the hidden terms.
  - `wp ubb term hidden list` to list the hidden terms.
  - `wp ubb term hidden fix` to fix the hidden terms.

### Changed

- Improved output string of `CLI\Post`.
- Finished language change method for Terms.
- Improved Term language metabox.
- Improved Post language metabox.
- Check post type when fetching a post's translations.

### Fixed

- `LangInterface::translate_current_url` when WP_Query is not set.
- Check post/term language is not empty in translations fetch methods in `LangInterface`.
- `API\Actions\HiddenContent` default for the `focus` argument.
- Bugs when editing hiddent posts/terms.
- Bug with array key 0 of query's post types for language filter.

## [0.0.12] - 2023-03-27

### Added

- New `ubb_do_hidden_languages_filter` filter to stop or allow hidden languages from being filtered out of the languages list.
- Arguments to the Hidden Content API for better control.
- Unit tests for `LangInterface`.

### Changed

- Improve hidden languages handling in LangInterface.

### Fixed

- Stop hidden languages from being hidden in the WPML migrator.
- Issue with `$_SERVER['HTTPS']` being unset during wp_cron.
- Issues with language switcher.

### Removed

- Forcing `true` in permission callback for Hidden Content API.

## [0.0.11] - 2023-03-11

### Added

- New option `hidden_languages` to hide a language from the frontend while allowing creation of content for it in the back-office.
- New class `Validator` for options validation.
- Validation of options format, value types and the values themselves in some edge cases.
- Admin notices for failed validation and successful options update.
- New methods for `LangInterface`:
  - `get_languages()` to fetch an array of all the available language codes.
  - `is_language_allowed()` to check if a language is allowed in the options.
  - `get_default_language()` to get the default language defined in the options.
  - `get_translatable_post_types()` to get a list of translatable post types as defined in the options.
  - `get_translatable_taxonomies()` to get a list of translatable taxonomies as defined in the options.

### Changed

- Passed most fetching of option values from `Options` to `LangInterface`. Separates the actual option values from the ones necessary for most of the code. This allows us to change the values of the options, for example, what languages are allowed according to the new option `hidden_languages`.
- Replace fetching of options values to use the new `LangInterface` methods. Exceptions are places where we need to use the options values directly (e.g. `OptionsPage`, `CLI/Options`).
- Move options update method in `DB\Options` to the `Options` class. 
- Improve option defaults.
- Improve how the constant `UNBABBLE_IDLE` works to allow for better control in multisites.
- Make `defaults` method in `Options` public.

### Removed

- Remove `DB\Options` class.

## [0.0.10] - 2023-03-08

### Added

- New action `ubb_options_updated` for when the Unbabble options value is changed.
- New language filter for accurate post counts by post status.
- Added missing locations for the customization of the front page.

### Changed

- Passing the check for running Unbabble into `Plugin`.
- Use component classes instead of instantiating them immeditately in the list of components.
- Register all the hooks immediately instead of waiting for `init`. This assumes that the components themselves are only registering hooks and not running code immediately. The only exception to this is the routing for the `Directory`.
- Moved `Directory` language fetching to the earliest possible time when the plugin is being loaded.
- Changed how options filter `ubb_options` works. Values passed to it will update the 'ubb_options' WordPress option value in the database during `wp_loaded`. The options returned in `Options::get` will now only return the value in the database or the default options value.

## [0.0.9] - 2023-03-06

### Added

- Constant `UNBABBLE_IDLE` to stop most of Unbabble from running on demand without having to turn off the plugin. Helpful to run migrations before switching completely to Unbabble.

### Changed

- When Unbabble shouldn't run: now depends only on the constant `UNBABBLE_IDLE` and if there is only one language defined.

### Removed

- Left over `console.log` in `ubb-admin.js`.

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

[unreleased]: https://github.com/26b/unbabble/compare/0.4.2...HEAD
[0.4.2]: https://github.com/26b/unbabble/compare/0.4.1...0.4.2
[0.4.1]: https://github.com/26b/unbabble/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/26b/unbabble/compare/0.3.1...0.4.0
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
