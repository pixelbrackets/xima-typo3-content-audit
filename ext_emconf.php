<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Content Audit Widget',
    'description' => 'A widget for the TYPO3 dashboard to evaluate the relevance, accuracy and freshness of your digital content',
    'category' => 'module',
    'state' => 'stable',
    'version' => '1.9.0',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.5.99',
            'typo3' => '12.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
