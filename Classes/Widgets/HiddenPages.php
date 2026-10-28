<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentAudit\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use Xima\XimaTypo3ContentAudit\EventListener\PageTreeFilterListener;

class HiddenPages implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        protected readonly WidgetConfigurationInterface $configuration,
        protected readonly ListDataProviderInterface $dataProvider,
        protected readonly BackendViewFactory $backendViewFactory,
        protected readonly ?ButtonProviderInterface $buttonProvider = null,
        protected array $options = []
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        // @todo Remove StandaloneView fallback once v12 support is dropped
        if (class_exists(\TYPO3\CMS\Fluid\View\StandaloneView::class)) {
            $view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
            $view->setFormat('html');
            $view->setTemplateRootPaths(['EXT:xima_typo3_content_audit/Resources/Private/Templates/']);
            $view->setPartialRootPaths(['EXT:xima_typo3_content_audit/Resources/Private/Partials/']);
        } else {
            $view = $this->backendViewFactory->create($this->request, ['xima/xima-typo3-content-audit']);
        }

        $this->dataProvider->setExcludePageUids($this->options['excludePageUids'] ?? []);
        $thresholdDays = (int)($this->options['thresholdDays'] ?? 365);
        $this->dataProvider->setThresholdDays($thresholdDays);

        $resultSet = $this->dataProvider->getItems();
        $view->assignMultiple([
            'configuration' => $this->configuration,
            'records' => $resultSet['results'],
            'button' => $this->buttonProvider,
            'options' => $this->options,
            'matchCount' => $resultSet['matchCount'],
            'totalCount' => $resultSet['totalCount'],
            'thresholdDays' => $thresholdDays,
            'version' => GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion(),
            'pageTreeFilterToken' => PageTreeFilterListener::TOKEN_HIDDEN_PAGES,
        ]);
        return $view->render('HiddenPages');
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
