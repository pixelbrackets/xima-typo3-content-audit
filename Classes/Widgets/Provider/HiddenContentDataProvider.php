<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentAudit\Service\PagePreviewUrlProvider;

class HiddenContentDataProvider implements ListDataProviderInterface
{
    private const DISPLAY_LIMIT = 20;

    /**
    * @var array<int>
    */
    protected array $excludePageUids = [];

    protected int $thresholdDays = 730;

    public function __construct(
        protected readonly PagePreviewUrlProvider $previewUrlProvider,
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

    public function setThresholdDays(int $thresholdDays): void
    {
        $this->thresholdDays = $thresholdDays;
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function getItems(): array
    {
        $matchingContent = $this->fetchMatchingItems();
        $matchCount = count($matchingContent);

        $totalCountQueryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $totalCountQueryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);
        $totalCount = (int)$totalCountQueryBuilder
            ->count('uid')
            ->from('tt_content')
            ->executeQuery()
            ->fetchOne();

        // Check if user has access to edit the content record
        $accessibleContent = [];
        if ($GLOBALS['BE_USER']->check('tables_modify', 'tt_content')) {
            foreach ($matchingContent as $content) {
                $pageRecord = [
                    'uid' => $content['pid'],
                    'perms_userid' => $content['perms_userid'],
                    'perms_groupid' => $content['perms_groupid'],
                    'perms_user' => $content['perms_user'],
                    'perms_group' => $content['perms_group'],
                    'perms_everybody' => $content['perms_everybody'],
                ];
                if (!$GLOBALS['BE_USER']->doesUserHaveAccess($pageRecord, 2)) {
                    continue;
                }
                $accessibleContent[] = $content;
                if (count($accessibleContent) >= self::DISPLAY_LIMIT) {
                    break;
                }
            }
        }

        return [
            'matchCount' => $matchCount,
            'totalCount' => $totalCount,
            'results' => $this->fetchContentDetails($accessibleContent),
        ];
    }

    /**
    * Fetch raw content list
    *
    * @return list<array<string, mixed>>
    */
    public function fetchMatchingItems(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        // Remove TYPO3 default "hidden" restriction to also find hidden content elements
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $queryBuilder
            ->select(
                'content.uid',
                'content.pid',
                'content.tstamp as updated',
                'page.slug as pageSlug',
                'page.perms_userid',
                'page.perms_groupid',
                'page.perms_user',
                'page.perms_group',
                'page.perms_everybody'
            )
            ->from('tt_content', 'content')
            ->innerJoin(
                'content',
                'pages',
                'page',
                'page.uid = content.pid'
            )
            ->where(
                $queryBuilder->expr()->eq('content.hidden', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
                $queryBuilder->expr()->lt(
                    'content.tstamp',
                    $queryBuilder->createNamedParameter(strtotime('-' . $this->thresholdDays . ' days'), Connection::PARAM_INT)
                )
            )
            ->orderBy('updated', 'ASC')
            ->addOrderBy('content.uid', 'ASC');

        // Add optional page exclusions
        if (!empty($this->excludePageUids)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->notIn(
                    'content.pid',
                    $queryBuilder->createNamedParameter($this->excludePageUids, Connection::PARAM_INT_ARRAY)
                )
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
    * Enrich already access-checked content elements with the remaining display columns
    *
    * @param list<array<string, mixed>> $contentElements
    * @return list<array<string, mixed>>
    */
    private function fetchContentDetails(array $contentElements): array
    {
        if (empty($contentElements)) {
            return [];
        }

        $contentUids = array_column($contentElements, 'uid');

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        // Remove TYPO3 default "hidden" restriction - these elements are hidden by definition
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $rows = $queryBuilder
            ->select('uid', 'header as contentTitle')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($contentUids, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $rowsByUid = array_column($rows, null, 'uid');

        $results = [];
        foreach ($contentElements as $content) {
            $content = array_merge($content, $rowsByUid[$content['uid']] ?? []);
            $content['previewUrl'] = $this->previewUrlProvider->getUrl((int)$content['pid']);
            $results[] = $content;
        }

        return $results;
    }
}
