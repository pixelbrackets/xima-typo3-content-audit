<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentAudit\Service\PageCountProvider;
use Xima\XimaTypo3ContentAudit\Service\PagePreviewUrlProvider;

class EmptyPagesDataProvider implements ListDataProviderInterface
{
    /**
    * Pages created within this many days are marked as »New«
    */
    protected const NEW_THRESHOLD_DAYS = 7;

    private const DISPLAY_LIMIT = 20;

    /**
    * @var array<int>
    */
    protected array $excludePageUids = [];

    /**
    * @var array<int>
    */
    protected array $allowedPageTypes = [1];

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
    * @param array<int> $allowedPageTypes
    */
    public function setAllowedPageTypes(array $allowedPageTypes): void
    {
        $this->allowedPageTypes = $allowedPageTypes;
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

        $queryBuilder
            ->select(
                'pages.uid',
                'pages.tstamp as updated',
                'pages.perms_userid',
                'pages.perms_groupid',
                'pages.perms_user',
                'pages.perms_group',
                'pages.perms_everybody'
            )
            ->addSelectLiteral('COUNT(' . $queryBuilder->quoteIdentifier('content.uid') . ') as content_count')
            ->from('pages')
            // Select only configured page types
            ->where(
                $queryBuilder->expr()->eq('pages.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'pages.doktype',
                    $queryBuilder->createNamedParameter($this->allowedPageTypes, Connection::PARAM_INT_ARRAY)
                )
            )
            ->leftJoin(
                'pages',
                'tt_content',
                'content',
                (string)$queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('content.pid', $queryBuilder->quoteIdentifier('pages.uid')),
                    $queryBuilder->expr()->eq('content.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('content.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
                )
            )
            ->groupBy('pages.uid')
            ->having('content_count = 0')
            ->orderBy('updated', 'DESC')
            ->addOrderBy('pages.uid', 'ASC');

        // Add optional page exclusions
        if (!empty($this->excludePageUids)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->notIn(
                    'pages.uid',
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
        $rows = $queryBuilder
            ->select('uid', 'title as pageTitle', 'slug as pageSlug', 'crdate as created')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $rowsByUid = array_column($rows, null, 'uid');

        $isNewThreshold = time() - self::NEW_THRESHOLD_DAYS * 86400;
        $results = [];
        foreach ($pages as $page) {
            $page = array_merge($page, $rowsByUid[$page['uid']] ?? []);
            $page['isNew'] = (int)$page['created'] >= $isNewThreshold;
            $page['previewUrl'] = $this->previewUrlProvider->getUrl((int)$page['uid']);
            $results[] = $page;
        }

        return $results;
    }
}
