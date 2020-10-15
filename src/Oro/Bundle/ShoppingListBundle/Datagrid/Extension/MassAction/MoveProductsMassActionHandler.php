<?php

namespace Oro\Bundle\ShoppingListBundle\Datagrid\Extension\MassAction;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\BatchBundle\ORM\Query\ResultIterator\IdentifierWithoutOrderByIterationStrategy;
use Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResult;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\DataGridBundle\Exception\LogicException;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerArgs;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\ShoppingListBundle\Manager\ShoppingListManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * DataGrid mass action handler that move products between shopping lists.
 */
class MoveProductsMassActionHandler implements MassActionHandlerInterface
{
    private const FLUSH_BATCH_SIZE = 100;

    /** @var ManagerRegistry */
    private $registry;

    /** @var TranslatorInterface */
    private $translator;

    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;

    /** @var RequestStack */
    private $requestStack;

    /** @var ShoppingListManager */
    private $shoppingListManager;

    /**
     * @param ManagerRegistry $registry
     * @param TranslatorInterface $translator
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param RequestStack $requestStack
     * @param ShoppingListManager $shoppingListManager
     */
    public function __construct(
        ManagerRegistry $registry,
        TranslatorInterface $translator,
        AuthorizationCheckerInterface $authorizationChecker,
        RequestStack $requestStack,
        ShoppingListManager $shoppingListManager
    ) {
        $this->registry = $registry;
        $this->translator = $translator;
        $this->authorizationChecker = $authorizationChecker;
        $this->requestStack = $requestStack;
        $this->shoppingListManager = $shoppingListManager;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(MassActionHandlerArgs $args)
    {
        $request = $this->requestStack->getMasterRequest();
        if ($request->getMethod() === 'POST') {
            $result = $this->doHandle($args);
        } else {
            $result = new MassActionResponse(
                false,
                sprintf('Request method "%s" is not supported', $request->getMethod())
            );
        }

        return $result;
    }

    /**
     * @param MassActionHandlerArgs $args
     * @return MassActionResponse
     */
    private function doHandle(MassActionHandlerArgs $args): MassActionResponse
    {
        $shoppingList = $this->getTargetShoppingList($args);
        if (!$this->isEditAllowed($shoppingList)) {
            return $this->getNoAccessResponse();
        }

        $results = new IterableResult($args->getResults()->getSource());
        $results->setIterationStrategy(new IdentifierWithoutOrderByIterationStrategy());
        $results->setBufferSize(self::FLUSH_BATCH_SIZE);

        $entityIdentifiedField = $this->getEntityIdentifierField($args);
        /** @var EntityManager $manager */
        $manager = $this->registry->getManagerForClass(LineItem::class);
        $updated = 0;

        /** @var ResultRecordInterface[] $results */
        foreach ($results as $result) {
            $entity = $result->getRootEntity();
            if (!$entity) {
                // No entity in result record, it should be extracted from DB.
                $entity = $manager->getReference(LineItem::class, $result->getValue($entityIdentifiedField));
            }

            if (!$entity) {
                continue;
            }

            $originalShoppingList = $entity->getShoppingList();
            if ($originalShoppingList->getId() === $shoppingList->getId() ||
                !$this->isEditAllowed($originalShoppingList)
            ) {
                continue;
            }

            $this->shoppingListManager->addLineItem($entity, $shoppingList, false);

            $updated++;
            if ($updated % self::FLUSH_BATCH_SIZE === 0) {
                $manager->flush();
            }
        }

        if ($updated % self::FLUSH_BATCH_SIZE > 0) {
            $manager->flush();
        }

        return $this->getResponse($args->getMassAction(), $updated);
    }

    /**
     * @param MassActionHandlerArgs $args
     * @return ShoppingList|null
     */
    private function getTargetShoppingList(MassActionHandlerArgs $args): ?ShoppingList
    {
        $id = $args->getData()['shopping_list_id'] ?? null;
        if (!$id) {
            return null;
        }

        return $this->registry->getManagerForClass(ShoppingList::class)
            ->getRepository(ShoppingList::class)
            ->find($id);
    }

    /**
     * @param null|ShoppingList $shoppingList
     * @return bool
     */
    private function isEditAllowed(?ShoppingList $shoppingList): bool
    {
        return $shoppingList && $this->authorizationChecker->isGranted('EDIT', $shoppingList);
    }

    /**
     * @param MassActionInterface $massAction
     * @param int $entitiesCount
     * @return MassActionResponse
     */
    private function getResponse(MassActionInterface $massAction, $entitiesCount = 0): MassActionResponse
    {
        $responseMessage = $massAction->getOptions()
            ->offsetGetByPath('[messages][success]', 'oro.shoppinglist.mass_actions.move_line_items.success_message');

        return new MassActionResponse(
            $entitiesCount > 0,
            $this->translator->trans($responseMessage, ['%count%' => $entitiesCount]),
            ['count' => $entitiesCount]
        );
    }

    /**
     * @return MassActionResponse
     */
    private function getNoAccessResponse(): MassActionResponse
    {
        return new MassActionResponse(
            false,
            $this->translator->trans('oro.shoppinglist.mass_actions.move_line_items.no_edit_permission_message')
        );
    }

    /**
     * @param MassActionHandlerArgs $args
     *
     * @throws LogicException
     * @return string
     */
    private function getEntityIdentifierField(MassActionHandlerArgs $args): string
    {
        $massAction = $args->getMassAction();
        $identifier = $massAction->getOptions()->offsetGet('data_identifier');
        if (!$identifier) {
            throw new LogicException(sprintf('Mass action "%s" must define identifier name', $massAction->getName()));
        }

        // if we ask identifier that's means that we have plain data in array
        // so we will just use column name without entity alias
        if (strpos('.', $identifier) !== -1) {
            $parts      = explode('.', $identifier);
            $identifier = end($parts);
        }

        return $identifier;
    }
}