<?php

namespace Oro\Bundle\WebCatalogBundle\EventListener;

use Oro\Bundle\ConfigBundle\Event\ConfigUpdateEvent;
use Oro\Bundle\WebsiteSearchBundle\Event\ReindexationRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WebCatalogConfigChangeListener
{
    const WEB_CATALOG_CONFIGURATION_NAME = 'oro_web_catalog.web_catalog';
    
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    
    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param ConfigUpdateEvent $event
     */
    public function onConfigurationUpdate(ConfigUpdateEvent $event)
    {
        if (!$event->isChanged(self::WEB_CATALOG_CONFIGURATION_NAME)) {
            return;
        }
        
        $reindexationEvent = $this->getReindexationRequestEvent($event);
        $this->dispatcher->dispatch($reindexationEvent, ReindexationRequestEvent::EVENT_NAME);
    }

    /**
     * @param ConfigUpdateEvent $event
     *
     * @return ReindexationRequestEvent
     */
    protected function getReindexationRequestEvent(ConfigUpdateEvent $event)
    {
        return new ReindexationRequestEvent();
    }
}
