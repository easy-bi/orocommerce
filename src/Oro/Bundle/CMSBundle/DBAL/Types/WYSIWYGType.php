<?php

namespace Oro\Bundle\CMSBundle\DBAL\Types;

use Doctrine\DBAL\Types\TextType;

/**
 * Doctrine type for WYSIWYG field that extends base text type.
 */
class WYSIWYGType extends TextType
{
    const TYPE = 'wysiwyg';

    /** {@inheritdoc} */
    public function getName()
    {
        return self::TYPE;
    }
}
