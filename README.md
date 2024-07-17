# Unbabble

Our stab at translations in WordPress.

This plugin aims to be several things which, hopefully, we'll be able to keep:

- Simple concepts of translation
- Aligned with how we found translations to be used in WordPress (it's our take after all)
- Developer first APIs and configuration
- No magic stuff, just default to something obvious, even if wrong or causes an error.

## No more than what's needed

Many of the existing solutions to translate content in WordPress offer too many features. They are not bad or wrong, but in our use cases we found that they are more trouble when there is a lot of custom code. In Unbabble we remove everything that is not translation management related, pushing these things to add-ons and third-party plugins that then enhance the existing solution.

Here's what Unbabble does:

- Manages languages that exist for variations of your content
- Allows for content to be created in different languages
- Allows for different languages to be connected as translations of the same content

Here's what it doesn't do:

- Copy content when creating a new translation
  There are many plugins that do cloning and copying well, we don't need this. We support the use of Yoast's [Duplicate Post](https://github.com/26B/duplicate-post) to enable this as a feature, through an internal extension.
- Translate your content for you.

## Architecture

A lot of decisions went into this. We try to provide information on all of them to allow for new contributions to be as easy as possible, in case our solution aligns with the needs of others. As well as, keep as much documentation as possible for everyone.

### Extra table for translations

We add two new tables with the following schemas that contain all the translation references.

`{$wpdb->prefix}_ubb_post_translations`

| Columns name | Type       |
| ------------ | ---------- |
| `post_id`    | Integer    |
| `locale`     | VARCHAR(5) |
 
`{$wpdb->prefix}_ubb_term_translations`

| Columns name | Type       |
| ------------ | ---------- |
| `post_id`    | Integer    |
| `locale`     | VARCHAR(5) |

### Options

Options have a specific table, but there is no interface to connect them and create translations groups. For Options we make an automatic translation management. When you view an option on a given language, or access it in a given context (admin or code), we filter every option access to a new value `my_option_[language-code]`. All access is filtered through the language in a given context.

*Note:* When looking directly at the database structure and data, you should be aware of this, so that you search appropriately for the option's key.

### Translations Links

To know which entities are translations of any other entity, we add a meta to all entities that contains the original ID `ubb_source`. The original is the first post that was created, and can be in any langue. Although the site might have a given default language that is not required to be the language of all original posts/terms.

### Enforced restrictions

- Only one translation for a given `ubb_source` with the same locale (avoids multiple options for the same entity). This does not break the site, but is shown as an issue. By default, when many posts share the same language, it first is selected.

## Develop

We use a standard WordPress plugin approach with OOP style and composer to manage dependencies. There are also some screens that are made with JavaScript, using ReactJS, and we try to match all the minimum requirements from WordPress.

Development requires:

- NodeJS v18
- PHP >=8.0
- Composer v2
- WordPress >=6.5
- Docker (optional)

To get up and running follow the steps below.

- Clone and install dependencies

```bash
# Clone the repository.
git clone git@github.com:26B/unbabble.git

# Switch to the plugin folder and install dependencies
cd unbabble
composer install
npm install
```

- If you want to use docker, you can use `@wordpress/env` to set it up

```bash
# Local wp-env setup
npm run wp-env start

# If you have a global wp-env install just use that
wp-env start
```

- Build development assets and watch for changes

```bash
npm run start
```

## Contributions

We welcome all contributors that find our approach reasonable and useful. You may contribute by filing a bug report, suggesting some feature or submiting your own code. We'll revew new features and code submitted through our goals for the project, stated above.

All contributions must include a CHANGELOG entry adhering to the specification from: https://keepachangelog.com/en/1.1.0/
