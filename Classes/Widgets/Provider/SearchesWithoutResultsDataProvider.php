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

    protected int $thresholdSearches = 1;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PackageManager $packageManager
    ) {
    }

    public function setThresholdDays(int $thresholdDays): void
    {
        $this->thresholdDays = $thresholdDays;
    }

    public function setThresholdSearches(int $thresholdSearches): void
    {
        $this->thresholdSearches = $thresholdSearches;
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function getItems(): array
    {
        $matchingTerms = $this->fetchMatchingItems();

        return [
            'matchCount' => count($matchingTerms),
            'totalCount' => $this->fetchSearchTermCount(),
            'results' => array_slice($matchingTerms, 0, self::DISPLAY_LIMIT),
        ];
    }

    /**
    * Fetch search terms without results which were searched at least
    * the minimum number of times, most searched first
    *
    * Prefers ke_search over indexed_search if both are installed
    *
    * @return list<array<string, mixed>>
    */
    public function fetchMatchingItems(): array
    {
        if ($this->packageManager->isPackageActive('ke_search')) {
            return $this->fetchKeSearchItems();
        }
        if ($this->packageManager->isPackageActive('indexed_search')) {
            return $this->fetchIndexedSearchItems();
        }
        return [];
    }

    /**
    * ke_search logs each search phrase with its number of hits
    *
    * @return list<array<string, mixed>>
    */
    private function fetchKeSearchItems(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_kesearch_stat_search');
        $queryBuilder
            ->select('searchphrase as searchWord')
            ->addSelectLiteral('COUNT(uid) as search_count')
            ->addSelectLiteral('MAX(tstamp) as last_searched')
            ->from('tx_kesearch_stat_search')
            ->where(
                $queryBuilder->expr()->gte(
                    'tstamp',
                    $queryBuilder->createNamedParameter(strtotime('-' . $this->thresholdDays . ' days'), Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq('hits', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->groupBy('searchphrase')
            ->having('search_count >= ' . $queryBuilder->createNamedParameter($this->thresholdSearches, Connection::PARAM_INT))
            ->orderBy('search_count', 'DESC')
            ->addOrderBy('last_searched', 'DESC');

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
    * indexed_search logs single words without the number of hits,
    * so a word counts as without results if it does not exist in the search index
    *
    * @return list<array<string, mixed>>
    */
    private function fetchIndexedSearchItems(): array
    {
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
            ->having('search_count >= ' . $queryBuilder->createNamedParameter($this->thresholdSearches, Connection::PARAM_INT))
            ->orderBy('search_count', 'DESC')
            ->addOrderBy('last_searched', 'DESC');

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
    * Count all distinct search terms in the given period
    */
    private function fetchSearchTermCount(): int
    {
        if ($this->packageManager->isPackageActive('ke_search')) {
            return $this->countDistinctTerms('tx_kesearch_stat_search', 'searchphrase');
        }
        if ($this->packageManager->isPackageActive('indexed_search')) {
            return $this->countDistinctTerms('index_stat_word', 'word');
        }
        return 0;
    }

    private function countDistinctTerms(string $table, string $column): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder
            ->selectLiteral('COUNT(DISTINCT ' . $queryBuilder->quoteIdentifier($column) . ')')
            ->from($table)
            ->where(
                $queryBuilder->expr()->gte(
                    'tstamp',
                    $queryBuilder->createNamedParameter(strtotime('-' . $this->thresholdDays . ' days'), Connection::PARAM_INT)
                )
            );

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }
}
