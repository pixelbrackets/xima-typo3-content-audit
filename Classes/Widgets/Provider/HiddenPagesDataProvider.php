<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentAudit\Service\PageCountProvider;
use Xima\XimaTypo3ContentAudit\Service\PagePreviewUrlProvider;

class HiddenPagesDataProvider implements ListDataProviderInterface
{
    private const DISPLAY_LIMIT = 20;

    /**
    * @var array<int>
    */
    protected array $excludePageUids = [];

    public function __construct(
        protected readonly PagePreviewUrlProvider $previewUrlProvider,
        private readonly PageCountProvider $pageCountProvider,
        private readonly \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool
    ) {
    }

    /**
    * @param array<int> $excludePageUids
    */
    public function setExcludePageUids(array $excludePageUids): void
    {
        $this->excludePageUids = $excludePageUids;
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function getItems(): array
    {
        $matchingPages = $this->fetchMatchingItems();
        $matchCount = count($matchingPages);
        $totalCount = $this->pageCountProvider->getTotalPageCount();

        // Check if user has access to edit page record
        $accessiblePages = [];
        foreach ($matchingPages as $page) {
            if (!$GLOBALS['BE_USER']->doesUserHaveAccess($page, 2)) { // 2 = edit page
                continue;
            }
            $accessiblePages[] = $page;
            if (count($accessiblePages) >= self::DISPLAY_LIMIT) {
                break;
            }
        }

        return [
            'matchCount' => $matchCount,
            'totalCount' => $totalCount,
            'results' => $this->fetchPageDetails($accessiblePages),
        ];
    }

    /**
    * Fetch raw page list
    *
    * @return list<array<string, mixed>>
    */
    public function fetchMatchingItems(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        // Remove TYPO3 default "hidden" restriction to also find hidden pages
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $queryBuilder
            ->select('uid', 'tstamp as updated', 'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody')
            ->from('pages')
            // Select only pages and shortcuts, no folders etc, default language only
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'doktype',
                    $queryBuilder->createNamedParameter([1, 4], Connection::PARAM_INT_ARRAY)
                ),
                // Only select hidden (not deleted) pages not updated for x days
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
                $queryBuilder->expr()->lt(
                    'tstamp',
                    $queryBuilder->createNamedParameter(strtotime('-365 days'), Connection::PARAM_INT)
                )
            )
            ->orderBy('updated', 'ASC')
            ->addOrderBy('uid', 'ASC');

        // Add optional page exclusions
        if (!empty($this->excludePageUids)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->notIn(
                    'uid',
                    $queryBuilder->createNamedParameter($this->excludePageUids, Connection::PARAM_INT_ARRAY)
                )
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
    * Enrich already access-checked pages with the remaining display columns
    *
    * @param list<array<string, mixed>> $pages
    * @return list<array<string, mixed>>
    */
    private function fetchPageDetails(array $pages): array
    {
        if (empty($pages)) {
            return [];
        }

        $pageUids = array_column($pages, 'uid');

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        // Remove TYPO3 default "hidden" restriction - these pages are hidden by definition
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $rows = $queryBuilder
            ->select('uid', 'title as pageTitle', 'slug as pageSlug')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $rowsByUid = array_column($rows, null, 'uid');

        $results = [];
        foreach ($pages as $page) {
            $page = array_merge($page, $rowsByUid[$page['uid']] ?? []);
            $page['previewUrl'] = $this->previewUrlProvider->getUrl((int)$page['uid']);
            $results[] = $page;
        }

        return $results;
    }
}
