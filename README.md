# DiscordEmbed

MediaWiki extension that gives article links custom Discord embeds using Discord's component embeds using `<link rel="discord:component-embed">`.

It pulls the description from `description` property of the page written by the [Description2](https://www.mediawiki.org/wiki/Extension:Description2) extension, and the image from the `page_image`/`page_image_free` property of the page written by the [PageImages](https://www.mediawiki.org/wiki/Extension:PageImages) extension.

Derived from the Discord feature set of the [WikimediaCustomizations](https://github.com/wikimedia/mediawiki-extensions-WikimediaCustomizations)
extension (GPL-2.0-or-later) in `src/Discord`

Endpoint: `/rest.php/discord-embed?title=Page_name`

## Install

You should have the [Description2](https://www.mediawiki.org/wiki/Extension:Description2) and [PageImages](https://www.mediawiki.org/wiki/Extension:PageImages) extensions installed and enabled for this extension to work properly, it won't break your wiki but the embeds will be missing the description and image if they aren't installed.

Copy this folder to `extensions/DiscordEmbed`, then in LocalSettings.php:

```php
    wfLoadExtension('DiscordEmbed');
```

## License

This extension is licensed under the GPL-2.0-or-later license. See [LICENSE](LICENSE) for details.
