<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentAudit\Service\PageCountProvider;
use Xima\XimaTypo3ContentAudit\Service\PagePreviewUrlProvider;

class MergedPageChangeDataProvider implements ListDataProviderInterface
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

    protected bool $showOldestFirst = true;

    public function __construct(
        protected readonly PagePreviewUrlProvider $previewUrlProvider,
        private readonly PageCountProvider $pageCountProvider,
        private readonly \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool
    ) {
    }

    public function setShowOldestFirst(bool $oldestFirst): void
    {
        $this->showOldestFirst = $oldestFirst;
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
        $connection = $this->connectionPool->getConnectionForTable('pages');
        $sortDirectionKeyword = $this->showOldestFirst ? 'ASC' : 'DESC';
        $changeConditionOperator = $this->showOldestFirst ? '<' : '>';

        // TYPO3 QueryBuilder does not support subqueries in JOINs directly
        // Fallback to raw SQL query for now and restore the query builder later if possible
        $sql = <<<SQL
            SELECT
                p.uid,
                p.tstamp as lastPageChange,
                IFNULL(content.lastContentChange, p.tstamp) as lastContentChange,
                GREATEST(IFNULL(content.lastContentChange, 0), p.tstamp) AS updated,
                p.perms_userid,
                p.perms_groupid,
                p.perms_user,
                p.perms_group,
                p.perms_everybody
            FROM pages AS p
            LEFT JOIN (
                SELECT pid, MAX(tstamp) AS lastContentChange
                FROM tt_content
                WHERE deleted = 0
                GROUP BY pid
            ) AS content ON content.pid = p.uid
            WHERE
                p.sys_language_uid = 0
                AND p.deleted = 0
                AND p.hidden = 0
                AND p.doktype IN (1, 4)
                AND p.uid NOT IN (:uids)
                AND GREATEST(IFNULL(content.lastContentChange, 0), p.tstamp) {$changeConditionOperator} :timestamp
            ORDER BY updated {$sortDirectionKeyword}, p.uid ASC
            SQL;

        return $connection->executeQuery(
            $sql,
            [
                'timestamp' => strtotime('-180 days'),
                'uids' => empty($this->excludePageUids) ? [0] : $this->excludePageUids, // »0« workaround for valid sql
            ],
            [
                'timestamp' => Connection::PARAM_INT,
                'uids' => Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAllAssociative();
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
