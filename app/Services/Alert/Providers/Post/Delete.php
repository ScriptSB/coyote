<?php

namespace Coyote\Services\Alert\Providers\Post;

use Coyote\Alert;
use Coyote\Services\Alert\Providers\Provider;
use Coyote\Services\Alert\Providers\ProviderInterface;

class Delete extends Provider implements ProviderInterface
{
    const ID = Alert::POST_DELETE;
    const EMAIL = null;

    /**
     * @var string
     */
    private $reasonName;

    /**
     * @var string
     */
    private $reasonText;

    /**
     * @return string
     */
    public function getReasonName()
    {
        return $this->reasonName;
    }

    /**
     * @param string $reasonName
     */
    public function setReasonName($reasonName)
    {
        $this->reasonName = $reasonName;
    }

    /**
     * @return string
     */
    public function getReasonText()
    {
        return $this->reasonText;
    }

    /**
     * @param string $reasonText
     */
    public function setReasonText($reasonText)
    {
        $this->reasonText = $reasonText;
    }
}
