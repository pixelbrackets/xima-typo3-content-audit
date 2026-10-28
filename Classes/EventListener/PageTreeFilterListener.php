<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Backend\Dto\Tree\Label\Label;
use TYPO3\CMS\Backend\Tree\Repository\BeforePageTreeIsFilteredEvent;
use Xima\XimaTypo3ContentAudit\Widgets\Provider\BrokenLinksDataProvider;
use Xima\XimaTypo3ContentAudit\Widgets\Provider\EmptyPagesDataProvider;
use Xima\XimaTypo3ContentAudit\Widgets\Provider\HiddenContentDataProvider;
use Xima\XimaTypo3ContentAudit\Widgets\Provider\HiddenPagesDataProvider;
use Xima\XimaTypo3ContentAudit\Widgets\Provider\MergedPageChangeDataProvider;
use Xima\XimaTypo3ContentAudit\Widgets\Provider\MissingPageFieldsDataProvider;
use Xima\XimaTypo3ContentAudit\Widgets\Provider\UntranslatedPagesDataProvider;

/**
* Couple the content audit widgets to the page tree filter and highlight matching pages.
*
* Matches fixed phrases in the page tree's filter field to the same page id lists
* the widgets show via fetchMatchingItems().
*/
final class PageTreeFilterListener
{
    public const TOKEN_STALE_PAGES = 'xima-content-audit-stale-pages';
    public const TOKEN_FRESH_PAGES = 'xima-content-audit-fresh-pages';
    public const TOKEN_HIDDEN_PAGES = 'xima-content-audit-hidden-pages';
    public const TOKEN_HIDDEN_CONTENT = 'xima-content-audit-hidden-content';
    public const TOKEN_EMPTY_PAGES = 'xima-content-audit-empty-pages';
    public const TOKEN_UNTRANSLATED_PAGES = 'xima-content-audit-untranslated-pages';
    public const TOKEN_MISSING_PAGE_FIELDS = 'xima-content-audit-missing-page-fields';
    public const TOKEN_BROKEN_LINKS = 'xima-content-audit-broken-links';

    private const MARKER_LABEL = 'Content audit match';
    private const MARKER_COLOR = '#5b3cc4';

    private ?string $resolvedSearchPhrase = null;

    /**
    * @var list<int>
    */
    private array $resolvedUids = [];

    /**
    * @param array<string, mixed> $stalePagesOptions
    * @param array<string, mixed> $freshPagesOptions
    * @param array<string, mixed> $hiddenPagesOptions
    * @param array<string, mixed> $hiddenContentOptions
    * @param array<string, mixed> $emptyPagesOptions
    * @param array<string, mixed> $untranslatedPagesOptions
    * @param array<string, mixed> $missingPageFieldsOptions
    */
    public function __construct(
        private readonly MergedPageChangeDataProvider $pageChangeDataProvider,
        private readonly HiddenPagesDataProvider $hiddenPagesDataProvider,
        private readonly HiddenContentDataProvider $hiddenContentDataProvider,
        private readonly EmptyPagesDataProvider $emptyPagesDataProvider,
        private readonly UntranslatedPagesDataProvider $untranslatedPagesDataProvider,
        private readonly MissingPageFieldsDataProvider $missingPageFieldsDataProvider,
        private readonly BrokenLinksDataProvider $brokenLinksDataProvider,
        private readonly array $stalePagesOptions,
        private readonly array $freshPagesOptions,
        private readonly array $hiddenPagesOptions,
        private readonly array $hiddenContentOptions,
        private readonly array $emptyPagesOptions,
        private readonly array $untranslatedPagesOptions,
        private readonly array $missingPageFieldsOptions,
    ) {
    }

    public function __invoke(BeforePageTreeIsFilteredEvent $event): void
    {
        $uids = $this->resolveMatchingPageUids(trim($event->searchPhrase));
        if ([] !== $uids) {
            $event->searchUids = array_values(array_unique([...$event->searchUids, ...$uids]));
        }
    }

    /**
    * Mark filtered pages with a colored bar on the left.
    * Replicates the core "Search result" highlight logic.
    */
    public function markMatchedPages(AfterPageTreeItemsPreparedEvent $event): void
    {
        $searchPhrase = trim((string)($event->getRequest()->getQueryParams()['q'] ?? ''));
        $uids = array_flip($this->resolveMatchingPageUids($searchPhrase));
        if ([] === $uids) {
            return;
        }

        $items = $event->getItems();
        foreach ($items as &$item) {
            $page = $item['_page'] ?? [];
            if (!is_array($page) || !isset($uids[(int)($page['uid'] ?? 0)])) {
                continue;
            }
            $item['labels'][] = new Label(self::MARKER_LABEL, self::MARKER_COLOR);
        }
        unset($item);
        $event->setItems($items);
    }

