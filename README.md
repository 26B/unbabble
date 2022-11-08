# unbabble

Our simple and particular way to translate content in WordPress.

This plugin aims to be simple and, above all, aligned with how we found translations to be used in WordPress.

## Developing

We use a standard WordPress plugin approach with OOP style and composer to manage dependencies. There are also some screens that are made with JavaScript, using ReactJS, and we try to match all the minimum requirements from WordPress.

## No more than what's needed

Many of the existing solutions to translate content in WordPress offer too many features. They are not bad or wrong, but in our use cases we found that they are more trouble when there is a lot of custom code. In Unbabble we remove everything that is not translation management related to add-ons and third-party plugins that then enhance the existing solution.

Here's what Unbabble does:

- Manages languages that exist for variations of your content
- Allows for content to be created in different languages
- Allows for different languages to be connected as translations of the same content

Here's what it doesn't do:

- Copy content when creating a new translation
  There are many plugins that do cloning and copying well, we don't need this as well. We plan support the use of Yoast's [Duplicate Post](https://github.com/26B/duplicate-post) to enable this as a feature.
- Translate your content for you.

## Architecture

A lot of decisions went into this. We try to provide information on all of them to allow for new contributions to be as easy as possible, in case our solution aligns with the needs of others. As well as, keep as much documentation as possible for everyone.

### Extra table for translations

We add two new tables with the following schemas that contains all the translation references.

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
