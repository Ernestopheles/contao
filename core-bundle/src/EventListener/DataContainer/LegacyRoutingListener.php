<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\DataContainer;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\ServiceAnnotationBundle\ServiceAnnotationInterface;

/**
 * @internal
 */
class LegacyRoutingListener implements ServiceAnnotationInterface
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var bool
     */
    private $prependLocale;

    /**
     * @var string
     */
    private $urlSuffix;

    public function __construct(ContaoFramework $framework, TranslatorInterface $translator, bool $prependLocale = false, string $urlSuffix = '.html')
    {
        $this->framework = $framework;
        $this->translator = $translator;
        $this->prependLocale = $prependLocale;
        $this->urlSuffix = $urlSuffix;
    }

    /**
     * @Callback(table="tl_page", target="config.onload")
     */
    public function disableRoutingFields(): void
    {
        /** @var Image $adapter */
        $adapter = $this->framework->getAdapter(Image::class);

        $renderHelpIcon = function () use ($adapter) {
            return $adapter->getHtml(
                'show.svg',
                '',
                sprintf(
                    'title="%s"',
                    StringUtil::specialchars($this->translator->trans('tl_page.legacyRouting', [], 'contao_tl_page'))
                )
            );
        };

        foreach (['urlPrefix', 'urlSuffix'] as $field) {
            $GLOBALS['TL_DCA']['tl_page']['fields'][$field]['eval']['disabled'] = true;
            $GLOBALS['TL_DCA']['tl_page']['fields'][$field]['eval']['helpwizard'] = false;
            $GLOBALS['TL_DCA']['tl_page']['fields'][$field]['xlabel'][] = $renderHelpIcon;
        }
    }

    /**
     * @Callback(table="tl_page", target="fields.urlPrefix.load")
     */
    public function overrideUrlPrefix($value, DataContainer $dc)
    {
        return $this->prependLocale ? $dc->activeRecord->language : '';
    }

    /**
     * @Callback(table="tl_page", target="fields.urlSuffix.load")
     */
    public function overrideUrlSuffix(): string
    {
        return $this->urlSuffix;
    }
}
