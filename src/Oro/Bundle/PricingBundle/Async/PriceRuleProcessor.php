<?php

namespace Oro\Bundle\PricingBundle\Async;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PricingBundle\Builder\ProductPriceBuilder;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\Repository\PriceListRepository;
use Oro\Bundle\PricingBundle\Model\PriceListTriggerHandler;
use Oro\Bundle\PricingBundle\NotificationMessage\Message;
use Oro\Bundle\PricingBundle\NotificationMessage\Messenger;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves price lists rules and updates actuality of price lists.
 */
class PriceRuleProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var LoggerInterface */
    private $logger;

    /** @var ProductPriceBuilder */
    private $priceBuilder;

    /** @var Messenger */
    private $messenger;

    /** @var TranslatorInterface */
    private $translator;

    /** @var PriceListTriggerHandler */
    private $triggerHandler;

    /**
     * @param ManagerRegistry $doctrine
     * @param LoggerInterface $logger
     * @param ProductPriceBuilder $priceBuilder
     * @param Messenger $messenger
     * @param TranslatorInterface $translator
     * @param PriceListTriggerHandler $triggerHandler
     */
    public function __construct(
        ManagerRegistry $doctrine,
        LoggerInterface $logger,
        ProductPriceBuilder $priceBuilder,
        Messenger $messenger,
        TranslatorInterface $translator,
        PriceListTriggerHandler $triggerHandler
    ) {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->priceBuilder = $priceBuilder;
        $this->messenger = $messenger;
        $this->translator = $translator;
        $this->triggerHandler = $triggerHandler;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::RESOLVE_PRICE_RULES];
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $body = JSON::decode($message->getBody());
        if (!isset($body['product']) || !\is_array($body['product'])) {
            $this->logger->critical('Got invalid message.');

            return self::REJECT;
        }
        $priceListsCount = count($body['product']);

        /** @var EntityManagerInterface $em */
        $em = $this->doctrine->getManagerForClass(PriceList::class);
        foreach ($body['product'] as $priceListId => $productIds) {
            /** @var PriceList|null $priceList */
            $priceList = $em->find(PriceList::class, $priceListId);
            if (null === $priceList) {
                $this->logger->warning(sprintf(
                    'PriceList entity with identifier %s not found.',
                    $priceListId
                ));
                continue;
            }

            $em->beginTransaction();
            try {
                $this->processPriceList($em, $priceList, $productIds);
                $em->commit();
            } catch (\Exception $e) {
                $em->rollback();
                $this->logger->error(
                    'Unexpected exception occurred during Price Rule build.',
                    ['exception' => $e]
                );

                if ($e instanceof RetryableException) {
                    // On RetryableException send back to queue the message related to a single price list
                    // that triggered an exception.
                    // If this was the only one PL in the message REQUEUE it to persist retries counter
                    if ($priceListsCount === 1) {
                        return self::REQUEUE;
                    }

                    $this->triggerHandler->handlePriceListTopic(
                        Topics::RESOLVE_PRICE_RULES,
                        $priceList,
                        $productIds
                    );
                } else {
                    $this->onFailedPriceListId($priceList->getId());
                    if ($priceListsCount === 1) {
                        return self::REJECT;
                    }
                }
            }
        }

        return self::ACK;
    }

    /**
     * @param EntityManagerInterface $em
     * @param PriceList $priceList
     * @param int[] $productIds
     */
    private function processPriceList(EntityManagerInterface $em, PriceList $priceList, array $productIds): void
    {
        $this->messenger->remove(
            NotificationMessages::CHANNEL_PRICE_LIST,
            NotificationMessages::TOPIC_PRICE_RULES_BUILD,
            PriceList::class,
            $priceList->getId()
        );

        $startTime = $priceList->getUpdatedAt();
        $this->priceBuilder->buildByPriceList($priceList, $productIds);
        $this->updatePriceListActuality($em, $priceList, $startTime);
    }

    /**
     * @param int $priceListId
     */
    private function onFailedPriceListId(int $priceListId): void
    {
        $this->messenger->send(
            NotificationMessages::CHANNEL_PRICE_LIST,
            NotificationMessages::TOPIC_PRICE_RULES_BUILD,
            Message::STATUS_ERROR,
            $this->translator->trans('oro.pricing.notification.price_list.error.price_rule_build'),
            PriceList::class,
            $priceListId
        );
    }

    /**
     * @param EntityManagerInterface $em
     * @param PriceList $priceList
     * @param \DateTime $startTime
     */
    private function updatePriceListActuality(
        EntityManagerInterface $em,
        PriceList $priceList,
        \DateTime $startTime
    ): void {
        $em->refresh($priceList);
        if ($startTime == $priceList->getUpdatedAt()) {
            /** @var PriceListRepository $repo */
            $repo = $em->getRepository(PriceList::class);
            $repo->updatePriceListsActuality([$priceList], true);
        }
    }
}
