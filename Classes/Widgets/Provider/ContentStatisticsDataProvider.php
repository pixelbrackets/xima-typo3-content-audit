<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Xima\XimaTypo3ContentAudit\Service\PageCountProvider;
use Xima\XimaTypo3ContentAudit\Service\SiteLanguageProvider;

class ContentStatisticsDataProvider implements ListDataProviderInterface
{
    private const PAGE_DOKTYPES = [1, 4];

    private const ACTIVE_USER_THRESHOLD_DAYS = 90;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PageCountProvider $pageCountProvider,
        private readonly SiteFinder $siteFinder,
        private readonly SiteLanguageProvider $siteLanguageProvider,
        private readonly PackageManager $packageManager
    ) {
    }

    /**
    * @return array{results: list<array{label: string, value: string}>}
    */
    public function getItems(): array
    {
        $pagesTotal = $this->pageCountProvider->getTotalPageCount();
        $contentElementsTotal = $this->countContentElements();

        $pageAge = $this->formatAge($this->fetchPageDate('MIN')) . ' / ' . $this->formatAge($this->fetchPageDate('MAX'));

        return [
            'results' => [
                // Content
                ['label' => 'pages_total', 'value' => (string)$pagesTotal],
                ['label' => 'content_elements_total', 'value' => (string)$contentElementsTotal],
                ['label' => 'content_element_types_total', 'value' => (string)$this->countDistinctContentElementTypes()],
                ['label' => 'avg_content_per_page', 'value' => $this->formatAverage($contentElementsTotal, $pagesTotal)],
                ['label' => 'page_age', 'value' => $pageAge],
                ['label' => 'page_tree_depth', 'value' => (string)$this->fetchPageTreeDepth()],
                ['label' => 'redirects_total', 'value' => $this->countRedirects()],
                ['label' => 'files_total', 'value' => (string)$this->countRows('sys_file')],
                // People
                ['label' => 'backend_users', 'value' => (string)$this->countRows('be_users', 'disable')],
                ['label' => 'active_backend_users', 'value' => (string)$this->countActiveBackendUsers()],
                ['label' => 'frontend_users', 'value' => (string)$this->countRows('fe_users', 'disable')],
                // Site structure
                ['label' => 'languages_total', 'value' => (string)(count($this->siteLanguageProvider->getAdditionalLanguageUids()) + 1)],
                ['label' => 'sites_total', 'value' => (string)count($this->siteFinder->getAllSites())],
            ],
        ];
    }

    private function countContentElements(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        // Remove TYPO3 default "hidden" restriction - hidden content still counts towards the total
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        return (int)$queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
    * Counts how many distinct content element types (CType) are in use
    */
    private function countDistinctContentElementTypes(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        return (int)$queryBuilder
            ->selectLiteral('COUNT(DISTINCT ' . $queryBuilder->quoteIdentifier('CType') . ') as type_count')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
    * Fetch the oldest or newest page date
    *
    * @param 'MIN'|'MAX' $aggregateFunction
    */
    private function fetchPageDate(string $aggregateFunction): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        // Fall back to tstamp if crdate is empty
        $crdate = $queryBuilder->quoteIdentifier('crdate');
        $tstamp = $queryBuilder->quoteIdentifier('tstamp');

        return (int)$queryBuilder
            ->selectLiteral($aggregateFunction . '(COALESCE(NULLIF(' . $crdate . ', 0), NULLIF(' . $tstamp . ', 0))) as aggregated')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'doktype',
                    $queryBuilder->createNamedParameter(self::PAGE_DOKTYPES, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
    * Calculates the deepest nesting level of the page tree
    */
    private function fetchPageTreeDepth(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $pages = $queryBuilder
            ->select('uid', 'pid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $childUidsByParentUid = [];
        foreach ($pages as $page) {
            $childUidsByParentUid[(int)$page['pid']][] = (int)$page['uid'];
        }

        // calculateMaxDepth() counts the root page's children as level 1, so subtract that root level again
        return max(0, $this->calculateMaxDepth($childUidsByParentUid, 0, 0) - 1);
    }

    /**
    * @param array<int, list<int>> $childUidsByParentUid
    */
    private function calculateMaxDepth(array $childUidsByParentUid, int $parentUid, int $depth): int
    {
        $maxDepth = $depth;
        foreach ($childUidsByParentUid[$parentUid] ?? [] as $childUid) {
            $maxDepth = max($maxDepth, $this->calculateMaxDepth($childUidsByParentUid, $childUid, $depth + 1));
        }

        return $maxDepth;
    }

    /**
    * Counts backend users who logged in within the last ACTIVE_USER_THRESHOLD_DAYS days
    */
    private function countActiveBackendUsers(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        return (int)$queryBuilder
            ->count('uid')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('disable', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->gte(
                    'lastlogin',
                    $queryBuilder->createNamedParameter(strtotime('-' . self::ACTIVE_USER_THRESHOLD_DAYS . ' days'), Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function countRedirects(): string
    {
        // Requires typo3/cms-redirects, same optional-dependency pattern as BrokenLinksDataProvider
        if (!$this->packageManager->isPackageActive('redirects')) {
            return '-';
        }

        return (string)$this->countRows('sys_redirect');
    }

    /**
    * Count non-deleted rows in a table, optionally also excluding disabled records
    */
    private function countRows(string $table, ?string $disableField = null): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->count('uid')->from($table);

        if ($disableField !== null) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq($disableField, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );
        }

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    private function formatAverage(int $contentElementsTotal, int $pagesTotal): string
    {
        if ($pagesTotal === 0) {
            return '0';
        }

        return number_format($contentElementsTotal / $pagesTotal, 1);
    }

    /**
    * Formats a timestamp as a rough human age using a single unit, e.g. "300 days" or "2.5 years"
    */
    private function formatAge(int $timestamp): string
    {
        if ($timestamp === 0) {
            return '-';
        }

        $interval = (new \DateTimeImmutable('@' . $timestamp))->diff(new \DateTimeImmutable());

        if ($interval->y > 0) {
            return $this->formatUnit($interval->y + $interval->m / 12, 'age_year', 'age_years');
        }

        return $this->formatUnit(max(1, $interval->days), 'age_day', 'age_days');
    }

    private function formatUnit(int|float $count, string $singularKey, string $pluralKey): string
    {
        $unit = $this->translate($count == 1 ? $singularKey : $pluralKey);
        $formattedCount = is_float($count) ? number_format($count, 1) : (string)$count;

        return $formattedCount . ' ' . $unit;
    }

    private function translate(string $key): string
    {
        return LocalizationUtility::translate('widgets.content_statistics.' . $key, 'XimaTypo3ContentAudit') ?? $key;
    }
}
