<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentAudit\Service\PageCountProvider;
use Xima\XimaTypo3ContentAudit\Service\PagePreviewUrlProvider;

class MissingPageFieldsDataProvider implements ListDataProviderInterface
{
    private const DISPLAY_LIMIT = 20;

    protected string $missingField = 'abstract';

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

    public function setMissingField(string $missingField): void
    {
        $this->missingField = $missingField;
    }

    /**
    * @param array<int> $excludePageUids
    */
    public function setExcludePageUids(array $excludePageUids): void
    {
        $this->excludePageUids = $excludePageUids;
    }

    /**
    * TYPO3 stores FAL relations (TCA type "file") as sys_file_reference
    * rows, not as a column on the record itself - unlike a plain text field.
    */
    protected function isFileField(): bool
    {
        return ($GLOBALS['TCA']['pages']['columns'][$this->missingField]['config']['type'] ?? null) === 'file';
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
            ->select('pages.uid', 'pages.tstamp as updated', 'pages.perms_userid', 'pages.perms_groupid', 'pages.perms_user', 'pages.perms_group', 'pages.perms_everybody')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pages.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('pages.doktype', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT))
            )
            ->orderBy('updated', 'DESC')
            ->addOrderBy('pages.uid', 'ASC');

        if ($this->isFileField()) {
            // Only look for pages missing an image field
            $queryBuilder
                ->leftJoin(
                    'pages',
                    'sys_file_reference',
                    'reference',
                    (string)$queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq('reference.tablenames', $queryBuilder->createNamedParameter('pages')),
                        $queryBuilder->expr()->eq('reference.fieldname', $queryBuilder->createNamedParameter($this->missingField)),
                        $queryBuilder->expr()->eq('reference.uid_foreign', $queryBuilder->quoteIdentifier('pages.uid')),
                        $queryBuilder->expr()->eq('reference.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
                    )
                )
                ->andWhere($queryBuilder->expr()->isNull('reference.uid'));
        } else {
            // Only look for pages missing an input field
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('pages.' . $this->missingField, $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('pages.' . $this->missingField)
                )
            );
        }

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
