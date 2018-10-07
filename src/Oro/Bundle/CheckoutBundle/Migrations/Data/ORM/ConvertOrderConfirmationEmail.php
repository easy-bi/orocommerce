<?php

namespace Oro\Bundle\CheckoutBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\EmailBundle\Entity\EmailTemplate;
use Oro\Bundle\EmailBundle\Migrations\Data\ORM\AbstractEmailFixture;

class ConvertOrderConfirmationEmail extends AbstractEmailFixture implements DependentFixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function getEmailsDir()
    {
        return $this->container
            ->get('kernel')
            ->locateResource('@OroCheckoutBundle/Migrations/Data/ORM/data/emails/v2_0');
    }

    /**
     * Return path to old email templates
     *
     * @return string
     */
    public function getPreviousEmailsDir()
    {
        return $this->container
            ->get('kernel')
            ->locateResource('@OroCheckoutBundle/Migrations/Data/ORM/data/emails/order');
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [UpdateOrderConfirmationEmailTemplate::class];
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTemplate(ObjectManager $manager, $fileName, array $file)
    {
        if ($fileName !== 'order_confirmation') {
            return;
        }
        $template = file_get_contents($file['path']);
        $templateContent = EmailTemplate::parseContent($template);

        $existingEmailTemplatesList = $this->getEmailTemplatesList($this->getPreviousEmailsDir());
        $existingTemplate = file_get_contents($existingEmailTemplatesList[$fileName]['path']);
        $existingParsedTemplate = EmailTemplate::parseContent($existingTemplate);
        $existingEmailTemplate = $this->findExistingTemplate($manager, $existingParsedTemplate);

        if ($existingTemplate) {
            $this->updateExistingTemplate($existingEmailTemplate, $templateContent);
        }
    }

    /**
     * @inheritdoc
     */
    protected function updateExistingTemplate(EmailTemplate $emailTemplate, array $template)
    {
        $emailTemplate->setContent($template['content']);
    }

    /**
     * {@inheritdoc}
     */
    protected function findExistingTemplate(ObjectManager $manager, array $template)
    {
        if (!isset($template['params']['name'])
            || !isset($template['content'])
        ) {
            return null;
        }

        return $manager->getRepository('OroEmailBundle:EmailTemplate')->findOneBy([
            'name' => $template['params']['name'],
            'entityName' => 'Oro\Bundle\OrderBundle\Entity\Order',
            'content' => $template['content']
        ]);
    }
}
