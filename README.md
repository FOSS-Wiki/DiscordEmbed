# DiscordEmbed

MediaWiki extension that gives article links custom Discord embeds using Discord's component embeds using `<link rel="discord:component-embed">`.

It pulls the description from `description` property of the page written by the [Description2](https://www.mediawiki.org/wiki/Extension:PageImages) extension, and the image from the `page_image`/`page_image_free` property of the page written by the [PageImages](https://www.mediawiki.org/wiki/Extension:PageImages) extension.

Derived from the Discord feature set of the [WikimediaCustomizations](https://github.com/wikimedia/mediawiki-extensions-WikimediaCustomizations)
extension (GPL-2.0-or-later) in `src/Discord`

Endpoint: `/rest.php/discord-embed?title=Page_name`

## Install

Copy this folder to `extensions/DiscordEmbed`, then in LocalSettings.php:

```php
    wfLoadExtension('DiscordEmbed');
```

## License

This extension is licensed under the GPL-2.0-or-later license. See [LICENSE](LICENSE) for details.
