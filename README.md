# WP-Plugin

All you need to start creating a plugin for WordPress.

We aim to deliver a focus on the API features of WordPress and it's eventual use as a headless CMS, but feel free to use this base for any kind of plugin. It provides several features that will enable you to develop and test your code.

## Getting started

### Find and Replace

This project is a skeleton, so it has a bunch of keys that need to be replaced with values specific to your project.

For these changes, you should use the "Find and Replace" feature of your editor. Later there will be more options to this. Below you can find the table with the keys and their respective description, along with an example for the possible value.

| Key                      | Description                                                         | Example value                         |
| ------------------------ | ------------------------------------------------------------------- | ------------------------------------- |
| `26b`          | Your username or company name: no spaces                            | `26B`                                 |
| `unbabble`          | See ["Planning Your Plugin – Pick a good name"][1] ([more info][2]) | `Foo Bar`                             |
| `A new and simple i18n system for WordPress`   | Description for the plugin ([more info][2])                         | `A WordPress plugin starter.`         |
| `https://github.com/26B/unbabble`           | Plugin URL ([more info][2])                                         | `https://github.com/26B/wp-plugin`    |
| `0.0.0`      | Version to start the plugin with ([more info][2])                   | `1.0.0`                               |
| `26B`          | Author name ([more info][2])                                        | `Pedro Duarte`                        |
| `https://26b.io/`           | Author URL ([more info][2])                                         | `https://github.com/xipasduarte`      |
| `unbabble`          | Text domain ([more info][2])                                        | `foo-bar`                             |
| `26b`      | Your username, company or project name: lowercase and no spaces     | `26b`                                 |
| `unbabble`        | Plugin identifier: usually the `unbabble` in dash-case          | `foo-bar`                             |
| `TwentySixB\WP\Plugin\Unbabble`            | Desired PHP namespace                                               | `26B\WP\Plugin\FooBar`                |
| `TwentySixB\\WP\\Plugin\\Unbabble\\`       | [PSR-4 autoload][3] for `TwentySixB\WP\Plugin\Unbabble`                               | `26B\\WP\\Plugin\\FooBar\\`           |
| `TwentySixB\\WP\\Plugin\\Unbabble\\Tests\\` | [PSR-4 autoload][3] for `[namespace_tests]`                         | `26B\\WP\\Plugin\\FooBar\\Tests\\`    |

[1]: https://developer.wordpress.org/plugins/wordpress-org/planning-your-plugin/#2-pick-a-good-name
[2]: https://developer.wordpress.org/plugins/the-basics/header-requirements/
[3]: https://getcomposer.org/doc/04-schema.md#psr-4

For further information on writing WordPress plugins refer to the [official documentation](https://developer.wordpress.org/plugins/).

### Run composer

After all of the changes don't forget to run `composer install` to have the dependencies load and the autoload built. (Without this your plugin will break.)
