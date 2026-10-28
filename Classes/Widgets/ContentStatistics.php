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

class ContentStatistics implements WidgetInterface, RequestAwareWidgetInterface
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

        $this->dataProvider->setActiveUserThresholdDays((int)($this->options['activeUserThresholdDays'] ?? 90));

        $view->assignMultiple([
            'configuration' => $this->configuration,
            'records' => $this->dataProvider->getItems()['results'],
            'button' => $this->buttonProvider,
            'options' => $this->options,
            'version' => GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion(),
        ]);
        return $view->render('ContentStatistics');
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
