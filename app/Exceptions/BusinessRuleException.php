<?php

namespace App\Exceptions;

use DomainException;

/** خرق لقاعدة عمل؛ رسالته عربية وتُعرض للمستخدم كما هي. */
class BusinessRuleException extends DomainException
{
}
