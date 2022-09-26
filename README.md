# unbabble

A simpler way to translate content in WordPress.

This plugin aims to be simpler and, above all, aligned with WordPress in providing translations for entities (posts and terms). We don't use any custom tables columns, or taxonomies. Just a few meta fields and a lot of hooking.

## Developing

We use a standard WordPress plugin approach with OOP style and composer to manage dependencies. This is where we don't align so well with WordPress' core. There are also some screens that are made with JavaScript, using ReactJS, and we try to match all the minimum requirements from WordPress.

## Architecture

A lot of decisions went into this. We try to provide information on all of them to allow for new contributions to be easy and, also, for those seeking to contribute to have a place to fallback when understanding everything. (It's not just for newcommers we all use it 😉)

### SOL System

This is the hearth and sol (pun intended 😄) of the plugin's copy and data resolution. This, along with the copy-on-write approach, allows us to save on meta fields and provide some interesting features without much effort.

SOL stands for:
- **S** Synchronize
- **O** Original
- **L** Language

Each represents a branching point in the data fetching flow for any entity's content (data and metadata).

When fetching any of the entity's content the systems follows these steps:
1. *(Synchronize)* Fetch the `ubb_sync[_lang]`. Use the result, if any, as the current `lang`.
2. *(Language)* Check if this is a Language scenario where we need to copy from a specific language. Use the result, if any, as the current `lang`. (Too similar to sync? yes, but doesn't live in the database. It's more like a temporary sync.)
2. Fetch the content for the current `lang`. If it's not empty return it.
3. *(Original)* If it's empty, fetch the original content and return it.

(`[_lang]` is the language being requested)
