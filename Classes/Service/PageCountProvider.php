<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;

/**
* Counts all pages eligible for the page-based audit widgets
*/
class PageCountProvider
{
    /**
    * Page types considered regular content pages
    *
    * @var array<int>
    */
    protected array $pageDoktypes = [1, 4];

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    /**
    * Counts all eligible pages
    *
    * @param array<int> $excludePageUids
    */
    public function getTotalPageCount(array $excludePageUids = []): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        // Remove TYPO3 default "hidden" restriction - hidden pages still count towards the total
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $queryBuilder
            ->count('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'doktype',
                    $queryBuilder->createNamedParameter($this->pageDoktypes, Connection::PARAM_INT_ARRAY)
                )
            );

        if (!empty($excludePageUids)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->notIn(
                    'uid',
                    $queryBuilder->createNamedParameter($excludePageUids, Connection::PARAM_INT_ARRAY)
                )
            );
        }

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }
}
