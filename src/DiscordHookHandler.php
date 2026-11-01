<?php

// SPDX-License-Identifier: GPL-2.0-or-later
// Derived from the Discord feature of the WikimediaCustomizations
// https://github.com/wikimedia/mediawiki-extensions-WikimediaCustomizations
// GPL-2.0-or-later - Eric Gardner, Daniel Erenrich, Arlo Breault, GergesShamon, Neriah, Umherirrender

namespace MediaWiki\Extension\DiscordEmbed;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\Utils\UrlUtils;

class DiscordHookHandler
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlUtils $urlUtils,
    ) {
    }

    public function onBeforePageDisplay($out, $skin): void
    {
        $title = $out->getTitle();
        if (!$title || !$title->inNamespace(NS_MAIN) || !$title->exists()) {
            return;
        }

        $path = wfAppendQuery(
            $this->config->get(MainConfigNames::RestPath) . '/discord-embed',
            [ 'title' => $title->getPrefixedText() ]
        );
        $href = $this->urlUtils->expand($path, PROTO_CANONICAL);
        if ($href) {
            $out->addLink([
                'rel' => 'discord:component-embed',
                'type' => 'application/json',
                'href' => $href,
            ]);
        }
    }
}
