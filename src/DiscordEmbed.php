<?php

// SPDX-License-Identifier: GPL-2.0-or-later
// Derived from the Discord feature of the WikimediaCustomizations
// https://github.com/wikimedia/mediawiki-extensions-WikimediaCustomizations
// GPL-2.0-or-later - Wikimedia Foundation contributors

namespace MediaWiki\Extension\DiscordEmbed;

use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Page\ExistingPageRecord;
use MediaWiki\Page\PageProps;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Handler\Helper\PageContentHelper;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use Wikimedia\ParamValidator\ParamValidator;

class DiscordEmbed extends SimpleHandler
{
    private const MAX_LENGTH = 350;

    private PageContentHelper $contentHelper;

    public function __construct(
        PageRestHelperFactory $helperFactory,
        private readonly TitleFactory $titleFactory,
        private readonly UrlUtils $urlUtils,
        private readonly PageProps $pageProps,
        private readonly RepoGroup $repoGroup,
    ) {
        $this->contentHelper = $helperFactory->newPageContentHelper();
    }

    private function escapeMarkdown(string $text): string
    {
        return preg_replace('/[\\\\*_~`|#>\[\]]/', '\\\\$0', $text);
    }

    private function fetchDescription(ExistingPageRecord $page): string
    {
        $props = $this->pageProps->getProperties($page, [ 'description' ]);
        $description = trim($props[$page->getId()]['description'] ?? '');
        return mb_strlen($description) > self::MAX_LENGTH
            ? mb_substr($description, 0, self::MAX_LENGTH - 1) . '…'
            : $description;
    }

    private function fetchThumbnailUrl(ExistingPageRecord $page): ?string
    {
        $props = $this->pageProps->getProperties($page, [ 'page_image' ]);
        $pageProps = $props[$page->getId()] ?? [];
        $fileName = $pageProps['page_image'] ?? null;
        $file = $fileName !== null ? $this->repoGroup->findFile($fileName) : null;
        $thumb = $file ? $file->transform([ 'width' => 500 ]) : null;
        if (!$thumb || $thumb->isError()) {
            return null;
        }
        return $this->urlUtils->expand($thumb->getUrl(), PROTO_CANONICAL);
    }

    public function run(): Response
    {
        $this->contentHelper->init($this->getAuthority(), [ 'title' => $this->getValidatedParams()['title'] ]);
        $this->contentHelper->checkAccess();
        $page = $this->contentHelper->getPage();
        $title = $this->titleFactory->newFromPageIdentity($page);

        // Discord needs an absolute URL
        $markdownUrl = str_replace([ '(', ')' ], [ '%28', '%29' ], $title->getCanonicalURL());
        $markdownTitle = $this->escapeMarkdown($title->getPrefixedText());
        $description = $this->escapeMarkdown($this->fetchDescription($page));
        $thumbnail = $this->fetchThumbnailUrl($page);

        $text = ['type' => 10, 'content' => "# [$markdownTitle]($markdownUrl)\n\n$description"];
        $components = [ $text ];
        if ($thumbnail !== null) {
            $components = [ [
                'type' => 9,
                'components' => [ $text ],
                'accessory' => [ 'type' => 11, 'media' => [ 'url' => $thumbnail ] ],
            ] ];
        }

        // Link button under the embed
        $components[] = [
            'type' => 1,
            'components' => [ [
                'type' => 2,
                'style' => 5,
                'label' => 'Edit Page',
                'url' => $title->getCanonicalURL() . '?action=edit',
            ] ],
        ];

        $response = $this->getResponseFactory()->createJson(
            [ 'component' => [ 'type' => 17, 'components' => $components ] ]
        );
        $response->setHeader('Cache-Control', 'public, s-maxage=3600, max-age=300');
        return $response;
    }

    public function getParamSettings(): array
    {
        return [
            'title' => [
                Handler::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
        ];
    }
}
