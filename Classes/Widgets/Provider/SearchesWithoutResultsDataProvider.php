<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;

class SearchesWithoutResultsDataProvider implements ListDataProviderInterface
{
    private const DISPLAY_LIMIT = 20;

    protected int $thresholdDays = 90;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PackageManager $packageManager
    ) {
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
        $matchingWords = $this->fetchMatchingItems();

        return [
            'matchCount' => count($matchingWords),
            'totalCount' => $this->fetchSearchedWordCount(),
            'results' => array_slice($matchingWords, 0, self::DISPLAY_LIMIT),
        ];
    }

    /**
    * Fetch searched words which do not exist in the search index, most searched first
    *
    * @return list<array<string, mixed>>
    */
    public function fetchMatchingItems(): array
    {
        if (!$this->packageManager->isPackageActive('indexed_search')) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('index_stat_word');
        $queryBuilder
            ->select('s.word as searchWord')
            ->addSelectLiteral('COUNT(s.uid) as search_count')
            ->addSelectLiteral('MAX(s.tstamp) as last_searched')
            ->from('index_stat_word', 's')
            ->leftJoin(
                's',
                'index_words',
                'w',
                $queryBuilder->expr()->eq('w.baseword', $queryBuilder->quoteIdentifier('s.word'))
            )
            ->where(
                $queryBuilder->expr()->gte(
                    's.tstamp',
                    $queryBuilder->createNamedParameter(strtotime('-' . $this->thresholdDays . ' days'), Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->isNull('w.wid')
            )
            ->groupBy('s.word')
            ->orderBy('search_count', 'DESC')
            ->addOrderBy('last_searched', 'DESC');

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
    * Count all distinct searched words in the given period
    */
    private function fetchSearchedWordCount(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('index_stat_word');
        $queryBuilder
            ->selectLiteral('COUNT(DISTINCT word)')
            ->from('index_stat_word')
            ->where(
                $queryBuilder->expr()->gte(
                    'tstamp',
                    $queryBuilder->createNamedParameter(strtotime('-' . $this->thresholdDays . ' days'), Connection::PARAM_INT)
                )
            );

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }
}