    /**
    * Memorize per request to use in both events (change pagetree, mark pages)
    *
    * @return list<int>
    */
    private function resolveMatchingPageUids(string $searchPhrase): array
    {
        if ($this->resolvedSearchPhrase === $searchPhrase) {
            return $this->resolvedUids;
        }

        $uids = match ($searchPhrase) {
            self::TOKEN_STALE_PAGES => $this->matchingStaleOrFreshPageUids(true, $this->stalePagesOptions),
            self::TOKEN_FRESH_PAGES => $this->matchingStaleOrFreshPageUids(false, $this->freshPagesOptions),
            self::TOKEN_HIDDEN_PAGES => $this->matchingHiddenPageUids(),
            self::TOKEN_HIDDEN_CONTENT => $this->matchingHiddenContentPageUids(),
            self::TOKEN_EMPTY_PAGES => $this->matchingEmptyPageUids(),
            self::TOKEN_UNTRANSLATED_PAGES => $this->matchingUntranslatedPageUids(),
            self::TOKEN_MISSING_PAGE_FIELDS => $this->matchingMissingPageFieldsUids(),
            self::TOKEN_BROKEN_LINKS => $this->pageUids($this->brokenLinksDataProvider->fetchMatchingItems()),
            default => [],
        };

        $this->resolvedSearchPhrase = $searchPhrase;
        $this->resolvedUids = $uids;

        return $uids;
    }

    /**
    * @param array<string, mixed> $options
    * @return list<int>
    */
    private function matchingStaleOrFreshPageUids(bool $oldestFirst, array $options): array
    {
        $this->pageChangeDataProvider->setShowOldestFirst($oldestFirst);
        $this->pageChangeDataProvider->setExcludePageUids($options['excludePageUids'] ?? []);
        $this->pageChangeDataProvider->setThresholdDays((int)($options['thresholdDays'] ?? 180));

        return $this->pageUids($this->pageChangeDataProvider->fetchMatchingItems());
    }

    /**
    * @return list<int>
    */
    private function matchingHiddenPageUids(): array
    {
        $this->hiddenPagesDataProvider->setExcludePageUids($this->hiddenPagesOptions['excludePageUids'] ?? []);
        $this->hiddenPagesDataProvider->setThresholdDays((int)($this->hiddenPagesOptions['thresholdDays'] ?? 365));

        return $this->pageUids($this->hiddenPagesDataProvider->fetchMatchingItems());
    }

    /**
    * Content elements share pages, so unlike the other providers this
    * deduplicates by pid rather than mapping uid directly.
    *
    * @return list<int>
    */
    private function matchingHiddenContentPageUids(): array
    {
        $this->hiddenContentDataProvider->setExcludePageUids($this->hiddenContentOptions['excludePageUids'] ?? []);
        $this->hiddenContentDataProvider->setThresholdDays((int)($this->hiddenContentOptions['thresholdDays'] ?? 730));

        return array_values(array_unique($this->pageUids($this->hiddenContentDataProvider->fetchMatchingItems(), 'pid')));
    }

    /**
    * @return list<int>
    */
    private function matchingEmptyPageUids(): array
    {
        $this->emptyPagesDataProvider->setAllowedPageTypes($this->emptyPagesOptions['allowedPageTypes'] ?? [1]);
        $this->emptyPagesDataProvider->setExcludePageUids($this->emptyPagesOptions['excludePageUids'] ?? []);

        return $this->pageUids($this->emptyPagesDataProvider->fetchMatchingItems());
    }

    /**
    * @return list<int>
    */
    private function matchingUntranslatedPageUids(): array
    {
        $this->untranslatedPagesDataProvider->setExcludePageUids($this->untranslatedPagesOptions['excludePageUids'] ?? []);

        return $this->pageUids($this->untranslatedPagesDataProvider->fetchMatchingItems());
    }

    /**
    * @return list<int>
    */
    private function matchingMissingPageFieldsUids(): array
    {
        $this->missingPageFieldsDataProvider->setMissingField($this->missingPageFieldsOptions['missingField'] ?? 'abstract');
        $this->missingPageFieldsDataProvider->setExcludePageUids($this->missingPageFieldsOptions['excludePageUids'] ?? []);

        return $this->pageUids($this->missingPageFieldsDataProvider->fetchMatchingItems());
    }

    /**
    * @param list<array<string, mixed>> $rows
    * @return list<int>
    */
    private function pageUids(array $rows, string $column = 'uid'): array
    {
        return array_map(intval(...), array_column($rows, $column));
    }
}
