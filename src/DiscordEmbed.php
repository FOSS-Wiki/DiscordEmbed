<?php

// SPDX-License-Identifier: GPL-2.0-or-later
// Derived from the Discord feature of the WikimediaCustomizations
// https://github.com/wikimedia/mediawiki-extensions-WikimediaCustomizations
// GPL-2.0-or-later - Wikimedia Foundation contributors

namespace MediaWiki\Extension\DiscordEmbed;

use LogicException;
use MediaWiki\Content\WikiTextStructure;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Page\ExistingPageRecord;
use MediaWiki\Page\PageProps;
use MediaWiki\Page\ParserOutputAccess;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Handler\Helper\PageContentHelper;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

class DiscordEmbed extends SimpleHandler
{
    private const CACHE_VERSION = 1;
    private const EXCLUDED_ELEMENT_SELECTORS = [ '.noexcerpt' ];

    private PageContentHelper $contentHelper;

    public function __construct(
        PageRestHelperFactory $helperFactory,
        private readonly TitleFactory $titleFactory,
        private readonly UrlUtils $urlUtils,
        private readonly ParserOutputAccess $parserOutputAccess,
        private readonly PageProps $pageProps,
        private readonly RepoGroup $repoGroup,
        private readonly WANObjectCache $cache,
    ) {
        $this->contentHelper = $helperFactory->newPageContentHelper();
    }

    // Escape characters Discord would treat as Markdown
    private function escapeMarkdown(string $text): string
    {
        return preg_replace('/[\\\\*_~`|#>\[\]]/', '\\\\$0', $text);
    }

    private function fetchExtract(ExistingPageRecord $page): string
    {
        return $this->cache->getWithSetCallback(
            $this->cache->makeKey(
                'DiscordEmbed',
                'extract',
                self::CACHE_VERSION,
                $page->getId(),
                $page->getTouched()
            ),
            WANObjectCache::TTL_DAY,
            function ($oldValue, &$ttl) use ($page): string {
                $extract = $this->buildExtract($page);
                if ($extract === '') {
                    $ttl = WANObjectCache::TTL_UNCACHEABLE;
                }
                return $extract;
            }
        );
    }

    private function buildExtract(ExistingPageRecord $page): string
    {
        $status = $this->parserOutputAccess->getParserOutput(
            $page,
            ParserOptions::newFromAnon(),
            null,
            [ ParserOutputAccess::OPT_FOR_ARTICLE_VIEW => true ]
        );
        if (!$status->isOK()) {
            return '';
        }
        try {
            $structure = new WikiTextStructure($status->getValue(), self::EXCLUDED_ELEMENT_SELECTORS);
            $extract = $structure->getOpeningText() ?? $structure->getMainText();
        } catch (LogicException) {
            return '';
        }
        return mb_strlen($extract) > 350 ? mb_substr($extract, 0, 349) . '…' : $extract;
    }

    private function fetchThumbnailUrl(ExistingPageRecord $page): ?string
    {
        $props = $this->pageProps->getProperties($page, [ 'page_image_free', 'page_image' ]);
        $pageProps = $props[$page->getId()] ?? [];
        $fileName = $pageProps['page_image_free'] ?? $pageProps['page_image'] ?? null;
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
        $extract = $this->escapeMarkdown($this->fetchExtract($page));
        $thumbnail = $this->fetchThumbnailUrl($page);

        $text = ['type' => 10, 'content' => "# [$markdownTitle]($markdownUrl)\n\n$extract"];
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
                'label' => 'Edit',
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
