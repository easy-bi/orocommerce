<?php

namespace OroB2B\Bundle\CheckoutBundle\Layout\DataProvider;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Component\Layout\ContextInterface;

use OroB2B\Bundle\CheckoutBundle\Entity\Checkout;

class ContinueTransitionDataProvider extends AbstractTransitionDataProvider
{
    /**
     * {@inheritdoc}
     */
    public function getData(ContextInterface $context)
    {
        /** @var Checkout $checkout */
        $checkout = $context->data()->get('checkout');

        $workflowItem = $checkout->getWorkflowItem();
        $continueTransition = $this->getContinueTransition($workflowItem);

        if ($continueTransition) {
            return $this->getTransitionData($continueTransition, $workflowItem);
        }

        return null;
    }

    /**
     * @param WorkflowItem $workflowItem
     * @return null|Transition
     */
    protected function getContinueTransition(WorkflowItem $workflowItem)
    {
        $transitions = $this->workflowManager->getTransitionsByWorkflowItem($workflowItem);
        foreach ($transitions as $transition) {
            $frontendOptions = $transition->getFrontendOptions();
            if ($transition->hasForm() && !empty($frontendOptions['is_checkout_continue'])) {
                return $transition;
            }
        }

        return null;
    }
}
