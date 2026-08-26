<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentAudit\Service\PageCountProvider;
use Xima\XimaTypo3ContentAudit\Service\PagePreviewUrlProvider;
use Xima\XimaTypo3ContentAudit\Service\SiteLanguageProvider;

class UntranslatedPagesDataProvider implements ListDataProviderInterface
{
    private const DISPLAY_LIMIT = 20;

    /**
    * @var array<int>
    */
    protected array $excludePageUids = [];

    public function __construct(
        protected readonly PagePreviewUrlProvider $previewUrlProvider,
        private readonly PageCountProvider $pageCountProvider,
        private readonly SiteLanguageProvider $siteLanguageProvider,
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

    public function hasTranslationsConfigured(): bool
    {
        return $this->siteLanguageProvider->hasAdditionalLanguagesConfigured();
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
        $additionalLanguageUids = $this->siteLanguageProvider->getAdditionalLanguageUids();
        if ($additionalLanguageUids === []) {
            return [];
        }

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
            ->addSelectLiteral('COUNT(' . $queryBuilder->quoteIdentifier('translation.uid') . ') as translation_count')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pages.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'pages.doktype',
                    $queryBuilder->createNamedParameter([1, 4], Connection::PARAM_INT_ARRAY)
                )
            )
            ->leftJoin(
                'pages',
                'pages',
                'translation',
                (string)$queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('translation.l10n_parent', $queryBuilder->quoteIdentifier('pages.uid')),
                    $queryBuilder->expr()->in(
                        'translation.sys_language_uid',
                        $queryBuilder->createNamedParameter($additionalLanguageUids, Connection::PARAM_INT_ARRAY)
                    ),
                    $queryBuilder->expr()->eq('translation.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
                )
            )
            ->groupBy('pages.uid')
            ->having('translation_count = 0')
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
